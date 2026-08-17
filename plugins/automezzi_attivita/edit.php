<?php

include_once __DIR__.'/../../core.php';

// Migrazione idempotente: eseguita una sola volta alla prima apertura del plugin.
$column = $dbo->fetchArray("SHOW COLUMNS FROM `in_interventi_tecnici` LIKE 'idautomezzo'");
if (empty($column)) {
    $dbo->query('ALTER TABLE `in_interventi_tecnici` ADD `idautomezzo` INT NULL AFTER `idtecnico`, ADD INDEX `idx_in_interventi_tecnici_idautomezzo` (`idautomezzo`)');
}

$sessioni = $dbo->fetchArray('SELECT it.id, it.idtecnico, it.idautomezzo, it.orario_inizio, it.orario_fine, it.km, a.ragione_sociale, COALESCE(tl.title, t.descrizione) AS tipo FROM in_interventi_tecnici AS it INNER JOIN an_anagrafiche AS a ON a.idanagrafica = it.idtecnico LEFT JOIN in_tipiintervento AS t ON t.id = it.idtipointervento LEFT JOIN in_tipiintervento_lang AS tl ON tl.id_record = t.id AND tl.id_lang = '.prepare(Models\Locale::getDefault()->id).' WHERE it.idintervento = '.prepare($id_record).' ORDER BY it.orario_inizio ASC, a.ragione_sociale ASC');

$automezzi = $dbo->fetchArray('SELECT id, nome_sede, targa, nome FROM an_sedi WHERE is_automezzo = 1 ORDER BY nome_sede ASC');
$automezzi_by_id = [];
foreach ($automezzi as $automezzo) {
    $automezzi_by_id[$automezzo['id']] = $automezzo;
}

$tecnici = array_values(array_unique(array_filter(array_column($sessioni, 'idtecnico'))));
$assegnazioni = [];
if (!empty($tecnici)) {
    $rows = $dbo->fetchArray('SELECT u.idanagrafica AS idtecnico, s.id AS idautomezzo FROM zz_users AS u INNER JOIN zz_user_sedi AS us ON us.id_user = u.id INNER JOIN an_sedi AS s ON s.id = us.id_sede AND s.is_automezzo = 1 WHERE u.idanagrafica IN ('.implode(',', array_map('intval', $tecnici)).')');
    foreach ($rows as $row) {
        $assegnazioni[$row['idtecnico']][] = (int) $row['idautomezzo'];
    }
}

if (empty($sessioni)) {
    echo '<div class="alert alert-info"><i class="fa fa-info-circle"></i> '.tr('Nessuna sessione presente nell\'attività.').'</div>';
    return;
}

echo '<div class="card card-primary">
    <div class="card-header"><h3 class="card-title"><i class="fa fa-car"></i> '.tr('Automezzi attività').'</h3></div>
    <div class="card-body">
        <div class="alert alert-light border mb-3">'.tr('Il mezzo viene proposto quando l\'operatore ha un solo automezzo associato. La scelta resta sempre modificabile.').'</div>
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
    $current = $sessione['idautomezzo'] ? (int) $sessione['idautomezzo'] : null;
    $assigned = array_values(array_unique($assegnazioni[$sessione['idtecnico']] ?? []));
    $suggested = (!$current && count($assigned) === 1) ? $assigned[0] : null;
    $selected = $current ?: $suggested;

    echo '<tr>
        <td>'.htmlentities((string) $sessione['ragione_sociale']).'</td>
        <td>'.htmlentities((string) $sessione['tipo']).'</td>
        <td>'.Translator::timestampToLocale($sessione['orario_inizio']).'</td>
        <td class="text-right">'.Translator::numberToLocale($sessione['km']).'</td>
        <td>
            <select class="form-control form-control-sm osm-automezzo" data-sessione="'.(int) $sessione['id'].'">
                <option value="">'.tr('Nessun automezzo').'</option>';

    foreach ($automezzi as $automezzo) {
        $label = trim(($automezzo['targa'] ? $automezzo['targa'].' - ' : '').($automezzo['nome'] ?: $automezzo['nome_sede']));
        echo '<option value="'.(int) $automezzo['id'].'"'.((int) $selected === (int) $automezzo['id'] ? ' selected' : '').'>'.htmlentities($label).'</option>';
    }

    echo '</select>';
    if ($suggested) {
        echo '<small class="text-muted"><i class="fa fa-magic"></i> '.tr('Proposto in base all\'operatore').'</small>';
    } elseif (!$current && count($assigned) > 1) {
        echo '<small class="text-warning"><i class="fa fa-info-circle"></i> '.tr('Più mezzi associati: selezione manuale').'</small>';
    }
    echo '</td></tr>';
}

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
        var rows = {};
        $('.osm-automezzo').each(function () {
            rows[$(this).data('sessione')] = $(this).val();
        });

        button.prop('disabled', true);
        $.ajax({
            url: globals.rootdir + '/plugins/automezzi_attivita/actions.php',
            type: 'POST',
            dataType: 'json',
            data: {
                op: 'save',
                id_record: globals.id_record,
                automezzi: rows
            },
            success: function (response) {
                if (response && response.ok) {
                    Swal.fire(tr('Salvataggio completato'), tr('Automezzi associati alle sessioni.'), 'success');
                } else {
                    Swal.fire(tr('Errore'), response && response.message ? response.message : tr('Salvataggio non riuscito'), 'error');
                }
            },
            error: function () {
                Swal.fire(tr('Errore'), tr('Salvataggio non riuscito'), 'error');
            },
            complete: function () {
                button.prop('disabled', false);
            }
        });
    });
});
</script>
