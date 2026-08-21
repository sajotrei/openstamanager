<?php

namespace Modules\Backups;

use Tasks\Manager;
use Throwable;

class BackupTask extends Manager
{
    public function execute()
    {
        if (!setting('Backup automatico')) {
            return [
                'response' => 2,
                'message' => tr('Backup automatico disattivato'),
            ];
        }

        if (\Backup::isDailyComplete()) {
            try {
                $results = BackupRetryService::retryLatest();
            } catch (Throwable) {
                return [
                    'response' => 2,
                    'message' => tr('Backup locale già presente, ma il retry delle destinazioni secondarie non è riuscito.'),
                ];
            }

            if (empty($results) && BackupRetryService::hasPendingRetryForLatest()) {
                return [
                    'response' => 2,
                    'message' => tr('Backup locale già presente; le destinazioni fallite sono in attesa del prossimo retry pianificato.'),
                ];
            }

            return self::distributionResult($results, tr('Backup già eseguito e destinazioni secondarie allineate.'));
        }

        try {
            $job = BackupManager::daily();
        } catch (Throwable) {
            return [
                'response' => 2,
                'message' => tr('Errore durante la creazione del backup.'),
            ];
        }

        if ($job['busy']) {
            return [
                'response' => 2,
                'message' => tr('Un’altra operazione di backup è già in corso'),
            ];
        }

        if (!empty($job['collision'])) {
            return [
                'response' => 2,
                'message' => tr('Creazione annullata per evitare la sovrascrittura di un backup con lo stesso nome.'),
            ];
        }

        if (!$job['created']) {
            return [
                'response' => 2,
                'message' => tr('Backup già eseguito'),
            ];
        }

        if (!empty($job['distribution_error'])) {
            return [
                'response' => 2,
                'message' => tr('Backup locale completato, ma la distribuzione verso le destinazioni secondarie non è stata completata.'),
            ];
        }

        return self::distributionResult($job['distribution'], tr('Backup generato correttamente!'));
    }

    protected static function distributionResult(array $results, string $success_message): array
    {
        $failed = array_filter($results, static fn ($item) => empty($item['success']));

        if (!empty($failed)) {
            $names = array_map(
                static fn ($item) => $item['adapter'] ?: tr('Destinazione sconosciuta'),
                $failed
            );

            return [
                'response' => 2,
                'message' => tr('Backup locale disponibile, ma alcune destinazioni secondarie non sono state aggiornate: _DESTINATIONS_', [
                    '_DESTINATIONS_' => implode(', ', $names),
                ]),
            ];
        }

        return [
            'response' => 1,
            'message' => $success_message,
        ];
    }
}
