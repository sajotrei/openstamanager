<?php

namespace Tests\Unit\Partitario;

use Modules\Partitario\Esercizio;
use PHPUnit\Framework\TestCase;

class EsercizioTest extends TestCase
{
    public function testPeriodiAnnualiETrimestrali(): void
    {
        $this->assertTrue((new Esercizio('2026-01-01', '2026-12-31', '2026-01-01'))->isAnnual());
        $this->assertTrue((new Esercizio('2025-07-01', '2026-06-30', '2025-07-01'))->isAnnual());
        $this->assertFalse((new Esercizio('2026-01-01', '2026-03-31', '2026-01-01'))->isAnnual());
    }

    public function testRegoleTemporali(): void
    {
        $this->assertSame('before_start', Esercizio::temporalAvailability('apertura', '2026-01-01', '2026-12-31', '2025-12-31')['key']);
        $this->assertTrue(Esercizio::temporalAvailability('apertura', '2026-01-01', '2026-12-31', '2026-01-01')['allowed']);
        $this->assertSame('before_end', Esercizio::temporalAvailability('chiusura', '2026-01-01', '2026-12-31', '2026-12-30')['key']);
        $this->assertTrue(Esercizio::temporalAvailability('chiusura', '2026-01-01', '2026-12-31', '2026-12-31')['allowed']);
    }

    public function testAperturaCon184ContiEChiusuraCon208Conti(): void
    {
        $openingRows = [];
        for ($i = 1; $i <= 184; ++$i) {
            $openingRows[] = ['id' => $i, 'descrizione' => 'Conto '.$i, 'totale' => $i % 2 ? 100.123456 : -50.123456];
        }
        $opening = Esercizio::calculateEntries('apertura', $openingRows, 999, 'Apertura');
        $this->assertCount(185, $opening['entries']);
        $this->assertEqualsWithDelta($opening['debit'], $opening['credit'], 0.000001);

        $closingRows = [];
        for ($i = 1; $i <= 208; ++$i) {
            $closingRows[] = ['id' => $i, 'descrizione' => 'Conto '.$i, 'totale' => $i % 3 ? 10.333333 : -20.666666];
        }
        $closing = Esercizio::calculateEntries('chiusura', $closingRows, 998, 'Chiusura');
        $this->assertCount(209, $closing['entries']);
        $this->assertEqualsWithDelta($closing['debit'], $closing['credit'], 0.000001);
    }

    public function testNessunaContropartitaSenzaSaldi(): void
    {
        $result = Esercizio::calculateEntries('apertura', [], 999, 'Apertura');
        $this->assertSame([], $result['entries']);
    }

    public function testClassificazioneCoerenza(): void
    {
        $entries = [
            ['idconto' => 1, 'totale' => 100.0],
            ['idconto' => 2, 'totale' => -40.0],
            ['idconto' => 99, 'totale' => -60.0],
        ];
        $existing = ['present' => true, 'rows' => 3, 'anomalies' => [], 'account_totals' => [1 => 100.0, 2 => -40.0, 99 => -60.0]];
        $this->assertSame('consistent', Esercizio::classifyExisting($existing, $entries)['status']);

        $existing['account_totals'][1] = 101.0;
        $this->assertSame('stale', Esercizio::classifyExisting($existing, $entries)['status']);

        $existing['anomalies'] = ['Mastrino non in pareggio'];
        $this->assertSame('anomaly', Esercizio::classifyExisting($existing, $entries)['status']);
    }

    public function testValidazioneContiTecnici(): void
    {
        $valid = Esercizio::validateTechnicalAccountsData(
            ['apertura' => 1, 'chiusura' => 2],
            ['apertura' => ['gruppo' => 'Patrimoniale'], 'chiusura' => ['gruppo' => 'Patrimoniale']]
        );
        $this->assertTrue($valid['valid']);

        $same = Esercizio::validateTechnicalAccountsData(
            ['apertura' => 1, 'chiusura' => 1],
            ['apertura' => ['gruppo' => 'Patrimoniale'], 'chiusura' => ['gruppo' => 'Patrimoniale']]
        );
        $this->assertFalse($same['valid']);
    }

    public function testSorgenteNonUsaPdoODistruzioneAutomatica(): void
    {
        $source = file_get_contents(__DIR__.'/../../src/Esercizio.php');
        $this->assertStringNotContainsString('getPDO()', $source);
        $this->assertDoesNotMatchRegularExpression('/DELETE\s+FROM\s+co_movimenti/i', $source);
        $this->assertStringContainsString('getCapsule()->getConnection()', $source);
    }
}
