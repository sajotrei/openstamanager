<?php

if ($structure->permission !== 'rw') {
    return;
}
?>
<script>
window.creaBackup = function (button) {
    Swal.fire({
        title: <?php echo json_encode(tr('Creare un nuovo backup?')); ?>,
        html: '<style>#swal-backup-select{width:100%}</style>' +
            '<div class="swal2-select-container">' +
            '<label class="swal2-select-label"><?php echo addslashes(tr('Seleziona cosa escludere dal backup:')); ?></label>' +
            '<select id="swal-backup-select" class="form-control">' +
            '<option value=""><?php echo addslashes(tr('Non escludere nulla')); ?></option>' +
            '<option value="exclude_attachments">📎 <?php echo addslashes(tr('Allegati')); ?></option>' +
            '<option value="only_database">🗃️ <?php echo addslashes(tr('Solo database')); ?></option>' +
            '</select>' +
            '</div>',
        showCancelButton: true,
        confirmButtonText: <?php echo json_encode(tr('Crea')); ?>,
        cancelButtonText: <?php echo json_encode(tr('Annulla')); ?>,
        customClass: {
            confirmButton: 'btn btn-lg btn-success'
        }
    }).then(function (result) {
        if (!result.isConfirmed) {
            return;
        }

        var restore = buttonLoading(button);
        $('#main_loading').show();

        $.ajax({
            url: globals.rootdir + '/actions.php',
            type: 'POST',
            data: {
                id_module: globals.id_module,
                op: 'backup',
                exclude: $('#swal-backup-select').val()
            },
            success: function () {
                $('#main_loading').fadeOut();
                buttonRestore(button, restore);
                window.location.reload();
            },
            error: function () {
                $('#main_loading').fadeOut();
                buttonRestore(button, restore);
                Swal.fire(
                    <?php echo json_encode(tr('Errore')); ?>,
                    <?php echo json_encode(tr('Errore durante la creazione del backup')); ?>,
                    'error'
                );
                renderMessages();
            }
        });
    }).catch(swal.noop);
};
</script>
