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

use Throwable;

/**
 * Coordina la creazione del backup locale e la successiva distribuzione.
 */
class BackupManager
{
    public static function create(array $ignores = []): array
    {
        return self::run(static fn () => \Backup::create($ignores));
    }

    public static function daily(): array
    {
        return self::run(static fn () => \Backup::daily());
    }

    protected static function run(callable $creator): array
    {
        $lock = self::acquireLock();

        if ($lock === null) {
            return [
                'created' => false,
                'busy' => true,
                'backup' => null,
                'distribution' => [],
                'distribution_error' => null,
            ];
        }

        try {
            $before = \Backup::getList();
            $created = (bool) $creator();

            if (!$created) {
                return [
                    'created' => false,
                    'busy' => false,
                    'backup' => null,
                    'distribution' => [],
                    'distribution_error' => null,
                ];
            }

            $after = \Backup::getList();
            $created_backups = array_values(array_diff($after, $before));
            $backup = end($created_backups) ?: end($after) ?: null;
            $distribution = [];
            $distribution_error = null;

            if ($backup !== null) {
                try {
                    $distribution = BackupDistributor::distribute($backup);
                } catch (Throwable $e) {
                    $distribution_error = $e->getMessage();
                }
            } else {
                $distribution_error = tr('Backup locale creato, ma il relativo file non è stato individuato per la distribuzione.');
            }

            return [
                'created' => true,
                'busy' => false,
                'backup' => $backup,
                'distribution' => $distribution,
                'distribution_error' => $distribution_error,
            ];
        } finally {
            self::releaseLock($lock);
        }
    }

    /**
     * Impedisce l'esecuzione contemporanea di creazione e distribuzione backup.
     * Il file di lock rimane sul filesystem ed è escluso dai backup tramite *.lock.
     *
     * @return resource|null
     */
    protected static function acquireLock()
    {
        $lock_path = \Backup::getDirectory().'/.osm-backup-job.lock';
        $handle = fopen($lock_path, 'c');

        if ($handle === false) {
            throw new \RuntimeException(tr('Impossibile creare il lock per l’operazione di backup.'));
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return null;
        }

        return $handle;
    }

    /**
     * @param resource $handle
     */
    protected static function releaseLock($handle): void
    {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
