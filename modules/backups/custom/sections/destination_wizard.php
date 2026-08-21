<?php
// Wizard guidato; non espone mai il JSON options o la password salvata.
?>
<div class="modal fade" id="backup-destination-wizard" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form method="post" id="backup-destination-wizard-form">
                <input type="hidden" name="op" value="backup_destination_wizard_save">
                <input type="hidden" name="id" id="backup-wizard-id" value="0">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa fa-copy mr-2"></i><?php echo tr('Configura destinazione backup'); ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label><?php echo tr('Tipo di destinazione'); ?></label>
                        <select class="form-control" name="mode" id="backup-wizard-mode" onchange="refreshBackupWizardMode()">
                            <option value="existing"><?php echo tr('Usa un adattatore OSM già configurato'); ?></option>
                            <?php if ($can_manage_adapters) { ?>
                                <option value="ftp"><?php echo tr('FTP / FTPS guidato'); ?></option>
                                <option value="local"><?php echo tr('Cartella locale OSM guidata'); ?></option>
                            <?php } ?>
                        </select>
                        <?php if (!$can_manage_adapters) { ?>
                            <small class="form-text text-warning"><i class="fa fa-lock mr-1"></i><?php echo tr('Per creare FTP/FTPS o cartelle locali servono permessi RW sul modulo Adattatori di archiviazione. Puoi comunque usare adattatori esistenti.'); ?></small>
                        <?php } ?>
                    </div>

                    <div data-backup-wizard-mode="existing">
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label><?php echo tr('Adattatore esistente'); ?></label>
                                <select name="id_adapter" id="backup-wizard-id-adapter" class="form-control">
                                    <option value=""><?php echo tr('Seleziona...'); ?></option>
                                    <?php foreach ($adapters as $adapter) { ?>
                                        <option value="<?php echo (int) $adapter->id; ?>"><?php echo $esc($adapter->name); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label><?php echo tr('Percorso relativo nell’adattatore'); ?></label>
                                <input type="text" name="path" id="backup-wizard-path" class="form-control" maxlength="255" value="backups" placeholder="backups">
                            </div>
                        </div>
                    </div>

                    <?php if ($can_manage_adapters) { ?>
                        <div data-backup-wizard-mode="ftp" style="display:none">
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label><?php echo tr('Nome'); ?></label>
                                    <input type="text" name="name" id="backup-wizard-name" class="form-control" maxlength="255" placeholder="NAS Ufficio">
                                </div>
                                <div class="form-group col-md-6">
                                    <label><?php echo tr('Host / IP'); ?></label>
                                    <input type="text" name="host" id="backup-wizard-host" class="form-control" maxlength="255" placeholder="192.168.1.20">
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-3">
                                    <label><?php echo tr('Porta'); ?></label>
                                    <input type="number" name="port" id="backup-wizard-port" class="form-control" min="1" max="65535" value="21">
                                </div>
                                <div class="form-group col-md-4">
                                    <label><?php echo tr('Username'); ?></label>
                                    <input type="text" name="username" id="backup-wizard-username" class="form-control" autocomplete="off">
                                </div>
                                <div class="form-group col-md-5">
                                    <label><?php echo tr('Password'); ?></label>
                                    <input type="password" name="password" id="backup-wizard-password" class="form-control" autocomplete="new-password" placeholder="••••••••">
                                    <small class="form-text text-muted"><?php echo tr('In modifica lascia vuoto per mantenere la password salvata.'); ?></small>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-3">
                                    <label><?php echo tr('Protocollo'); ?></label>
                                    <select name="ssl" id="backup-wizard-ssl" class="form-control">
                                        <option value="0">FTP</option>
                                        <option value="1">FTPS (TLS)</option>
                                    </select>
                                </div>
                                <div class="form-group col-md-3">
                                    <label><?php echo tr('Modalità passiva'); ?></label>
                                    <select name="passive" id="backup-wizard-passive" class="form-control">
                                        <option value="1"><?php echo tr('Sì'); ?></option>
                                        <option value="0"><?php echo tr('No'); ?></option>
                                    </select>
                                </div>
                                <div class="form-group col-md-3">
                                    <label><?php echo tr('Timeout'); ?></label>
                                    <div class="input-group">
                                        <input type="number" name="timeout" id="backup-wizard-timeout" class="form-control" min="1" max="300" value="30">
                                        <div class="input-group-append"><span class="input-group-text">s</span></div>
                                    </div>
                                </div>
                                <div class="form-group col-md-3">
                                    <label><?php echo tr('Cartella remota'); ?></label>
                                    <input type="text" name="path" id="backup-wizard-ftp-path" class="form-control" maxlength="255" value="backups" placeholder="cliente-osm/backups">
                                </div>
                            </div>
                        </div>

                        <div data-backup-wizard-mode="local" style="display:none">
                            <div class="row">
                                <div class="form-group col-md-5">
                                    <label><?php echo tr('Nome'); ?></label>
                                    <input type="text" name="name" id="backup-wizard-local-name" class="form-control" maxlength="255" placeholder="Copia locale secondaria">
                                </div>
                                <div class="form-group col-md-7">
                                    <label><?php echo tr('Cartella locale relativa a OSM'); ?></label>
                                    <input type="text" name="local_directory" id="backup-wizard-local-directory" class="form-control" maxlength="255" value="files/backups-secondary" placeholder="files/backups-secondary">
                                    <small class="form-text text-muted"><?php echo tr('Per sicurezza sono ammessi solo percorsi relativi alla directory di OpenSTAManager.'); ?></small>
                                </div>
                            </div>
                        </div>
                    <?php } ?>

                    <hr>
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label><?php echo tr('Backup da mantenere'); ?></label>
                            <input type="number" name="retention" id="backup-wizard-retention" class="form-control" min="1" max="3650" value="10" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label><?php echo tr('Attiva dopo test riuscito'); ?></label>
                            <select name="enabled_requested" id="backup-wizard-enabled" class="form-control">
                                <option value="1"><?php echo tr('Sì'); ?></option>
                                <option value="0"><?php echo tr('No'); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="alert alert-light border mb-0">
                        <i class="fa fa-shield mr-1"></i>
                        <?php echo tr('Salvando, OSM esegue un test reale di scrittura, lettura ed eliminazione. Se il test fallisce la destinazione resta disattivata. Le password non vengono mostrate nella schermata o nei messaggi di errore.'); ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo tr('Annulla'); ?></button>
                    <button type="submit" class="btn btn-success"><i class="fa fa-plug mr-1"></i><?php echo tr('Salva e testa'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
var backupDestinationConfigs = <?php echo json_encode($wizard_configs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

function refreshBackupWizardMode() {
    var mode = $('#backup-wizard-mode').val();
    $('[data-backup-wizard-mode]').hide();
    $('[data-backup-wizard-mode="' + mode + '"]').show();

    // Disabilita i campi nascosti per evitare valori duplicati con lo stesso name.
    $('[data-backup-wizard-mode] :input').prop('disabled', true);
    $('[data-backup-wizard-mode="' + mode + '"] :input').prop('disabled', false);
}

function resetBackupDestinationWizard() {
    var form = document.getElementById('backup-destination-wizard-form');
    form.reset();
    $('#backup-wizard-id').val('0');
    $('#backup-wizard-mode').val('existing');
    $('#backup-wizard-path').val('backups');
    $('#backup-wizard-ftp-path').val('backups');
    $('#backup-wizard-local-directory').val('files/backups-secondary');
    $('#backup-wizard-retention').val('10');
    $('#backup-wizard-enabled').val('1');
    $('#backup-wizard-port').val('21');
    $('#backup-wizard-timeout').val('30');
    $('#backup-wizard-passive').val('1');
    $('#backup-wizard-ssl').val('0');
    $('#backup-wizard-password').val('');
}

function openBackupDestinationWizard(id) {
    resetBackupDestinationWizard();

    var config = backupDestinationConfigs[id] || null;
    if (config) {
        $('#backup-wizard-id').val(config.id || id);
        $('#backup-wizard-mode').val(config.mode || 'existing');
        $('#backup-wizard-id-adapter').val(config.id_adapter || '');
        $('#backup-wizard-path').val(config.path || '');
        $('#backup-wizard-ftp-path').val(config.path || '');
        $('#backup-wizard-name').val(config.name || '');
        $('#backup-wizard-local-name').val(config.name || '');
        $('#backup-wizard-host').val(config.host || '');
        $('#backup-wizard-port').val(config.port || 21);
        $('#backup-wizard-username').val(config.username || '');
        $('#backup-wizard-password').val('');
        $('#backup-wizard-ssl').val(config.ssl ? '1' : '0');
        $('#backup-wizard-passive').val(config.passive === false ? '0' : '1');
        $('#backup-wizard-timeout').val(config.timeout || 30);
        $('#backup-wizard-local-directory').val(config.local_directory || 'files/backups-secondary');
        $('#backup-wizard-retention').val(config.retention || 10);
        $('#backup-wizard-enabled').val(config.enabled_requested ? '1' : '0');
    }

    refreshBackupWizardMode();
    $('#backup-destination-wizard').modal('show');
}

$(function () {
    refreshBackupWizardMode();
});
</script>
