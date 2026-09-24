<?php
include_once __DIR__.'/../../core.php';

use Modules\Partitario\Esercizio;
use Modules\Partitario\Workflow;

Permissions::check(['r', 'rw']);
$canWrite = Permissions::check('rw', false);
$esercizio = new Esercizio($_SESSION['period_start'], $_SESSION['period_end']);
$previews = [
    'apertura' => $esercizio->getPreview('apertura'),
    'chiusura' => $esercizio->getPreview('chiusura'),
];
$openingPresent = $previews['apertura']['existing']['present'];
$closingPresent = $previews['chiusura']['existing']['present'];
$hasPreviousBalances = $previews['apertura']['accounts_count'] > 0;
?>
<div class="small text-muted mb-3 pdc-exercise-sequence">
    <strong>1. <?php echo tr('Apertura'); ?></strong>
    <i class="fa fa-long-arrow-right mx-2"></i>
    <strong>2. <?php echo tr('Chiusura'); ?></strong>
    <span class="ml-2"><?php echo dateFormat($_SESSION['period_start']).' - '.dateFormat($_SESSION['period_end']); ?></span>
</div>

<?php foreach ($previews as $operation => $preview) {
    $title = $operation === 'apertura' ? tr('Apertura esercizio') : tr('Chiusura esercizio');
    $state = Workflow::state($operation, $preview, $openingPresent, $closingPresent, $hasPreviousBalances);
    $detailId = 'pdc-'.$operation.'-details';
    $filterId = 'pdc-'.$operation.'-filter';
    $postOperation = $operation === 'apertura' ? 'apri-bilancio' : 'chiudi-bilancio';
    $actionLabel = $operation === 'apertura' ? tr('Apri esercizio') : tr('Chiudi esercizio');
    $confirmLabel = $operation === 'apertura' ? tr('Conferma apertura') : tr('Conferma chiusura');
    $balanced = abs($preview['debit'] - $preview['credit']) < 0.000001;
    $rowCount = (int) $preview['entries_count'];
    $confirmMessage = '<div class="text-left pdc-confirm-summary">'
        .'<h5 class="mb-3">'.$title.'</h5>'
        .'<table class="table table-sm table-borderless mb-3">'
        .'<tr><td>'.tr('Periodo').'</td><td class="text-right"><strong>'.dateFormat($preview['period_start']).' - '.dateFormat($preview['period_end']).'</strong></td></tr>'
        .'<tr><td>'.tr('Data registrazione').'</td><td class="text-right"><strong>'.dateFormat($preview['date']).'</strong></td></tr>'
        .'<tr><td>'.tr('Conti interessati').'</td><td class="text-right"><strong>'.(int) $preview['accounts_count'].'</strong></td></tr>'
        .'<tr><td>'.tr('Righe da creare').'</td><td class="text-right"><strong>'.$rowCount.'</strong></td></tr>'
        .'<tr><td>'.tr('Totale Dare').'</td><td class="text-right"><strong>'.moneyFormat($preview['debit'], 2).'</strong></td></tr>'
        .'<tr><td>'.tr('Totale Avere').'</td><td class="text-right"><strong>'.moneyFormat($preview['credit'], 2).'</strong></td></tr>'
        .'<tr><td>'.tr('Pareggio').'</td><td class="text-right"><strong>'.($balanced ? tr('Verificato') : tr('Non verificato')).'</strong></td></tr>'
        .'</table>'
        .'<p class="mb-0">'.tr('Nessuna scrittura esistente verrà eliminata o rigenerata.').'</p>'
        .'</div>';
    ?>
    <div class="card card-outline card-secondary mb-3 pdc-exercise-card" data-operation="<?php echo $operation; ?>">
        <div class="card-body py-3">
            <div class="row align-items-center">
                <div class="col-lg-4 col-md-5">
                    <strong><?php echo $operation === 'apertura' ? '1. ' : '2. '; ?><?php echo $title; ?></strong>
                    <span class="badge <?php echo $state['class']; ?> ml-2"><?php echo $state['label']; ?></span>
                    <div class="small text-muted mt-1">
                        <?php echo $operation === 'apertura'
                            ? tr('Riporta i saldi patrimoniali dell\'esercizio precedente.')
                            : tr('Chiude i saldi patrimoniali alla fine dell\'esercizio.'); ?>
                    </div>
                </div>
                <div class="col-lg-5 col-md-7 mt-2 mt-md-0">
                    <strong><?php echo (int) $preview['accounts_count']; ?></strong> <?php echo tr('conti'); ?>
                    <span class="mx-1">·</span> <?php echo tr('Dare'); ?> <strong><?php echo moneyFormat($preview['debit'], 2); ?></strong>
                    <span class="mx-1">·</span> <?php echo tr('Avere'); ?> <strong><?php echo moneyFormat($preview['credit'], 2); ?></strong>
                    <?php if ($preview['existing']['present'] && $preview['existing']['mastrini']) { ?>
                        <div class="small mt-1">
                            <?php echo tr('Data'); ?>: <?php echo dateFormat($preview['date']); ?>
                            <span class="mx-1">·</span>
                            <?php echo tr('Mastrino'); ?>: #<?php echo implode(', #', $preview['existing']['mastrini']); ?>
                        </div>
                    <?php } ?>
                </div>
                <div class="col-lg-3 text-lg-right mt-2 mt-lg-0">
                    <div class="d-flex flex-wrap justify-content-lg-end pdc-action-group">
                        <?php if ($preview['entries']) { ?>
                            <button class="btn btn-sm btn-outline-secondary pdc-detail-toggle mb-1 ml-1" type="button" data-toggle="collapse" data-target="#<?php echo $detailId; ?>" aria-expanded="false">
                                <i class="fa fa-search"></i> <span><?php echo tr('Dettagli'); ?></span>
                            </button>
                        <?php } ?>
                        <?php if ($preview['existing']['present'] && count($preview['existing']['mastrini']) === 1) { ?>
                            <?php echo Modules::link('Prima nota', (int) $preview['existing']['mastrini'][0], tr('Visualizza Mastrino'), 'btn btn-sm btn-info mb-1 ml-1'); ?>
                        <?php } elseif ($state['action'] && $canWrite) { ?>
                            <button type="button" class="btn btn-sm btn-primary pdc-exercise-action mb-1 ml-1"
                                data-op="<?php echo $postOperation; ?>"
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
            </div>

            <?php if ($state['key'] === 'closed_without_opening') { ?>
                <div class="alert alert-warning py-2 mt-3 mb-0">
                    <?php echo tr('Apertura non rilevata: l\'esercizio risulta già chiuso. Nessuna apertura retroattiva viene proposta.'); ?>
                </div>
            <?php } elseif ($state['key'] === 'not_yet' || $state['key'] === 'waiting_end') { ?>
                <div class="small text-muted mt-2"><i class="fa fa-clock-o"></i> <?php echo $preview['availability']['message']; ?></div>
            <?php } elseif ($state['key'] === 'waiting_opening') { ?>
                <div class="small text-muted mt-2"><i class="fa fa-clock-o"></i> <?php echo tr('Prima completa l\'Apertura dell\'esercizio.'); ?></div>
            <?php } elseif ($state['key'] === 'review') { ?>
                <div class="alert alert-warning py-2 mt-3 mb-0">
                    <?php foreach ($preview['existing']['messages'] as $message) { ?><div><?php echo $message; ?></div><?php } ?>
                </div>
            <?php } elseif ($state['key'] === 'anomaly') { ?>
                <div class="alert alert-danger py-2 mt-3 mb-0">
                    <?php foreach ($preview['existing']['messages'] as $message) { ?><div><?php echo $message; ?></div><?php } ?>
                </div>
            <?php } elseif ($state['key'] === 'configuration_error') { ?>
                <div class="alert alert-danger py-2 mt-3 mb-0">
                    <strong><?php echo tr('Configurazione contabile da correggere.'); ?></strong>
                    <?php foreach ($preview['configuration']['errors'] as $error) { ?><div><?php echo $error; ?></div><?php } ?>
                </div>
            <?php } elseif ($state['key'] === 'blocked') { ?>
                <div class="alert alert-danger py-2 mt-3 mb-0">
                    <?php foreach ($preview['errors'] as $error) { ?><div><?php echo $error; ?></div><?php } ?>
                </div>
            <?php } elseif ($state['key'] === 'nothing_to_do') { ?>
                <div class="small text-muted mt-2"><i class="fa fa-info-circle"></i> <?php echo tr('Nessun saldo patrimoniale da elaborare per il periodo selezionato.'); ?></div>
            <?php } elseif ($state['key'] === 'ready' && !$canWrite) { ?>
                <div class="small text-muted mt-2"><i class="fa fa-lock"></i> <?php echo tr('Permesso di sola lettura: operazione non disponibile.'); ?></div>
            <?php } ?>

            <?php if ($preview['entries']) { ?>
                <div class="collapse mt-3" id="<?php echo $detailId; ?>">
                    <div class="row align-items-center mb-2">
                        <div class="col-md-5">
                            <input type="text" class="form-control form-control-sm pdc-account-filter" id="<?php echo $filterId; ?>" data-target="#<?php echo $detailId; ?>" placeholder="<?php echo tr('Filtra per numero o descrizione conto'); ?>">
                        </div>
                        <div class="col-md-7 text-md-right mt-2 mt-md-0 pdc-detail-totals">
                            <?php echo tr('Dare'); ?>: <strong><?php echo moneyFormat($preview['debit'], 2); ?></strong>
                            <span class="mx-2">·</span>
                            <?php echo tr('Avere'); ?>: <strong><?php echo moneyFormat($preview['credit'], 2); ?></strong>
                            <span class="mx-2">·</span>
                            <?php echo tr('Pareggio'); ?>: <strong><?php echo $balanced ? tr('Verificato') : tr('Non verificato'); ?></strong>
                        </div>
                    </div>
                    <div class="table-responsive pdc-table-scroll">
                        <table class="table table-sm table-striped mb-0 pdc-account-table">
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
                                <tr<?php echo $entry['contropartita'] ? ' class="table-info pdc-counterpart"' : ''; ?>>
                                    <td class="pdc-account-description">
                                        <?php echo htmlspecialchars($entry['descrizione']); ?>
                                        <?php if ($entry['contropartita']) { ?>
                                            <span class="badge badge-info ml-1"><?php echo tr('Contropartita'); ?></span>
                                        <?php } ?>
                                    </td>
                                    <td class="text-right text-nowrap"><?php echo $entry['saldo_origine'] === null ? '-' : moneyFormat($entry['saldo_origine'], 2); ?></td>
                                    <td class="text-right text-nowrap"><?php echo moneyFormat(max(0, $entry['totale']), 2); ?></td>
                                    <td class="text-right text-nowrap"><?php echo moneyFormat(max(0, -$entry['totale']), 2); ?></td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
<?php } ?>

<style>
.pdc-action-group { gap: .25rem; }
.pdc-table-scroll { max-height: 420px; overflow-y: auto; border: 1px solid #dee2e6; }
.pdc-account-table thead th { position: sticky; top: 0; z-index: 2; background: #fff; box-shadow: 0 1px 0 #dee2e6; }
.pdc-detail-totals { white-space: normal; }
@media (max-width: 991.98px) {
    .pdc-action-group { justify-content: flex-start !important; }
    .pdc-exercise-sequence span { display: block; margin-left: 0 !important; margin-top: .25rem; }
}
</style>
<script>
(function ($) {
    $('.pdc-detail-toggle').off('click.pdc').on('click.pdc', function () {
        var button = $(this);
        var target = $(button.data('target'));
        target.one('shown.bs.collapse', function () {
            button.find('span').text('<?php echo addslashes(tr('Nascondi dettagli')); ?>');
            button.attr('aria-expanded', 'true');
        });
        target.one('hidden.bs.collapse', function () {
            button.find('span').text('<?php echo addslashes(tr('Dettagli')); ?>');
            button.attr('aria-expanded', 'false');
        });
    });

    $('.pdc-account-filter').off('input.pdc').on('input.pdc', function () {
        var value = String($(this).val() || '').toLocaleLowerCase();
        var target = $($(this).data('target'));
        target.find('tbody tr').each(function () {
            var text = $(this).find('.pdc-account-description').text().toLocaleLowerCase();
            $(this).toggle(text.indexOf(value) !== -1);
        });
    });
})(jQuery);
</script>
