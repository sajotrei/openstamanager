<?php

include_once __DIR__.'/../../core.php';

header('Content-Type: application/json; charset=utf-8');

$response = ['ok' => false];

try {
    if (post('op') !== 'save') {
        throw new RuntimeException('Operazione non valida.');
    }

    $id_record = (int) post('id_record');
    $automezzi = (array) post('automezzi');

    if ($id_record <= 0) {
        throw new RuntimeException('Attività non valida.');
    }

    $sessioni = $dbo->fetchArray('SELECT id FROM in_interventi_tecnici WHERE idintervento = '.prepare($id_record));
    $session_ids = array_map('intval', array_column($sessioni, 'id'));

    if (empty($session_ids)) {
        throw new RuntimeException('Nessuna sessione disponibile.');
    }

    $valid_automezzi = $dbo->fetchArray('SELECT id FROM an_sedi WHERE is_automezzo = 1');
    $valid_ids = array_map('intval', array_column($valid_automezzi, 'id'));

    $database->beginTransaction();

    foreach ($automezzi as $id_sessione => $id_automezzo) {
        $id_sessione = (int) $id_sessione;
        $id_automezzo = $id_automezzo !== '' && $id_automezzo !== null ? (int) $id_automezzo : null;

        // Una sessione può essere modificata solo se appartiene all'attività corrente.
        if (!in_array($id_sessione, $session_ids, true)) {
            continue;
        }

        // Impedisce di salvare sedi normali o ID non esistenti come automezzi.
        if ($id_automezzo !== null && !in_array($id_automezzo, $valid_ids, true)) {
            continue;
        }

        $dbo->update('in_interventi_tecnici', [
            'idautomezzo' => $id_automezzo,
        ], [
            'id' => $id_sessione,
            'idintervento' => $id_record,
        ]);
    }

    $database->commitTransaction();

    $response = ['ok' => true];
} catch (Throwable $e) {
    if (isset($database)) {
        try {
            $database->rollbackTransaction();
        } catch (Throwable) {
        }
    }

    http_response_code(400);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
