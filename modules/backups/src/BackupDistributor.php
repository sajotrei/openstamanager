<?php

/*
 * OpenSTAManager: il software gestionale open source per l'assistenza tecnica e la fatturazione
 * Copyright (C) DevCode s.r.l.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace Modules\Backups;

use Modules\FileAdapters\OSMFilesystem;
use Throwable;

class BackupDistributor
{
    public static function distribute(string $backup_path): array
    {
        if (!is_file($backup_path) || !is_readable($backup_path)) {
            throw new \InvalidArgumentException(tr('Il file di backup da distribuire non è leggibile.'));
        }

        $results = [];
        $destinations = BackupDestination::with('adapter')
            ->where('enabled', 1)
            ->orderBy('id')
            ->get();

        foreach ($destinations as $destination) {
            $results[] = self::distributeTo($backup_path, $destination);
        }

        return $results;
    }

    public static function test(BackupDestination $destination): array
    {
        try {
            self::assertSecondaryDestination($destination);
            $directory = self::normalizeDirectory($destination->path);
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
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
                'message' => $e->getMessage(),
            ];
        } finally {
            fclose($stream);
        }
    }

    public static function distributeTo(string $backup_path, BackupDestination $destination): array
    {
        $adapter = $destination->adapter;
        $result = [
            'id' => $destination->id,
            'adapter' => $adapter?->name,
            'success' => false,
            'message' => '',
        ];

        try {
            self::assertSecondaryDestination($destination);
        } catch (Throwable $e) {
            $result['message'] = $e->getMessage();

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

            if ($filesystem->fileExists($remote_path)) {
                $filesystem->delete($remote_path);
            }

            $filesystem->move($temporary_path, $remote_path);

            if (!$filesystem->fileExists($remote_path)) {
                throw new \RuntimeException(tr('Il backup verificato non è stato finalizzato sulla destinazione.'));
            }

            self::cleanup($filesystem, $directory, (int) $destination->retention);

            $result['success'] = true;
            $result['message'] = tr('Backup trasferito e verificato correttamente.');
            $result['path'] = $remote_path;
            $result['size'] = $filesystem->fileSize($remote_path);
        } catch (Throwable $e) {
            if ($filesystem !== null && $temporary_path !== null) {
                try {
                    if ($filesystem->fileExists($temporary_path)) {
                        $filesystem->delete($temporary_path);
                    }
                } catch (Throwable) {
                }
            }

            $result['message'] = $e->getMessage();
        } finally {
            fclose($stream);
        }

        return $result;
    }

    public static function normalizeDirectory(?string $directory): string
    {
        $directory = str_replace('\\', '/', trim((string) $directory));
        $directory = trim($directory, '/');

        if ($directory === '') {
            return '';
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $directory)) {
            throw new \InvalidArgumentException(tr('Il percorso della destinazione contiene caratteri non validi.'));
        }

        $segments = explode('/', $directory);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new \InvalidArgumentException(tr('Il percorso della destinazione deve essere relativo e non può contenere segmenti . o ...'));
            }
        }

        return implode('/', $segments);
    }

    protected static function assertSecondaryDestination(BackupDestination $destination): void
    {
        $adapter = $destination->adapter;
        if (empty($adapter)) {
            throw new \RuntimeException(tr('Adattatore di archiviazione non disponibile.'));
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

        if (empty($class) || !class_exists($class)) {
            throw new \RuntimeException(tr('Classe dell’adattatore di archiviazione non disponibile.'));
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

    protected static function joinPath(string $directory, string $filename): string
    {
        return $directory === '' ? $filename : $directory.'/'.$filename;
    }
}
