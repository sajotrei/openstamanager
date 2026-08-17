<?php

include_once __DIR__.'/../../core.php';

$table = 'zz_automezzi_attivita_sessioni';

if (!$dbo->tableExists($table)) {
    $dbo->query("CREATE TABLE `{$table}` (
        `id_sessione` INT NOT NULL,
        `id_automezzo` INT NULL,
        `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id_sessione`),
        INDEX `idx_automezzo` (`id_automezzo`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

$sessioni = $dbo->fetchArray(
    'SELECT it.id, it.idtecnico, rel.id_automezzo, it.orario_inizio, it.orario_fine, it.km, a.ragione_sociale, COALESCE(tl.title, \'\') AS tipo
    FROM in_interventi_tecnici AS it
    INNER JOIN an_anagrafiche AS a ON a.idanagrafica = it.idtecnico
    LEFT JOIN in_tipiintervento AS t ON t.id = it.idtipointervento
    LEFT JOIN in_tipiintervento_lang AS tl ON tl.id_record = t.id AND tl.id_lang = '.prepare(Models\Locale::getDefault()->id).'
    LEFT JOIN `'.$table.'` AS rel ON rel.id_sessione = it.id
    WHERE it.idintervento = '.prepare($id_record).'
    ORDER BY it.orario_inizio ASC, a.ragione_sociale ASC'
);

$automezzi = $dbo->fetchArray(
    'SELECT id, nomesede, targa, nome FROM an_sedi WHERE is_automezzo = 1 ORDER BY nomesede ASC'
);

$tecnici = array_values(array_unique(array_filter(array_column($sessioni, 'idtecnico'))));
$assegnazioni = [];

if (!empty($tecnici)) {
    $rows = $dbo->fetchArray(
        'SELECT DISTINCT u.idanagrafica AS idtecnico, s.id AS idautomezzo
        FROM zz_users AS u
        INNER JOIN zz_user_sedi AS us ON us.id_user = u.id
        INNER JOIN an_sedi AS s ON s.id = us.idsede AND s.is_automezzo = 1
        WHERE u.idanagrafica IN ('.implode(',', array_map('intval', $tecnici)).')'
    );

    foreach ($rows as $row) {
        $assegnazioni[(int) $row['idtecnico']][] = (int) $row['idautomezzo'];
    }
}

if (empty($sessioni)) {
    echo '<div class="alert alert-info"><i class="fa fa-info-circle"></i> '.tr('Nessuna sessione presente nell\'attività.').'</div>';
    return;
}

$assegnate = 0;
$proposte = 0;
$manuali = 0;

foreach ($sessioni as $sessione) {
    $assigned = array_values(array_unique($assegnazioni[(int) $sessione['idtecnico']] ?? []));

    if (!empty($sessione['id_automezzo'])) {
        ++$assegnate;
    } elseif (count($assigned) === 1) {
        ++$proposte;
    } elseif (count($assigned) > 1) {
        ++$manuali;
    }
}

echo '<div class="card card-primary">
    <div class="card-header"><h3 class="card-title"><i class="fa fa-car"></i> '.tr('Automezzi attività').'</h3></div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-3"><strong>'.tr('Sessioni').':</strong> '.count($sessioni).'</div>
            <div class="col-md-3"><strong>'.tr('Assegnate').':</strong> '.$assegnate.'</div>
            <div class="col-md-3"><strong>'.tr('Proposte').':</strong> '.$proposte.'</div>
            <div class="col-md-3"><strong>'.tr('Scelta manuale').':</strong> '.$manuali.'</div>
        </div>
        <div class="alert alert-light border mb-3">'.tr('Il mezzo viene proposto quando l\'operatore ha un solo automezzo associato. La proposta non viene salvata automaticamente: puoi confermarla o modificarla.').'</div>
        <div class="table-responsive">
            <table class="table table-sm table-striped table-hover">
                <thead><tr>
                    <th>'.tr('Operatore').'</th>
                    <th>'.tr('Sessione').'</th>
                    <th width="180">'.tr('Data').'</th>
                    <th width="90">'.tr('Km').'</th>
                    <th width="320">'.tr('Automezzo').'</th>
                </tr></thead><tbody>';

foreach ($sessioni as $sessione) {
    $current = !empty($sessione['id_automezzo']) ? (int) $sessione['id_automezzo'] : null;
    $assigned = array_values(array_unique($assegnazioni[(int) $sessione['idtecnico']] ?? []));
    $suggested = (!$current && count($assigned) === 1) ? $assigned[0] : null;
    $selected = $current ?: $suggested;

    echo '<tr>
        <td>'.htmlspecialchars((string) $sessione['ragione_sociale'], ENT_QUOTES, 'UTF-8').'</td>
        <td>'.htmlspecialchars((string) $sessione['tipo'], ENT_QUOTES, 'UTF-8').'</td>
        <td>'.Translator::timestampToLocale($sessione['orario_inizio']).'</td>
        <td class="text-right">'.Translator::numberToLocale($sessione['km']).'</td>
        <td>
            <select class="form-control form-control-sm osm-automezzo" data-sessione="'.(int) $sessione['id'].'" data-original="'.($current ?: '').'">
                <option value="">'.tr('Nessun automezzo').'</option>';

    foreach ($automezzi as $automezzo) {
        $label = trim(($automezzo['targa'] ? $automezzo['targa'].' - ' : '').($automezzo['nome'] ?: $automezzo['nomesede']));
        echo '<option value="'.(int) $automezzo['id'].'"'.((int) $selected === (int) $automezzo['id'] ? ' selected' : '').'>'.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'</option>';
    }

    echo '</select>';

    if ($suggested) {
        echo '<small class="text-muted"><i class="fa fa-magic"></i> '.tr('Proposto in base all\'operatore').'</small>';
    } elseif (!$current && count($assigned) > 1) {
        echo '<small class="text-warning"><i class="fa fa-info-circle"></i> '.tr('Più mezzi associati: selezione manuale').'</small>';
    }

    echo '</td></tr>';
}

$msg_title_ok = json_encode(tr('Salvataggio completato'));
$msg_ok = json_encode(tr('Automezzi associati alle sessioni.'));
$msg_no_changes = json_encode(tr('Nessuna modifica da salvare.'));
$msg_error_title = json_encode(tr('Errore'));
$msg_error = json_encode(tr('Salvataggio non riuscito'));

echo '</tbody></table></div>
        <div class="text-right">
            <button type="button" class="btn btn-primary" id="save-automezzi-attivita"><i class="fa fa-save"></i> '.tr('Salva automezzi').'</button>
        </div>
    </div>
</div>';
?>
<script>
$(document).ready(function () {
    $('#save-automezzi-attivita').on('click', function () {
        var button = $(this);
        var changed = {};

        $('.osm-automezzo').each(function () {
            var field = $(this);
            var current = field.val() || '';
            var original = String(field.data('original') || '');

            if (current !== original) {
                changed[field.data('sessione')] = current;
            }
        });

        if (Object.keys(changed).length === 0) {
            Swal.fire(<?php echo $msg_title_ok; ?>, <?php echo $msg_no_changes; ?>, 'info');
            return;
        }

        button.prop('disabled', true);

        $.ajax({
            url: globals.rootdir + '/actions.php',
            type: 'POST',
            dataType: 'json',
            data: {
                id_module: globals.id_module,
                id_plugin: <?php echo (int) $id_plugin; ?>,
                id_record: globals.id_record,
                op: 'save',
                automezzi: changed
            },
            success: function (response) {
                if (response && response.ok) {
                    $.each(changed, function (id, value) {
                        $('.osm-automezzo[data-sessione="' + id + '"]').data('original', value);
                    });

                    Swal.fire(<?php echo $msg_title_ok; ?>, response.updated > 0 ? <?php echo $msg_ok; ?> : <?php echo $msg_no_changes; ?>, 'success');
                } else {
                    Swal.fire(<?php echo $msg_error_title; ?>, response && response.message ? response.message : <?php echo $msg_error; ?>, 'error');
                }
            },
            error: function () {
                Swal.fire(<?php echo $msg_error_title; ?>, <?php echo $msg_error; ?>, 'error');
            },
            complete: function () {
                button.prop('disabled', false);
            }
        });
    });
});
</script>
