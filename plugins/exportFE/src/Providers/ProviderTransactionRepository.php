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

    public function findByHash(int $id_documento, string $provider, string $xml_hash): ?array
    {
        if (!$this->tableAvailable()) {
            return null;
        }

        $row = database()->fetchOne(
            'SELECT * FROM `'.self::TABLE.'` WHERE `id_documento` = ? AND `provider` = ? AND `xml_hash` = ? ORDER BY `id` DESC LIMIT 1',
            [$id_documento, $provider, $xml_hash]
        );

        return $row ?: null;
    }

    public function findReusable(int $id_documento, string $provider, string $xml_hash): ?array
    {
        $row = $this->findByHash($id_documento, $provider, $xml_hash);
        if (!$row) {
            return null;
        }

        return in_array($row['status'], [
            self::STATUS_SENDING,
            self::STATUS_SENT,
            self::STATUS_UNCERTAIN,
            self::STATUS_WAITING,
            self::STATUS_FINAL,
        ], true) ? $row : null;
    }

    /**
     * Una richiesta rimasta SENDING oltre la finestra locale non puo' essere
     * considerata fallita: il processo potrebbe essersi interrotto dopo aver
     * consegnato la richiesta al provider. La trasformiamo quindi in UNCERTAIN.
     */
    public function recoverStaleSending(string $provider, int $older_than_minutes = 15): int
    {
        if (!$this->tableAvailable()) {
            return 0;
        }

        $older_than_minutes = max(5, min(1440, $older_than_minutes));
        $threshold = date('Y-m-d H:i:s', time() - ($older_than_minutes * 60));

        return (int) database()->table(self::TABLE)
            ->where('provider', $provider)
            ->where('status', self::STATUS_SENDING)
            ->where('updated_at', '<', $threshold)
            ->update([
                'status' => self::STATUS_UNCERTAIN,
                'last_error' => 'local_sending_interrupted',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    public function latestForDocument(int $id_documento, string $provider): ?array
    {
        if (!$this->tableAvailable()) {
            return null;
        }

        $row = database()->fetchOne(
            'SELECT * FROM `'.self::TABLE.'` WHERE `id_documento` = ? AND `provider` = ? ORDER BY `id` DESC LIMIT 1',
            [$id_documento, $provider]
        );

        return $row ?: null;
    }

    /**
     * Restituisce le transazioni aperte del provider. Il risultato viene usato
     * per riconciliazione e dal provider mock delle ricevute, non come seconda
     * coda di polling rispetto alle task automatiche del gestionale.
     *
     * @return array<int,array<string,mixed>>
     */
    public function openForProvider(string $provider, int $limit = 100): array
    {
        if (!$this->tableAvailable()) {
            return [];
        }

        $limit = max(1, min(250, $limit));

        return database()->fetchArray(
            'SELECT * FROM `'.self::TABLE.'`
             WHERE `provider` = ?
               AND `status` IN (?, ?, ?, ?)
             ORDER BY `updated_at` ASC, `id` ASC
             LIMIT '.$limit,
            [
                $provider,
                self::STATUS_SENDING,
                self::STATUS_SENT,
                self::STATUS_WAITING,
                self::STATUS_UNCERTAIN,
            ]
        );
    }

    /**
     * Individua la transazione aperta associata a una ricevuta SDI usando il
     * nome XML originariamente trasmesso. Le ricevute standard mantengono il
     * nome fattura come prefisso (es. IT..._00001_RC.xml).
     */
    public function findOpenByReceiptFilename(string $provider, string $receipt_filename): ?array
    {
        $receipt = pathinfo(basename($receipt_filename), PATHINFO_FILENAME);

        foreach ($this->openForProvider($provider, 250) as $transaction) {
            $invoice = pathinfo(basename((string) ($transaction['filename'] ?? '')), PATHINFO_FILENAME);
            if ($invoice !== '' && ($receipt === $invoice || str_starts_with($receipt, $invoice.'_'))) {
                return $transaction;
            }
        }

        return null;
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
                `status` = IF(`status` IN ("SENDING", "SENT", "UNCERTAIN", "WAITING", "FINAL"), `status`, VALUES(`status`)),
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
            'remote_id' => $remote_id !== null ? substr($remote_id, 0, 255) : null,
            'remote_status' => $remote_status !== null ? substr($remote_status, 0, 64) : null,
            'last_response_at' => date('Y-m-d H:i:s'),
            'last_error' => null,
        ]);
    }

    public function markUncertain(int $id_documento, string $provider, string $xml_hash, string $message): void
    {
        $this->update($id_documento, $provider, $xml_hash, [
            'status' => self::STATUS_UNCERTAIN,
            'last_error' => substr($message, 0, 65535),
            'last_response_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function markFailed(int $id_documento, string $provider, string $xml_hash, string $message): void
    {
        $this->update($id_documento, $provider, $xml_hash, [
            'status' => self::STATUS_FAILED,
            'last_error' => substr($message, 0, 65535),
            'last_response_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function markFinal(int $id_documento, string $provider, string $xml_hash, ?string $remote_status = null): void
    {
        $values = [
            'status' => self::STATUS_FINAL,
            'last_response_at' => date('Y-m-d H:i:s'),
            'last_error' => null,
        ];

        if ($remote_status !== null) {
            $values['remote_status'] = substr($remote_status, 0, 64);
        }

        $this->update($id_documento, $provider, $xml_hash, $values);
    }

    /**
     * Chiude il tracking solo dopo che Ricevuta::process() ha completato il
     * salvataggio locale e richiama processReceipt() sul provider.
     */
    public function markFinalByReceiptFilename(string $provider, string $receipt_filename, ?string $remote_status = null): bool
    {
        $transaction = $this->findOpenByReceiptFilename($provider, $receipt_filename);
        if (!$transaction) {
            return false;
        }

        $this->markFinal(
            (int) $transaction['id_documento'],
            $provider,
            (string) $transaction['xml_hash'],
            $remote_status
        );

        return true;
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
