<?php

include_once __DIR__.'/../../core.php';

use Models\Module;

$table = 'zz_automezzi_attivita_sessioni';

if (!$dbo->tableExists($table)) {
    echo '<div class="alert alert-info"><i class="fa fa-info-circle"></i> '
        .tr('Nessun utilizzo attività registrato per gli Automezzi.')
        .'</div>';
    return;
}

$id_modulo_interventi = Module::where('name', 'Interventi')->first()->id;

$riepilogo = $dbo->fetchOne(
    'SELECT
        COUNT(*) AS sessioni,
        COALESCE(SUM(it.km), 0) AS km_totali,
        MAX(it.orario_inizio) AS ultimo_utilizzo
    FROM `'.$table.'` AS rel
    INNER JOIN in_interventi_tecnici AS it ON it.id = rel.id_sessione
    WHERE rel.id_automezzo = '.prepare($id_record)
);

$utilizzi = $dbo->fetchArray(
    'SELECT
        it.id,
        it.idintervento,
        it.orario_inizio,
        it.orario_fine,
        it.km,
        tecnico.ragione_sociale AS operatore,
        cliente.ragione_sociale AS cliente,
        i.codice,
        COALESCE(tl.title, \'\') AS tipo
    FROM `'.$table.'` AS rel
    INNER JOIN in_interventi_tecnici AS it ON it.id = rel.id_sessione
    INNER JOIN in_interventi AS i ON i.id = it.idintervento
    INNER JOIN an_anagrafiche AS tecnico ON tecnico.idanagrafica = it.idtecnico
    INNER JOIN an_anagrafiche AS cliente ON cliente.idanagrafica = i.idanagrafica
    LEFT JOIN in_tipiintervento AS t ON t.id = it.idtipointervento
    LEFT JOIN in_tipiintervento_lang AS tl
        ON tl.id_record = t.id
        AND tl.id_lang = '.prepare(Models\Locale::getDefault()->id).'
    WHERE rel.id_automezzo = '.prepare($id_record).'
    ORDER BY it.orario_inizio DESC
    LIMIT 100'
);
?>
<div class="row">
    <div class="col-md-12">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-history"></i> <?php echo tr('Utilizzi attività'); ?></h3>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong><?php echo tr('Sessioni registrate'); ?>:</strong>
                        <?php echo (int) ($riepilogo['sessioni'] ?? 0); ?>
                    </div>
                    <div class="col-md-4">
                        <strong><?php echo tr('Km attività'); ?>:</strong>
                        <?php echo Translator::numberToLocale($riepilogo['km_totali'] ?? 0); ?>
                    </div>
                    <div class="col-md-4">
                        <strong><?php echo tr('Ultimo utilizzo'); ?>:</strong>
                        <?php echo !empty($riepilogo['ultimo_utilizzo']) ? Translator::timestampToLocale($riepilogo['ultimo_utilizzo']) : '-'; ?>
                    </div>
                </div>

                <?php if (empty($utilizzi)) { ?>
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i>
                        <?php echo tr('Nessuna sessione attività associata a questo Automezzo.'); ?>
                    </div>
                <?php } else { ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped table-hover">
                            <thead>
                                <tr>
                                    <th width="160"><?php echo tr('Data'); ?></th>
                                    <th><?php echo tr('Operatore'); ?></th>
                                    <th><?php echo tr('Cliente'); ?></th>
                                    <th><?php echo tr('Attività'); ?></th>
                                    <th><?php echo tr('Tipo'); ?></th>
                                    <th width="90"><?php echo tr('Km'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($utilizzi as $utilizzo) {
                                $tipo = trim(strip_tags(html_entity_decode((string) $utilizzo['tipo'], ENT_QUOTES, 'UTF-8')));
                            ?>
                                <tr>
                                    <td><?php echo Translator::timestampToLocale($utilizzo['orario_inizio']); ?></td>
                                    <td><?php echo htmlspecialchars((string) $utilizzo['operatore'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) $utilizzo['cliente'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <a href="<?php echo base_path_osm(); ?>/editor.php?id_module=<?php echo (int) $id_modulo_interventi; ?>&id_record=<?php echo (int) $utilizzo['idintervento']; ?>">
                                            <?php echo htmlspecialchars((string) $utilizzo['codice'], ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="text-right"><?php echo Translator::numberToLocale($utilizzo['km']); ?></td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <small class="text-muted"><?php echo tr('Visualizzati gli ultimi 100 utilizzi.'); ?></small>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
