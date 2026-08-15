<?php

/*
 * Override minimale del plugin ExportFE.
 * Mantiene integralmente la UI nativa e aggiunge soltanto gestione provider e
 * diagnostica tecnica senza duplicare il motore fiscale del gestionale.
 */

use Plugins\ExportFE\Providers\ProviderFactory;
use Plugins\ExportFE\Providers\ProviderSettings;
use Plugins\ExportFE\Providers\ProviderTransactionRepository;

include dirname(__DIR__).'/edit.php';

$provider_transaction = null;
if (ProviderSettings::selectedProvider() === ProviderFactory::HOSTING_SOLUTIONS && !empty($id_record)) {
    $repository = new ProviderTransactionRepository();
    if ($repository->tableAvailable()) {
        $provider_transaction = $repository->latestForDocument((int) $id_record, ProviderFactory::HOSTING_SOLUTIONS);
    }
}

if (!empty($provider_transaction)) {
    $status = (string) ($provider_transaction['status'] ?? '');
    $status_map = [
        ProviderTransactionRepository::STATUS_SENDING => [tr('In elaborazione'), 'info'],
        ProviderTransactionRepository::STATUS_SENT => [tr('In attesa'), 'info'],
        ProviderTransactionRepository::STATUS_WAITING => [tr('In attesa'), 'info'],
        ProviderTransactionRepository::STATUS_UNCERTAIN => [tr('Esito incerto'), 'warning'],
        ProviderTransactionRepository::STATUS_FINAL => [tr('Conclusa'), 'success'],
        ProviderTransactionRepository::STATUS_FAILED => [tr('Errore'), 'danger'],
    ];
    [$status_label, $status_class] = $status_map[$status] ?? [$status, 'secondary'];
    ?>

<div class="card card-outline card-secondary mt-3">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fa fa-exchange mr-2"></i><?php echo tr('Stato provider'); ?>
        </h3>
        <div class="card-tools">
            <span class="badge badge-<?php echo $status_class; ?>"><?php echo htmlentities($status_label); ?></span>
        </div>
    </div>
    <div class="card-body py-2">
        <div class="row small">
            <div class="col-md-3">
                <strong><?php echo tr('Provider'); ?>:</strong><br>
                Hosting Solutions
            </div>
            <div class="col-md-3">
                <strong><?php echo tr('Tentativi'); ?>:</strong><br>
                <?php echo (int) ($provider_transaction['attempt'] ?? 0); ?>
            </div>
            <div class="col-md-3">
                <strong><?php echo tr('Identificativo remoto'); ?>:</strong><br>
                <?php echo !empty($provider_transaction['remote_id']) ? htmlentities((string) $provider_transaction['remote_id']) : '—'; ?>
            </div>
            <div class="col-md-3">
                <strong><?php echo tr('Ultimo aggiornamento'); ?>:</strong><br>
                <?php echo !empty($provider_transaction['updated_at']) ? timestampFormat($provider_transaction['updated_at']) : '—'; ?>
            </div>
        </div>

        <?php if ($status === ProviderTransactionRepository::STATUS_UNCERTAIN) { ?>
            <div class="alert alert-warning mt-2 mb-0">
                <i class="fa fa-shield mr-1"></i>
                <?php echo tr('L’esito dell’invio non è certo. Non ritentare il documento finché non è stato riconciliato con il provider.'); ?>
            </div>
        <?php } elseif ($status === ProviderTransactionRepository::STATUS_FAILED && !empty($provider_transaction['last_error'])) { ?>
            <div class="alert alert-danger mt-2 mb-0">
                <i class="fa fa-exclamation-triangle mr-1"></i>
                <?php echo htmlentities((string) $provider_transaction['last_error']); ?>
            </div>
        <?php } ?>
    </div>
</div>
<?php
}
?>
<script>
function inviaFE(button) {
    if (!confirm("<?php echo addslashes(tr('Inviare la fattura al SDI?')); ?>")) {
        return;
    }

    let restore = buttonLoading(button);
    $("#main_loading").show();

    $.ajax({
        url: globals.rootdir + "/actions.php",
        type: "post",
        dataType: "json",
        data: {
            op: "send",
            id_module: "<?php echo $id_module; ?>",
            id_plugin: "<?php echo $id_plugin; ?>",
            id_record: "<?php echo $id_record; ?>"
        },
        success: function(data) {
            $("#main_loading").fadeOut();
            buttonRestore(button, restore);

            if (data.code === 200) {
                swal("<?php echo addslashes(tr('Fattura inviata!')); ?>", data.message, "success");
                $(button).attr("disabled", true).addClass("disabled");
                setTimeout(function() { location.reload(); }, 3000);
                return;
            }

            if (data.code === 202) {
                swal(
                    "<?php echo addslashes(tr('Invio in attesa di riconciliazione')); ?>",
                    data.message,
                    "warning"
                );
                $(button).attr("disabled", true).addClass("disabled");
                setTimeout(function() { location.reload(); }, 3000);
                return;
            }

            if (data.code === 301) {
                swal("<?php echo addslashes(tr('Invio già effettuato')); ?>", data.code + " - " + data.message, "warning");
                $(button).attr("disabled", true).addClass("disabled");
                return;
            }

            if (data.code === 423) {
                swal(
                    "<?php echo addslashes(tr('Invio già in elaborazione')); ?>",
                    data.message,
                    "warning"
                );
                $(button).attr("disabled", true).addClass("disabled");
                return;
            }

            if (data.code === 500 || data.code === 503) {
                swal(
                    "<?php echo addslashes(tr("Errore durante l'invio")); ?>",
                    data.message || "<?php echo addslashes(tr("Si è verificato un problema durante l'invio della fattura")); ?>",
                    "error"
                );
                return;
            }

            swal("<?php echo addslashes(tr('Invio fallito')); ?>", data.code + " - " + data.message, "error");
        },
        error: function() {
            $("#main_loading").fadeOut();
            buttonRestore(button, restore);
            $(button).attr("disabled", true).addClass("disabled");

            swal(
                "<?php echo addslashes(tr('Esito invio non verificabile')); ?>",
                "<?php echo addslashes(tr('La comunicazione si è interrotta durante l’invio. Non ritentare subito: il documento potrebbe essere già stato ricevuto dal provider. Verificare lo stato e le ricevute.')); ?>",
                "warning"
            );

            setTimeout(function() { location.reload(); }, 3000);
        }
    });
}
</script>
