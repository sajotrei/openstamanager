<?php

if (post('op') !== 'save') {
    return;
}

$response = ['ok' => false];

try {
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

    foreach ($automezzi as $id_sessione => $id_automezzo) {
        $id_sessione = (int) $id_sessione;
        $id_automezzo = $id_automezzo !== '' && $id_automezzo !== null ? (int) $id_automezzo : null;

        // La sessione deve appartenere all'attività corrente.
        if (!in_array($id_sessione, $session_ids, true)) {
            continue;
        }

        // Sono accettati solo automezzi reali del modulo Automezzi.
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

    $response = ['ok' => true];
} catch (Throwable $e) {
    http_response_code(400);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
