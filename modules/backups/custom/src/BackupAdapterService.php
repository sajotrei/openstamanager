<?php

namespace Modules\Backups;

use Models\Module;
use Modules\FileAdapters\Adapters\FTPAdapter;
use Modules\FileAdapters\Adapters\LocalAdapter;
use Modules\FileAdapters\FileAdapter;
use RuntimeException;
use Throwable;

class BackupAdapterService
{
    public static function canManageAdapters(): bool
    {
        try {
            $module = Module::where('name', 'Adattatori di archiviazione')->first();

            return !empty($module) && \Modules::getPermission($module->id) === 'rw';
        } catch (Throwable) {
            return false;
        }
    }

    public static function saveDestination(array $input): BackupDestination
    {
        $id = (int) ($input['id'] ?? 0);
        $mode = (string) ($input['mode'] ?? 'existing');
        $retention = max(1, min(3650, (int) ($input['retention'] ?? 10)));

        $destination = $id > 0 ? BackupDestination::find($id) : new BackupDestination();
        if (empty($destination)) {
            throw new RuntimeException(tr('Destinazione di backup non trovata.'));
        }

        $adapter = null;
        $path = '';
        $managed = false;

        if ($mode === 'existing') {
            $adapter = FileAdapter::find((int) ($input['id_adapter'] ?? 0));
            if (empty($adapter)) {
                throw new RuntimeException(tr('Adattatore di archiviazione non disponibile.'));
            }

            $path = BackupDistributor::normalizeDirectory($input['path'] ?? '');
        } elseif ($mode === 'ftp' || $mode === 'local') {
            if (!self::canManageAdapters()) {
                throw new RuntimeException(tr('Non disponi dei permessi per creare o modificare adattatori di archiviazione.'));
            }

            $adapter = self::managedAdapterFor($destination);
            if (empty($adapter)) {
                $adapter = new FileAdapter();
            }

            $adapter->name = self::normalizeName($input['name'] ?? '');

            if ($mode === 'ftp') {
                $adapter->class = FTPAdapter::class;
                $adapter->options = self::buildFtpOptions($input, $adapter->exists ? $adapter->options : null);
                $path = BackupDistributor::normalizeDirectory($input['path'] ?? 'backups');
            } else {
                $adapter->class = LocalAdapter::class;
                $adapter->options = json_encode([
                    'directory' => self::normalizeLocalDirectory($input['local_directory'] ?? ''),
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $path = '';
            }

            if ($adapter->exists) {
                self::assertNotPrimary($adapter);
                self::assertUniqueDestination($adapter, $path, $destination);
            }

            try {
                $adapter->save();
            } catch (Throwable) {
                throw new RuntimeException(tr('Impossibile salvare la configurazione dell’adattatore di archiviazione.'));
            }
            $managed = true;
        } else {
            throw new RuntimeException(tr('Tipo di destinazione non valido.'));
        }

        self::assertNotPrimary($adapter);
        self::assertUniqueDestination($adapter, $path, $destination);

        $destination->id_adapter = $adapter->id;
        $destination->path = $path;
        $destination->retention = $retention;
        $destination->managed_adapter = $managed;
        // Dopo una modifica la destinazione rimane disabilitata fino al test.
        $destination->enabled = false;
        try {
            $destination->save();
        } catch (Throwable) {
            throw new RuntimeException(tr('Impossibile salvare la destinazione di backup. Verifica che adattatore e percorso non siano già configurati.'));
        }
        $destination->setRelation('adapter', $adapter);

        return $destination;
    }

    public static function buildFtpOptions(array $input, ?string $current_options = null): string
    {
        $current = json_decode((string) $current_options, true);
        $current = is_array($current) ? $current : [];

        $host = trim((string) ($input['host'] ?? ''));
        if ($host === '' || strlen($host) > 255
            || preg_match('/[\x00-\x20\x7F]/', $host)
            || str_contains($host, '/')
            || str_contains($host, '://')) {
            throw new RuntimeException(tr('Host FTP non valido. Inserisci solo nome host o indirizzo IP.'));
        }

        $username = trim((string) ($input['username'] ?? ''));
        if ($username === '' || strlen($username) > 255 || preg_match('/[\x00-\x1F\x7F]/', $username)) {
            throw new RuntimeException(tr('Username FTP non valido.'));
        }

        $password = (string) ($input['password'] ?? '');
        if ($password === '') {
            $password = (string) ($current['password'] ?? '');
        }
        if ($password === '') {
            throw new RuntimeException(tr('La password FTP è obbligatoria.'));
        }

        $port = (int) ($input['port'] ?? 21);
        if ($port < 1 || $port > 65535) {
            throw new RuntimeException(tr('Porta FTP non valida.'));
        }

        $timeout = (int) ($input['timeout'] ?? 30);
        if ($timeout < 1 || $timeout > 300) {
            throw new RuntimeException(tr('Il timeout FTP deve essere compreso tra 1 e 300 secondi.'));
        }

        return json_encode([
            'host' => $host,
            'root' => '/',
            'username' => $username,
            'password' => $password,
            'port' => $port,
            'ssl' => self::toBool($input['ssl'] ?? false),
            'timeout' => $timeout,
            'passive' => self::toBool($input['passive'] ?? true),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public static function normalizeLocalDirectory(?string $directory): string
    {
        $normalized = str_replace('\\', '/', trim((string) $directory));

        if ($normalized === '' || strlen($normalized) > 255) {
            throw new RuntimeException(tr('Specifica una cartella locale relativa alla directory di OpenSTAManager.'));
        }

        if (str_starts_with($normalized, '/')
            || preg_match('/^[A-Za-z]:\//', $normalized)
            || preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:\/\//', $normalized)
            || preg_match('/[\x00-\x1F\x7F]/', $normalized)) {
            throw new RuntimeException(tr('La cartella locale deve essere un percorso relativo e sicuro.'));
        }

        $normalized = trim($normalized, '/');
        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new RuntimeException(tr('La cartella locale contiene segmenti non validi.'));
            }
        }

        return $normalized;
    }

    public static function describe(BackupDestination $destination): array
    {
        $adapter = $destination->adapter;
        if (empty($adapter)) {
            return ['mode' => 'existing'];
        }

        if (!$destination->managed_adapter) {
            return [
                'mode' => 'existing',
                'id_adapter' => (int) $adapter->id,
                'path' => (string) $destination->path,
            ];
        }

        $options = json_decode((string) $adapter->options, true);
        $options = is_array($options) ? $options : [];

        if (is_a($adapter->class, FTPAdapter::class, true)) {
            return [
                'mode' => 'ftp',
                'name' => (string) $adapter->name,
                'host' => (string) ($options['host'] ?? ''),
                'port' => (int) ($options['port'] ?? 21),
                'username' => (string) ($options['username'] ?? ''),
                'ssl' => !empty($options['ssl']),
                'passive' => !array_key_exists('passive', $options) || !empty($options['passive']),
                'timeout' => (int) ($options['timeout'] ?? 30),
                'path' => (string) $destination->path,
            ];
        }

        if (is_a($adapter->class, LocalAdapter::class, true)) {
            return [
                'mode' => 'local',
                'name' => (string) $adapter->name,
                'local_directory' => ltrim((string) ($options['directory'] ?? ''), '/'),
                'path' => '',
            ];
        }

        return [
            'mode' => 'existing',
            'id_adapter' => (int) $adapter->id,
            'path' => (string) $destination->path,
        ];
    }

    protected static function managedAdapterFor(BackupDestination $destination): ?FileAdapter
    {
        if (!$destination->exists || !$destination->managed_adapter) {
            return null;
        }

        return $destination->adapter;
    }

    protected static function normalizeName(string $name): string
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 255) {
            throw new RuntimeException(tr('Specifica un nome valido per la destinazione.'));
        }

        return $name;
    }

    protected static function assertNotPrimary(FileAdapter $adapter): void
    {
        $primary = \Backup::getStorageAdapter();
        if (!empty($primary?->id) && (int) $primary->id === (int) $adapter->id) {
            throw new RuntimeException(tr('La destinazione secondaria non può coincidere con l’adattatore usato per il backup principale.'));
        }
    }

    protected static function assertUniqueDestination(FileAdapter $adapter, string $path, BackupDestination $destination): void
    {
        $duplicate = BackupDestination::where('id_adapter', $adapter->id)->where('path', $path);
        if ($destination->exists) {
            $duplicate->where('id', '!=', $destination->id);
        }

        if ($duplicate->exists()) {
            throw new RuntimeException(tr('Questo adattatore e percorso sono già configurati come destinazione di backup.'));
        }
    }

    protected static function toBool(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'on', 'yes'], true);
    }
}
