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

    private array $openingBalancesCache = [];
    private array $closingBalancesCache = [];
    private array $existingCache = [];
    private array $accountInfoCache = [];

    public function __construct(
        protected string $start,
        protected string $end,
        ?string $today = null,
    ) {
        self::assertValidPeriod($start, $end);
        $today = $today ?: date('Y-m-d');
        self::assertValidDate($today, tr('Data corrente non valida.'));
        $this->today = $today;
    }

    public static function assertValidDate(string $date, ?string $message = null): void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new DomainException($message ?: tr('Formato data non valido.'));
        }

        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = \DateTimeImmutable::getLastErrors();
        $hasErrors = is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0);
        if (!$parsed || $hasErrors || $parsed->format('Y-m-d') !== $date) {
            throw new DomainException($message ?: tr('Data non valida.'));
        }
    }

    public static function assertValidPeriod(string $start, string $end): void
    {
        self::assertValidDate($start, tr('Data iniziale del periodo non valida.'));
        self::assertValidDate($end, tr('Data finale del periodo non valida.'));
        if ($start > $end) {
            throw new DomainException(tr('La data iniziale del periodo non può essere successiva alla data finale.'));
        }
    }

    public function isAnnual(): bool
    {
        $start = new \DateTimeImmutable($this->start);
        $expectedEnd = $start->modify('+1 year -1 day');

        return $expectedEnd->format('Y-m-d') === $this->end;
    }

    public function getToday(): string
    {
        return $this->today;
    }

    public static function temporalAvailability(string $operation, string $start, string $end, string $today): array
    {
        if (!in_array($operation, ['apertura', 'chiusura'], true)) {
            throw new DomainException(tr('Operazione non valida.'));
        }
        self::assertValidPeriod($start, $end);
        self::assertValidDate($today, tr('Data corrente non valida.'));

        $availableFrom = $operation === 'apertura' ? $start : $end;
        $key = 'available';
        $message = null;

        if ($today < $availableFrom) {
            if ($operation === 'apertura') {
                $key = 'before_start';
                $message = tr('L\'Apertura sarà disponibile dal _DATE_.', ['_DATE_' => dateFormat($availableFrom)]);
            } else {
                $key = 'before_end';
                $message = tr('La Chiusura sarà disponibile dal _DATE_.', ['_DATE_' => dateFormat($availableFrom)]);
            }
        }

        return [
            'key' => $key,
            'allowed' => $key === 'available',
            'available_from' => $availableFrom,
            'message' => $message,
        ];
    }

    /**
     * Mantiene la formula Dare/Avere validata nelle RC precedenti e nel nativo 2.10.4.
     */
    public static function calculateEntries(string $operation, array $rows, int $counterpartId, string $counterpartDescription): array
    {
        $ordinaryEntries = self::calculateOrdinaryEntries($operation, $rows);
        $entries = $ordinaryEntries;
        $ordinaryTotal = array_sum(array_column($ordinaryEntries, 'totale'));

        if ($rows) {
            $entries[] = [
                'idconto' => $counterpartId,
                'descrizione' => $counterpartDescription,
                'totale' => -$ordinaryTotal,
                'saldo_origine' => null,
                'contropartita' => true,
            ];
        }

        [$debit, $credit] = self::totalsFromEntries($entries);

        return [
            'entries' => $entries,
            'ordinary_entries' => $ordinaryEntries,
            'counterpart_total' => -$ordinaryTotal,
            'debit' => $debit,
            'credit' => $credit,
        ];
    }

    public static function calculateOrdinaryEntries(string $operation, array $rows): array
    {
        if (!in_array($operation, ['apertura', 'chiusura'], true)) {
            throw new DomainException(tr('Operazione non valida.'));
        }

        $entries = [];
        foreach ($rows as $row) {
            $value = (float) $row['totale'];
            $entries[] = [
                'idconto' => (int) $row['id'],
                'descrizione' => (string) ($row['descrizione'] ?? ''),
                'totale' => $operation === 'apertura' ? $value : -$value,
                'saldo_origine' => $value,
                'contropartita' => false,
            ];
        }

        return $entries;
    }

    public static function totalsFromEntries(array $entries): array
    {
        $debit = 0.0;
        $credit = 0.0;
        foreach ($entries as $entry) {
            $value = (float) $entry['totale'];
            if ($value >= 0) {
                $debit += $value;
            } else {
                $credit += abs($value);
            }
        }

        return [$debit, $credit];
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

    public static function compareTotals(array $expected, array $actual, float $tolerance = self::TOLERANCE): bool
    {
        $normalizedExpected = [];
        foreach ($expected as $id => $value) {
            if (abs((float) $value) > $tolerance) {
                $normalizedExpected[(int) $id] = (float) $value;
            }
        }
        $normalizedActual = [];
        foreach ($actual as $id => $value) {
            if (abs((float) $value) > $tolerance) {
                $normalizedActual[(int) $id] = (float) $value;
            }
        }
        ksort($normalizedExpected);
        ksort($normalizedActual);

        if (array_keys($normalizedExpected) !== array_keys($normalizedActual)) {
            return false;
        }

        foreach ($normalizedExpected as $id => $value) {
            if (abs($value - $normalizedActual[$id]) > $tolerance) {
                return false;
            }
        }

        return true;
    }

    public static function buildFingerprint(
        string $operation,
        string $periodStart,
        string $periodEnd,
        string $registrationDate,
        array $entries,
    ): string {
        self::assertValidPeriod($periodStart, $periodEnd);
        self::assertValidDate($registrationDate, tr('Data di registrazione non valida.'));

        $normalized = [];
        foreach ($entries as $entry) {
            $normalized[] = [
                'idconto' => (int) $entry['idconto'],
                'totale' => number_format(round((float) $entry['totale'], 2), 2, '.', ''),
                'contropartita' => !empty($entry['contropartita']),
            ];
        }
        usort($normalized, static function (array $a, array $b): int {
            return [$a['idconto'], $a['contropartita'], $a['totale']] <=> [$b['idconto'], $b['contropartita'], $b['totale']];
        });

        return hash('sha256', json_encode([
            'operation' => $operation,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'registration_date' => $registrationDate,
            'rows_count' => count($normalized),
            'entries' => $normalized,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Confronta una scrittura storica con i saldi ordinari attesi, ricavando la
     * contropartita dal Mastrino registrato. Il cambio dell'impostazione corrente
     * non rende quindi stale un libro giornale ancora coerente.
     */
    public static function classifyExisting(
        array $existing,
        string $operation,
        array $rows,
        int $configuredCounterpartId,
        string $configuredCounterpartDescription = '',
    ): array {
        if (empty($existing['present'])) {
            return [
                'status' => 'none',
                'messages' => [],
                'technical_account_changed' => false,
                'registered_counterpart_id' => null,
                'expected_entries' => self::calculateEntries($operation, $rows, $configuredCounterpartId, $configuredCounterpartDescription)['entries'],
                'comparison' => [],
            ];
        }

        $messages = array_values($existing['anomalies'] ?? []);
        $ordinaryEntries = self::calculateOrdinaryEntries($operation, $rows);
        $ordinaryTotals = self::aggregateEntries($ordinaryEntries);
        $counterpartTotal = -array_sum($ordinaryTotals);
        $actualTotals = $existing['account_totals'] ?? [];
        $registeredCounterpart = self::identifyRegisteredCounterpart(
            $actualTotals,
            $ordinaryTotals,
            $counterpartTotal,
            $existing['raw_rows'] ?? [],
            $configuredCounterpartId
        );
        $registeredCounterpartId = $registeredCounterpart['id'] ?? null;
        $registeredCounterpartTotal = $registeredCounterpart['total'] ?? null;

        if ($registeredCounterpartId === null || $registeredCounterpartTotal === null) {
            $messages[] = tr('Non è stato possibile identificare in modo univoco la contropartita tecnica registrata.');
        }

        $expectedEntries = $ordinaryEntries;
        if ($rows && $registeredCounterpartId !== null) {
            $expectedEntries[] = [
                'idconto' => $registeredCounterpartId,
                'descrizione' => $existing['account_descriptions'][$registeredCounterpartId]
                    ?? $configuredCounterpartDescription
                    ?? tr('Conto tecnico'),
                'totale' => $counterpartTotal,
                'saldo_origine' => null,
                'contropartita' => true,
            ];
        }

        $expectedTotals = self::aggregateEntries($expectedEntries);
        $comparison = self::buildComparison(
            $actualTotals,
            $expectedTotals,
            $existing['account_descriptions'] ?? [],
            self::descriptionsFromEntries($expectedEntries)
        );

        if ($messages) {
            return [
                'status' => 'anomaly',
                'messages' => array_values(array_unique($messages)),
                'technical_account_changed' => false,
                'registered_counterpart_id' => $registeredCounterpartId,
                'expected_entries' => $expectedEntries,
                'comparison' => $comparison,
            ];
        }

        $actualOrdinary = $actualTotals;
        if ($registeredCounterpartId !== null && $registeredCounterpartTotal !== null) {
            $actualOrdinary[$registeredCounterpartId] = ($actualOrdinary[$registeredCounterpartId] ?? 0.0) - $registeredCounterpartTotal;
        }

        if (!self::compareTotals($ordinaryTotals, $actualOrdinary)) {
            return [
                'status' => 'stale',
                'messages' => [
                    tr('Sono stati rilevati movimenti successivi o modifiche retroattive. Nessuna correzione automatica verrà eseguita.'),
                ],
                'technical_account_changed' => false,
                'registered_counterpart_id' => $registeredCounterpartId,
                'expected_entries' => $expectedEntries,
                'comparison' => $comparison,
            ];
        }

        if ((int) ($existing['rows'] ?? 0) !== count($ordinaryEntries) + ($rows ? 1 : 0)) {
            return [
                'status' => 'anomaly',
                'messages' => [tr('Il numero delle righe tecniche non coincide con la scrittura attesa.')],
                'technical_account_changed' => false,
                'registered_counterpart_id' => $registeredCounterpartId,
                'expected_entries' => $expectedEntries,
                'comparison' => $comparison,
            ];
        }

        $technicalAccountChanged = $registeredCounterpartId !== null
            && $configuredCounterpartId > 0
            && $registeredCounterpartId !== $configuredCounterpartId;
        if ($technicalAccountChanged) {
            $messages[] = tr('Il conto tecnico configurato è cambiato dopo la registrazione dell\'operazione.');
        }

        return [
            'status' => 'consistent',
            'messages' => $messages,
            'technical_account_changed' => $technicalAccountChanged,
            'registered_counterpart_id' => $registeredCounterpartId,
            'expected_entries' => $expectedEntries,
            'comparison' => $comparison,
        ];
    }

    private static function identifyRegisteredCounterpart(
        array $actualTotals,
        array $ordinaryTotals,
        float $expectedCounterpartTotal,
        array $rawRows,
        int $configuredCounterpartId,
    ): ?array {
        $deltaCandidates = [];
        $ids = array_unique(array_merge(array_keys($actualTotals), array_keys($ordinaryTotals)));
        foreach ($ids as $id) {
            $delta = (float) ($actualTotals[$id] ?? 0.0) - (float) ($ordinaryTotals[$id] ?? 0.0);
            if (abs($delta - $expectedCounterpartTotal) <= self::TOLERANCE) {
                $deltaCandidates[(int) $id] = $delta;
            }
        }
        if (count($deltaCandidates) === 1) {
            $id = (int) array_key_first($deltaCandidates);
            return ['id' => $id, 'total' => (float) $deltaCandidates[$id]];
        }

        // Se i saldi ordinari sono cambiati dopo la registrazione, il delta rispetto
        // all'anteprima attuale non identifica più la contropartita. Quando il conto
        // configurato non è cambiato, usa la riga storica su quel conto.
        if ($configuredCounterpartId > 0) {
            $rowsOnConfigured = array_values(array_filter(
                $rawRows,
                static fn (array $row): bool => (int) ($row['idconto'] ?? 0) === $configuredCounterpartId
            ));
            if (count($rowsOnConfigured) === 1) {
                return [
                    'id' => $configuredCounterpartId,
                    'total' => (float) $rowsOnConfigured[0]['totale'],
                ];
            }
            if (count($rowsOnConfigured) > 1) {
                $best = null;
                foreach ($rowsOnConfigured as $row) {
                    $candidateTotal = (float) $row['totale'];
                    $candidateOrdinary = $actualTotals;
                    $candidateOrdinary[$configuredCounterpartId] = ($candidateOrdinary[$configuredCounterpartId] ?? 0.0) - $candidateTotal;
                    $distance = self::totalsDistance($ordinaryTotals, $candidateOrdinary);
                    if ($best === null || $distance < $best['distance']) {
                        $best = ['id' => $configuredCounterpartId, 'total' => $candidateTotal, 'distance' => $distance];
                    }
                }
                if ($best !== null) {
                    return ['id' => $best['id'], 'total' => $best['total']];
                }
            }
        }

        if (abs($expectedCounterpartTotal) <= self::TOLERANCE) {
            $zeroCandidates = [];
            foreach ($rawRows as $row) {
                if (abs((float) ($row['totale'] ?? 0.0)) <= self::TOLERANCE) {
                    $zeroCandidates[(int) $row['idconto']] = (float) $row['totale'];
                }
            }
            if (count($zeroCandidates) === 1) {
                $id = (int) array_key_first($zeroCandidates);
                return ['id' => $id, 'total' => (float) $zeroCandidates[$id]];
            }
        }

        return null;
    }

    private static function totalsDistance(array $expected, array $actual): float
    {
        $ids = array_unique(array_merge(array_keys($expected), array_keys($actual)));
        $distance = 0.0;
        foreach ($ids as $id) {
            $distance += abs((float) ($expected[$id] ?? 0.0) - (float) ($actual[$id] ?? 0.0));
        }
        return $distance;
    }

    private static function descriptionsFromEntries(array $entries): array
    {
        $result = [];
        foreach ($entries as $entry) {
            $result[(int) $entry['idconto']] = (string) ($entry['descrizione'] ?? '');
        }

        return $result;
    }

    public static function buildComparison(
        array $registeredTotals,
        array $expectedTotals,
        array $registeredDescriptions = [],
        array $expectedDescriptions = [],
    ): array {
        $ids = array_unique(array_merge(array_keys($registeredTotals), array_keys($expectedTotals)));
        sort($ids, SORT_NUMERIC);
        $rows = [];
        foreach ($ids as $id) {
            $registered = (float) ($registeredTotals[$id] ?? 0.0);
            $expected = (float) ($expectedTotals[$id] ?? 0.0);
            $difference = $registered - $expected;
            $rows[] = [
                'idconto' => (int) $id,
                'descrizione' => $registeredDescriptions[$id] ?? $expectedDescriptions[$id] ?? ('#'.(int) $id),
                'registered' => $registered,
                'expected' => $expected,
                'difference' => $difference,
                'different' => abs($difference) > self::TOLERANCE,
            ];
        }

        return $rows;
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

    public function getPreview(string $operation, bool $locking = false): array
    {
        if (!in_array($operation, ['apertura', 'chiusura'], true)) {
            throw new DomainException(tr('Operazione non valida.'));
        }

        $settings = $this->getSettings();
        $configuration = $this->validateTechnicalAccounts($settings);
        $date = $operation === 'apertura' ? $this->start : $this->end;
        $openingExisting = $this->getExisting('apertura', $this->start, $locking);
        $closingExisting = $this->getExisting('chiusura', $this->end, $locking);
        $existing = $operation === 'apertura' ? $openingExisting : $closingExisting;
        $openingRows = $this->getOpeningBalances($settings['chiusura'], $locking);
        $rows = $operation === 'apertura'
            ? $openingRows
            : $this->getClosingBalances($settings['chiusura'], $locking);
        $counterpartId = $operation === 'apertura' ? $settings['apertura'] : $settings['chiusura'];
        $account = $configuration['accounts'][$operation] ?? null;
        $counterpartDescription = $account['descrizione_completa'] ?? tr('Conto tecnico');
        $calculation = self::calculateEntries($operation, $rows, $counterpartId, $counterpartDescription);

        $classification = self::classifyExisting(
            $existing,
            $operation,
            $rows,
            $counterpartId,
            $counterpartDescription
        );
        $existing = array_merge($existing, $classification);

        if ($operation === 'chiusura' && $openingExisting['present']) {
            $openingClassification = self::classifyExisting(
                $openingExisting,
                'apertura',
                $openingRows,
                $settings['apertura'],
                $configuration['accounts']['apertura']['descrizione_completa'] ?? tr('Conto tecnico')
            );
            $openingExisting = array_merge($openingExisting, $openingClassification);
        }

        $errors = [];
        $warnings = [];
        $blockingReasons = [];
        $annual = $this->isAnnual();
        $availability = self::temporalAvailability($operation, $this->start, $this->end, $this->today);
        $hasPreviousBalances = !empty($openingRows);

        $existing['temporal_status'] = 'consistent';
        if ($existing['present'] && !$availability['allowed']) {
            $existing['temporal_status'] = 'future';
            $existing['messages'][] = $operation === 'apertura'
                ? tr('L\'Apertura risulta già registrata, ma la data iniziale dell\'esercizio non è ancora stata raggiunta. Nessuna correzione automatica verrà eseguita.')
                : tr('La Chiusura risulta già registrata, ma la data finale dell\'esercizio non è ancora stata raggiunta. Nessuna correzione automatica verrà eseguita.');
        }
        $existing['messages'] = array_values(array_unique($existing['messages'] ?? []));

        if (!$annual) {
            $errors[] = tr('Apertura e Chiusura esercizio sono disponibili solo per periodi della durata di un anno.');
        }
        if (!$configuration['valid']) {
            $errors = array_merge($errors, $configuration['errors']);
        }
        if ($operation === 'apertura' && !$existing['present'] && $closingExisting['present'] && $hasPreviousBalances) {
            $errors[] = tr('Apertura non rilevata: l\'esercizio risulta già chiuso. Verificare lo storico prima di qualsiasi intervento.');
        }
        if ($operation === 'chiusura' && !$existing['present'] && !$openingExisting['present'] && $hasPreviousBalances) {
            $errors[] = tr('Prima di chiudere l\'esercizio è necessario registrare l\'Apertura dei saldi precedenti.');
        }
        if (
            $operation === 'chiusura'
            && !$existing['present']
            && $openingExisting['present']
            && in_array($openingExisting['status'] ?? null, ['stale', 'anomaly'], true)
        ) {
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
            $blockingReasons[] = $operation === 'apertura' && !$hasPreviousBalances
                ? tr('L\'Apertura non è necessaria: non esistono saldi patrimoniali precedenti da riportare.')
                : tr('Nessun saldo patrimoniale da elaborare per il periodo selezionato.');
        }
        if ($existing['present']) {
            $warnings = array_merge($warnings, $existing['messages']);
        }

        $fingerprint = self::buildFingerprint(
            $operation,
            $this->start,
            $this->end,
            $date,
            $calculation['entries']
        );
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
            'has_previous_balances' => $hasPreviousBalances,
            'opening_required' => $hasPreviousBalances,
            'configuration' => $configuration,
            'availability' => $availability,
            'existing' => $existing,
            'entries' => $calculation['entries'],
            'entries_count' => count($calculation['entries']),
            'accounts_count' => count($rows),
            'debit' => $calculation['debit'],
            'credit' => $calculation['credit'],
            'fingerprint' => $fingerprint,
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
            'blocking_reasons' => array_values(array_unique($blockingReasons)),
            'can_execute' => $canExecute,
        ];
    }

    public function execute(
        string $operation,
        string $expectedFingerprint,
        string $expectedStart,
        string $expectedEnd,
    ): int {
        if ($expectedStart !== $this->start || $expectedEnd !== $this->end) {
            throw new DomainException(tr('L\'anteprima è cambiata. Riapri Gestione esercizio e verifica nuovamente i dati.'));
        }
        if ($expectedFingerprint === '') {
            throw new DomainException(tr('L\'anteprima è cambiata. Riapri Gestione esercizio e verifica nuovamente i dati.'));
        }

        $database = database();
        $connection = $database->getCapsule()->getConnection();

        return $connection->transaction(function () use ($operation, $database, $expectedFingerprint) {
            $this->lockExercise($database);
            $this->clearCaches();
            $this->assertOperationAbsentForUpdate($database, $operation);
            $nextMastrino = $this->reserveNextMastrino($database);

            $preview = $this->getPreview($operation, true);
            if (!hash_equals($preview['fingerprint'], $expectedFingerprint)) {
                throw new DomainException(tr('L\'anteprima è cambiata. Riapri Gestione esercizio e verifica nuovamente i dati.'));
            }
            if (!$preview['can_execute']) {
                throw new DomainException($this->getBlockingMessage($preview));
            }

            $description = $operation === 'apertura' ? tr('Apertura conto') : tr('Chiusura conto');
            $mastrino = Mastrino::build($description, $preview['date'], 0, true);
            $mastrino->idmastrino = $nextMastrino;

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

            $this->clearCaches();
            $verified = $this->getPreview($operation, true);
            $created = $verified['existing'];
            if (
                !$created['present']
                || $created['status'] !== 'consistent'
                || count($created['mastrini']) !== 1
                || (int) $created['mastrini'][0] !== (int) $mastrino->id
            ) {
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
            'SELECT id FROM zz_settings
             WHERE nome IN (:opening_name, :closing_name)
             ORDER BY nome FOR UPDATE',
            [
                'opening_name' => 'Conto per Apertura conti patrimoniali',
                'closing_name' => 'Conto per Chiusura conti patrimoniali',
            ]
        );
        if (count($rows) !== 2) {
            throw new DomainException(tr('Configurazione contabile incompleta: conti tecnici non disponibili.'));
        }
    }

    protected function assertOperationAbsentForUpdate($database, string $operation): void
    {
        $flag = $operation === 'apertura' ? 'is_apertura' : 'is_chiusura';
        $rows = $database->fetchArray(
            'SELECT id, idmastrino FROM co_movimenti
             WHERE '.$flag.'=1 AND DATE(data) BETWEEN :start AND :end
             ORDER BY id FOR UPDATE',
            ['start' => $this->start, 'end' => $this->end]
        );
        if (!empty($rows)) {
            throw new DomainException(tr('L\'operazione risulta già registrata. Nessuna registrazione è stata modificata.'));
        }
    }

    protected function reserveNextMastrino($database): int
    {
        $row = $database->fetchOne(
            'SELECT idmastrino FROM co_movimenti ORDER BY idmastrino DESC LIMIT 1 FOR UPDATE'
        );
        return (int) ($row['idmastrino'] ?? 0) + 1;
    }

    protected function clearCaches(): void
    {
        $this->openingBalancesCache = [];
        $this->closingBalancesCache = [];
        $this->existingCache = [];
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
        if (array_key_exists($id, $this->accountInfoCache)) {
            return $this->accountInfoCache[$id];
        }

        $row = database()->fetchOne(
            'SELECT c3.id, c3.descrizione, c1.descrizione AS gruppo,
                    CONCAT(c2.numero, ".", c3.numero, " ", c3.descrizione) AS descrizione_completa
             FROM co_pianodeiconti3 c3
             INNER JOIN co_pianodeiconti2 c2 ON c2.id=c3.idpianodeiconti2
             INNER JOIN co_pianodeiconti1 c1 ON c1.id=c2.idpianodeiconti1
             WHERE c3.id=:id',
            ['id' => $id]
        );
        $this->accountInfoCache[$id] = $row ?: null;

        return $this->accountInfoCache[$id];
    }

    protected function getExisting(string $operation, string $expectedDate, bool $locking = false): array
    {
        $cacheKey = $operation.'|'.$expectedDate;
        if (!$locking && array_key_exists($cacheKey, $this->existingCache)) {
            return $this->existingCache[$cacheKey];
        }

        $flag = $operation === 'apertura' ? 'is_apertura' : 'is_chiusura';
        $oppositeFlag = $operation === 'apertura' ? 'is_chiusura' : 'is_apertura';
        $lockingClause = $locking ? ' FOR UPDATE' : '';
        $flaggedRows = database()->fetchArray(
            'SELECT id, idmastrino, idconto, data, totale, is_apertura, is_chiusura,
                    iddocumento, id_scadenza
             FROM co_movimenti
             WHERE '.$flag.'=1 AND DATE(data) BETWEEN :start AND :end
             ORDER BY idmastrino, id'.$lockingClause,
            ['start' => $this->start, 'end' => $this->end]
        );

        if (empty($flaggedRows)) {
            $result = [
                'present' => false,
                'mastrini' => [],
                'rows' => 0,
                'flagged_rows' => 0,
                'balance' => 0.0,
                'debit' => 0.0,
                'credit' => 0.0,
                'account_totals' => [],
                'account_descriptions' => [],
                'raw_rows' => [],
                'anomalies' => [],
                'status' => 'none',
                'messages' => [],
            ];
            if (!$locking) {
                $this->existingCache[$cacheKey] = $result;
            }
            return $result;
        }

        $mastrini = array_values(array_unique(array_map('intval', array_column($flaggedRows, 'idmastrino'))));
        $ids = implode(',', $mastrini);
        $allRows = database()->fetchArray(
            'SELECT m.id, m.idmastrino, m.idconto, m.data, m.totale,
                    m.is_apertura, m.is_chiusura, m.iddocumento, m.id_scadenza,
                    CONCAT(c2.numero, ".", c3.numero, " ", c3.descrizione) AS descrizione_conto
             FROM co_movimenti m
             LEFT JOIN co_pianodeiconti3 c3 ON c3.id=m.idconto
             LEFT JOIN co_pianodeiconti2 c2 ON c2.id=c3.idpianodeiconti2
             WHERE m.idmastrino IN ('.$ids.')
             ORDER BY m.idmastrino, m.id'.$lockingClause
        );

        $balance = 0.0;
        $debit = 0.0;
        $credit = 0.0;
        $accountTotals = [];
        $accountDescriptions = [];
        $dates = [];
        $flagsValid = true;
        $documentsLinked = false;
        $deadlinesLinked = false;
        foreach ($allRows as $row) {
            $value = (float) $row['totale'];
            $balance += $value;
            if ($value >= 0) {
                $debit += $value;
            } else {
                $credit += abs($value);
            }
            $idConto = (int) $row['idconto'];
            $accountTotals[$idConto] = ($accountTotals[$idConto] ?? 0.0) + $value;
            $accountDescriptions[$idConto] = (string) ($row['descrizione_conto'] ?? ('#'.$idConto));
            $dates[substr((string) $row['data'], 0, 10)] = true;
            $flagsValid = $flagsValid && (int) $row[$flag] === 1 && (int) $row[$oppositeFlag] === 0;
            $documentsLinked = $documentsLinked || !empty($row['iddocumento']);
            $deadlinesLinked = $deadlinesLinked || !empty($row['id_scadenza']);
        }
        ksort($accountTotals);
        ksort($accountDescriptions);

        $anomalies = [];
        if (count($mastrini) !== 1) {
            $anomalies[] = tr('Sono presenti più Mastrini tecnici per la stessa operazione e lo stesso esercizio.');
        }
        $dateKeys = array_keys($dates);
        sort($dateKeys);
        if ($dateKeys !== [$expectedDate]) {
            $anomalies[] = tr('Le scritture tecniche non risultano registrate esclusivamente nella data prevista.');
        }
        if (!$flagsValid || count($flaggedRows) !== count($allRows)) {
            $anomalies[] = tr('Il Mastrino contiene flag tecnici mancanti o incoerenti.');
        }
        if (abs($balance) > self::TOLERANCE) {
            $anomalies[] = tr('Il Mastrino tecnico non risulta in pareggio.');
        }
        if ($documentsLinked || $deadlinesLinked) {
            $anomalies[] = tr('Le scritture tecniche risultano collegate a documenti o scadenze.');
        }

        $result = [
            'present' => true,
            'mastrini' => $mastrini,
            'rows' => count($allRows),
            'flagged_rows' => count($flaggedRows),
            'balance' => $balance,
            'debit' => $debit,
            'credit' => $credit,
            'account_totals' => $accountTotals,
            'account_descriptions' => $accountDescriptions,
            'raw_rows' => $allRows,
            'anomalies' => $anomalies,
            'status' => null,
            'messages' => [],
        ];
        if (!$locking) {
            $this->existingCache[$cacheKey] = $result;
        }

        return $result;
    }

    protected function getOpeningBalances(int $closingAccount, bool $locking = false): array
    {
        $cacheKey = (string) $closingAccount;
        if (!$locking && array_key_exists($cacheKey, $this->openingBalancesCache)) {
            return $this->openingBalancesCache[$cacheKey];
        }
        $previousStart = (new \DateTimeImmutable($this->start))->modify('-1 year')->format('Y-m-d');
        $rows = $locking
            ? $this->getBalancesForUpdate($previousStart, $this->start, false, $closingAccount)
            : database()->fetchArray(
                'SELECT c3.id, CONCAT(c2.numero, ".", c3.numero, " ", c3.descrizione) AS descrizione, SUM(m.totale) AS totale
                 FROM co_pianodeiconti3 c3
                 INNER JOIN co_pianodeiconti2 c2 ON c3.idpianodeiconti2=c2.id
                 INNER JOIN co_pianodeiconti1 c1 ON c2.idpianodeiconti1=c1.id
                 INNER JOIN co_movimenti m ON c3.id=m.idconto
                 WHERE c1.descrizione="Patrimoniale" AND m.data>=:start AND m.data<:end
                   AND c3.id<>:closing_account AND m.is_chiusura=0
                 GROUP BY c3.id, c2.numero, c3.numero, c3.descrizione
                 HAVING SUM(m.totale)<>0 ORDER BY c2.numero, c3.numero',
                ['start' => $previousStart, 'end' => $this->start, 'closing_account' => $closingAccount]
            );
        if (!$locking) {
            $this->openingBalancesCache[$cacheKey] = $rows;
        }
        return $rows;
    }

    protected function getClosingBalances(int $closingAccount, bool $locking = false): array
    {
        $cacheKey = (string) $closingAccount;
        if (!$locking && array_key_exists($cacheKey, $this->closingBalancesCache)) {
            return $this->closingBalancesCache[$cacheKey];
        }
        $rows = $locking
            ? $this->getBalancesForUpdate($this->start, $this->end, true, $closingAccount)
            : database()->fetchArray(
                'SELECT c3.id, CONCAT(c2.numero, ".", c3.numero, " ", c3.descrizione) AS descrizione, SUM(m.totale) AS totale
                 FROM co_pianodeiconti3 c3
                 INNER JOIN co_pianodeiconti2 c2 ON c3.idpianodeiconti2=c2.id
                 INNER JOIN co_pianodeiconti1 c1 ON c2.idpianodeiconti1=c1.id
                 INNER JOIN co_movimenti m ON c3.id=m.idconto
                 WHERE c1.descrizione="Patrimoniale" AND m.data>=:start AND m.data<=:end
                   AND c3.id<>:closing_account AND m.is_chiusura=0
                 GROUP BY c3.id, c2.numero, c3.numero, c3.descrizione
                 HAVING SUM(m.totale)<>0 ORDER BY c2.numero, c3.numero',
                ['start' => $this->start, 'end' => $this->end, 'closing_account' => $closingAccount]
            );
        if (!$locking) {
            $this->closingBalancesCache[$cacheKey] = $rows;
        }
        return $rows;
    }

    private function getBalancesForUpdate(string $start, string $end, bool $inclusiveEnd, int $closingAccount): array
    {
        $operator = $inclusiveEnd ? '<=' : '<';
        $raw = database()->fetchArray(
            'SELECT c3.id, CONCAT(c2.numero, ".", c3.numero, " ", c3.descrizione) AS descrizione, m.totale
             FROM co_pianodeiconti3 c3
             INNER JOIN co_pianodeiconti2 c2 ON c3.idpianodeiconti2=c2.id
             INNER JOIN co_pianodeiconti1 c1 ON c2.idpianodeiconti1=c1.id
             INNER JOIN co_movimenti m ON c3.id=m.idconto
             WHERE c1.descrizione="Patrimoniale" AND m.data>=:start AND m.data'.$operator.':end
               AND c3.id<>:closing_account AND m.is_chiusura=0
             ORDER BY c2.numero, c3.numero, m.id FOR UPDATE',
            ['start' => $start, 'end' => $end, 'closing_account' => $closingAccount]
        );
        $aggregated = [];
        foreach ($raw as $row) {
            $id = (int) $row['id'];
            if (!isset($aggregated[$id])) {
                $aggregated[$id] = ['id' => $id, 'descrizione' => (string) $row['descrizione'], 'totale' => 0.0];
            }
            $aggregated[$id]['totale'] += (float) $row['totale'];
        }
        return array_values(array_filter($aggregated, static fn (array $row): bool => (float) $row['totale'] != 0.0));
    }
}
