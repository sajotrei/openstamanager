<?php

/*
 * Override minimale del plugin ExportFE.
 * Mantiene integralmente la UI nativa OSM 2.10.4 e sostituisce soltanto
 * la gestione client dell'invio per distinguere gli esiti provider incerti.
 */

include dirname(__DIR__).'/edit.php';

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
            swal(
                "<?php echo addslashes(tr('Errore')); ?>",
                "<?php echo addslashes(tr('Errore di comunicazione durante l’invio. Lo stato remoto non è noto: verificare prima di ritentare.')); ?>",
                "error"
            );
        }
    });
}
</script>
