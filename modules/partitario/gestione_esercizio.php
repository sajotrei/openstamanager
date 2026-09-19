<?php

/*
 * OpenSTAManager: il software gestionale open source per l'assistenza tecnica e la fatturazione
 * Copyright (C) DevCode s.r.l.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

include_once __DIR__.'/../../core.php';

use Modules\Partitario\Esercizio;

$esercizio = new Esercizio($_SESSION['period_start'], $_SESSION['period_end']);
$previews = [
    'apertura' => $esercizio->getPreview('apertura'),
    'chiusura' => $esercizio->getPreview('chiusura'),
];
$can_write = Permissions::check('rw', false);

?>
<div class="mb-3">
    <strong><?php echo tr('Gestione esercizio'); ?></strong>
    <span class="text-muted">
        <?php echo dateFormat($_SESSION['period_start']).' - '.dateFormat($_SESSION['period_end']); ?>
    </span>
</div>

<?php
foreach ($previews as $operation => $preview) {
    $title = $operation === 'apertura' ? tr('Apertura esercizio') : tr('Chiusura esercizio');
    $alreadyDone = $preview['existing']['present'];
    $status = $alreadyDone ? tr('Eseguita') : ($preview['errors'] ? tr('Bloccata') : tr('Da eseguire'));
    $statusClass = $alreadyDone ? 'badge-success' : ($preview['errors'] ? 'badge-danger' : 'badge-info');
    $detailId = 'exercise-'.$operation.'-details';
    $actionLabel = $operation === 'apertura' ? tr('Apri esercizio') : tr('Chiudi esercizio');
    $confirmLabel = $operation === 'apertura' ? tr('Conferma apertura') : tr('Conferma chiusura');
    $op = $operation === 'apertura' ? 'apri-bilancio' : 'chiudi-bilancio';
    $confirmMessage = '<div class="text-left"><strong>'.$title.'</strong><br>'.
        dateFormat($preview['period_start']).' - '.dateFormat($preview['period_end']).'<br><br>'.
        tr('Data registrazione').': <strong>'.dateFormat($preview['date']).'</strong><br>'.
        tr('Conti interessati').': <strong>'.$preview['accounts_count'].'</strong><br>'.
        tr('Totale Dare').': <strong>'.moneyFormat($preview['debit'], 2).'</strong><br>'.
        tr('Totale Avere').': <strong>'.moneyFormat($preview['credit'], 2).'</strong><br>'.
        tr('Pareggio').': <strong>'.(abs($preview['debit'] - $preview['credit']) < 0.000001 ? tr('Verificato') : tr('Non verificato')).'</strong><br><br>'.
        tr('Le scritture esistenti non saranno cancellate o rigenerate.').'</div>';
    ?>
    <div class="card card-outline card-secondary mb-3">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-5">
                    <strong><?php echo $title; ?></strong>
                    <span class="badge <?php echo $statusClass; ?> ml-2"><?php echo $status; ?></span>
                    <div class="small text-muted mt-1">
                        <?php echo $operation === 'apertura'
                            ? tr('Riporta i saldi patrimoniali dell\'esercizio precedente.')
                            : tr('Chiude i saldi patrimoniali alla fine dell\'esercizio.'); ?>
                    </div>
                </div>
                <div class="col-md-4 mt-2 mt-md-0">
                    <strong><?php echo $preview['accounts_count']; ?></strong> <?php echo tr('conti'); ?>
                    <span class="mx-1">·</span>
                    <strong><?php echo moneyFormat($preview['debit'], 2); ?></strong>
                    <?php if ($alreadyDone && $preview['existing']['mastrini']) { ?>
                        <div class="small">
                            <?php echo tr('Mastrino'); ?>:
                            <?php echo implode(', ', array_map(fn ($id) => '#'.$id, $preview['existing']['mastrini'])); ?>
                        </div>
                    <?php } ?>
                </div>
                <div class="col-md-3 text-md-right mt-2 mt-md-0">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="collapse" data-target="#<?php echo $detailId; ?>">
                        <i class="fa fa-search"></i> <?php echo tr('Dettagli'); ?>
                    </button>
                    <?php if ($alreadyDone && count($preview['existing']['mastrini']) === 1) { ?>
                        <?php echo Modules::link('Prima nota', (int) $preview['existing']['mastrini'][0], tr('Visualizza Mastrino'), 'btn btn-sm btn-info ml-1'); ?>
                    <?php } elseif ($preview['can_execute'] && $can_write) { ?>
                        <button type="button" class="btn btn-sm btn-primary ml-1"
                            data-op="<?php echo $op; ?>"
                            data-title="<?php echo $title; ?>"
                            data-backto="record-list"
                            data-msg="<?php echo htmlspecialchars($confirmMessage, ENT_QUOTES); ?>"
                            data-button="<?php echo $confirmLabel; ?>"
                            data-class="btn btn-primary"
                            onclick="message(this);">
                            <i class="fa fa-check"></i> <?php echo $actionLabel; ?>
                        </button>
                    <?php } ?>
                </div>
            </div>

            <?php if ($alreadyDone) { ?>
                <div class="small text-success mt-2">
                    <i class="fa fa-check-circle"></i>
                    <?php echo tr('Operazione già registrata. Nessuna scrittura verrà modificata.'); ?>
                </div>
            <?php } ?>

            <?php if ($preview['errors']) { ?>
                <div class="alert alert-danger py-2 mt-3 mb-0">
                    <?php foreach ($preview['errors'] as $error) { ?>
                        <div><?php echo $error; ?></div>
                    <?php } ?>
                </div>
            <?php } ?>

            <div class="collapse mt-3" id="<?php echo $detailId; ?>">
                <?php if ($preview['warnings']) { ?>
                    <div class="alert alert-info py-2">
                        <?php foreach ($preview['warnings'] as $warning) { ?>
                            <div><?php echo $warning; ?></div>
                        <?php } ?>
                    </div>
                <?php } ?>

                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th><?php echo tr('Conto'); ?></th>
                                <th class="text-right"><?php echo tr('Saldo origine'); ?></th>
                                <th class="text-right"><?php echo tr('Dare'); ?></th>
                                <th class="text-right"><?php echo tr('Avere'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($preview['entries'] as $entry) { ?>
                            <tr>
                                <td>
                                    <?php echo $entry['descrizione']; ?>
                                    <?php if ($entry['contropartita']) { ?>
                                        <small class="text-muted">(<?php echo tr('contropartita'); ?>)</small>
                                    <?php } ?>
                                </td>
                                <td class="text-right"><?php echo $entry['saldo_origine'] === null ? '-' : moneyFormat($entry['saldo_origine'], 2); ?></td>
                                <td class="text-right"><?php echo moneyFormat(max(0, $entry['totale']), 2); ?></td>
                                <td class="text-right"><?php echo moneyFormat(max(0, -$entry['totale']), 2); ?></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>


            </div>
        </div>
    </div>
<?php } ?>
