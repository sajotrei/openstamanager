<?php

namespace Tests\Unit\Partitario;

use Modules\Partitario\Workflow;
use PHPUnit\Framework\TestCase;

class WorkflowTest extends TestCase
{
    private function preview(bool $present = false, string $status = 'none', bool $canExecute = true, array $errors = [], string $availability = 'available', bool $configuration = true, bool $entries = true, bool $annual = true): array
    {
        return [
            'existing' => ['present' => $present, 'status' => $status],
            'can_execute' => $canExecute,
            'errors' => $errors,
            'availability' => ['key' => $availability],
            'configuration' => ['valid' => $configuration],
            'entries' => $entries ? [1] : [],
            'annual' => $annual,
        ];
    }

    public function testStatiOperativi(): void
    {
        $ready = Workflow::state('apertura', $this->preview(), false, false, true);
        $this->assertSame('ready', $ready['key']);
        $this->assertTrue($ready['action']);

        $this->assertSame('closed_without_opening', Workflow::state('apertura', $this->preview(), false, true, true)['key']);
        $this->assertSame('waiting_opening', Workflow::state('chiusura', $this->preview(), false, false, true)['key']);
        $this->assertSame('waiting_end', Workflow::state('chiusura', $this->preview(false, 'none', false, [], 'before_end'), true, false, true)['key']);
    }

    public function testStatiScrittureEsistenti(): void
    {
        $this->assertSame('done', Workflow::state('apertura', $this->preview(true, 'consistent', false), true, false, true)['key']);
        $this->assertSame('review', Workflow::state('apertura', $this->preview(true, 'stale', false), true, false, true)['key']);
        $this->assertSame('anomaly', Workflow::state('apertura', $this->preview(true, 'anomaly', false), true, false, true)['key']);
    }

    public function testRiepilogoContinuita(): void
    {
        $summary = Workflow::summary($this->preview(false), $this->preview(true, 'consistent', false));
        $this->assertSame('closed_continuity_review', $summary['key']);
    }
}
