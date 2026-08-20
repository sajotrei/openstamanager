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

use League\Flysystem\FilesystemAdapter as FlysystemAdapterContract;
use Modules\FileAdapters\OSMFilesystem;
use Throwable;

class BackupDistributor
{
    /**
     * Distribuisce un backup locale verso tutte le destinazioni abilitate.
     * Il fallimento di una destinazione non interrompe le successive.
     */
    public static function distribute(string $backup_path): array
    {
        if (!is_file($backup_path) || !is_readable($backup_path)) {
            throw new \InvalidArgumentException(tr('Il file di backup da distribuire non è leggibile.'));
        }

        $destinations = BackupDestination::with('adapter')
            ->where('enabled', 1)
            ->orderBy('id')
            ->get();

        return self::distributeDestinations($backup_path, $destinations);
    }

    /**
     * Verifica scrittura, lettura ed eliminazione di una singola destinazione.
     */
    public static function test(BackupDestination $destination): array
    {
        $adapter = null;

        try {
            $adapter = $destination->adapter;
            self::assertSecondaryDestination($destination);
            $directory = self::normalizeDirectory($destination->path);
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => self::safeErrorMessage($e, $adapter),
            ];
        }

        $filename = '.osm-backup-test-'.bin2hex(random_bytes(8)).'.tmp';
        $path = self::joinPath($directory, $filename);
        $payload = 'OpenSTAManager backup destination test';
        $stream = fopen('php://temp', 'w+b');
        $filesystem = null;

        if ($stream === false) {
            return [
                'success' => false,
                'message' => tr('Impossibile creare il file temporaneo di test.'),
            ];
        }

        fwrite($stream, $payload);
        rewind($stream);

        try {
            $filesystem = self::getFilesystem($destination);
            self::ensureDirectory($filesystem, $directory);
            $filesystem->writeStream($path, $stream);

            if (!$filesystem->fileExists($path)) {
                throw new \RuntimeException(tr('Il file di test non risulta presente sulla destinazione.'));
            }

            if ($filesystem->fileSize($path) !== strlen($payload)) {
                throw new \RuntimeException(tr('La dimensione del file di test non corrisponde al contenuto inviato.'));
            }

            if ($filesystem->read($path) !== $payload) {
                throw new \RuntimeException(tr('Il file di test non è leggibile correttamente dalla destinazione.'));
            }

            $filesystem->delete($path);
            if ($filesystem->fileExists($path)) {
                throw new \RuntimeException(tr('Il file di test non può essere eliminato dalla destinazione.'));
            }

            return [
                'success' => true,
                'message' => tr('Connessione e permessi di lettura, scrittura ed eliminazione verificati correttamente.'),
            ];
        } catch (Throwable $e) {
            if ($filesystem !== null) {
                try {
                    if ($filesystem->fileExists($path)) {
                        $filesystem->delete($path);
                    }
                } catch (Throwable) {
                }
            }

            return [
                'success' => false,
                'message' => self::safeErrorMessage($e, $adapter),
            ];
        } finally {
            fclose($stream);
        }
    }

    /**
     * Distribuisce il backup verso una singola destinazione.
     */
    public static function distributeTo(string $backup_path, BackupDestination $destination): array
    {
        $adapter = null;

        try {
            $adapter = $destination->adapter;
        } catch (Throwable $e) {
            return [
                'id' => $destination->id,
                'adapter' => null,
                'success' => false,
                'message' => self::safeErrorMessage($e),
            ];
        }

        $result = [
            'id' => $destination->id,
            'adapter' => $adapter?->name,
            'success' => false,
            'message' => '',
        ];

        try {
            self::assertSecondaryDestination($destination);
        } catch (Throwable $e) {
            $result['message'] = self::safeErrorMessage($e, $adapter);

            return $result;
        }

        if (!is_file($backup_path) || !is_readable($backup_path)) {
            $result['message'] = tr('Il file di backup da distribuire non è leggibile.');

            return $result;
        }

        $stream = fopen($backup_path, 'rb');
        if ($stream === false) {
            $result['message'] = tr('Impossibile aprire il backup per il trasferimento.');

            return $result;
        }

        $filesystem = null;
        $temporary_path = null;

        try {
            $filesystem = self::getFilesystem($destination);
            $directory = self::normalizeDirectory($destination->path);
            $remote_path = self::joinPath($directory, basename($backup_path));
            $temporary_path = $remote_path.'.part';

            self::ensureDirectory($filesystem, $directory);

            if ($filesystem->fileExists($temporary_path)) {
                $filesystem->delete($temporary_path);
            }

            $filesystem->writeStream($temporary_path, $stream);

            if (!$filesystem->fileExists($temporary_path)) {
                throw new \RuntimeException(tr('Il backup trasferito non è presente sulla destinazione.'));
            }

            $local_size = filesize($backup_path);
            $remote_size = $filesystem->fileSize($temporary_path);
            if ($local_size === false || $remote_size !== $local_size) {
                throw new \RuntimeException(tr('La dimensione del backup trasferito non corrisponde al file originale.'));
            }

            // Una replica già presente con la stessa dimensione è considerata valida.
            // Questo rende l'operazione idempotente senza eliminare inutilmente una copia buona.
            if ($filesystem->fileExists($remote_path) && $filesystem->fileSize($remote_path) === $local_size) {
                $filesystem->delete($temporary_path);
                self::cleanup($filesystem, $directory, (int) $destination->retention);

                $result['success'] = true;
                $result['message'] = tr('Backup già presente e verificato sulla destinazione.');
                $result['path'] = $remote_path;
                $result['size'] = $local_size;

                return $result;
            }

            if ($filesystem->fileExists($remote_path)) {
                $filesystem->delete($remote_path);
            }

            $filesystem->move($temporary_path, $remote_path);

            if (!$filesystem->fileExists($remote_path)) {
                throw new \RuntimeException(tr('Il backup verificato non è stato finalizzato sulla destinazione.'));
            }

            if ($filesystem->fileSize($remote_path) !== $local_size) {
                throw new \RuntimeException(tr('La dimensione del backup finalizzato non corrisponde al file originale.'));
            }

            self::cleanup($filesystem, $directory, (int) $destination->retention);

            $result['success'] = true;
            $result['message'] = tr('Backup trasferito e verificato correttamente.');
            $result['path'] = $remote_path;
            $result['size'] = $local_size;
        } catch (Throwable $e) {
            if ($filesystem !== null && $temporary_path !== null) {
                try {
                    if ($filesystem->fileExists($temporary_path)) {
                        $filesystem->delete($temporary_path);
                    }
                } catch (Throwable) {
                }
            }

            $result['message'] = self::safeErrorMessage($e, $adapter);
        } finally {
            fclose($stream);
        }

        return $result;
    }

    /**
     * Normalizza e valida il percorso relativo della destinazione.
     */
    public static function normalizeDirectory(?string $directory): string
    {
        $raw = trim((string) $directory);
        $normalized = str_replace('\\', '/', $raw);

        if ($normalized === '') {
            return '';
        }

        if (str_starts_with($normalized, '/')
            || preg_match('/^[A-Za-z]:\//', $normalized)
            || preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:\/\//', $normalized)) {
            throw new \InvalidArgumentException(tr('Il percorso della destinazione deve essere relativo.'));
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $normalized)) {
            throw new \InvalidArgumentException(tr('Il percorso della destinazione contiene caratteri non validi.'));
        }

        $segments = explode('/', $normalized);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new \InvalidArgumentException(tr('Il percorso della destinazione non può contenere segmenti . o ...'));
            }
        }

        return implode('/', $segments);
    }

    /**
     * Esegue la distribuzione su una collezione di destinazioni isolando ogni errore.
     */
    protected static function distributeDestinations(string $backup_path, iterable $destinations): array
    {
        $results = [];

        foreach ($destinations as $destination) {
            try {
                $results[] = self::distributeTo($backup_path, $destination);
            } catch (Throwable $e) {
                $adapter = null;
                if (is_object($destination)) {
                    try {
                        $adapter = $destination->adapter;
                    } catch (Throwable) {
                    }
                }

                $results[] = [
                    'id' => is_object($destination) ? ($destination->id ?? null) : null,
                    'adapter' => $adapter?->name,
                    'success' => false,
                    'message' => self::safeErrorMessage($e, $adapter),
                ];
            }
        }

        return $results;
    }

    protected static function assertSecondaryDestination(BackupDestination $destination): void
    {
        $adapter = $destination->adapter;
        if (empty($adapter)) {
            throw new \RuntimeException(tr('Adattatore di archiviazione non disponibile.'));
        }

        // Gli adapter non persistiti sono usati nei test unitari in-memory.
        if (empty($adapter->id)) {
            return;
        }

        $primary_adapter = \Backup::getStorageAdapter();
        if (!empty($primary_adapter) && (int) $primary_adapter->id === (int) $adapter->id) {
            throw new \RuntimeException(tr('La destinazione secondaria coincide con l’adattatore usato per il backup principale.'));
        }
    }

    protected static function getFilesystem(BackupDestination $destination): OSMFilesystem
    {
        $adapter_config = $destination->adapter;
        $class = $adapter_config->class;

        if (empty($class) || !class_exists($class) || !is_a($class, FlysystemAdapterContract::class, true)) {
            throw new \RuntimeException(tr('Classe dell’adattatore di archiviazione non valida o non disponibile.'));
        }

        $adapter = new $class($adapter_config->options);

        return new OSMFilesystem($adapter);
    }

    protected static function ensureDirectory(OSMFilesystem $filesystem, string $directory): void
    {
        if ($directory !== '' && !$filesystem->directoryExists($directory)) {
            $filesystem->createDirectory($directory);
        }
    }

    protected static function cleanup(OSMFilesystem $filesystem, string $directory, int $retention): void
    {
        if ($retention <= 0) {
            return;
        }

        $backups = [];
        foreach ($filesystem->listContents($directory, false) as $attributes) {
            if (!$attributes->isFile()) {
                continue;
            }

            $filename = basename($attributes->path());
            if (!str_starts_with($filename, 'OSM backup ') || !str_ends_with($filename, '.zip')) {
                continue;
            }

            $backups[] = $attributes->path();
        }

        sort($backups, SORT_STRING);
        $remove = count($backups) - $retention;
        for ($i = 0; $i < $remove; ++$i) {
            $filesystem->delete($backups[$i]);
        }
    }

    protected static function safeErrorMessage(Throwable $exception, ?object $adapter = null): string
    {
        $message = $exception->getMessage() ?: tr('Errore durante l’accesso alla destinazione di backup.');
        $options = json_decode((string) ($adapter->options ?? ''), true);

        if (!is_array($options)) {
            return $message;
        }

        $redact = static function (array $values) use (&$message, &$redact): void {
            foreach ($values as $key => $value) {
                if (is_array($value)) {
                    $redact($value);
                    continue;
                }

                if (!is_scalar($value) || $value === '') {
                    continue;
                }

                if (preg_match('/password|passphrase|secret|token|access[_-]?key|private[_-]?key/i', (string) $key)) {
                    $message = str_replace((string) $value, '***', $message);
                }
            }
        };

        $redact($options);

        return $message;
    }

    protected static function joinPath(string $directory, string $filename): string
    {
        return $directory === '' ? $filename : $directory.'/'.$filename;
    }
}
