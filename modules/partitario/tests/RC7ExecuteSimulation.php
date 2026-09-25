<?php
namespace Modules\PrimaNota {
    class Mastrino {
        public int $idmastrino = 0;
        public function __get($name) { return $name === 'id' ? $this->idmastrino : null; }
        public function __set($name, $value) { if ($name === 'idmastrino') $this->idmastrino = (int) $value; }
        public static function build($description = null, $date = null, $insoluto = false, $contabile = false) {
            return new static();
        }
    }
    class Movimento {
        public static array $rows = [];
        public static ?int $throwOnSaveNumber = null;
        public static int $saveCount = 0;
        public $is_apertura = false;
        public $is_chiusura = false;
        private $mastrino;
        private int $idconto;
        private float $totale = 0.0;
        public static function build($mastrino, $idconto) {
            $m = new static(); $m->mastrino = $mastrino; $m->idconto = (int) $idconto; return $m;
        }
        public function setTotale($avere, $dare) { $this->totale = (float) $dare - (float) $avere; }
        public function save() {
            self::$saveCount++;
            if (self::$throwOnSaveNumber === self::$saveCount) throw new \RuntimeException('simulated insert failure');
            self::$rows[] = [
                'idmastrino' => $this->mastrino->idmastrino,
                'idconto' => $this->idconto,
                'totale' => $this->totale,
                'is_apertura' => $this->is_apertura,
                'is_chiusura' => $this->is_chiusura,
            ];
        }
    }
}

namespace {
function tr($text, $replacements = []) { foreach ($replacements as $k=>$v) $text=str_replace($k,$v,$text); return $text; }
function dateFormat($date) { return $date; }
function setting($name) { return str_contains($name, 'Apertura') ? 99 : 98; }
class FakeConnection {
    public function transaction($callback) {
        $snapshot = \Modules\PrimaNota\Movimento::$rows;
        $count = \Modules\PrimaNota\Movimento::$saveCount;
        try { return $callback(); }
        catch (\Throwable $e) {
            \Modules\PrimaNota\Movimento::$rows = $snapshot;
            \Modules\PrimaNota\Movimento::$saveCount = $count;
            throw $e;
        }
    }
}
class FakeCapsule { public function getConnection() { return new FakeConnection(); } }
class FakeDatabase { public function getCapsule() { return new FakeCapsule(); } }
$GLOBAL_DB = new FakeDatabase();
function database() { global $GLOBAL_DB; return $GLOBAL_DB; }

require_once __DIR__.'/../custom/src/Esercizio.php';
use Modules\Partitario\Esercizio;
use Modules\PrimaNota\Movimento;

class ExecuteTestService extends Esercizio {
    public array $entries;
    public string $fp;
    public function __construct() {
        parent::__construct('2026-01-01','2026-12-31','2026-12-31');
        $this->entries = [
            ['idconto'=>1,'descrizione'=>'Banca','totale'=>100.0,'saldo_origine'=>100.0,'contropartita'=>false],
            ['idconto'=>99,'descrizione'=>'Apertura','totale'=>-100.0,'saldo_origine'=>null,'contropartita'=>true],
        ];
        $this->fp = self::buildFingerprint('apertura','2026-01-01','2026-12-31','2026-01-01',$this->entries);
    }
    protected function lockExercise($database): void {}
    protected function clearCaches(): void {}
    protected function reserveNextMastrino($database): int { return 1001; }
    protected function assertOperationAbsentForUpdate($database, string $operation): void {
        if (!empty(Movimento::$rows)) throw new \DomainException('already exists after lock');
    }
    public function getPreview(string $operation, bool $locking = false): array {
        if (empty(Movimento::$rows)) {
            return [
                'can_execute'=>true, 'fingerprint'=>$this->fp, 'date'=>'2026-01-01', 'entries'=>$this->entries,
                'existing'=>['present'=>false], 'blocking_reasons'=>[],
            ];
        }
        $balance = array_sum(array_column(Movimento::$rows, 'totale'));
        return [
            'can_execute'=>false, 'fingerprint'=>$this->fp, 'date'=>'2026-01-01', 'entries'=>$this->entries,
            'existing'=>['present'=>true,'status'=>abs($balance)<0.000001?'consistent':'anomaly','mastrini'=>[1001]],
            'blocking_reasons'=>[],
        ];
    }
}

$tests=[];
function check($condition,$name){ global $tests; $tests[]=[$name,(bool)$condition]; echo ($condition?'PASS ':'FAIL ').$name."\n"; }

// Successo completo.
Movimento::$rows=[]; Movimento::$saveCount=0; Movimento::$throwOnSaveNumber=null;
$s = new ExecuteTestService();
$id = $s->execute('apertura',$s->fp,'2026-01-01','2026-12-31');
check($id===1001 && count(Movimento::$rows)===2 && abs(array_sum(array_column(Movimento::$rows,'totale')))<0.000001, 'Execute crea Mastrino in pareggio');

// Seconda esecuzione rifiutata dopo lock.
try { $s->execute('apertura',$s->fp,'2026-01-01','2026-12-31'); check(false,'Seconda esecuzione bloccata'); }
catch (\DomainException $e) { check(str_contains($e->getMessage(),'already exists'), 'Seconda esecuzione bloccata'); }
check(count(Movimento::$rows)===2, 'Seconda esecuzione zero righe duplicate');

// Fingerprint diverso: zero scritture.
Movimento::$rows=[]; Movimento::$saveCount=0;
try { $s->execute('apertura',str_repeat('0',64),'2026-01-01','2026-12-31'); check(false,'Fingerprint diverso rifiutato'); }
catch (\DomainException $e) { check(str_contains($e->getMessage(),'anteprima è cambiata'), 'Fingerprint diverso rifiutato'); }
check(count(Movimento::$rows)===0, 'Fingerprint diverso zero scritture');

// Periodo diverso: zero scritture.
try { $s->execute('apertura',$s->fp,'2025-01-01','2025-12-31'); check(false,'Periodo diverso rifiutato'); }
catch (\DomainException $e) { check(str_contains($e->getMessage(),'anteprima è cambiata'), 'Periodo diverso rifiutato'); }
check(count(Movimento::$rows)===0, 'Periodo diverso zero scritture');

// Errore durante INSERT: rollback totale.
Movimento::$rows=[]; Movimento::$saveCount=0; Movimento::$throwOnSaveNumber=2;
try { $s->execute('apertura',$s->fp,'2026-01-01','2026-12-31'); check(false,'Errore INSERT provoca eccezione'); }
catch (\RuntimeException $e) { check(true,'Errore INSERT provoca eccezione'); }
check(count(Movimento::$rows)===0, 'Rollback elimina scritture parziali');

$failed=count(array_filter($tests,fn($t)=>!$t[1]));
echo 'SUMMARY '.(count($tests)-$failed).'/'.count($tests)." PASS\n";
exit($failed?1:0);
}
