<?php
include_once __DIR__.'/../../../core.php';
require_once __DIR__.'/src/Esercizio.php';
require_once __DIR__.'/src/Workflow.php';

use Modules\Partitario\Esercizio;
use Modules\Partitario\Workflow;

try {
    $periodStart = (string) ($_SESSION['period_start'] ?? '');
    $periodEnd = (string) ($_SESSION['period_end'] ?? '');
    Esercizio::assertValidPeriod($periodStart, $periodEnd);

    $esercizio = new Esercizio($periodStart, $periodEnd);
    $apertura = $esercizio->getPreview('apertura');
    $chiusura = $esercizio->getPreview('chiusura');
    $summary = Workflow::summary($apertura, $chiusura);
    $openingPresent = $apertura['existing']['present'];
    $closingPresent = $chiusura['existing']['present'];
    $hasPreviousBalances = $apertura['has_previous_balances'];
    $openingState = Workflow::state('apertura', $apertura, $openingPresent, $closingPresent, $hasPreviousBalances);
    $closingState = Workflow::state('chiusura', $chiusura, $openingPresent, $closingPresent, $hasPreviousBalances);
    $url = base_path_osm().'/modules/partitario/custom/gestione_esercizio.php?id_module='.(int) $id_module;
    $startYear = date('Y', strtotime($periodStart));
    $endYear = date('Y', strtotime($periodEnd));
    $label = $startYear === $endYear ? $startYear : $startYear.'/'.$endYear;
    ?>
    <div class="card card-outline card-info mb-3" id="pdc-exercise-summary">
        <div class="card-body py-2">
            <div class="row align-items-center">
                <div class="col-lg-5 col-md-6">
                    <strong><i class="fa fa-calendar-check-o"></i> <?php echo tr('Gestione esercizio').' '.$label; ?></strong>
                    <span class="badge <?php echo $summary['class']; ?> ml-2"><?php echo $summary['label']; ?></span>
                    <div class="small text-muted"><?php echo dateFormat($periodStart).' - '.dateFormat($periodEnd); ?></div>
                </div>
                <div class="col-lg-4 col-md-6 mt-2 mt-md-0">
                    <span class="mr-3"><?php echo tr('Apertura'); ?> <span class="badge <?php echo $openingState['class']; ?>"><?php echo $openingState['label']; ?></span></span>
                    <span><?php echo tr('Chiusura'); ?> <span class="badge <?php echo $closingState['class']; ?>"><?php echo $closingState['label']; ?></span></span>
                </div>
                <div class="col-lg-3 text-lg-right mt-2 mt-lg-0">
                    <button type="button" class="btn btn-info" onclick="openModal('<?php echo addslashes(tr('Gestione esercizio')); ?>', <?php echo htmlspecialchars(json_encode($url), ENT_QUOTES); ?>);">
                        <i class="fa fa-cog"></i> <?php echo tr('Gestisci'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php
} catch (Throwable $e) {
    error_log('[Partitario - Gestione esercizio RC7] '.$e->getMessage());
    echo '<div class="alert alert-warning"><strong>'.tr('Gestione esercizio non disponibile.').'</strong> '
        .tr('Il periodo selezionato non è valido oppure non può essere elaborato. Nessuna operazione contabile è stata eseguita.').'</div>';
}

// Il Piano dei conti resta quello ufficiale 2.10.4.
include __DIR__.'/../edit.php';
?>
<style>
/* Adattatore installabile: nasconde esclusivamente i due pulsanti nativi grandi. */
button.btn-lg[data-op="apri-bilancio"],
button.btn-lg[data-op="chiudi-bilancio"] {
    display: none !important;
}
</style>
