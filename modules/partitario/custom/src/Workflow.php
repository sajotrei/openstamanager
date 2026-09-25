<?php

namespace Modules\Partitario;

class Workflow
{
    public static function state(
        string $operation,
        array $preview,
        bool $openingPresent,
        bool $closingPresent,
        bool $hasPreviousBalances,
    ): array {
        if (empty($preview['configuration']['valid'])) {
            return [
                'key' => 'configuration_error',
                'label' => tr('Configurazione contabile da correggere'),
                'class' => 'badge-danger',
                'action' => false,
            ];
        }

        if (!empty($preview['existing']['present'])) {
            if (($preview['existing']['temporal_status'] ?? 'consistent') === 'future') {
                return ['key' => 'review', 'label' => tr('Da verificare'), 'class' => 'badge-warning', 'action' => false];
            }

            $status = $preview['existing']['status'] ?? 'anomaly';
            if ($status === 'consistent') {
                return ['key' => 'done', 'label' => tr('Eseguita'), 'class' => 'badge-success', 'action' => false];
            }
            if ($status === 'stale') {
                return ['key' => 'review', 'label' => tr('Da verificare'), 'class' => 'badge-warning', 'action' => false];
            }

            return ['key' => 'anomaly', 'label' => tr('Anomalia contabile'), 'class' => 'badge-danger', 'action' => false];
        }

        // Nel primo esercizio senza saldi precedenti l'Apertura non è necessaria,
        // anche se la Chiusura è già stata legittimamente registrata.
        if ($operation === 'apertura' && !$hasPreviousBalances) {
            return [
                'key' => 'not_required',
                'label' => tr('Non necessaria'),
                'class' => 'badge-secondary',
                'action' => false,
            ];
        }

        if ($operation === 'apertura' && $closingPresent && $hasPreviousBalances) {
            return [
                'key' => 'closed_without_opening',
                'label' => tr('Non rilevata - esercizio già chiuso'),
                'class' => 'badge-warning',
                'action' => false,
            ];
        }

        if ($operation === 'apertura' && ($preview['availability']['key'] ?? null) === 'before_start') {
            return [
                'key' => 'not_yet',
                'label' => tr('Non ancora disponibile'),
                'class' => 'badge-secondary',
                'action' => false,
            ];
        }

        if ($operation === 'chiusura' && !$openingPresent && $hasPreviousBalances) {
            return [
                'key' => 'waiting_opening',
                'label' => tr('In attesa dell\'Apertura'),
                'class' => 'badge-secondary',
                'action' => false,
            ];
        }

        if ($operation === 'chiusura' && ($preview['availability']['key'] ?? null) === 'before_end') {
            return [
                'key' => 'waiting_end',
                'label' => tr('In attesa della fine dell\'esercizio'),
                'class' => 'badge-secondary',
                'action' => false,
            ];
        }

        if (!empty($preview['errors'])) {
            return ['key' => 'blocked', 'label' => tr('Bloccata'), 'class' => 'badge-danger', 'action' => false];
        }

        if (empty($preview['entries'])) {
            return [
                'key' => 'nothing_to_do',
                'label' => tr('Nessuna operazione necessaria'),
                'class' => 'badge-secondary',
                'action' => false,
            ];
        }

        if (!empty($preview['can_execute'])) {
            return ['key' => 'ready', 'label' => tr('Da eseguire'), 'class' => 'badge-info', 'action' => true];
        }

        return ['key' => 'unavailable', 'label' => tr('Non disponibile'), 'class' => 'badge-secondary', 'action' => false];
    }

    public static function summary(array $opening, array $closing): array
    {
        if (empty($opening['annual']) || empty($closing['annual'])) {
            return ['key' => 'non_annual', 'label' => tr('Periodo non annuale'), 'class' => 'badge-secondary'];
        }

        if (empty($opening['configuration']['valid']) || empty($closing['configuration']['valid'])) {
            return ['key' => 'configuration_error', 'label' => tr('Configurazione incompleta'), 'class' => 'badge-danger'];
        }

        $openingStatus = $opening['existing']['status'] ?? 'none';
        $closingStatus = $closing['existing']['status'] ?? 'none';
        $openingTemporal = $opening['existing']['temporal_status'] ?? 'consistent';
        $closingTemporal = $closing['existing']['temporal_status'] ?? 'consistent';
        if (
            $openingTemporal === 'future'
            || $closingTemporal === 'future'
            || in_array($openingStatus, ['stale', 'anomaly'], true)
            || in_array($closingStatus, ['stale', 'anomaly'], true)
        ) {
            return ['key' => 'review', 'label' => tr('Da verificare'), 'class' => 'badge-warning'];
        }

        $openingPresent = !empty($opening['existing']['present']);
        $closingPresent = !empty($closing['existing']['present']);
        $hasPreviousBalances = !empty($opening['has_previous_balances']);

        if ($closingPresent && !$openingPresent && $hasPreviousBalances) {
            return [
                'key' => 'closed_continuity_review',
                'label' => tr('Chiuso - continuità da verificare'),
                'class' => 'badge-warning',
            ];
        }

        if ($closingPresent) {
            return ['key' => 'closed', 'label' => tr('Chiuso'), 'class' => 'badge-success'];
        }

        if ($openingPresent) {
            if (($closing['availability']['key'] ?? null) === 'before_end') {
                return [
                    'key' => 'waiting_end',
                    'label' => tr('In attesa della fine dell\'esercizio'),
                    'class' => 'badge-secondary',
                ];
            }

            return ['key' => 'in_progress', 'label' => tr('In corso'), 'class' => 'badge-info'];
        }

        if (!$hasPreviousBalances && ($closing['availability']['key'] ?? null) === 'before_end') {
            return [
                'key' => 'waiting_end',
                'label' => tr('In attesa della fine dell\'esercizio'),
                'class' => 'badge-secondary',
            ];
        }

        return ['key' => 'to_manage', 'label' => tr('Da gestire'), 'class' => 'badge-warning'];
    }
}
