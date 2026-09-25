<?php
$root = __DIR__.'/../custom';
$files = [
    'actions.php' => file_get_contents($root.'/actions.php'),
    'edit.php' => file_get_contents($root.'/edit.php'),
    'modal.php' => file_get_contents($root.'/gestione_esercizio.php'),
    'service.php' => file_get_contents($root.'/src/Esercizio.php'),
    'workflow.php' => file_get_contents($root.'/src/Workflow.php'),
];
$all = implode("\n", $files);
$checks = [
    'Nessun DELETE distruttivo co_movimenti' => !preg_match('/DELETE\s+FROM\s+co_movimenti/i', $all),
    'Nessuna transazione PDO manuale' => !str_contains($all, 'getPDO()') && !str_contains($all, 'beginTransaction()'),
    'Transazione Laravel invariata' => str_contains($files['service.php'], 'getCapsule()->getConnection()') && str_contains($files['service.php'], '->transaction('),
    'HAVING storico invariato' => substr_count($files['service.php'], 'HAVING SUM(m.totale)<>0') === 2,
    'Schema OSM 2.10.4' => str_contains($files['service.php'], 'co_pianodeiconti3') && str_contains($files['service.php'], 'idconto') && !str_contains($files['service.php'], 'co_piano_dei_conti3'),
    'A1 lettura bloccante esistenza' => str_contains($files['service.php'], 'assertOperationAbsentForUpdate') && str_contains($files['service.php'], 'ORDER BY id FOR UPDATE'),
    'A1 numerazione con lock' => str_contains($files['service.php'], 'ORDER BY idmastrino DESC LIMIT 1 FOR UPDATE'),
    'A2 periodo inviato' => str_contains($files['modal.php'], 'data-period_start=') && str_contains($files['modal.php'], 'data-period_end='),
    'A2 fingerprint inviato' => str_contains($files['modal.php'], 'data-fingerprint=') && str_contains($files['actions.php'], "post('fingerprint')"),
    'A2 ricalcolo e hash_equals' => str_contains($files['actions.php'], 'hash_equals') && str_contains($files['service.php'], 'hash_equals'),
    'A3 avviso conto tecnico cambiato' => str_contains($files['service.php'], 'Il conto tecnico configurato è cambiato'),
    'A4 apertura non necessaria' => str_contains($files['workflow.php'], "'not_required'") && str_contains($files['workflow.php'], 'Non necessaria'),
    'A5 nessun Modules::link errato in modale' => !str_contains($files['modal.php'], 'Modules::link('),
    'A5 link manuale con permesso Prima nota' => str_contains($files['modal.php'], "where('name', 'Prima nota')") && str_contains($files['modal.php'], 'editor.php?id_module='),
    'A6 permesso esplicito Piano dei conti' => str_contains($files['modal.php'], "where('name', 'Piano dei conti')") && str_contains($files['modal.php'], 'http_response_code(403)'),
    'A7 confronto Registrato Atteso Differenza' => str_contains($files['modal.php'], "tr('Registrato')") && str_contains($files['modal.php'], "tr('Atteso oggi')") && str_contains($files['modal.php'], "tr('Differenza')"),
    'A8 try catch modale' => str_contains($files['modal.php'], 'try {') && str_contains($files['modal.php'], 'catch (Throwable $e)'),
    'A8 validazione date' => str_contains($files['service.php'], 'createFromFormat') && str_contains($files['service.php'], 'assertValidPeriod'),
    'A9 cache istanza' => str_contains($files['service.php'], 'openingBalancesCache') && str_contains($files['service.php'], 'existingCache'),
    'Success link actions preservato' => str_contains($files['actions.php'], "Modules::link('Prima nota'"),
    'Nessuna classe CSS come testo alternativo' => !preg_match('/Modules::link\([^\)]*btn btn-sm/', $files['modal.php']),
];
$failed = 0;
foreach ($checks as $name => $ok) {
    echo ($ok ? 'PASS ' : 'FAIL ').$name."\n";
    if (!$ok) $failed++;
}
echo 'SUMMARY '.(count($checks)-$failed).'/'.count($checks)." PASS\n";
exit($failed ? 1 : 0);
