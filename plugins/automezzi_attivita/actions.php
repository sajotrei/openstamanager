<?php

if (post('op') !== 'save') {
    return;
}

$response = [
    'ok' => false,
    'updated' => 0,
];

try {
    $id_record = (int) post('id_record');
    $automezzi = (array) post('automezzi');

    if ($id_record <= 0) {
        throw new RuntimeException('Attività non valida.');
    }

    if (!$dbo->tableExists('zz_automezzi_attivita_sessioni')) {
        throw new RuntimeException('Plugin non inizializzato.');
    }

    if (empty($automezzi)) {
        echo json_encode([
            'ok' => true,
            'updated' => 0,
        ]);
        return;
    }

    $sessioni = $dbo->fetchArray(
        'SELECT id FROM in_interventi_tecnici WHERE idintervento = '.prepare($id_record)
    );
    $session_ids = array_map('intval', array_column($sessioni, 'id'));

    $valid_automezzi = $dbo->fetchArray(
        'SELECT id FROM an_sedi WHERE is_automezzo = 1'
    );
    $valid_ids = array_map('intval', array_column($valid_automezzi, 'id'));

    $upsert = [];
    $remove = [];

    foreach ($automezzi as $id_sessione => $id_automezzo) {
        $id_sessione = (int) $id_sessione;

        if (!in_array($id_sessione, $session_ids, true)) {
            continue;
        }

        if ($id_automezzo === '' || $id_automezzo === null) {
            $remove[] = $id_sessione;
            continue;
        }

        $id_automezzo = (int) $id_automezzo;

        if (!in_array($id_automezzo, $valid_ids, true)) {
            continue;
        }

        $upsert[$id_sessione] = $id_automezzo;
    }

    if (!empty($upsert)) {
        $values = [];

        foreach ($upsert as $id_sessione => $id_automezzo) {
            $values[] = '('.(int) $id_sessione.', '.(int) $id_automezzo.')';
        }

        $dbo->query(
            'INSERT INTO zz_automezzi_attivita_sessioni (id_sessione, id_automezzo) VALUES '.implode(', ', $values).' ON DUPLICATE KEY UPDATE id_automezzo = VALUES(id_automezzo), updated_at = CURRENT_TIMESTAMP'
        );

        $response['updated'] += count($upsert);
    }

    if (!empty($remove)) {
        $dbo->query(
            'DELETE FROM zz_automezzi_attivita_sessioni WHERE id_sessione IN ('.implode(',', array_map('intval', $remove)).')'
        );

        $response['updated'] += count($remove);
    }

    $dbo->query(
        'DELETE rel FROM zz_automezzi_attivita_sessioni AS rel LEFT JOIN in_interventi_tecnici AS it ON it.id = rel.id_sessione WHERE it.id IS NULL'
    );

    $response['ok'] = true;
} catch (Throwable $e) {
    http_response_code(400);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
