<?php

include_once __DIR__.'/../../../core.php';

use Modules\Backups\BackupDestination;
use Modules\Backups\BackupDistributor;
use Modules\Backups\BackupManager;
use Modules\FileAdapters\FileAdapter;

$op = filter('op');
$custom_ops = [
    'backup',
    'backup_destination_add',
    'backup_destination_update',
    'backup_destination_toggle',
    'backup_destination_delete',
    'backup_destination_test',
];

if (!in_array($op, $custom_ops, true)) {
    include dirname(__DIR__).'/actions.php';

    return;
}

// Le operazioni custom sono sempre mutative o effettuano I/O remoto: devono
// passare esclusivamente dal dispatcher autenticato con permessi di scrittura.
if (empty($structure) || empty($structure['enabled']) || $structure->permission !== 'rw') {
    http_response_code(403);
    exit(tr('Accesso negato'));
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit(tr('Metodo non consentito'));
}

switch ($op) {
    case 'backup':
        $ignores = ['dirs' => [], 'files' => []];

        if (filter('exclude') == 'exclude_attachments') {
            $ignores = ['dirs' => ['files'], 'files' => []];
        } elseif (filter('exclude') == 'only_database') {
            $ignores = [
                'dirs' => ['vendor', 'update', 'templates', 'src', 'plugins', 'modules', 'logs', 'locale', 'lib', 'include', 'files', 'config', 'assets', 'api'],
                'files' => ['*.php', '*.md', '*.json', '*.js', '*.xml', '.*'],
            ];
        }

        try {
            $job = BackupManager::create($ignores);

            if ($job['busy']) {
                flash()->warning(tr('È già in corso un’altra operazione di backup.'));
                break;
            }

            if (!empty($job['collision'])) {
                flash()->warning(tr('Creazione annullata per evitare la sovrascrittura di un backup con lo stesso nome. Riprova tra un minuto.'));
                break;
            }

            if (!$job['created']) {
                $backup_dir = Backup::getDirectory();
                flash()->error(tr('Errore durante la creazione del backup!').' '.str_replace('_DIR_', '"'.$backup_dir.'"', tr('Verifica che la cartella _DIR_ abbia i permessi di scrittura!')));
                break;
            }

            flash()->info(tr('Nuovo backup creato correttamente!'));

            if (!empty($job['distribution_error'])) {
                flash()->warning(tr('Backup locale creato, ma la distribuzione verso le destinazioni secondarie non è stata completata.'));
            }

            $failed = array_filter($job['distribution'], static fn ($item) => empty($item['success']));
            if (!empty($failed)) {
                $names = array_map(
                    static fn ($item) => $item['adapter'] ?: tr('Destinazione sconosciuta'),
                    $failed
                );

                flash()->warning(tr('Backup locale creato, ma alcune destinazioni secondarie non sono state aggiornate: _DESTINATIONS_', [
                    '_DESTINATIONS_' => implode(', ', $names),
                ]));
            }
        } catch (Throwable) {
            flash()->error(tr('Errore durante la creazione del backup.'));
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
        if (empty($adapter)) {
            flash()->error(tr('Adattatore di archiviazione non disponibile.'));
            break;
        }

        $primary_adapter = Backup::getStorageAdapter();
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

        try {
            $destination->save();
        } catch (Throwable) {
            flash()->error(tr('Impossibile salvare la destinazione di backup. Verifica che adattatore e percorso non siano già configurati.'));
            break;
        }

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
}
