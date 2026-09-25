<?php
include_once __DIR__.'/../../../core.php';
require_once __DIR__.'/src/Esercizio.php';
require_once __DIR__.'/src/Workflow.php';

use Models\Module;
use Modules\Partitario\Esercizio;
use Modules\Partitario\Workflow;

try {
    $pdcModule = Module::where('name', 'Piano dei conti')->first();
    $pdcPermission = $pdcModule ? Modules::getPermission($pdcModule->id) : null;
    if (!in_array($pdcPermission, ['r', 'rw'], true)) {
        http_response_code(403);
        echo '<div class="alert alert-danger">'.tr('Non hai i permessi per consultare il Piano dei conti.').'</div>';
        return;
    }
    $canWrite = $pdcPermission === 'rw';

    $periodStart = (string) ($_SESSION['period_start'] ?? '');
    $periodEnd = (string) ($_SESSION['period_end'] ?? '');
    Esercizio::assertValidPeriod($periodStart, $periodEnd);

    $esercizio = new Esercizio($periodStart, $periodEnd);
    $previews = [
        'apertura' => $esercizio->getPreview('apertura'),
        'chiusura' => $esercizio->getPreview('chiusura'),
    ];
    $openingPresent = $previews['apertura']['existing']['present'];
    $closingPresent = $previews['chiusura']['existing']['present'];
    $hasPreviousBalances = $previews['apertura']['has_previous_balances'];

    $primaNotaModule = Module::where('name', 'Prima nota')->first();
    $primaNotaPermission = $primaNotaModule ? Modules::getPermission($primaNotaModule->id) : null;
    $canViewMastrino = $primaNotaModule && in_array($primaNotaPermission, ['r', 'rw'], true);
} catch (Throwable $e) {
    error_log('[Partitario - Gestione esercizio RC7 modal] '.$e->getMessage());
    echo '<div class="alert alert-warning"><strong>'.tr('Gestione esercizio non disponibile.').'</strong> '
        .tr('Il periodo selezionato non è valido oppure non può essere elaborato. Nessuna operazione contabile è stata eseguita.').'</div>';
    return;
}
?>
<div class="small text-muted mb-3 pdc-exercise-sequence">
    <strong>1. <?php echo tr('Apertura'); ?></strong>
    <i class="fa fa-long-arrow-right mx-2"></i>
    <strong>2. <?php echo tr('Chiusura'); ?></strong>
    <span class="ml-2"><?php echo dateFormat($periodStart).' - '.dateFormat($periodEnd); ?></span>
</div>

<?php foreach ($previews as $operation => $preview) {
    $title = $operation === 'apertura' ? tr('Apertura esercizio') : tr('Chiusura esercizio');
    $state = Workflow::state($operation, $preview, $openingPresent, $closingPresent, $hasPreviousBalances);
    $detailId = 'pdc-'.$operation.'-details';
    $filterId = 'pdc-'.$operation.'-filter';
    $postOperation = $operation === 'apertura' ? 'apri-bilancio' : 'chiudi-bilancio';
    $actionLabel = $operation === 'apertura' ? tr('Apri esercizio') : tr('Chiudi esercizio');
    $confirmLabel = $operation === 'apertura' ? tr('Conferma apertura') : tr('Conferma chiusura');
    $expectedBalanced = abs($preview['debit'] - $preview['credit']) < 0.000001;
    $existing = $preview['existing'];
    $registered = !empty($existing['present']);

    $displayAccounts = $registered ? count($existing['account_totals'] ?? []) : (int) $preview['accounts_count'];
    $displayRows = $registered ? (int) ($existing['rows'] ?? 0) : (int) $preview['entries_count'];
    $displayDebit = $registered ? (float) ($existing['debit'] ?? 0) : (float) $preview['debit'];
    $displayCredit = $registered ? (float) ($existing['credit'] ?? 0) : (float) $preview['credit'];

    $confirmMessage = '<div class="text-left pdc-confirm-summary">'
        .'<h5 class="mb-3">'.$title.'</h5>'
        .'<table class="table table-sm table-borderless mb-3">'
        .'<tr><td>'.tr('Periodo').'</td><td class="text-right"><strong>'.dateFormat($preview['period_start']).' - '.dateFormat($preview['period_end']).'</strong></td></tr>'
        .'<tr><td>'.tr('Data registrazione').'</td><td class="text-right"><strong>'.dateFormat($preview['date']).'</strong></td></tr>'
        .'<tr><td>'.tr('Conti interessati').'</td><td class="text-right"><strong>'.(int) $preview['accounts_count'].'</strong></td></tr>'
        .'<tr><td>'.tr('Righe da creare').'</td><td class="text-right"><strong>'.(int) $preview['entries_count'].'</strong></td></tr>'
        .'<tr><td>'.tr('Totale Dare').'</td><td class="text-right"><strong>'.moneyFormat($preview['debit'], 2).'</strong></td></tr>'
        .'<tr><td>'.tr('Totale Avere').'</td><td class="text-right"><strong>'.moneyFormat($preview['credit'], 2).'</strong></td></tr>'
        .'<tr><td>'.tr('Pareggio').'</td><td class="text-right"><strong>'.($expectedBalanced ? tr('Verificato') : tr('Non verificato')).'</strong></td></tr>'
        .'</table>'
        .'<p class="mb-0">'.tr('Nessuna scrittura esistente verrà eliminata o rigenerata.').'</p>'
        .'</div>';
    ?>
    <div class="card card-outline card-secondary mb-3 pdc-exercise-card" data-operation="<?php echo htmlspecialchars($operation); ?>">
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
                    <?php if ($registered) { ?>
                        <strong><?php echo $displayRows; ?></strong> <?php echo tr('righe registrate'); ?>
                    <?php } else { ?>
                        <strong><?php echo $displayAccounts; ?></strong> <?php echo tr('conti'); ?>
                    <?php } ?>
                    <span class="mx-1">·</span> <?php echo tr('Dare'); ?> <strong><?php echo moneyFormat($displayDebit, 2); ?></strong>
                    <span class="mx-1">·</span> <?php echo tr('Avere'); ?> <strong><?php echo moneyFormat($displayCredit, 2); ?></strong>
                    <?php if ($registered && !empty($existing['mastrini'])) { ?>
                        <div class="small">
                            <?php echo tr('Mastrino'); ?>: <?php echo '#'.implode(', #', array_map('intval', $existing['mastrini'])); ?>
                        </div>
                    <?php } ?>
                </div>
                <div class="col-lg-3 text-lg-right mt-2 mt-lg-0 pdc-action-group d-flex flex-wrap justify-content-lg-end">
                    <button class="btn btn-sm btn-outline-secondary mb-1 pdc-detail-toggle" type="button"
                        data-toggle="collapse" data-target="#<?php echo $detailId; ?>" aria-expanded="false">
                        <i class="fa fa-search"></i> <span><?php echo tr('Dettagli'); ?></span>
                    </button>
                    <?php if ($registered && count($existing['mastrini']) === 1 && $canViewMastrino) {
                        $mastrinoUrl = base_path_osm().'/editor.php?id_module='.(int) $primaNotaModule->id.'&id_record='.(int) $existing['mastrini'][0];
                        ?>
                        <a class="btn btn-sm btn-info mb-1 ml-1" target="_blank" rel="noopener"
                            href="<?php echo htmlspecialchars($mastrinoUrl, ENT_QUOTES); ?>">
                            <i class="fa fa-external-link"></i> <?php echo tr('Visualizza Mastrino'); ?>
                        </a>
                    <?php } elseif ($state['action'] && $canWrite) { ?>
                        <button type="button" class="btn btn-sm btn-primary mb-1 ml-1 pdc-exercise-action"
                            data-op="<?php echo $postOperation; ?>"
                            data-operation="<?php echo $operation; ?>"
                            data-period_start="<?php echo htmlspecialchars($preview['period_start'], ENT_QUOTES); ?>"
                            data-period_end="<?php echo htmlspecialchars($preview['period_end'], ENT_QUOTES); ?>"
                            data-registration_date="<?php echo htmlspecialchars($preview['date'], ENT_QUOTES); ?>"
                            data-fingerprint="<?php echo htmlspecialchars($preview['fingerprint'], ENT_QUOTES); ?>"
                            data-title="<?php echo htmlspecialchars($title, ENT_QUOTES); ?>"
                            data-backto="record-list"
                            data-msg="<?php echo htmlspecialchars($confirmMessage, ENT_QUOTES); ?>"
                            data-button="<?php echo htmlspecialchars($confirmLabel, ENT_QUOTES); ?>"
                            data-class="btn btn-primary"
                            onclick="message(this);">
                            <i class="fa fa-check"></i> <?php echo $actionLabel; ?>
                        </button>
                    <?php } ?>
                </div>
            </div>

            <?php if ($state['key'] === 'closed_without_opening') { ?>
                <div class="alert alert-warning py-2 mt-3 mb-0">
                    <?php echo tr('Apertura non rilevata: l\'esercizio risulta già chiuso. Nessuna apertura retroattiva viene proposta.'); ?>
                </div>
            <?php } elseif ($state['key'] === 'not_required') { ?>
                <div class="small text-muted mt-2"><i class="fa fa-info-circle"></i> <?php echo tr('Non esistono saldi patrimoniali precedenti da riportare.'); ?></div>
            <?php } elseif ($state['key'] === 'waiting_opening') { ?>
                <div class="small text-muted mt-2"><i class="fa fa-clock-o"></i> <?php echo tr('Prima completa l\'Apertura dell\'esercizio.'); ?></div>
            <?php } elseif (in_array($state['key'], ['not_yet', 'waiting_end'], true)) { ?>
                <div class="small text-muted mt-2"><i class="fa fa-clock-o"></i> <?php echo htmlspecialchars((string) ($preview['availability']['message'] ?? '')); ?></div>
            <?php } elseif ($state['key'] === 'ready' && !$canWrite) { ?>
                <div class="small text-muted mt-2"><i class="fa fa-lock"></i> <?php echo tr('Permesso di sola lettura: operazione non disponibile.'); ?></div>
            <?php } ?>

            <?php foreach (array_values(array_unique(array_merge($preview['warnings'], $preview['errors']))) as $message) { ?>
                <div class="alert <?php echo $state['key'] === 'anomaly' || $state['key'] === 'blocked' ? 'alert-danger' : 'alert-warning'; ?> py-2 mt-3 mb-0">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php } ?>

            <div class="collapse mt-3" id="<?php echo $detailId; ?>">
                <div class="row align-items-center mb-2">
                    <div class="col-md-5">
                        <input type="text" class="form-control form-control-sm pdc-account-filter" id="<?php echo $filterId; ?>"
                            data-target="#<?php echo $detailId; ?>" placeholder="<?php echo tr('Filtra per numero o descrizione conto'); ?>">
                    </div>
                    <div class="col-md-7 text-md-right mt-2 mt-md-0 pdc-detail-totals">
                        <?php if ($registered) { ?>
                            <?php echo tr('Registrato Dare'); ?>: <strong><?php echo moneyFormat($existing['debit'], 2); ?></strong>
                            <span class="mx-2">·</span>
                            <?php echo tr('Registrato Avere'); ?>: <strong><?php echo moneyFormat($existing['credit'], 2); ?></strong>
                            <span class="mx-2">·</span>
                            <?php echo tr('Saldo Mastrino'); ?>: <strong><?php echo moneyFormat($existing['balance'], 2); ?></strong>
                        <?php } else { ?>
                            <?php echo tr('Dare'); ?>: <strong><?php echo moneyFormat($preview['debit'], 2); ?></strong>
                            <span class="mx-2">·</span>
                            <?php echo tr('Avere'); ?>: <strong><?php echo moneyFormat($preview['credit'], 2); ?></strong>
                            <span class="mx-2">·</span>
                            <?php echo tr('Pareggio anteprima'); ?>: <strong><?php echo $expectedBalanced ? tr('Verificato') : tr('Non verificato'); ?></strong>
                        <?php } ?>
                    </div>
                </div>

                <?php
                $showComparison = $registered && (
                    in_array($state['key'], ['review', 'anomaly'], true)
                    || ($existing['temporal_status'] ?? 'consistent') === 'future'
                );
                if ($showComparison) {
                    $differentRows = array_values(array_filter($existing['comparison'] ?? [], static fn (array $row): bool => !empty($row['different'])));
                    ?>
                    <div class="mb-2 small text-muted">
                        <?php echo tr('Atteso oggi'); ?>: <?php echo (int) $preview['entries_count']; ?> <?php echo tr('righe'); ?>,
                        <?php echo tr('Dare'); ?> <?php echo moneyFormat($preview['debit'], 2); ?>,
                        <?php echo tr('Avere'); ?> <?php echo moneyFormat($preview['credit'], 2); ?>.
                    </div>
                    <?php if (empty($differentRows)) { ?>
                        <div class="alert alert-info py-2"><?php echo tr('Nessuna differenza contabile rilevata; la verifica richiesta riguarda la data o la configurazione.'); ?></div>
                    <?php } else { ?>
                        <div class="table-responsive pdc-table-scroll">
                            <table class="table table-sm table-striped mb-0 pdc-account-table">
                                <thead><tr>
                                    <th><?php echo tr('Conto'); ?></th>
                                    <th class="text-right"><?php echo tr('Registrato'); ?></th>
                                    <th class="text-right"><?php echo tr('Atteso oggi'); ?></th>
                                    <th class="text-right"><?php echo tr('Differenza'); ?></th>
                                </tr></thead>
                                <tbody>
                                <?php foreach ($differentRows as $row) { ?>
                                    <tr class="table-warning">
                                        <td class="pdc-account-description"><?php echo htmlspecialchars($row['descrizione']); ?></td>
                                        <td class="text-right text-nowrap"><?php echo moneyFormat($row['registered'], 2); ?></td>
                                        <td class="text-right text-nowrap"><?php echo moneyFormat($row['expected'], 2); ?></td>
                                        <td class="text-right text-nowrap"><?php echo moneyFormat($row['difference'], 2); ?></td>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    <?php } ?>
                <?php } elseif ($registered) { ?>
                    <div class="table-responsive pdc-table-scroll">
                        <table class="table table-sm table-striped mb-0 pdc-account-table">
                            <thead><tr>
                                <th><?php echo tr('Conto registrato'); ?></th>
                                <th class="text-right"><?php echo tr('Dare'); ?></th>
                                <th class="text-right"><?php echo tr('Avere'); ?></th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($existing['account_totals'] as $idConto => $value) { ?>
                                <tr>
                                    <td class="pdc-account-description"><?php echo htmlspecialchars($existing['account_descriptions'][$idConto] ?? ('#'.$idConto)); ?></td>
                                    <td class="text-right text-nowrap"><?php echo moneyFormat(max(0, $value), 2); ?></td>
                                    <td class="text-right text-nowrap"><?php echo moneyFormat(max(0, -$value), 2); ?></td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } else { ?>
                    <div class="table-responsive pdc-table-scroll">
                        <table class="table table-sm table-striped mb-0 pdc-account-table">
                            <thead><tr>
                                <th><?php echo tr('Conto'); ?></th>
                                <th class="text-right"><?php echo tr('Saldo origine'); ?></th>
                                <th class="text-right"><?php echo tr('Dare'); ?></th>
                                <th class="text-right"><?php echo tr('Avere'); ?></th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($preview['entries'] as $entry) { ?>
                                <tr<?php echo $entry['contropartita'] ? ' class="table-info pdc-counterpart"' : ''; ?>>
                                    <td class="pdc-account-description">
                                        <?php echo htmlspecialchars($entry['descrizione']); ?>
                                        <?php if ($entry['contropartita']) { ?><span class="badge badge-info ml-1"><?php echo tr('Contropartita'); ?></span><?php } ?>
                                    </td>
                                    <td class="text-right text-nowrap"><?php echo $entry['saldo_origine'] === null ? '-' : moneyFormat($entry['saldo_origine'], 2); ?></td>
                                    <td class="text-right text-nowrap"><?php echo moneyFormat(max(0, $entry['totale']), 2); ?></td>
                                    <td class="text-right text-nowrap"><?php echo moneyFormat(max(0, -$entry['totale']), 2); ?></td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>
            </div>
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
