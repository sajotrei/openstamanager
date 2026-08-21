<?php

include_once __DIR__.'/../../../core.php';

use Modules\Backups\BackupAdapterService;
use Modules\Backups\BackupDestination;
use Modules\Backups\BackupDistributor;
use Modules\Backups\BackupManager;
use Modules\Backups\BackupRetryService;

$op = filter('op');
$custom_ops = [
    'backup',
    'backup_destination_wizard_save',
    'backup_destination_toggle',
    'backup_destination_delete',
    'backup_destination_test',
    'backup_destination_retry',
];

if (!in_array($op, $custom_ops, true)) {
    include dirname(__DIR__).'/actions.php';

    return;
}

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

    case 'backup_destination_wizard_save':
        $requested_enabled = (int) post('enabled_requested') === 1;

        try {
            $destination = BackupAdapterService::saveDestination([
                'id' => (int) post('id'),
                'mode' => post('mode'),
                'id_adapter' => (int) post('id_adapter'),
                'name' => post('name'),
                'host' => post('host'),
                'port' => post('port'),
                'username' => post('username'),
                'password' => post('password'),
                'ssl' => post('ssl'),
                'passive' => post('passive'),
                'timeout' => post('timeout'),
                'local_directory' => post('local_directory'),
                'path' => post('path'),
                'retention' => post('retention'),
            ]);

            $test = BackupRetryService::test($destination);
            if ($test['success']) {
                $destination->enabled = $requested_enabled;
                $destination->save();

                flash()->info(tr('Destinazione salvata e connessione verificata correttamente.'));
            } else {
                $destination->enabled = false;
                $destination->save();

                flash()->warning(tr('Destinazione salvata ma lasciata disattivata perché il test non è riuscito: _ERROR_', [
                    '_ERROR_' => $test['message'],
                ]));
            }
        } catch (RuntimeException $e) {
            flash()->error($e->getMessage());
        } catch (Throwable) {
            flash()->error(tr('Impossibile salvare o verificare la destinazione di backup.'));
        }
        break;

    case 'backup_destination_toggle':
        $destination = BackupDestination::find((int) post('id'));
        if (empty($destination)) {
            flash()->error(tr('Destinazione di backup non trovata.'));
            break;
        }

        if (!$destination->enabled && $destination->last_test_success !== true) {
            flash()->warning(tr('Testa con successo la destinazione prima di attivarla.'));
            break;
        }

        $destination->enabled = !$destination->enabled;
        $destination->save();
        flash()->info(tr('Stato della destinazione di backup aggiornato.'));
        break;

    case 'backup_destination_delete':
        $destination = BackupDestination::find((int) post('id'));
        if (empty($destination)) {
            flash()->error(tr('Destinazione di backup non trovata.'));
            break;
        }

        $destination->delete();
        flash()->info(tr('Destinazione di backup eliminata. L’adattatore di archiviazione eventualmente creato dal wizard non viene eliminato automaticamente.'));
        break;

    case 'backup_destination_test':
        $destination = BackupDestination::find((int) post('id'));
        if (empty($destination)) {
            flash()->error(tr('Destinazione di backup non trovata.'));
            break;
        }

        $test = BackupRetryService::test($destination);
        if ($test['success']) {
            flash()->info($test['message']);
        } else {
            flash()->error(tr('Test destinazione fallito: _ERROR_', [
                '_ERROR_' => $test['message'],
            ]));
        }
        break;

    case 'backup_destination_retry':
        $destination = BackupDestination::find((int) post('id'));
        if (empty($destination)) {
            flash()->error(tr('Destinazione di backup non trovata.'));
            break;
        }

        $result = BackupRetryService::retryDestination($destination);
        if (!empty($result['success'])) {
            flash()->info(tr('Replica completata correttamente.'));
        } else {
            flash()->warning(tr('Replica non completata: _ERROR_', [
                '_ERROR_' => $result['message'] ?? tr('Errore non specificato.'),
            ]));
        }
        break;
}
