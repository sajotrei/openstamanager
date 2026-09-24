<?php

namespace Modules\Partitario;

use DomainException;
use Modules\PrimaNota\Mastrino;
use Modules\PrimaNota\Movimento;

/**
 * Gestione controllata delle scritture di apertura e chiusura esercizio.
 * Preview e validazione non modificano il database.
 */
class Esercizio
{
    private const TOLERANCE = 0.000001;

    protected string $today;

    public function __construct(
        protected string $start,
        protected string $end,
        ?string $today = null,
    ) {
        // core.php imposta la timezone nativa OSM (Europe/Rome sulla 2.10.4).
        $this->today = $today ?: date('Y-m-d');
    }

    public function isAnnual(): bool
    {
        $start = new \DateTimeImmutable($this->start);
        $expectedEnd = $start->modify('+1 year -1 day');

        return $expectedEnd->format('Y-m-d') === (new \DateTimeImmutable($this->end))->format('Y-m-d');
    }

    public function getToday(): string
    {
        return $this->today;
    }

    public static function temporalAvailability(string $operation, string $start, string $end, string $today): array
    {
        if (!in_array($operation, ['apertura', 'chiusura'], true)) {
            throw new DomainException('Operazione non valida.');
        }

        $availableFrom = $operation === 'apertura' ? $start : $end;
        $key = 'available';
        $message = null;

        if ($today < $availableFrom) {
            if ($operation === 'apertura') {
                $key = 'before_start';
                $message = tr('L\'Apertura sarà disponibile dal _DATE_.', [
                    '_DATE_' => dateFormat($availableFrom),
                ]);
            } else {
                $key = 'before_end';
                $message = tr('La Chiusura sarà disponibile dal _DATE_.', [
                    '_DATE_' => dateFormat($availableFrom),
                ]);
            }
        }

        return [
            'key' => $key,
            'allowed' => $key === 'available',
            'available_from' => $availableFrom,
            'message' => $message,
        ];
    }

    public static function calculateEntries(string $operation, array $rows, int $counterpartId, string $counterpartDescription): array
    {
        if (!in_array($operation, ['apertura', 'chiusura'], true)) {
            throw new DomainException('Operazione non valida.');
        }

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

        if ($rows) {
            $entries[] = [
                'idconto' => $counterpartId,
                'descrizione' => $counterpartDescription,
                'totale' => $operation === 'apertura' ? -$total : $total,
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

        return ['entries' => $entries, 'debit' => $debit, 'credit' => $credit];
    }

    public static function aggregateEntries(array $entries): array
    {
        $totals = [];
        foreach ($entries as $entry) {
            $id = (int) $entry['idconto'];
            $totals[$id] = ($totals[$id] ?? 0.0) + (float) $entry['totale'];
        }
        ksort($totals);

        return $totals;
    }

    public static function compareEntries(array $entries, array $accountTotals, float $tolerance = self::TOLERANCE): bool
    {
        $expected = self::aggregateEntries($entries);
        $actual = [];
        foreach ($accountTotals as $id => $value) {
            $actual[(int) $id] = (float) $value;
        }
        ksort($actual);

        if (array_keys($expected) !== array_keys($actual)) {
            return false;
        }

        foreach ($expected as $id => $value) {
            if (abs($value - $actual[$id]) > $tolerance) {
                return false;
            }
        }

        return true;
    }

    public static function classifyExisting(array $existing, array $expectedEntries): array
    {
        if (empty($existing['present'])) {
            return ['status' => 'none', 'messages' => []];
        }

        $messages = array_values($existing['anomalies'] ?? []);
        if ($messages) {
            return ['status' => 'anomaly', 'messages' => $messages];
        }

        if (!self::compareEntries($expectedEntries, $existing['account_totals'] ?? [])) {
            return [
                'status' => 'stale',
                'messages' => [tr('Sono stati rilevati movimenti successivi o modifiche retroattive. Nessuna correzione automatica verrà eseguita.')],
            ];
        }

        if ((int) ($existing['rows'] ?? 0) !== count($expectedEntries)) {
            return [
                'status' => 'anomaly',
                'messages' => [tr('Il numero delle righe tecniche non coincide con la scrittura attesa.')],
            ];
        }

        return ['status' => 'consistent', 'messages' => []];
    }

    public static function validateTechnicalAccountsData(array $settings, array $accounts): array
    {
        $errors = [];
        $openingId = (int) ($settings['apertura'] ?? 0);
        $closingId = (int) ($settings['chiusura'] ?? 0);

        if ($openingId <= 0) {
            $errors[] = tr('Il conto tecnico di Apertura non è configurato.');
        }
        if ($closingId <= 0) {
            $errors[] = tr('Il conto tecnico di Chiusura non è configurato.');
        }
        if ($openingId > 0 && $closingId > 0 && $openingId === $closingId) {
            $errors[] = tr('I conti tecnici di Apertura e Chiusura devono essere differenti.');
        }

        foreach (['apertura' => $openingId, 'chiusura' => $closingId] as $name => $id) {
            if ($id <= 0) {
                continue;
            }
            $account = $accounts[$name] ?? null;
            if (empty($account)) {
                $errors[] = $name === 'apertura'
                    ? tr('Il conto tecnico di Apertura non esiste nel Piano dei conti.')
                    : tr('Il conto tecnico di Chiusura non esiste nel Piano dei conti.');
                continue;
            }
            if (($account['gruppo'] ?? null) !== 'Patrimoniale') {
                $errors[] = $name === 'apertura'
                    ? tr('Il conto tecnico di Apertura deve appartenere allo Stato patrimoniale.')
                    : tr('Il conto tecnico di Chiusura deve appartenere allo Stato patrimoniale.');
            }
        }

        return ['valid' => empty($errors), 'errors' => $errors, 'accounts' => $accounts];
    }

    public function getPreview(string $operation): array
    {
        if (!in_array($operation, ['apertura', 'chiusura'], true)) {
            throw new DomainException(tr('Operazione non valida.'));
        }

        $settings = $this->getSettings();
        $configuration = $this->validateTechnicalAccounts($settings);
        $date = $operation === 'apertura' ? $this->start : $this->end;
        $openingExisting = $this->getExisting('apertura', $this->start);
        $closingExisting = $this->getExisting('chiusura', $this->end);
        $existing = $operation === 'apertura' ? $openingExisting : $closingExisting;
        $rows = $operation === 'apertura'
            ? $this->getOpeningBalances($settings['chiusura'])
            : $this->getClosingBalances($settings['chiusura']);
        $counterpartId = $operation === 'apertura' ? $settings['apertura'] : $settings['chiusura'];
        $account = $configuration['accounts'][$operation] ?? null;
        $counterpartDescription = $account['descrizione_completa'] ?? tr('Conto tecnico');
        $calculation = self::calculateEntries($operation, $rows, $counterpartId, $counterpartDescription);

        $classification = self::classifyExisting($existing, $calculation['entries']);
        $existing['status'] = $classification['status'];
        $existing['messages'] = $classification['messages'];

        $previousRows = null;
        if ($operation === 'chiusura') {
            $previousRows = $this->getOpeningBalances($settings['chiusura']);
            if ($openingExisting['present']) {
                $openingCalculation = self::calculateEntries(
                    'apertura',
                    $previousRows,
                    $settings['apertura'],
                    $configuration['accounts']['apertura']['descrizione_completa'] ?? tr('Conto tecnico')
                );
                $openingClassification = self::classifyExisting($openingExisting, $openingCalculation['entries']);
                $openingExisting['status'] = $openingClassification['status'];
                $openingExisting['messages'] = $openingClassification['messages'];
            }
        }

        $errors = [];
        $warnings = [];
        $blockingReasons = [];
        $annual = $this->isAnnual();
        $availability = self::temporalAvailability($operation, $this->start, $this->end, $this->today);

        if (!$annual) {
            $errors[] = tr('Apertura e Chiusura esercizio sono disponibili solo per periodi della durata di un anno.');
        }
        if (!$configuration['valid']) {
            $errors = array_merge($errors, $configuration['errors']);
        }
        if ($operation === 'apertura' && !$existing['present'] && $closingExisting['present']) {
            $errors[] = tr('Apertura non rilevata: l\'esercizio risulta già chiuso. Verificare lo storico prima di qualsiasi intervento.');
        }
        if ($operation === 'chiusura' && !$existing['present'] && !$openingExisting['present'] && !empty($previousRows)) {
            $errors[] = tr('Prima di chiudere l\'esercizio è necessario registrare l\'Apertura dei saldi precedenti.');
        }
        if ($operation === 'chiusura' && !$existing['present'] && $openingExisting['present'] && in_array($openingExisting['status'] ?? null, ['stale', 'anomaly'], true)) {
            $errors[] = tr('L\'Apertura dell\'esercizio deve essere verificata prima di procedere con la Chiusura.');
        }
        if (round($calculation['debit'] - $calculation['credit'], 6) !== 0.0) {
            $errors[] = tr('L\'anteprima non risulta in pareggio.');
        }

        if (!$availability['allowed'] && !$existing['present'] && $availability['message']) {
            $blockingReasons[] = $availability['message'];
        }
        $blockingReasons = array_merge($blockingReasons, $errors);
        if (!$existing['present'] && empty($calculation['entries'])) {
            $blockingReasons[] = tr('Nessun saldo patrimoniale da elaborare per il periodo selezionato.');
        }
        if ($existing['present']) {
            $warnings = $existing['status'] === 'consistent'
                ? [tr('L\'operazione risulta già eseguita e non verrà ripetuta.')]
                : array_merge($warnings, $existing['messages']);
        }

        $canExecute = $annual
            && $configuration['valid']
            && !$existing['present']
            && empty($errors)
            && $availability['allowed']
            && !empty($calculation['entries']);

        return [
            'operation' => $operation,
            'period_start' => $this->start,
            'period_end' => $this->end,
            'today' => $this->today,
            'date' => $date,
            'annual' => $annual,
            'configuration' => $configuration,
            'availability' => $availability,
            'existing' => $existing,
            'entries' => $calculation['entries'],
            'entries_count' => count($calculation['entries']),
            'accounts_count' => count($rows),
            'debit' => $calculation['debit'],
            'credit' => $calculation['credit'],
            'errors' => $errors,
            'warnings' => array_values(array_unique($warnings)),
            'blocking_reasons' => array_values(array_unique($blockingReasons)),
            'can_execute' => $canExecute,
        ];
    }

    public function execute(string $operation): int
    {
        $database = database();
        $connection = $database->getCapsule()->getConnection();

        return $connection->transaction(function () use ($operation, $database) {
            $this->lockExercise($database);
            $preview = $this->getPreview($operation);
            if (!$preview['can_execute']) {
                throw new DomainException($this->getBlockingMessage($preview));
            }

            $description = $operation === 'apertura' ? tr('Apertura conto') : tr('Chiusura conto');
            $mastrino = Mastrino::build($description, $preview['date'], 0, true);
            foreach ($preview['entries'] as $entry) {
                $movement = Movimento::build($mastrino, $entry['idconto']);
                $entry['totale'] >= 0
                    ? $movement->setTotale(0, abs($entry['totale']))
                    : $movement->setTotale(abs($entry['totale']), 0);
                $operation === 'apertura' ? $movement->is_apertura = true : $movement->is_chiusura = true;
                $movement->save();
            }

            $verified = $this->getPreview($operation);
            $created = $verified['existing'];
            if (!$created['present'] || $created['status'] !== 'consistent' || count($created['mastrini']) !== 1 || (int) $created['mastrini'][0] !== (int) $mastrino->id) {
                throw new DomainException(tr('Verifica finale delle scritture non superata.'));
            }

            return (int) $mastrino->id;
        });
    }

    protected function getBlockingMessage(array $preview): string
    {
        if (!empty($preview['existing']['present'])) {
            return tr('L\'operazione risulta già registrata. Nessuna registrazione è stata modificata.');
        }
        return !empty($preview['blocking_reasons'])
            ? implode(' ', $preview['blocking_reasons'])
            : tr('Operazione non disponibile per il periodo selezionato.');
    }

    protected function lockExercise($database): void
    {
        $rows = $database->fetchArray(
            'SELECT id FROM zz_settings WHERE nome IN (:opening_name, :closing_name) ORDER BY nome FOR UPDATE',
            ['opening_name' => 'Conto per Apertura conti patrimoniali', 'closing_name' => 'Conto per Chiusura conti patrimoniali']
        );
        if (count($rows) !== 2) {
            throw new DomainException(tr('Configurazione contabile incompleta: conti tecnici non disponibili.'));
        }
    }

    protected function getSettings(): array
    {
        return [
            'apertura' => (int) setting('Conto per Apertura conti patrimoniali'),
            'chiusura' => (int) setting('Conto per Chiusura conti patrimoniali'),
        ];
    }

    protected function validateTechnicalAccounts(array $settings): array
    {
        return self::validateTechnicalAccountsData($settings, [
            'apertura' => $this->getAccountInfo((int) $settings['apertura']),
            'chiusura' => $this->getAccountInfo((int) $settings['chiusura']),
        ]);
    }

    protected function getAccountInfo(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $row = database()->fetchOne(
            'SELECT c3.id, c3.descrizione, c1.descrizione AS gruppo, CONCAT(c2.numero, ".", c3.numero, " ", c3.descrizione) AS descrizione_completa
             FROM co_pianodeiconti3 c3
             INNER JOIN co_pianodeiconti2 c2 ON c2.id=c3.idpianodeiconti2
             INNER JOIN co_pianodeiconti1 c1 ON c1.id=c2.idpianodeiconti1
             WHERE c3.id=:id',
            ['id' => $id]
        );
        return $row ?: null;
    }

    protected function getExisting(string $operation, string $expectedDate): array
    {
        $flag = $operation === 'apertura' ? 'is_apertura' : 'is_chiusura';
        $oppositeFlag = $operation === 'apertura' ? 'is_chiusura' : 'is_apertura';
        $flaggedRows = database()->fetchArray(
            'SELECT id, idmastrino, idconto, data, totale, is_apertura, is_chiusura, iddocumento, id_scadenza
             FROM co_movimenti WHERE '.$flag.'=1 AND DATE(data) BETWEEN :start AND :end ORDER BY idmastrino, id',
            ['start' => $this->start, 'end' => $this->end]
        );
        if (empty($flaggedRows)) {
            return ['present' => false, 'mastrini' => [], 'rows' => 0, 'flagged_rows' => 0, 'balance' => 0.0, 'account_totals' => [], 'anomalies' => [], 'status' => 'none', 'messages' => []];
        }

        $mastrini = array_values(array_unique(array_map('intval', array_column($flaggedRows, 'idmastrino'))));
        $allRows = database()->fetchArray(
            'SELECT id, idmastrino, idconto, data, totale, is_apertura, is_chiusura, iddocumento, id_scadenza
             FROM co_movimenti WHERE idmastrino IN ('.implode(',', $mastrini).') ORDER BY idmastrino, id'
        );
        $balance = 0.0;
        $accountTotals = [];
        $dates = [];
        $flagsValid = true;
        $documentsLinked = false;
        $deadlinesLinked = false;
        foreach ($allRows as $row) {
            $balance += (float) $row['totale'];
            $idConto = (int) $row['idconto'];
            $accountTotals[$idConto] = ($accountTotals[$idConto] ?? 0.0) + (float) $row['totale'];
            $dates[substr((string) $row['data'], 0, 10)] = true;
            $flagsValid = $flagsValid && (int) $row[$flag] === 1 && (int) $row[$oppositeFlag] === 0;
            $documentsLinked = $documentsLinked || !empty($row['iddocumento']);
            $deadlinesLinked = $deadlinesLinked || !empty($row['id_scadenza']);
        }
        ksort($accountTotals);
        $dateKeys = array_keys($dates);
        sort($dateKeys);
        $anomalies = [];
        if (count($mastrini) !== 1) $anomalies[] = tr('Sono presenti più Mastrini tecnici per la stessa operazione e lo stesso esercizio.');
        if ($dateKeys !== [$expectedDate]) $anomalies[] = tr('Le scritture tecniche non risultano registrate esclusivamente nella data prevista.');
        if (!$flagsValid || count($flaggedRows) !== count($allRows)) $anomalies[] = tr('Il Mastrino contiene flag tecnici mancanti o incoerenti.');
        if (abs($balance) > self::TOLERANCE) $anomalies[] = tr('Il Mastrino tecnico non risulta in pareggio.');
        if ($documentsLinked || $deadlinesLinked) $anomalies[] = tr('Le scritture tecniche risultano collegate a documenti o scadenze.');

        return ['present' => true, 'mastrini' => $mastrini, 'rows' => count($allRows), 'flagged_rows' => count($flaggedRows), 'balance' => $balance, 'account_totals' => $accountTotals, 'anomalies' => $anomalies, 'status' => null, 'messages' => []];
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
             WHERE c1.descrizione="Patrimoniale" AND m.data>=:start AND m.data<:end
               AND c3.id<>:closing_account AND m.is_chiusura=0
             GROUP BY c3.id, c2.numero, c3.numero, c3.descrizione HAVING SUM(m.totale)<>0 ORDER BY c2.numero, c3.numero',
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
             WHERE c1.descrizione="Patrimoniale" AND m.data>=:start AND m.data<=:end
               AND c3.id<>:closing_account AND m.is_chiusura=0
             GROUP BY c3.id, c2.numero, c3.numero, c3.descrizione HAVING SUM(m.totale)<>0 ORDER BY c2.numero, c3.numero',
            ['start' => $this->start, 'end' => $this->end, 'closing_account' => $closingAccount]
        );
    }
}
