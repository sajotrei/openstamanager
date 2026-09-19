<?php

namespace Tests\Unit\Partitario;

use Modules\Partitario\Workflow;
use PHPUnit\Framework\TestCase;

class WorkflowTest extends TestCase
{
    public function testAperturaDisponibileQuandoMancanoEntrambeLeOperazioni(): void
    {
        $state = Workflow::state('apertura', $this->preview(false, true), false, false, true);

        $this->assertSame('ready', $state['key']);
        $this->assertTrue($state['action']);
    }

    public function testAperturaNonPropostaDopoLaChiusura(): void
    {
        $state = Workflow::state('apertura', $this->preview(false, true), false, true, true);

        $this->assertSame('closed_without_opening', $state['key']);
        $this->assertFalse($state['action']);
    }

    public function testChiusuraAttendeAperturaQuandoEsistonoSaldiPrecedenti(): void
    {
        $state = Workflow::state('chiusura', $this->preview(false, true), false, false, true);

        $this->assertSame('waiting_opening', $state['key']);
        $this->assertFalse($state['action']);
    }

    public function testChiusuraDisponibileDopoApertura(): void
    {
        $state = Workflow::state('chiusura', $this->preview(false, true), true, false, true);

        $this->assertSame('ready', $state['key']);
        $this->assertTrue($state['action']);
    }

    public function testPrimoEsercizioPuoEssereChiusoSenzaApertura(): void
    {
        $state = Workflow::state('chiusura', $this->preview(false, true), false, false, false);

        $this->assertSame('ready', $state['key']);
        $this->assertTrue($state['action']);
    }

    public function testOperazioneEseguitaNonEReplicabile(): void
    {
        $state = Workflow::state('apertura', $this->preview(true, false), true, false, true);

        $this->assertSame('done', $state['key']);
        $this->assertFalse($state['action']);
    }

    private function preview(bool $present, bool $canExecute, array $errors = []): array
    {
        return [
            'existing' => ['present' => $present],
            'can_execute' => $canExecute,
            'errors' => $errors,
        ];
    }
}
