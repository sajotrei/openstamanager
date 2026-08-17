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

include_once __DIR__.'/../../core.php';

use Modules\Backups\BackupDestination;
use Modules\Backups\BackupDistributor;
use Modules\FileAdapters\FileAdapter;

switch (filter('op')) {
    case 'getfile':
        $number = filter('number');
        $number = intval($number);

        $backups = Backup::getList();
        $backup = $backups[$number];
        $filename = basename((string) $backup);

        download($backup, $filename);

        break;

    case 'del':
        $number = filter('number');
        $number = intval($number);

        $backups = Backup::getList();
        $backup = $backups[$number];
        $filename = basename((string) $backup);

        delete($backup);

        if (!file_exists($backup)) {
            flash()->info(tr('Backup _FILE_ eliminato!', [
                '_FILE_' => '"'.$filename.'"',
            ]));
        } else {
            flash()->error(tr("Errore durante l'eliminazione del backup _FILE_!", [
                '_FILE_' => '"'.$filename.'"',
            ]));
        }

        break;

    case 'backup':
        $ignores = ['dirs' => [], 'files' => []];

        if (filter('exclude') == 'exclude_attachments') {
            $ignores = ['dirs' => ['files']];
        } elseif (filter('exclude') == 'only_database') {
            $ignores = ['dirs' => ['vendor', 'update', 'templates', 'src', 'plugins', 'modules', 'logs', 'locale', 'lib', 'include', 'files', 'config', 'assets', 'api'], 'files' => ['*.php', '*.md', '*.json', '*.js', '*.xml', '.*']];
        }

        try {
            $before = Backup::getList();
            $result = Backup::create($ignores);

            if ($result) {
                flash()->info(tr('Nuovo backup creato correttamente!'));

                try {
                    $after = Backup::getList();
                    $created_backups = array_values(array_diff($after, $before));
                    $backup = end($created_backups) ?: end($after);
                    $distribution_results = $backup ? BackupDistributor::distribute($backup) : [];
                    $failed_destinations = array_filter($distribution_results, fn ($item) => !$item['success']);

                    if (!empty($failed_destinations)) {
                        $names = array_map(fn ($item) => $item['adapter'] ?: tr('Destinazione sconosciuta'), $failed_destinations);
                        flash()->warning(tr('Backup locale creato, ma alcune destinazioni secondarie non sono state aggiornate: _DESTINATIONS_', [
                            '_DESTINATIONS_' => implode(', ', $names),
                        ]));
                    }
                } catch (Throwable $e) {
                    flash()->warning(tr('Backup locale creato, ma la distribuzione verso le destinazioni secondarie non è stata completata: _ERROR_', [
                        '_ERROR_' => $e->getMessage(),
                    ]));
                }
            } else {
                $backup_dir = Backup::getDirectory();
                flash()->error(tr('Errore durante la creazione del backup!').' '.str_replace('_DIR_', '"'.$backup_dir.'"', tr('Verifica che la cartella _DIR_ abbia i permessi di scrittura!')));
            }
        } catch (Throwable $e) {
            flash()->error(tr('Errore durante la creazione del backup!').' '.$e->getMessage());
        }

        break;

    case 'backup_destination_add':
    case 'backup_destination_update':
        $id = intval(post('id'));
        $id_adapter = intval(post('id_adapter'));
        $retention = max(1, intval(post('retention')));
        $enabled = intval(post('enabled')) === 1;

        try {
            $path = BackupDistributor::normalizeDirectory(post('path'));
        } catch (Throwable $e) {
            flash()->error($e->getMessage());
            break;
        }

        $adapter = FileAdapter::find($id_adapter);
        $primary_adapter = Backup::getStorageAdapter();

        if (empty($adapter)) {
            flash()->error(tr('Adattatore di archiviazione non disponibile.'));
            break;
        }

        if (!empty($primary_adapter) && (int) $primary_adapter->id === $id_adapter) {
            flash()->error(tr('La destinazione secondaria non può coincidere con l’adattatore usato per il backup principale.'));
            break;
        }

        $duplicate = BackupDestination::where('id_adapter', $id_adapter)->where('path', $path);
        if ($id > 0) {
            $duplicate->where('id', '!=', $id);
        }

        if ($duplicate->exists()) {
            flash()->error(tr('Questo adattatore e percorso sono già configurati come destinazione di backup.'));
            break;
        }

        $destination = $id > 0 ? BackupDestination::find($id) : new BackupDestination();
        if (empty($destination)) {
            flash()->error(tr('Destinazione di backup non trovata.'));
            break;
        }

        $destination->id_adapter = $id_adapter;
        $destination->path = $path;
        $destination->retention = $retention;
        $destination->enabled = $enabled;
        $destination->save();

        flash()->info($id > 0 ? tr('Destinazione di backup aggiornata.') : tr('Destinazione di backup aggiunta.'));
        break;

    case 'backup_destination_toggle':
        $destination = BackupDestination::find(intval(post('id')));
        if (empty($destination)) {
            flash()->error(tr('Destinazione di backup non trovata.'));
            break;
        }

        $destination->enabled = !$destination->enabled;
        $destination->save();
        flash()->info(tr('Stato della destinazione di backup aggiornato.'));
        break;

    case 'backup_destination_delete':
        $destination = BackupDestination::find(intval(post('id')));
        if (empty($destination)) {
            flash()->error(tr('Destinazione di backup non trovata.'));
            break;
        }

        $destination->delete();
        flash()->info(tr('Destinazione di backup eliminata.'));
        break;

    case 'backup_destination_test':
        $destination = BackupDestination::find(intval(post('id')));
        if (empty($destination)) {
            flash()->error(tr('Destinazione di backup non trovata.'));
            break;
        }

        $test = BackupDistributor::test($destination);
        if ($test['success']) {
            flash()->info($test['message']);
        } else {
            flash()->error(tr('Test destinazione fallito: _ERROR_', [
                '_ERROR_' => $test['message'],
            ]));
        }
        break;

    case 'size':
        $number = filter('number');
        $number = intval($number);

        $backups = Backup::getList();
        $backup = $backups[$number] ?: Backup::getDirectory();

        echo Util\FileSystem::size($backup);

        break;
}

if (filter('op') == 'restore') {
    if (!extension_loaded('zip')) {
        flash()->error(tr('Estensione zip non supportata!').'<br>'.tr('Verifica e attivala sul tuo file _FILE_', [
            '_FILE_' => '<b>php.ini</b>',
        ]));

        return;
    }

    $number = filter('number');
    if ($number === null) {
        $path = $_FILES['blob']['tmp_name'];
    } else {
        $number != '' ? $number : 0;
        $number = intval($number);

        $backups = Backup::getList();
        $path = $backups[$number];
    }

    try {
        // Ottieni la password per i backup esterni se impostata
        $password = setting('Password backup esterni');

        $result = Backup::restore($path, is_file($path), $password);
        $database->beginTransaction();

        if ($result) {
            flash()->warning(tr('Ripristino eseguito correttamente!'));
        } else {
            flash()->error(tr('Errore durante il ripristino del backup!').'<br>'.$result);
        }
    } catch (Exception $e) {
        flash()->error(tr('Errore durante il ripristino del backup!').' '.$e->getMessage());
    }
}
