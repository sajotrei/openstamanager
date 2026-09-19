<?php

namespace Tests\Unit\Partitario;

use Modules\Partitario\Esercizio;
use PHPUnit\Framework\TestCase;

class EsercizioTest extends TestCase
{
    public function testPeriodoAnnualeSolare(): void
    {
        $this->assertTrue((new Esercizio('2026-01-01', '2026-12-31'))->isAnnual());
    }

    public function testPeriodoAnnualeNonSolare(): void
    {
        $this->assertTrue((new Esercizio('2025-07-01', '2026-06-30'))->isAnnual());
    }

    public function testPeriodoTrimestraleNonEUnEsercizio(): void
    {
        $this->assertFalse((new Esercizio('2026-01-01', '2026-03-31'))->isAnnual());
    }

    public function testAperturaCon184ContiEContropartita(): void
    {
        $rows = [];
        for ($id = 1; $id <= 184; ++$id) {
            $rows[] = [
                'id' => $id,
                'descrizione' => 'Conto '.$id,
                'totale' => $id % 2 === 0 ? -10.0 : 10.0,
            ];
        }

        $result = Esercizio::calculateEntries('apertura', $rows, 999, 'Conto apertura');

        $this->assertCount(185, $result['entries']);
        $this->assertSame($result['debit'], $result['credit']);
        $this->assertTrue($result['entries'][184]['contropartita']);
    }

    public function testChiusuraCon208ContiEContropartita(): void
    {
        $rows = [];
        for ($id = 1; $id <= 208; ++$id) {
            $rows[] = [
                'id' => $id,
                'descrizione' => 'Conto '.$id,
                'totale' => $id % 2 === 0 ? -25.0 : 25.0,
            ];
        }

        $result = Esercizio::calculateEntries('chiusura', $rows, 998, 'Conto chiusura');

        $this->assertCount(209, $result['entries']);
        $this->assertSame($result['debit'], $result['credit']);
        $this->assertTrue($result['entries'][208]['contropartita']);
    }

    public function testAperturaMantieneIValoriDeiSaldi(): void
    {
        $result = Esercizio::calculateEntries('apertura', [
            ['id' => 1, 'descrizione' => 'Cassa', 'totale' => 100.0],
            ['id' => 2, 'descrizione' => 'Debito fornitore', 'totale' => -40.0],
        ], 99, 'Apertura conti patrimoniali');

        $this->assertSame(100.0, $result['entries'][0]['totale']);
        $this->assertSame(-40.0, $result['entries'][1]['totale']);
        $this->assertSame(-60.0, $result['entries'][2]['totale']);
        $this->assertSame(100.0, $result['debit']);
        $this->assertSame(100.0, $result['credit']);
    }

    public function testChiusuraInverteIValoriDeiSaldi(): void
    {
        $result = Esercizio::calculateEntries('chiusura', [
            ['id' => 1, 'descrizione' => 'Banca', 'totale' => 100.0],
            ['id' => 2, 'descrizione' => 'Credito cliente', 'totale' => -40.0],
        ], 98, 'Chiusura conti patrimoniali');

        $this->assertSame(-100.0, $result['entries'][0]['totale']);
        $this->assertSame(40.0, $result['entries'][1]['totale']);
        $this->assertSame(60.0, $result['entries'][2]['totale']);
        $this->assertSame(100.0, $result['debit']);
        $this->assertSame(100.0, $result['credit']);
    }

    public function testNessunSaldoNonCreaContropartitaZero(): void
    {
        $result = Esercizio::calculateEntries('apertura', [], 99, 'Conto apertura');

        $this->assertSame([], $result['entries']);
        $this->assertSame(0.0, $result['debit']);
        $this->assertSame(0.0, $result['credit']);
    }

    public function testServizioNonGestisceDirettamenteLaPdo(): void
    {
        $source = file_get_contents(__DIR__.'/../../../modules/partitario/src/Esercizio.php');

        $this->assertStringNotContainsString('getPDO()', $source);
        $this->assertStringNotContainsString('->commit()', $source);
        $this->assertStringNotContainsString('->rollBack()', $source);
        $this->assertStringContainsString('getCapsule()->getConnection()', $source);
        $this->assertStringContainsString('->transaction(', $source);
    }

    public function testNessunaRigenerazioneDistruttiva(): void
    {
        $source = file_get_contents(__DIR__.'/../../../modules/partitario/src/Esercizio.php');

        $this->assertDoesNotMatchRegularExpression('/DELETE\s+FROM\s+co_movimenti/i', $source);
    }
}
