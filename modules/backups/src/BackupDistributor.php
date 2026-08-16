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
    /**
     * Distribuisce un backup locale verso tutte le destinazioni abilitate.
     *
     * Il fallimento di una destinazione non interrompe le successive.
     */
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

    /**
     * Verifica lettura e scrittura di una singola destinazione con un file temporaneo.
     */
    public static function test(BackupDestination $destination): array
    {
        $filesystem = self::getFilesystem($destination);
        $directory = self::normalizeDirectory($destination->path);
        $filename = '.osm-backup-test-'.bin2hex(random_bytes(8)).'.tmp';
        $path = self::joinPath($directory, $filename);
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            throw new \RuntimeException(tr('Impossibile creare il file temporaneo di test.'));
        }

        fwrite($stream, 'OpenSTAManager backup destination test');
        rewind($stream);

        try {
            self::ensureDirectory($filesystem, $directory);
            $filesystem->writeStream($path, $stream);

            if (!$filesystem->fileExists($path)) {
                throw new \RuntimeException(tr('Il file di test non risulta presente sulla destinazione.'));
            }

            $filesystem->delete($path);

            return [
                'success' => true,
                'message' => tr('Connessione e permessi verificati correttamente.'),
            ];
        } catch (Throwable $e) {
            try {
                if ($filesystem->fileExists($path)) {
                    $filesystem->delete($path);
                }
            } catch (Throwable) {
            }

            return [
                'success' => false,
                'message' => $e->getMessage(),
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
        $adapter = $destination->adapter;
        $result = [
            'id' => $destination->id,
            'adapter' => $adapter?->name,
            'success' => false,
            'message' => '',
        ];

        if (empty($adapter)) {
            $result['message'] = tr('Adattatore di archiviazione non disponibile.');

            return $result;
        }

        $stream = fopen($backup_path, 'rb');
        if ($stream === false) {
            $result['message'] = tr('Impossibile aprire il backup per il trasferimento.');

            return $result;
        }

        try {
            $filesystem = self::getFilesystem($destination);
            $directory = self::normalizeDirectory($destination->path);
            $remote_path = self::joinPath($directory, basename($backup_path));

            self::ensureDirectory($filesystem, $directory);
            $filesystem->writeStream($remote_path, $stream);

            if (!$filesystem->fileExists($remote_path)) {
                throw new \RuntimeException(tr('Il backup trasferito non è presente sulla destinazione.'));
            }

            $local_size = filesize($backup_path);
            $remote_size = $filesystem->fileSize($remote_path);
            if ($local_size === false || $remote_size !== $local_size) {
                throw new \RuntimeException(tr('La dimensione del backup trasferito non corrisponde al file originale.'));
            }

            self::cleanup($filesystem, $directory, (int) $destination->retention);

            $result['success'] = true;
            $result['message'] = tr('Backup trasferito e verificato correttamente.');
            $result['path'] = $remote_path;
            $result['size'] = $remote_size;
        } catch (Throwable $e) {
            $result['message'] = $e->getMessage();
        } finally {
            fclose($stream);
        }

        return $result;
    }

    protected static function getFilesystem(BackupDestination $destination): OSMFilesystem
    {
        $adapter_config = $destination->adapter;
        if (empty($adapter_config)) {
            throw new \RuntimeException(tr('Adattatore di archiviazione non disponibile.'));
        }

        $class = $adapter_config->class;
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

    protected static function normalizeDirectory(?string $directory): string
    {
        return trim((string) $directory, '/');
    }

    protected static function joinPath(string $directory, string $filename): string
    {
        return $directory === '' ? $filename : $directory.'/'.$filename;
    }
}
