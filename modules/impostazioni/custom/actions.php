<?php

/**
 * Estensione minimale del salvataggio impostazioni per il gateway FE.
 * Il controller originale continua a gestire validazione, risposta JSON e flash.
 */

include dirname(__DIR__).'/actions.php';

if (filter('op') !== 'salva' || empty($result) || empty($impostazione)) {
    return;
}

$relevant = [
    'Fatturazione Elettronica Provider',
    'Hosting Solutions FE Abilitato',
    'Hosting Solutions FE Mock Scenario',
];

if (!in_array((string) $impostazione->nome, $relevant, true)) {
    return;
}

// Lettura diretta dal DB per non dipendere da cache delle impostazioni durante
// lo stesso request in cui il valore e' appena stato modificato.
$provider = (string) (\Models\Setting::where('nome', 'Fatturazione Elettronica Provider')->value('valore') ?? 'osmcloud');
$enabled_value = \Models\Setting::where('nome', 'Hosting Solutions FE Abilitato')->value('valore');
$hs_enabled = in_array($enabled_value, [true, 1, '1', 'true'], true);

if ($provider === 'hosting_solutions' && $hs_enabled) {
    $tasks = [
        [
            'class' => 'Plugins\\ExportFE\\InvoiceHookTask',
            'name' => 'Hook Invio Fatture Elettroniche',
        ],
        [
            'class' => 'Plugins\\ReceiptFE\\ReceiptTask',
            'name' => 'Importazione automatica Ricevute FE',
        ],
        [
            'class' => 'Plugins\\ImportFE\\InvoiceHookTask',
            'name' => 'Hook Importazione Fatture Elettroniche',
        ],
    ];

    foreach ($tasks as $definition) {
        $task = database()->fetchOne(
            'SELECT `id`, `enabled` FROM `zz_tasks` WHERE `class` = ? OR `name` = ? ORDER BY (`class` = ?) DESC LIMIT 1',
            [$definition['class'], $definition['name'], $definition['class']]
        );

        if (empty($task)) {
            continue;
        }

        $values = ['enabled' => 1];

        // Alla riattivazione la rendiamo subito eleggibile; dopo l'esecuzione
        // la normale classe Task ricalcolera' la successiva data dal cron nativo.
        if (empty($task['enabled'])) {
            $values['next_execution_at'] = date('Y-m-d H:i:s');
        }

        database()->table('zz_tasks')->where('id', $task['id'])->update($values);
    }
}

// Ogni nuova selezione dello scenario passivo crea una nuova fixture logica:
// progressivo e numero cambiano, cosi' il parser mantiene correttamente attivi
// i controlli anti-duplicato anche durante test ripetuti.
if ((string) $impostazione->nome === 'Hosting Solutions FE Mock Scenario') {
    $scenario = (string) \Models\Setting::where('nome', 'Hosting Solutions FE Mock Scenario')->value('valore');

    if ($scenario === 'passive_invoice') {
        $processed = \Models\Cache::where('name', 'Hosting Solutions FE Mock Passive Processed')->first();
        if (empty($processed)) {
            $processed = \Models\Cache::build(
                'Hosting Solutions FE Mock Passive Processed',
                null,
                \Carbon\Carbon::now()->addYears(10)
            );
        }
        $processed->set([]);

        $run_cache = \Models\Cache::where('name', 'Hosting Solutions FE Mock Passive Run')->first();
        if (empty($run_cache)) {
            $run_cache = \Models\Cache::build(
                'Hosting Solutions FE Mock Passive Run',
                null,
                \Carbon\Carbon::now()->addYears(10)
            );
        }

        $current = (int) ($run_cache->content ?? 0);
        $run_cache->set(max(1, $current + 1));
    }
}
