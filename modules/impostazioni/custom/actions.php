<?php

/**
 * Estensione minimale del salvataggio impostazioni per il gateway FE.
 * Il salvataggio vero resta nel controller originale; qui sincronizziamo solo
 * le task FE quando Hosting Solutions è il provider selezionato.
 */

include dirname(__DIR__).'/actions.php';

if (filter('op') !== 'salva' || empty($result) || empty($impostazione)) {
    return;
}

$relevant = [
    'Fatturazione Elettronica Provider',
    'Hosting Solutions FE Abilitato',
    'Hosting Solutions FE Modalita mock',
    'Hosting Solutions FE Mock Scenario',
];

if (!in_array((string) $impostazione->nome, $relevant, true)) {
    return;
}

$provider = (string) setting('Fatturazione Elettronica Provider');

// Non modifichiamo la politica delle task quando viene usato OSMCloud:
// in quel caso resta valido il comportamento configurato dall'installazione.
if ($provider !== 'hosting_solutions') {
    return;
}

$enabled_value = setting('Hosting Solutions FE Abilitato');
$mock_value = setting('Hosting Solutions FE Modalita mock');
$hs_enabled = in_array($enabled_value, [true, 1, '1', 'true'], true);
$mock_enabled = in_array($mock_value, [true, 1, '1', 'true'], true);
$should_run = $hs_enabled && $mock_enabled;

$task_names = [
    'Hook Invio Fatture Elettroniche',
    'Importazione automatica Ricevute FE',
    'Hook Importazione Fatture Elettroniche',
];

foreach ($task_names as $task_name) {
    $task = database()->fetchOne('SELECT `id`, `enabled` FROM `zz_tasks` WHERE `name` = ? LIMIT 1', [$task_name]);
    if (empty($task)) {
        continue;
    }

    $values = [
        'enabled' => $should_run ? 1 : 0,
    ];

    // Alla riattivazione lasciamo che cron.php ricalcoli la prima esecuzione
    // secondo l'espressione già configurata nel task nativo.
    if ($should_run && empty($task['enabled'])) {
        $values['next_execution_at'] = null;
    }

    database()->table('zz_tasks')->where('id', $task['id'])->update($values);
}

// Ogni nuovo avvio dello scenario passivo deve rendere nuovamente disponibile
// la fixture per consentire test ripetibili del ciclo ricezione/import/conferma.
if ((string) $impostazione->nome === 'Hosting Solutions FE Mock Scenario'
    && (string) setting('Hosting Solutions FE Mock Scenario') === 'passive_invoice') {
    $cache = \Models\Cache::where('name', 'Hosting Solutions FE Mock Passive Processed')->first();
    if (empty($cache)) {
        $cache = \Models\Cache::build('Hosting Solutions FE Mock Passive Processed');
    }
    $cache->set(false);
}
