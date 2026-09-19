<?php

namespace Tests\Unit\Partitario;

use Modules\Partitario\Esercizio;
use PHPUnit\Framework\TestCase;

class EsercizioTest extends TestCase
{
    public function testPeriodoAnnualeSolare(): void
    {
        $esercizio = new Esercizio('2026-01-01', '2026-12-31');

        $this->assertTrue($esercizio->isAnnual());
    }

    public function testPeriodoAnnualeNonSolare(): void
    {
        $esercizio = new Esercizio('2025-07-01', '2026-06-30');

        $this->assertTrue($esercizio->isAnnual());
    }

    public function testPeriodoTrimestraleNonEUnEsercizio(): void
    {
        $esercizio = new Esercizio('2026-01-01', '2026-03-31');

        $this->assertFalse($esercizio->isAnnual());
    }

    public function testCalcoloAperturaMantieneSegniEQuadra(): void
    {
        $result = Esercizio::calculateEntries('apertura', [
            ['id' => 1, 'descrizione' => 'Cassa', 'totale' => 100.0],
            ['id' => 2, 'descrizione' => 'Fornitore', 'totale' => -40.0],
        ], 99, 'Apertura conti patrimoniali');

        $this->assertSame(3, count($result['entries']));
        $this->assertSame(100.0, $result['debit']);
        $this->assertSame(100.0, $result['credit']);
        $this->assertSame(-60.0, $result['entries'][2]['totale']);
    }

    public function testCalcoloChiusuraInverteSegniEQuadra(): void
    {
        $result = Esercizio::calculateEntries('chiusura', [
            ['id' => 1, 'descrizione' => 'Cassa', 'totale' => 100.0],
            ['id' => 2, 'descrizione' => 'Fornitore', 'totale' => -40.0],
        ], 98, 'Chiusura conti patrimoniali');

        $this->assertSame(-100.0, $result['entries'][0]['totale']);
        $this->assertSame(40.0, $result['entries'][1]['totale']);
        $this->assertSame(60.0, $result['entries'][2]['totale']);
        $this->assertSame(100.0, $result['debit']);
        $this->assertSame(100.0, $result['credit']);
    }

    public function testNessunSaldoNonCreaContropartitaZero(): void
    {
        $result = Esercizio::calculateEntries('apertura', [], 99, 'Apertura conti patrimoniali');

        $this->assertSame([], $result['entries']);
        $this->assertSame(0.0, $result['debit']);
        $this->assertSame(0.0, $result['credit']);
    }

    public function testCentottantaquattroContiCreanoCentottantacinqueMovimentiInPareggio(): void
    {
        $rows = [];
        for ($i = 1; $i <= 184; ++$i) {
            $rows[] = [
                'id' => $i,
                'descrizione' => 'Conto '.$i,
                'totale' => $i % 2 === 0 ? 100.0 : -100.0,
            ];
        }

        $result = Esercizio::calculateEntries('apertura', $rows, 999, 'Apertura conti patrimoniali');

        $this->assertCount(185, $result['entries']);
        $this->assertEqualsWithDelta($result['debit'], $result['credit'], 0.000001);
    }
}
