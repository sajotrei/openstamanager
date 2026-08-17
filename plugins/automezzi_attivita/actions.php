<?php
switch (post('op')) {
case 'save_automezzo_sessione':
    $response = ['ok' => false];
    try {
        $table = 'zz_automezzi_attivita_sessioni';
        $id_sessione = (int)post('id_sessione');
        $id_automezzo_raw = post('id_automezzo');
        $id_automezzo = ($id_automezzo_raw === '' || $id_automezzo_raw === null) ? null : (int)$id_automezzo_raw;
        $valid_session = $dbo->fetchOne('SELECT id FROM in_interventi_tecnici WHERE id='.prepare($id_sessione).' AND idintervento='.prepare($id_record));
        if (empty($valid_session)) throw new RuntimeException('Sessione non valida.');
        if ($id_automezzo !== null) {
            $valid_vehicle = $dbo->fetchOne('SELECT id FROM an_sedi WHERE id='.prepare($id_automezzo).' AND is_automezzo=1');
            if (empty($valid_vehicle)) throw new RuntimeException('Automezzo non valido.');
            $dbo->query('INSERT INTO `'.$table.'` (`id_sessione`,`id_automezzo`) VALUES ('.prepare($id_sessione).','.prepare($id_automezzo).') ON DUPLICATE KEY UPDATE `id_automezzo`=VALUES(`id_automezzo`), `updated_at`=CURRENT_TIMESTAMP');
        } else {
            $dbo->delete($table, ['id_sessione' => $id_sessione]);
        }
        $response['ok'] = true;
    } catch (Throwable $e) {
        http_response_code(400); $response['message'] = $e->getMessage();
    }
    echo json_encode($response);
    break;
}
