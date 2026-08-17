<?php

include_once __DIR__.'/../../core.php';

use Models\Plugin;

$table = 'zz_automezzi_attivita_sessioni';

$dbo->query(
    'UPDATE `zz_plugins` SET `enabled` = 0
    WHERE `id` != '.prepare($id_plugin).'
    AND `directory` = '.prepare('automezzi_attivita')
);

$dbo->update('zz_plugins', [
    'name' => 'Automezzi attività',
    'version' => '1.3.0',
    'options' => 'custom',
    'position' => 'tab',
], [
    'id' => $id_plugin,
]);

$dbo->update('zz_plugins_lang', [
    'title' => 'Automezzi attività',
], [
    'id_record' => $id_plugin,
    'id_lang' => Models\Locale::getDefault()->id,
]);

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
    'SELECT
        it.id,
        it.idtecnico,
        rel.id_automezzo,
        it.orario_inizio,
        it.orario_fine,
        it.km,
        a.ragione_sociale,
        COALESCE(tl.title, \'\') AS tipo
    FROM in_interventi_tecnici AS it
    INNER JOIN an_anagrafiche AS a ON a.idanagrafica = it.idtecnico
    LEFT JOIN in_tipiintervento AS t ON t.id = it.idtipointervento
    LEFT JOIN in_tipiintervento_lang AS tl
        ON tl.id_record = t.id
        AND tl.id_lang = '.prepare(Models\Locale::getDefault()->id).'
    LEFT JOIN `'.$table.'` AS rel ON rel.id_sessione = it.id
    WHERE it.idintervento = '.prepare($id_record).'
    ORDER BY it.orario_inizio ASC, a.ragione_sociale ASC'
);

if (empty($sessioni)) {
    echo '<div class="alert alert-info"><i class="fa fa-info-circle"></i> '
        .tr('Nessuna sessione presente nell\'attività. Aggiungi prima un Operatore/sessione.')
        .'</div>';
    return;
}

$automezzi = $dbo->fetchArray(
    'SELECT id, nomesede, targa, nome
    FROM an_sedi
    WHERE is_automezzo = 1
    ORDER BY nomesede ASC'
);

$tecnici = array_values(array_unique(array_filter(array_column($sessioni, 'idtecnico'))));
$assegnazioni = [];

if (!empty($tecnici)) {
    $rows = $dbo->fetchArray(
        'SELECT DISTINCT
            u.idanagrafica AS idtecnico,
            s.id AS idautomezzo
        FROM zz_users AS u
        INNER JOIN zz_user_sedi AS us ON us.id_user = u.id
        INNER JOIN an_sedi AS s ON s.id = us.idsede AND s.is_automezzo = 1
        WHERE u.idanagrafica IN ('.implode(',', array_map('intval', $tecnici)).')'
    );

    foreach ($rows as $row) {
        $assegnazioni[(int) $row['idtecnico']][] = (int) $row['idautomezzo'];
    }
}

$assegnate = $proposte = $manuali = 0;
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
?>
<div class="row">
    <div class="col-md-12">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-car"></i> <?php echo tr('Automezzi attività'); ?></h3>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3"><strong><?php echo tr('Sessioni'); ?>:</strong> <?php echo count($sessioni); ?></div>
                    <div class="col-md-3"><strong><?php echo tr('Assegnate'); ?>:</strong> <?php echo $assegnate; ?></div>
                    <div class="col-md-3"><strong><?php echo tr('Proposte'); ?>:</strong> <?php echo $proposte; ?></div>
                    <div class="col-md-3"><strong><?php echo tr('Scelta manuale'); ?>:</strong> <?php echo $manuali; ?></div>
                </div>

                <div class="alert alert-light border">
                    <i class="fa fa-info-circle"></i>
                    <?php echo tr('Se l\'Operatore ha un solo Automezzo associato, il mezzo viene proposto automaticamente. La proposta viene registrata solo dopo il salvataggio.'); ?>
                </div>

                <?php if (empty($automezzi)) { ?>
                    <div class="alert alert-warning">
                        <i class="fa fa-warning"></i>
                        <?php echo tr('Non sono presenti Automezzi configurati nel modulo Automezzi.'); ?>
                    </div>
                <?php } ?>

                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover">
                        <thead>
                            <tr>
                                <th><?php echo tr('Operatore'); ?></th>
                                <th><?php echo tr('Tipo attività'); ?></th>
                                <th width="180"><?php echo tr('Inizio'); ?></th>
                                <th width="90"><?php echo tr('Km'); ?></th>
                                <th width="340"><?php echo tr('Automezzo'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($sessioni as $sessione) {
                            $current = !empty($sessione['id_automezzo']) ? (int) $sessione['id_automezzo'] : null;
                            $assigned = array_values(array_unique($assegnazioni[(int) $sessione['idtecnico']] ?? []));
                            $suggested = (!$current && count($assigned) === 1) ? $assigned[0] : null;
                            $selected = $current ?: $suggested;
                            $tipo = trim(strip_tags(html_entity_decode((string) $sessione['tipo'], ENT_QUOTES, 'UTF-8')));
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string) $sessione['ragione_sociale'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo Translator::timestampToLocale($sessione['orario_inizio']); ?></td>
                                <td class="text-right"><?php echo Translator::numberToLocale($sessione['km']); ?></td>
                                <td>
                                    <select class="form-control form-control-sm osm-automezzo"
                                            data-sessione="<?php echo (int) $sessione['id']; ?>"
                                            data-original="<?php echo $current ?: ''; ?>">
                                        <option value=""><?php echo tr('Nessun automezzo'); ?></option>
                                        <?php foreach ($automezzi as $automezzo) {
                                            $label = trim(
                                                ($automezzo['targa'] ? $automezzo['targa'].' - ' : '').
                                                ($automezzo['nome'] ?: $automezzo['nomesede'])
                                            );
                                        ?>
                                            <option value="<?php echo (int) $automezzo['id']; ?>"<?php echo ((int) $selected === (int) $automezzo['id']) ? ' selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php } ?>
                                    </select>
                                    <?php if ($suggested) { ?>
                                        <small class="text-muted"><i class="fa fa-magic"></i> <?php echo tr('Proposto in base all\'Operatore'); ?></small>
                                    <?php } elseif (!$current && count($assigned) > 1) { ?>
                                        <small class="text-warning"><i class="fa fa-info-circle"></i> <?php echo tr('Più Automezzi associati: scegli manualmente'); ?></small>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>

                <div class="text-right">
                    <button type="button" class="btn btn-primary" id="save-automezzi-attivita">
                        <i class="fa fa-save"></i> <?php echo tr('Salva automezzi'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

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
            Swal.fire('<?php echo addslashes(tr('Informazione')); ?>', '<?php echo addslashes(tr('Nessuna modifica da salvare.')); ?>', 'info');
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
                op: 'save_automezzi_attivita',
                automezzi: changed
            },
            success: function (response) {
                if (response && response.ok) {
                    $.each(changed, function (id, value) {
                        $('.osm-automezzo[data-sessione="' + id + '"]').data('original', value);
                    });
                    Swal.fire('<?php echo addslashes(tr('Salvataggio completato')); ?>', '<?php echo addslashes(tr('Automezzi associati alle sessioni.')); ?>', 'success');
                } else {
                    Swal.fire('<?php echo addslashes(tr('Errore')); ?>', response && response.message ? response.message : '<?php echo addslashes(tr('Salvataggio non riuscito')); ?>', 'error');
                }
            },
            error: function () {
                Swal.fire('<?php echo addslashes(tr('Errore')); ?>', '<?php echo addslashes(tr('Salvataggio non riuscito')); ?>', 'error');
            },
            complete: function () {
                button.prop('disabled', false);
            }
        });
    });
});
</script>
