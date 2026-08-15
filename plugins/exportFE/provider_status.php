<?php

use Plugins\ExportFE\Providers\ProviderFactory;
use Plugins\ExportFE\Providers\ProviderSettings;
use Plugins\ExportFE\Providers\ProviderTransactionRepository;

$provider_name = ProviderSettings::selectedProvider();
$is_hs = $provider_name === ProviderFactory::HOSTING_SOLUTIONS;

if ($is_hs) {
    $enabled = ProviderSettings::isHostingSolutionsEnabled()
        && ProviderSettings::isHostingSolutionsMockEnabled();
} else {
    // Stato locale: l'apertura delle impostazioni non deve effettuare chiamate remote.
    $enabled = !empty(setting('OSMCloud Services API Token'));
}

$mode = $is_hs
    ? (ProviderSettings::isHostingSolutionsMockEnabled() ? tr('Simulazione') : tr('API reale non disponibile'))
    : 'OSMCloud';

$repository = new ProviderTransactionRepository();
$tracking_available = $repository->tableAvailable();
$counts = [
    ProviderTransactionRepository::STATUS_SENDING => 0,
    ProviderTransactionRepository::STATUS_WAITING => 0,
    ProviderTransactionRepository::STATUS_UNCERTAIN => 0,
    ProviderTransactionRepository::STATUS_FINAL => 0,
    ProviderTransactionRepository::STATUS_FAILED => 0,
];

if ($tracking_available) {
    try {
        $rows = database()->fetchArray(
            'SELECT `status`, COUNT(*) AS `total` FROM `fe_provider_transactions` WHERE `provider` = ? GROUP BY `status`',
            [$provider_name]
        );

        foreach ($rows as $row) {
            if (array_key_exists($row['status'], $counts)) {
                $counts[$row['status']] = (int) $row['total'];
            }
        }
    } catch (\Throwable) {
        $tracking_available = false;
    }
}

$provider_label = $is_hs ? 'Hosting Solutions' : 'OSMCloud';
$status_label = $enabled ? tr('Configurato') : tr('Da configurare');
$status_class = $enabled ? 'success' : ($is_hs ? 'warning' : 'secondary');
$pending = $counts[ProviderTransactionRepository::STATUS_SENDING]
    + $counts[ProviderTransactionRepository::STATUS_WAITING]
    + $counts[ProviderTransactionRepository::STATUS_UNCERTAIN];

?>

<div class="col-md-12 mb-3">
    <div class="card card-outline card-primary mb-0">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fa fa-exchange mr-2"></i><?php echo tr('Gateway Fatturazione Elettronica'); ?>
            </h3>
            <div class="card-tools">
                <span class="badge badge-<?php echo $status_class; ?>"><?php echo $status_label; ?></span>
            </div>
        </div>

        <div class="card-body pb-2">
            <div class="row">
                <div class="col-lg-3 col-md-6">
                    <div class="info-box shadow-none border">
                        <span class="info-box-icon bg-light"><i class="fa fa-cloud-upload text-primary"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text"><?php echo tr('Provider'); ?></span>
                            <span class="info-box-number"><?php echo $provider_label; ?></span>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="info-box shadow-none border">
                        <span class="info-box-icon bg-light"><i class="fa fa-flask text-info"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text"><?php echo tr('Modalità'); ?></span>
                            <span class="info-box-number"><?php echo $mode; ?></span>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="info-box shadow-none border">
                        <span class="info-box-icon bg-light"><i class="fa fa-clock-o text-warning"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text"><?php echo tr('Automazione'); ?></span>
                            <span class="info-box-number"><?php echo tr('Schedulazione automatica'); ?></span>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="info-box shadow-none border">
                        <span class="info-box-icon bg-light"><i class="fa fa-list-alt text-secondary"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text"><?php echo tr('Transazioni aperte'); ?></span>
                            <span class="info-box-number"><?php echo $tracking_available ? $pending : '—'; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($is_hs && ProviderSettings::isHostingSolutionsMockEnabled()) { ?>
                <div class="alert alert-info mb-2">
                    <i class="fa fa-info-circle mr-1"></i>
                    <?php echo tr('Hosting Solutions è in modalità simulazione. Nessun documento viene inviato alle API reali.'); ?>
                </div>
            <?php } elseif ($is_hs) { ?>
                <div class="alert alert-warning mb-2">
                    <i class="fa fa-exclamation-triangle mr-1"></i>
                    <?php echo tr('La modalità API reale Hosting Solutions non è ancora disponibile. Riattivare la simulazione per eseguire i test.'); ?>
                </div>
            <?php } ?>

            <?php if ($tracking_available && $counts[ProviderTransactionRepository::STATUS_UNCERTAIN] > 0) { ?>
                <div class="alert alert-warning mb-2">
                    <i class="fa fa-shield mr-1"></i>
                    <?php echo tr('Sono presenti _NUM_ invii con esito incerto. Il sistema li mantiene sospesi per evitare duplicazioni.', [
                        '_NUM_' => $counts[ProviderTransactionRepository::STATUS_UNCERTAIN],
                    ]); ?>
                </div>
            <?php } ?>

            <div class="small text-muted mb-2">
                <i class="fa fa-calendar-check-o mr-1"></i>
                <?php echo tr('Invio, acquisizione ricevute e ricerca fatture passive vengono gestiti automaticamente dalla schedulazione del gestionale.'); ?>
            </div>

            <div class="d-flex flex-wrap small text-muted mt-2">
                <span class="mr-3"><strong><?php echo tr('In attesa'); ?>:</strong> <?php echo $tracking_available ? $counts[ProviderTransactionRepository::STATUS_WAITING] : '—'; ?></span>
                <span class="mr-3"><strong><?php echo tr('Esito incerto'); ?>:</strong> <?php echo $tracking_available ? $counts[ProviderTransactionRepository::STATUS_UNCERTAIN] : '—'; ?></span>
                <span class="mr-3"><strong><?php echo tr('Concluse'); ?>:</strong> <?php echo $tracking_available ? $counts[ProviderTransactionRepository::STATUS_FINAL] : '—'; ?></span>
                <span><strong><?php echo tr('Errori'); ?>:</strong> <?php echo $tracking_available ? $counts[ProviderTransactionRepository::STATUS_FAILED] : '—'; ?></span>
            </div>
        </div>
    </div>
</div>
