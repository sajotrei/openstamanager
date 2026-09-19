<?php

/*
 * OpenSTAManager: il software gestionale open source per l'assistenza tecnica e la fatturazione
 * Copyright (C) DevCode s.r.l.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace Modules\Partitario;

use DomainException;
use Modules\PrimaNota\Mastrino;
use Modules\PrimaNota\Movimento;
use Throwable;

/**
 * Gestione controllata delle scritture di apertura e chiusura esercizio.
 *
 * La classe mantiene separate anteprima, validazione ed esecuzione.
 * Nessuna operazione di anteprima modifica il database.
 */
class Esercizio
{
    public function __construct(
        protected string $start,
        protected string $end,
    ) {
    }

    public function isAnnual(): bool
    {
        $start = new \DateTimeImmutable($this->start);
        $expectedEnd = $start->modify('+1 year -1 day');

        return $expectedEnd->format('Y-m-d') === (new \DateTimeImmutable($this->end))->format('Y-m-d');
    }

    public function getPreview(string $operation): array
    {
        if (!in_array($operation, ['apertura', 'chiusura'], true)) {
            throw new DomainException(tr('Operazione non valida.'));
        }

        $settings = $this->getSettings();
        $date = $operation === 'apertura' ? $this->start : $this->end;
        $existing = $this->getExisting($operation, $date);
        $rows = $operation === 'apertura'
            ? $this->getOpeningBalances($settings['chiusura'])
            : $this->getClosingBalances($settings['chiusura']);

        $entries = [];
        $total = 0.0;
        foreach ($rows as $row) {
            $value = (float) $row['totale'];
            $entries[] = [
                'idconto' => (int) $row['id'],
                'descrizione' => $row['descrizione'],
                'totale' => $operation === 'apertura' ? $value : -$value,
                'saldo_origine' => $value,
                'contropartita' => false,
            ];
            $total += $value;
        }

        $counterpart = $operation === 'apertura' ? -$total : $total;
        $counterpartId = $operation === 'apertura' ? $settings['apertura'] : $settings['chiusura'];
        if ($rows) {
            $entries[] = [
                'idconto' => $counterpartId,
                'descrizione' => $this->getAccountDescription($counterpartId),
                'totale' => $counterpart,
                'saldo_origine' => null,
                'contropartita' => true,
            ];
        }

        $debit = 0.0;
        $credit = 0.0;
        foreach ($entries as $entry) {
            if ($entry['totale'] >= 0) {
                $debit += $entry['totale'];
            } else {
                $credit += abs($entry['totale']);
            }
        }

        $errors = [];
        $warnings = [];

        if (!$this->isAnnual()) {
            $errors[] = tr('Apertura e chiusura esercizio sono disponibili solo per periodi della durata di un anno.');
        }
        if (!$counterpartId || !$this->accountExists($counterpartId)) {
            $errors[] = tr('Il conto tecnico necessario non è configurato correttamente.');
        }
        if ($existing['present']) {
            $warnings[] = tr('L\'operazione risulta già eseguita e non verrà ripetuta.');
        }
        if (round($debit - $credit, 6) !== 0.0) {
            $errors[] = tr('L\'anteprima non risulta in pareggio.');
        }

        return [
            'operation' => $operation,
            'period_start' => $this->start,
            'period_end' => $this->end,
            'date' => $date,
            'existing' => $existing,
            'entries' => $entries,
            'accounts_count' => count($rows),
            'debit' => $debit,
            'credit' => $credit,
            'errors' => $errors,
            'warnings' => $warnings,
            'can_execute' => !$errors && !$existing['present'] && !empty($rows),
        ];
    }

    public function execute(string $operation): int
    {
        $database = database();
        $pdo = $database->getPDO();
        $lock = 'osm-esercizio-'.sha1($this->start.'|'.$this->end.'|'.$operation);
        $locked = (int) $database->fetchOne('SELECT GET_LOCK('.prepare($lock).', 10) AS acquired')['acquired'];

        if ($locked !== 1) {
            throw new DomainException(tr('Operazione già in corso. Riprovare tra qualche istante.'));
        }

        try {
            $pdo->beginTransaction();

            // Ricalcolo dentro la sezione critica: la conferma non autorizza dati ormai cambiati.
            $preview = $this->getPreview($operation);
            if (!$preview['can_execute']) {
                throw new DomainException($preview['existing']['present']
                    ? tr('L\'operazione risulta già eseguita. Nessuna registrazione è stata modificata.')
                    : implode(' ', $preview['errors']));
            }

            $description = $operation === 'apertura' ? tr('Apertura conto') : tr('Chiusura conto');
            $mastrino = Mastrino::build($description, $preview['date'], 0, true);

            foreach ($preview['entries'] as $entry) {
                $movement = Movimento::build($mastrino, $entry['idconto']);
                if ($entry['totale'] >= 0) {
                    $movement->setTotale(0, abs($entry['totale']));
                } else {
                    $movement->setTotale(abs($entry['totale']), 0);
                }

                if ($operation === 'apertura') {
                    $movement->is_apertura = true;
                } else {
                    $movement->is_chiusura = true;
                }
                $movement->save();
            }

            $created = $this->getExisting($operation, $preview['date']);
            if (!$created['present'] || abs((float) $created['balance']) > 0.000001) {
                throw new DomainException(tr('Verifica finale delle scritture non superata.'));
            }

            $pdo->commit();

            return (int) $mastrino->id;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        } finally {
            $database->fetchOne('SELECT RELEASE_LOCK('.prepare($lock).') AS released');
        }
    }

    protected function getSettings(): array
    {
        return [
            'apertura' => (int) setting('Conto per Apertura conti patrimoniali'),
            'chiusura' => (int) setting('Conto per Chiusura conti patrimoniali'),
        ];
    }

    protected function getExisting(string $operation, string $date): array
    {
        $flag = $operation === 'apertura' ? 'is_apertura' : 'is_chiusura';
        $rows = database()->fetchArray(
            'SELECT idmastrino, COUNT(*) AS righe, SUM(totale) AS totale FROM co_movimenti WHERE '.$flag.'=1 AND DATE(data)=:date GROUP BY idmastrino ORDER BY idmastrino',
            ['date' => $date]
        );

        return [
            'present' => !empty($rows),
            'mastrini' => array_map('intval', array_column($rows, 'idmastrino')),
            'rows' => array_sum(array_map('intval', array_column($rows, 'righe'))),
            'balance' => array_sum(array_map('floatval', array_column($rows, 'totale'))),
        ];
    }

    protected function getOpeningBalances(int $closingAccount): array
    {
        $previousStart = (new \DateTimeImmutable($this->start))->modify('-1 year')->format('Y-m-d');

        return database()->fetchArray(
            'SELECT c3.id, CONCAT(c2.numero, ".", c3.numero, " ", c3.descrizione) AS descrizione, SUM(m.totale) AS totale
            FROM co_pianodeiconti3 c3
            INNER JOIN co_pianodeiconti2 c2 ON c3.idpianodeiconti2=c2.id
            INNER JOIN co_pianodeiconti1 c1 ON c2.idpianodeiconti1=c1.id
            INNER JOIN co_movimenti m ON c3.id=m.idconto
            WHERE c1.descrizione=\'Patrimoniale\'
              AND m.data>=:start AND m.data<:end
              AND c3.id<>:closing_account AND m.is_chiusura=0
            GROUP BY c3.id, c2.numero, c3.numero, c3.descrizione
            HAVING SUM(m.totale)<>0
            ORDER BY c2.numero, c3.numero',
            ['start' => $previousStart, 'end' => $this->start, 'closing_account' => $closingAccount]
        );
    }

    protected function getClosingBalances(int $closingAccount): array
    {
        return database()->fetchArray(
            'SELECT c3.id, CONCAT(c2.numero, ".", c3.numero, " ", c3.descrizione) AS descrizione, SUM(m.totale) AS totale
            FROM co_pianodeiconti3 c3
            INNER JOIN co_pianodeiconti2 c2 ON c3.idpianodeiconti2=c2.id
            INNER JOIN co_pianodeiconti1 c1 ON c2.idpianodeiconti1=c1.id
            INNER JOIN co_movimenti m ON c3.id=m.idconto
            WHERE c1.descrizione=\'Patrimoniale\'
              AND m.data>=:start AND m.data<=:end
              AND c3.id<>:closing_account AND m.is_chiusura=0
            GROUP BY c3.id, c2.numero, c3.numero, c3.descrizione
            HAVING SUM(m.totale)<>0
            ORDER BY c2.numero, c3.numero',
            ['start' => $this->start, 'end' => $this->end, 'closing_account' => $closingAccount]
        );
    }

    protected function accountExists(int $id): bool
    {
        return $id > 0 && database()->fetchNum('SELECT id FROM co_pianodeiconti3 WHERE id='.prepare($id)) > 0;
    }

    protected function getAccountDescription(int $id): string
    {
        $row = database()->fetchOne(
            'SELECT CONCAT(c2.numero, ".", c3.numero, " ", c3.descrizione) AS descrizione
             FROM co_pianodeiconti3 c3 INNER JOIN co_pianodeiconti2 c2 ON c2.id=c3.idpianodeiconti2
             WHERE c3.id='.prepare($id)
        );

        return $row['descrizione'] ?? tr('Conto tecnico');
    }
}
