<?php

/*
 * OpenSTAManager: il software gestionale open source per l'assistenza tecnica e la fatturazione
 * Copyright (C) DevCode s.r.l.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace Modules\Backups;

use Tasks\Manager;
use Throwable;

/**
 * Task dedicato alla gestione del backup giornaliero automatico, se abilitato da Impostazioni.
 */
class BackupTask extends Manager
{
    public function needsExecution()
    {
        return setting('Backup automatico') && !\Backup::isDailyComplete();
    }

    public function execute()
    {
        if (!setting('Backup automatico')) {
            return [
                'response' => 2,
                'message' => tr('Backup automatico disattivato'),
            ];
        }

        if (\Backup::isDailyComplete()) {
            return [
                'response' => 2,
                'message' => tr('Backup già eseguito'),
            ];
        }

        try {
            $job = BackupManager::daily();
        } catch (Throwable $e) {
            return [
                'response' => 2,
                'message' => tr('Errore durante la creazione del backup: _ERROR_', [
                    '_ERROR_' => $e->getMessage(),
                ]),
            ];
        }

        if ($job['busy']) {
            return [
                'response' => 2,
                'message' => tr('Un’altra operazione di backup è già in corso'),
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
                'message' => tr('Backup locale completato, ma la distribuzione verso le destinazioni secondarie non è stata completata: _ERROR_', [
                    '_ERROR_' => $job['distribution_error'],
                ]),
            ];
        }

        $failed_destinations = array_filter($job['distribution'], fn ($item) => !$item['success']);
        if (!empty($failed_destinations)) {
            $names = array_map(fn ($item) => $item['adapter'] ?: tr('Destinazione sconosciuta'), $failed_destinations);

            return [
                'response' => 2,
                'message' => tr('Backup locale completato, ma alcune destinazioni secondarie non sono state aggiornate: _DESTINATIONS_', [
                    '_DESTINATIONS_' => implode(', ', $names),
                ]),
            ];
        }

        return [
            'response' => 1,
            'message' => tr('Backup generato correttamente!'),
        ];
    }
}
