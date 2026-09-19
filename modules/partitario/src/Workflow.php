<?php

namespace Modules\Partitario;

class Workflow
{
    public static function state(string $operation, array $preview, bool $openingPresent, bool $closingPresent, bool $hasPreviousBalances): array
    {
        if ($preview['existing']['present']) {
            return ['key' => 'done', 'label' => tr('Eseguita'), 'class' => 'badge-success', 'action' => false];
        }
        if ($operation === 'apertura' && $closingPresent) {
            return ['key' => 'closed_without_opening', 'label' => tr('Non rilevata - esercizio già chiuso'), 'class' => 'badge-warning', 'action' => false];
        }
        if ($operation === 'chiusura' && !$openingPresent && $hasPreviousBalances) {
            return ['key' => 'waiting_opening', 'label' => tr('In attesa dell\'apertura'), 'class' => 'badge-secondary', 'action' => false];
        }
        if (!empty($preview['errors'])) {
            return ['key' => 'blocked', 'label' => tr('Bloccata'), 'class' => 'badge-danger', 'action' => false];
        }
        if (!empty($preview['can_execute'])) {
            return ['key' => 'ready', 'label' => tr('Da eseguire'), 'class' => 'badge-info', 'action' => true];
        }
        return ['key' => 'unavailable', 'label' => tr('Non disponibile'), 'class' => 'badge-secondary', 'action' => false];
    }
}
