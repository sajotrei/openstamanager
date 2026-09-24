<?php
include_once __DIR__.'/../../core.php';

use Modules\Partitario\Esercizio;
use Modules\Partitario\Workflow;

try {
    $esercizio = new Esercizio($_SESSION['period_start'], $_SESSION['period_end']);
    $apertura = $esercizio->getPreview('apertura');
    $chiusura = $esercizio->getPreview('chiusura');
    $summary = Workflow::summary($apertura, $chiusura);
    $openingPresent = $apertura['existing']['present'];
    $closingPresent = $chiusura['existing']['present'];
    $hasPreviousBalances = $apertura['accounts_count'] > 0;
    $openingState = Workflow::state('apertura', $apertura, $openingPresent, $closingPresent, $hasPreviousBalances);
    $closingState = Workflow::state('chiusura', $chiusura, $openingPresent, $closingPresent, $hasPreviousBalances);
    $url = base_path_osm().'/modules/partitario/gestione_esercizio.php?id_module='.$id_module;
    $startYear = date('Y', strtotime($_SESSION['period_start']));
    $endYear = date('Y', strtotime($_SESSION['period_end']));
    $label = $startYear === $endYear ? $startYear : $startYear.'/'.$endYear;
    ?>
    <div class="card card-outline card-info mb-3" id="pdc-exercise-summary">
        <div class="card-body py-2">
            <div class="row align-items-center">
                <div class="col-lg-5 col-md-6">
                    <strong><i class="fa fa-calendar-check-o"></i> <?php echo tr('Gestione esercizio').' '.$label; ?></strong>
                    <span class="badge <?php echo $summary['class']; ?> ml-2"><?php echo $summary['label']; ?></span>
                    <div class="small text-muted"><?php echo dateFormat($_SESSION['period_start']).' - '.dateFormat($_SESSION['period_end']); ?></div>
                </div>
                <div class="col-lg-4 col-md-6 mt-2 mt-md-0">
                    <span class="mr-3"><?php echo tr('Apertura'); ?> <span class="badge <?php echo $openingState['class']; ?>"><?php echo $openingState['label']; ?></span></span>
                    <span><?php echo tr('Chiusura'); ?> <span class="badge <?php echo $closingState['class']; ?>"><?php echo $closingState['label']; ?></span></span>
                </div>
                <div class="col-lg-3 text-lg-right mt-2 mt-lg-0">
                    <button type="button" class="btn btn-info" onclick="openModal('<?php echo tr('Gestione esercizio'); ?>', <?php echo htmlspecialchars(json_encode($url), ENT_QUOTES); ?>);">
                        <i class="fa fa-cog"></i> <?php echo tr('Gestisci'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php
} catch (Throwable $e) {
    error_log('[Partitario - Gestione esercizio RC5] '.$e->getMessage());
    echo '<div class="alert alert-warning"><strong>'.tr('Gestione esercizio non disponibile.').'</strong> '.tr('Nessuna operazione contabile è stata eseguita.').'</div>';
}

// Il Piano dei conti resta quello ufficiale 2.10.4.
include __DIR__.'/piano_dei_conti.php';
?>
<style>
/* Adattatore 2.10.4: nasconde esclusivamente i due pulsanti nativi grandi. */
button.btn-lg[data-op="apri-bilancio"],
button.btn-lg[data-op="chiudi-bilancio"] {
    display: none !important;
}
</style>
