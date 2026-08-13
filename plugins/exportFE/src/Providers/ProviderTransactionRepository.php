<?php

namespace Plugins\ExportFE\Providers;

class ProviderTransactionRepository
{
    public const STATUS_SENDING = 'SENDING';
    public const STATUS_SENT = 'SENT';
    public const STATUS_UNCERTAIN = 'UNCERTAIN';
    public const STATUS_WAITING = 'WAITING';
    public const STATUS_FINAL = 'FINAL';
    public const STATUS_FAILED = 'FAILED';

    private const TABLE = 'fe_provider_transactions';

    public function tableAvailable(): bool
    {
        try {
            return (bool) database()->tableExists(self::TABLE);
        } catch (\Exception) {
            return false;
        }
    }

    public function findReusable(int $id_documento, string $provider, string $xml_hash): ?array
    {
        if (!$this->tableAvailable()) {
            return null;
        }

        $statuses = [
            self::STATUS_SENDING,
            self::STATUS_SENT,
            self::STATUS_UNCERTAIN,
            self::STATUS_WAITING,
        ];

        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $row = database()->fetchOne(
            'SELECT * FROM `'.self::TABLE.'` WHERE `id_documento` = ? AND `provider` = ? AND `xml_hash` = ? AND `status` IN ('.$placeholders.') ORDER BY `id` DESC',
            array_merge([$id_documento, $provider, $xml_hash], $statuses)
        );

        return $row ?: null;
    }

    public function start(int $id_documento, string $provider, string $filename, string $xml_hash): void
    {
        if (!$this->tableAvailable()) {
            return;
        }

        database()->query(
            'INSERT INTO `'.self::TABLE.'` (`id_documento`, `provider`, `filename`, `xml_hash`, `status`, `attempt`, `created_at`, `updated_at`, `last_request_at`)
             VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                `attempt` = `attempt` + 1,
                `status` = IF(`status` IN ("SENDING", "SENT", "UNCERTAIN", "WAITING"), `status`, VALUES(`status`)),
                `updated_at` = NOW(),
                `last_request_at` = NOW()',
            [$id_documento, $provider, $filename, $xml_hash, self::STATUS_SENDING]
        );
    }

    public function acquireLock(int $id_documento, string $provider, string $xml_hash): bool
    {
        if (!$this->tableAvailable()) {
            return false;
        }

        $lock_name = $this->lockName($id_documento, $provider, $xml_hash);
        $result = database()->fetchOne('SELECT GET_LOCK(?, 0) AS `locked`', [$lock_name]);

        return (int) ($result['locked'] ?? 0) === 1;
    }

    public function releaseLock(int $id_documento, string $provider, string $xml_hash): void
    {
        if (!$this->tableAvailable()) {
            return;
        }

        database()->fetchOne('SELECT RELEASE_LOCK(?) AS `released`', [
            $this->lockName($id_documento, $provider, $xml_hash),
        ]);
    }

    public function markSent(int $id_documento, string $provider, string $xml_hash, ?string $remote_id = null, ?string $remote_status = null): void
    {
        $this->update($id_documento, $provider, $xml_hash, [
            'status' => self::STATUS_WAITING,
            'remote_id' => $remote_id,
            'remote_status' => $remote_status,
            'last_response_at' => date('Y-m-d H:i:s'),
            'next_poll_at' => date('Y-m-d H:i:s', time() + ProviderSettings::pollingMinutes() * 60),
        ]);
    }

    public function markUncertain(int $id_documento, string $provider, string $xml_hash, string $message): void
    {
        $this->update($id_documento, $provider, $xml_hash, [
            'status' => self::STATUS_UNCERTAIN,
            'last_error' => $message,
            'last_response_at' => date('Y-m-d H:i:s'),
            'next_poll_at' => date('Y-m-d H:i:s', time() + ProviderSettings::pollingMinutes() * 60),
        ]);
    }

    public function markFailed(int $id_documento, string $provider, string $xml_hash, string $message): void
    {
        $this->update($id_documento, $provider, $xml_hash, [
            'status' => self::STATUS_FAILED,
            'last_error' => $message,
            'last_response_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function update(int $id_documento, string $provider, string $xml_hash, array $values): void
    {
        if (!$this->tableAvailable()) {
            return;
        }

        $values['updated_at'] = date('Y-m-d H:i:s');

        database()->table(self::TABLE)
            ->where('id_documento', $id_documento)
            ->where('provider', $provider)
            ->where('xml_hash', $xml_hash)
            ->update($values);
    }

    private function lockName(int $id_documento, string $provider, string $xml_hash): string
    {
        return 'fe_'.sha1($id_documento.'|'.$provider.'|'.$xml_hash);
    }
}
