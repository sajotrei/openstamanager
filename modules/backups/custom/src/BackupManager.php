<?php

namespace Modules\Backups;

use Throwable;

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
            return self::result(false, true);
        }

        try {
            $before = \Backup::getList();
            $created = (bool) $creator();

            if (!$created) {
                return self::result(false, false);
            }

            $after = \Backup::getList();
            $created_backups = array_values(array_diff($after, $before));
            $backup = end($created_backups) ?: null;

            if ($backup === null) {
                return self::result(
                    true,
                    false,
                    null,
                    [],
                    tr('Backup locale creato, ma il relativo file non è stato individuato per la distribuzione.')
                );
            }

            try {
                $distribution = BackupDistributor::distribute($backup);

                return self::result(true, false, $backup, $distribution);
            } catch (Throwable $e) {
                return self::result(true, false, $backup, [], $e->getMessage());
            }
        } finally {
            self::releaseLock($lock);
        }
    }

    protected static function result(
        bool $created,
        bool $busy,
        ?string $backup = null,
        array $distribution = [],
        ?string $distribution_error = null
    ): array {
        return [
            'created' => $created,
            'busy' => $busy,
            'backup' => $backup,
            'distribution' => $distribution,
            'distribution_error' => $distribution_error,
        ];
    }

    /** @return resource|null */
    protected static function acquireLock(?string $lock_path = null)
    {
        $lock_path ??= \Backup::getDirectory().'/.osm-backup-job.lock';
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

    /** @param resource $handle */
    protected static function releaseLock($handle): void
    {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
