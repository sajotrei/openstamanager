<?php

namespace Modules\Backups;

use Throwable;

class BackupRetryService
{
    public static function distribute(string $backup_path, bool $only_pending = false): array
    {
        if (!is_file($backup_path) || !is_readable($backup_path)) {
            throw new \InvalidArgumentException(tr('Il file di backup da distribuire non è leggibile.'));
        }

        $query = BackupDestination::with('adapter')
            ->where('enabled', 1)
            ->orderBy('id');

        if ($only_pending) {
            self::applyPendingFilter($query, basename($backup_path), true);
        }

        $results = [];
        foreach ($query->get() as $destination) {
            $results[] = self::distributeTo($backup_path, $destination);
        }

        return $results;
    }

    public static function distributeTo(string $backup_path, BackupDestination $destination): array
    {
        $result = BackupDistributor::distributeTo($backup_path, $destination);

        if (!empty($result['success'])) {
            self::recordSuccess($destination);
        } else {
            self::recordFailure($destination);
        }

        return $result;
    }

    public static function test(BackupDestination $destination): array
    {
        $result = BackupDistributor::test($destination);
        self::recordTest($destination, !empty($result['success']), (string) ($result['message'] ?? ''));

        return $result;
    }

    public static function retryLatest(): array
    {
        $backup = self::latestLocalBackup();
        if ($backup === null) {
            return [];
        }

        return self::distribute($backup, true);
    }

    public static function retryDestination(BackupDestination $destination): array
    {
        $backup = self::latestLocalBackup();
        if ($backup === null) {
            return [
                'success' => false,
                'message' => tr('Non è disponibile un backup locale da replicare.'),
            ];
        }

        return self::distributeTo($backup, $destination);
    }

    public static function pendingRetryCount(bool $due_only = false): int
    {
        $backup = self::latestLocalBackup();
        if ($backup === null) {
            return 0;
        }

        $query = BackupDestination::where('enabled', 1);
        self::applyPendingFilter($query, basename($backup), $due_only);

        return (int) $query->count();
    }

    public static function hasPendingRetryForLatest(): bool
    {
        return self::pendingRetryCount(false) > 0;
    }

    public static function retryDelaySeconds(int $attempt): int
    {
        return match (true) {
            $attempt <= 1 => 300,
            $attempt === 2 => 900,
            $attempt === 3 => 1800,
            $attempt === 4 => 3600,
            default => 21600,
        };
    }

    protected static function applyPendingFilter($query, string $filename, bool $due_only): void
    {
        $query->where(function ($query) use ($filename) {
            $query->whereNull('last_success_file')
                ->orWhere('last_success_file', '!=', $filename);
        });

        if ($due_only) {
            $query->where(function ($query) {
                $query->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', date('Y-m-d H:i:s'));
            });
        }
    }

    protected static function latestLocalBackup(): ?string
    {
        $backups = \Backup::getList();
        if (empty($backups)) {
            return null;
        }

        $backup = end($backups);

        return is_string($backup) ? $backup : null;
    }

    protected static function recordSuccess(BackupDestination $destination): void
    {
        if (!$destination->exists) {
            return;
        }

        try {
            $destination->retry_count = 0;
            $destination->next_retry_at = null;
            $destination->save();
        } catch (Throwable) {
            // Telemetria best-effort: il backup remoto è già stato verificato.
        }
    }

    protected static function recordFailure(BackupDestination $destination): void
    {
        if (!$destination->exists) {
            return;
        }

        try {
            $attempt = max(0, (int) $destination->retry_count) + 1;
            $destination->retry_count = $attempt;
            $destination->next_retry_at = date('Y-m-d H:i:s', time() + self::retryDelaySeconds($attempt));
            $destination->save();
        } catch (Throwable) {
            // Telemetria best-effort: non mascherare l'esito reale della replica.
        }
    }

    protected static function recordTest(BackupDestination $destination, bool $success, string $message): void
    {
        if (!$destination->exists) {
            return;
        }

        try {
            $destination->last_test_at = date('Y-m-d H:i:s');
            $destination->last_test_success = $success;
            $destination->last_test_error = $success ? null : mb_substr($message, 0, 2000);
            $destination->save();
        } catch (Throwable) {
            // Stato test best-effort: il risultato restituito all'utente resta autorevole.
        }
    }
}
