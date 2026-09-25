<?php
function tr($text, $replacements = []) {
    foreach ($replacements as $key => $value) {
        $text = str_replace($key, $value, $text);
    }
    return $text;
}
function dateFormat($date) { return $date; }

require_once __DIR__.'/../custom/src/Esercizio.php';
require_once __DIR__.'/../custom/src/Workflow.php';

use Modules\Partitario\Esercizio;
use Modules\Partitario\Workflow;

$tests = [];
function ok($condition, $name) {
    global $tests;
    $tests[] = [$name, (bool) $condition];
    echo ($condition ? 'PASS ' : 'FAIL ').$name."\n";
}

// Formula contabile validata.
$rows = [
    ['id' => 1, 'descrizione' => 'Banca', 'totale' => 100.0],
    ['id' => 2, 'descrizione' => 'Fornitore', 'totale' => -40.0],
];
$a = Esercizio::calculateEntries('apertura', $rows, 99, 'Apertura');
$c = Esercizio::calculateEntries('chiusura', $rows, 98, 'Chiusura');
ok($a['entries'][0]['totale'] === 100.0 && $a['entries'][1]['totale'] === -40.0 && $a['entries'][2]['totale'] === -60.0, 'Formula Apertura invariata');
ok($c['entries'][0]['totale'] === -100.0 && $c['entries'][1]['totale'] === 40.0 && $c['entries'][2]['totale'] === 60.0, 'Formula Chiusura invariata');
ok(abs($a['debit'] - $a['credit']) < 0.000001 && abs($c['debit'] - $c['credit']) < 0.000001, 'Pareggio Dare/Avere');

// Fingerprint anteprima.
$f1 = Esercizio::buildFingerprint('apertura', '2026-01-01', '2026-12-31', '2026-01-01', $a['entries']);
$f2 = Esercizio::buildFingerprint('apertura', '2026-01-01', '2026-12-31', '2026-01-01', array_reverse($a['entries']));
$f3 = Esercizio::buildFingerprint('apertura', '2027-01-01', '2027-12-31', '2027-01-01', $a['entries']);
ok($f1 === $f2, 'Fingerprint deterministico indipendente dall ordine');
ok($f1 !== $f3, 'Fingerprint vincolato al periodo');

// Classificazione storica e cambio conto tecnico.
$existing = [
    'present' => true,
    'rows' => 3,
    'account_totals' => [1 => 100.0, 2 => -40.0, 99 => -60.0],
    'account_descriptions' => [1 => 'Banca', 2 => 'Fornitore', 99 => 'Apertura vecchia'],
    'raw_rows' => [
        ['idconto' => 1, 'totale' => 100.0],
        ['idconto' => 2, 'totale' => -40.0],
        ['idconto' => 99, 'totale' => -60.0],
    ],
    'anomalies' => [],
];
$classSame = Esercizio::classifyExisting($existing, 'apertura', $rows, 99, 'Apertura vecchia');
$classChanged = Esercizio::classifyExisting($existing, 'apertura', $rows, 100, 'Apertura nuova');
ok($classSame['status'] === 'consistent' && !$classSame['technical_account_changed'], 'Scrittura storica coerente');
ok($classChanged['status'] === 'consistent' && $classChanged['technical_account_changed'], 'Cambio conto tecnico non produce stale');
ok(str_contains(implode(' ', $classChanged['messages']), 'conto tecnico configurato'), 'Avviso specifico cambio conto tecnico');

$changedRows = [
    ['id' => 1, 'descrizione' => 'Banca', 'totale' => 120.0],
    ['id' => 2, 'descrizione' => 'Fornitore', 'totale' => -40.0],
];
$classStale = Esercizio::classifyExisting($existing, 'apertura', $changedRows, 99, 'Apertura');
ok($classStale['status'] === 'stale', 'Movimento retroattivo produce Da verificare');

$anomalous = $existing;
$anomalous['anomalies'] = ['Mastrino non in pareggio'];
$classAnomaly = Esercizio::classifyExisting($anomalous, 'apertura', $rows, 99, 'Apertura');
ok($classAnomaly['status'] === 'anomaly', 'Anomalia strutturale resta bloccante');

// Conto tecnico presente anche tra i conti ordinari: confronto aggregato compatibile con lo storico.
$rowsSameAccount = [
    ['id' => 1, 'descrizione' => 'Banca', 'totale' => 100.0],
    ['id' => 99, 'descrizione' => 'Apertura', 'totale' => 10.0],
];
$existingSameAccount = [
    'present' => true,
    'rows' => 3,
    'account_totals' => [1 => 100.0, 99 => -100.0],
    'account_descriptions' => [1 => 'Banca', 99 => 'Apertura'],
    'raw_rows' => [
        ['idconto' => 1, 'totale' => 100.0],
        ['idconto' => 99, 'totale' => 10.0],
        ['idconto' => 99, 'totale' => -110.0],
    ],
    'anomalies' => [],
];
$classSameAccount = Esercizio::classifyExisting($existingSameAccount, 'apertura', $rowsSameAccount, 99, 'Apertura');
ok($classSameAccount['status'] === 'consistent', 'Conto Apertura ordinario + contropartita resta compatibile');

// Primo esercizio senza saldi precedenti.
$emptyPreview = [
    'configuration' => ['valid' => true],
    'existing' => ['present' => false, 'status' => 'none', 'temporal_status' => 'consistent'],
    'availability' => ['key' => 'available'],
    'errors' => [],
    'entries' => [],
    'can_execute' => false,
    'annual' => true,
    'has_previous_balances' => false,
    'accounts_count' => 0,
];
$closedPreview = $emptyPreview;
$closedPreview['existing'] = ['present' => true, 'status' => 'consistent', 'temporal_status' => 'consistent'];
$openingState = Workflow::state('apertura', $emptyPreview, false, true, false);
$summary = Workflow::summary($emptyPreview, $closedPreview);
ok($openingState['key'] === 'not_required', 'Primo esercizio: Apertura Non necessaria');
ok($summary['key'] === 'closed', 'Primo esercizio chiuso senza falso allarme continuita');

// Validazione date e periodi.
try { Esercizio::assertValidPeriod('2026-02-30', '2026-12-31'); ok(false, 'Data impossibile rifiutata'); } catch (DomainException $e) { ok(true, 'Data impossibile rifiutata'); }
try { Esercizio::assertValidPeriod('2026/01/01', '2026-12-31'); ok(false, 'Formato data non valido rifiutato'); } catch (DomainException $e) { ok(true, 'Formato data non valido rifiutato'); }
try { Esercizio::assertValidPeriod('2026-12-31', '2026-01-01'); ok(false, 'Periodo inverso rifiutato'); } catch (DomainException $e) { ok(true, 'Periodo inverso rifiutato'); }

// Conti tecnici.
$validAccounts = [
    'apertura' => ['gruppo' => 'Patrimoniale'],
    'chiusura' => ['gruppo' => 'Patrimoniale'],
];
ok(Esercizio::validateTechnicalAccountsData(['apertura' => 10, 'chiusura' => 11], $validAccounts)['valid'], 'Conti tecnici validi');
ok(!Esercizio::validateTechnicalAccountsData(['apertura' => 10, 'chiusura' => 10], $validAccounts)['valid'], 'Conti tecnici uguali bloccati');
$badAccounts = $validAccounts; $badAccounts['chiusura']['gruppo'] = 'Economico';
ok(!Esercizio::validateTechnicalAccountsData(['apertura' => 10, 'chiusura' => 11], $badAccounts)['valid'], 'Conto tecnico economico bloccato');

// Lettura bloccante dopo il lock e numerazione basata sull'ultimo dato committato.
class TestEsercizio extends Esercizio {
    public function assertAbsent($db, string $op): void { $this->assertOperationAbsentForUpdate($db, $op); }
    public function nextMastrino($db): int { return $this->reserveNextMastrino($db); }
}
class FakeDb {
    public array $queries = [];
    public array $existingRows = [];
    public array $lastRow = [];
    public function fetchArray($sql, $params = []) { $this->queries[] = $sql; return $this->existingRows; }
    public function fetchOne($sql, $params = []) { $this->queries[] = $sql; return $this->lastRow; }
}
$db = new FakeDb();
$t = new TestEsercizio('2026-01-01', '2026-12-31', '2026-12-31');
$t->assertAbsent($db, 'apertura');
ok(str_contains($db->queries[0], 'FOR UPDATE'), 'A1 lettura esistenza bloccante presente');
$db->lastRow = ['idmastrino' => 123];
ok($t->nextMastrino($db) === 124 && str_contains(end($db->queries), 'FOR UPDATE'), 'A1 numerazione legge ultimo Mastrino con lock');
$db2 = new FakeDb(); $db2->existingRows = [['id' => 1, 'idmastrino' => 124]];
try { $t->assertAbsent($db2, 'apertura'); ok(false, 'Seconda esecuzione dopo lock rifiutata'); } catch (DomainException $e) { ok(true, 'Seconda esecuzione dopo lock rifiutata'); }

$failed = count(array_filter($tests, static fn ($test) => !$test[1]));
echo "SUMMARY ".(count($tests)-$failed)."/".count($tests)." PASS\n";
exit($failed ? 1 : 0);
