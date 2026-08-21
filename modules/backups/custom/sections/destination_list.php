<?php
// Rendering lista destinazioni; variabili preparate da destinations.php.
?>
<div class="row mt-3">
    <div class="col-md-12">
        <div class="card card-outline card-secondary">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-copy mr-2"></i><?php echo tr('Destinazioni backup'); ?></h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-sm btn-success" onclick="openBackupDestinationWizard(0)">
                        <i class="fa fa-plus mr-1"></i><?php echo tr('Aggiungi destinazione'); ?>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted">
                    <?php echo tr('Il backup principale viene creato una sola volta e successivamente copiato nelle destinazioni secondarie abilitate. Il fallimento di una destinazione non elimina il backup principale.'); ?>
                </p>

                <?php if ($destinations->isEmpty()) { ?>
                    <div class="alert alert-info mb-0">
                        <i class="fa fa-info-circle mr-1"></i>
                        <?php echo tr('Nessuna destinazione secondaria configurata. Usa “Aggiungi destinazione” per configurare FTP/FTPS o una cartella locale senza scrivere JSON.'); ?>
                    </div>
                <?php } else { ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-sm mb-0">
                            <thead>
                                <tr>
                                    <th><?php echo tr('Destinazione'); ?></th>
                                    <th><?php echo tr('Connessione'); ?></th>
                                    <th><?php echo tr('Replica'); ?></th>
                                    <th><?php echo tr('Ultimo successo'); ?></th>
                                    <th class="text-center"><?php echo tr('Retention'); ?></th>
                                    <th class="text-right"><?php echo tr('Azioni'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($destinations as $destination) {
                                $adapter = $destination->adapter;
                                $config = BackupAdapterService::describe($destination);
                                $config['id'] = (int) $destination->id;
                                $config['retention'] = (int) $destination->retention;
                                $config['enabled_requested'] = (bool) $destination->enabled;
                                $wizard_configs[(int) $destination->id] = $config;

                                $adapter_type = tr('Non disponibile');
                                if (!empty($adapter)) {
                                    if (is_a($adapter->class, FTPAdapter::class, true)) {
                                        $options = json_decode((string) $adapter->options, true);
                                        $adapter_type = !empty($options['ssl']) ? 'FTPS' : 'FTP';
                                    } elseif (is_a($adapter->class, LocalAdapter::class, true)) {
                                        $adapter_type = tr('Locale OSM');
                                    } else {
                                        $adapter_type = basename(str_replace('\\', '/', (string) $adapter->class));
                                    }
                                }

                                if ($destination->last_test_success === true) {
                                    $connection_badge = 'success';
                                    $connection_text = tr('Test OK');
                                } elseif ($destination->last_test_success === false) {
                                    $connection_badge = 'danger';
                                    $connection_text = tr('Test fallito');
                                } else {
                                    $connection_badge = 'secondary';
                                    $connection_text = tr('Non testata');
                                }

                                if (!$destination->enabled) {
                                    $replica_badge = 'secondary';
                                    $replica_text = tr('Disattivata');
                                } elseif (!empty($destination->last_error)) {
                                    $replica_badge = 'warning';
                                    $replica_text = tr('Da ritentare');
                                } elseif (!empty($destination->last_success_at)) {
                                    $replica_badge = 'success';
                                    $replica_text = tr('OK');
                                } else {
                                    $replica_badge = 'info';
                                    $replica_text = tr('In attesa');
                                }

                                $can_edit = !$destination->managed_adapter || $can_manage_adapters;
                            ?>
                                <tr>
                                    <td>
                                        <strong><?php echo $esc($adapter?->name ?? tr('Adattatore non disponibile')); ?></strong>
                                        <div class="text-muted small">
                                            <?php echo $esc($adapter_type); ?>
                                            <?php if ($destination->path !== '') { ?> · <?php echo $esc($destination->path); ?><?php } ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $connection_badge; ?>"><?php echo $connection_text; ?></span>
                                        <div class="text-muted small"><?php echo $esc($formatDate($destination->last_test_at)); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $replica_badge; ?>"><?php echo $replica_text; ?></span>
                                        <?php if (!empty($destination->last_error) && !empty($destination->next_retry_at)) { ?>
                                            <div class="text-muted small"><?php echo tr('Retry'); ?>: <?php echo $esc($formatDate($destination->next_retry_at)); ?> · #<?php echo (int) $destination->retry_count; ?></div>
                                        <?php } elseif (!empty($destination->last_success_file)) { ?>
                                            <div class="text-muted small text-truncate" style="max-width:260px" title="<?php echo $esc($destination->last_success_file); ?>"><?php echo $esc($destination->last_success_file); ?></div>
                                        <?php } ?>
                                    </td>
                                    <td><?php echo $esc($formatDate($destination->last_success_at)); ?></td>
                                    <td class="text-center"><?php echo (int) $destination->retention; ?></td>
                                    <td class="text-right text-nowrap">
                                        <div class="dropdown d-inline-block">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown">
                                                <?php echo tr('Azioni'); ?>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-right">
                                                <?php if ($can_edit) { ?>
                                                    <button type="button" class="dropdown-item" onclick="openBackupDestinationWizard(<?php echo (int) $destination->id; ?>)">
                                                        <i class="fa fa-pencil mr-2"></i><?php echo tr('Modifica'); ?>
                                                    </button>
                                                <?php } ?>
                                                <form method="post">
                                                    <input type="hidden" name="op" value="backup_destination_test">
                                                    <input type="hidden" name="id" value="<?php echo (int) $destination->id; ?>">
                                                    <button type="submit" class="dropdown-item"><i class="fa fa-plug mr-2"></i><?php echo tr('Test connessione'); ?></button>
                                                </form>
                                                <?php if (!empty($destination->last_error)) { ?>
                                                    <form method="post">
                                                        <input type="hidden" name="op" value="backup_destination_retry">
                                                        <input type="hidden" name="id" value="<?php echo (int) $destination->id; ?>">
                                                        <button type="submit" class="dropdown-item"><i class="fa fa-refresh mr-2"></i><?php echo tr('Riprova ora'); ?></button>
                                                    </form>
                                                <?php } ?>
                                                <form method="post">
                                                    <input type="hidden" name="op" value="backup_destination_toggle">
                                                    <input type="hidden" name="id" value="<?php echo (int) $destination->id; ?>">
                                                    <button type="submit" class="dropdown-item">
                                                        <i class="fa <?php echo $destination->enabled ? 'fa-pause' : 'fa-play'; ?> mr-2"></i><?php echo $destination->enabled ? tr('Disattiva') : tr('Attiva'); ?>
                                                    </button>
                                                </form>
                                                <div class="dropdown-divider"></div>
                                                <form method="post" onsubmit="return confirm('<?php echo $esc(tr('Eliminare questa destinazione di backup?')); ?>');">
                                                    <input type="hidden" name="op" value="backup_destination_delete">
                                                    <input type="hidden" name="id" value="<?php echo (int) $destination->id; ?>">
                                                    <button type="submit" class="dropdown-item text-danger"><i class="fa fa-trash mr-2"></i><?php echo tr('Elimina'); ?></button>
                                                </form>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
