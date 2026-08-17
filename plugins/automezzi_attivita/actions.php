<?php

switch (post('op')) {
    case 'save_automezzi_attivita':
        $response = ['ok' => false, 'updated' => 0];

        try {
            $id_intervento = (int) $id_record;
            $automezzi = (array) post('automezzi');
            $table = 'zz_automezzi_attivita_sessioni';

            if ($id_intervento <= 0) {
                throw new RuntimeException('Attività non valida.');
            }

            if (!$dbo->tableExists($table)) {
                throw new RuntimeException('Plugin non inizializzato.');
            }

            if (empty($automezzi)) {
                $response['ok'] = true;
                echo json_encode($response);
                break;
            }

            $sessioni = $dbo->fetchArray(
                'SELECT id FROM in_interventi_tecnici WHERE idintervento = '.prepare($id_intervento)
            );
            $session_ids = array_map('intval', array_column($sessioni, 'id'));

            $valid_automezzi = $dbo->fetchArray('SELECT id FROM an_sedi WHERE is_automezzo = 1');
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
                    'INSERT INTO `'.$table.'` (`id_sessione`, `id_automezzo`) VALUES '.implode(', ', $values).'
                    ON DUPLICATE KEY UPDATE
                        `id_automezzo` = VALUES(`id_automezzo`),
                        `updated_at` = CURRENT_TIMESTAMP'
                );

                $response['updated'] += count($upsert);
            }

            if (!empty($remove)) {
                $dbo->query(
                    'DELETE FROM `'.$table.'` WHERE `id_sessione` IN ('.implode(',', array_map('intval', $remove)).')'
                );
                $response['updated'] += count($remove);
            }

            $response['ok'] = true;
        } catch (Throwable $e) {
            http_response_code(400);
            $response['message'] = $e->getMessage();
        }

        echo json_encode($response);
        break;
}
