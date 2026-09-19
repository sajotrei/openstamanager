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
}
