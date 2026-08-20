<?php

use Modules\Backups\BackupDestination;
use Modules\FileAdapters\FileAdapter;

try {
    $destinations = BackupDestination::with('adapter')->orderBy('id')->get();
    $primary_adapter = Backup::getStorageAdapter();
    $adapters = FileAdapter::orderBy('name')->get()->filter(function ($adapter) use ($primary_adapter) {
        return empty($primary_adapter) || (int) $adapter->id !== (int) $primary_adapter->id;
    });
} catch (Throwable) {
    return;
}

$esc = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>

<div class="row mt-3">
    <div class="col-md-12">
        <div class="card card-outline card-secondary">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-copy mr-2"></i><?php echo tr('Destinazioni backup'); ?>
                </h3>
            </div>
            <div class="card-body">
                <p class="text-muted">
                    <?php echo tr('Il backup principale viene creato una sola volta e successivamente copiato nelle destinazioni secondarie abilitate. Il fallimento di una destinazione non elimina il backup principale.'); ?>
                </p>

                <?php if ($destinations->isEmpty()) { ?>
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i>
                        <?php echo tr('Nessuna destinazione secondaria configurata.'); ?>
                    </div>
                <?php } else { ?>
                    <div class="table-responsive mb-4">
                        <table class="table table-striped table-sm">
                            <thead>
                                <tr>
                                    <th><?php echo tr('Destinazione'); ?></th>
                                    <th><?php echo tr('Tipo'); ?></th>
                                    <th><?php echo tr('Percorso'); ?></th>
                                    <th class="text-center"><?php echo tr('Retention'); ?></th>
                                    <th><?php echo tr('Ultimo esito'); ?></th>
                                    <th class="text-center"><?php echo tr('Stato'); ?></th>
                                    <th class="text-right"><?php echo tr('Azioni'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($destinations as $destination) {
                                $adapter = $destination->adapter;
                                $adapter_type = $adapter ? basename(str_replace('\\', '/', (string) $adapter->class)) : '-';
                            ?>
                                <tr>
                                    <td><?php echo $esc($adapter?->name ?? tr('Adattatore non disponibile')); ?></td>
                                    <td><?php echo $esc($adapter_type); ?></td>
                                    <td><code><?php echo $esc($destination->path ?: '/'); ?></code></td>
                                    <td class="text-center"><?php echo (int) $destination->retention; ?></td>
                                    <td>
                                        <?php if (!empty($destination->last_error)) { ?>
                                            <span class="badge badge-warning" title="<?php echo $esc($destination->last_error); ?>"><?php echo tr('Da ritentare'); ?></span>
                                        <?php } elseif (!empty($destination->last_success_at)) { ?>
                                            <span class="badge badge-success"><?php echo tr('OK'); ?></span>
                                            <small class="text-muted"><?php echo $esc($destination->last_success_at); ?></small>
                                        <?php } else { ?>
                                            <span class="badge badge-secondary"><?php echo tr('Non testata'); ?></span>
                                        <?php } ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($destination->enabled) { ?>
                                            <span class="badge badge-success"><?php echo tr('Attiva'); ?></span>
                                        <?php } else { ?>
                                            <span class="badge badge-secondary"><?php echo tr('Disattivata'); ?></span>
                                        <?php } ?>
                                    </td>
                                    <td class="text-right text-nowrap">
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="op" value="backup_destination_test">
                                            <input type="hidden" name="id" value="<?php echo (int) $destination->id; ?>">
                                            <button type="submit" class="btn btn-sm btn-info" title="<?php echo tr('Test connessione'); ?>"><i class="fa fa-plug"></i></button>
                                        </form>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="op" value="backup_destination_toggle">
                                            <input type="hidden" name="id" value="<?php echo (int) $destination->id; ?>">
                                            <button type="submit" class="btn btn-sm btn-secondary" title="<?php echo $destination->enabled ? tr('Disattiva') : tr('Attiva'); ?>"><i class="fa <?php echo $destination->enabled ? 'fa-pause' : 'fa-play'; ?>"></i></button>
                                        </form>
                                        <button type="button" class="btn btn-sm btn-warning" data-toggle="collapse" data-target="#backup-destination-<?php echo (int) $destination->id; ?>" title="<?php echo tr('Modifica'); ?>"><i class="fa fa-pencil"></i></button>
                                        <form method="post" class="d-inline" onsubmit="return confirm('<?php echo $esc(tr('Eliminare questa destinazione di backup?')); ?>');">
                                            <input type="hidden" name="op" value="backup_destination_delete">
                                            <input type="hidden" name="id" value="<?php echo (int) $destination->id; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="<?php echo tr('Elimina'); ?>"><i class="fa fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <tr class="collapse" id="backup-destination-<?php echo (int) $destination->id; ?>">
                                    <td colspan="7">
                                        <form method="post" class="form-row align-items-end">
                                            <input type="hidden" name="op" value="backup_destination_update">
                                            <input type="hidden" name="id" value="<?php echo (int) $destination->id; ?>">
                                            <div class="form-group col-md-4">
                                                <label><?php echo tr('Adattatore'); ?></label>
                                                <select name="id_adapter" class="form-control" required>
                                                    <?php foreach ($adapters as $item) { ?>
                                                        <option value="<?php echo (int) $item->id; ?>" <?php echo (int) $item->id === (int) $destination->id_adapter ? 'selected' : ''; ?>><?php echo $esc($item->name); ?></option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                            <div class="form-group col-md-3">
                                                <label><?php echo tr('Percorso relativo'); ?></label>
                                                <input type="text" name="path" class="form-control" maxlength="255" value="<?php echo $esc($destination->path); ?>" placeholder="backups">
                                            </div>
                                            <div class="form-group col-md-2">
                                                <label><?php echo tr('Retention'); ?></label>
                                                <input type="number" name="retention" class="form-control" min="1" value="<?php echo (int) $destination->retention; ?>" required>
                                            </div>
                                            <div class="form-group col-md-1">
                                                <label><?php echo tr('Attiva'); ?></label>
                                                <select name="enabled" class="form-control">
                                                    <option value="1" <?php echo $destination->enabled ? 'selected' : ''; ?>><?php echo tr('Sì'); ?></option>
                                                    <option value="0" <?php echo !$destination->enabled ? 'selected' : ''; ?>><?php echo tr('No'); ?></option>
                                                </select>
                                            </div>
                                            <div class="form-group col-md-2 text-right">
                                                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> <?php echo tr('Salva'); ?></button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>

                <div class="card bg-light mb-0">
                    <div class="card-header"><strong><?php echo tr('Aggiungi destinazione'); ?></strong></div>
                    <div class="card-body">
                        <?php if ($adapters->isEmpty()) { ?>
                            <div class="alert alert-warning mb-0"><?php echo tr('Non sono disponibili adattatori secondari. Configura prima un adattatore di archiviazione da Strumenti.'); ?></div>
                        <?php } else { ?>
                            <form method="post" class="form-row align-items-end">
                                <input type="hidden" name="op" value="backup_destination_add">
                                <div class="form-group col-md-4">
                                    <label><?php echo tr('Adattatore'); ?></label>
                                    <select name="id_adapter" class="form-control" required>
                                        <option value=""><?php echo tr('Seleziona...'); ?></option>
                                        <?php foreach ($adapters as $adapter) { ?>
                                            <option value="<?php echo (int) $adapter->id; ?>"><?php echo $esc($adapter->name); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="form-group col-md-3">
                                    <label><?php echo tr('Percorso relativo'); ?></label>
                                    <input type="text" name="path" class="form-control" maxlength="255" value="backups" placeholder="backups">
                                </div>
                                <div class="form-group col-md-2">
                                    <label><?php echo tr('Retention'); ?></label>
                                    <input type="number" name="retention" class="form-control" min="1" value="10" required>
                                </div>
                                <div class="form-group col-md-1">
                                    <label><?php echo tr('Attiva'); ?></label>
                                    <select name="enabled" class="form-control">
                                        <option value="1"><?php echo tr('Sì'); ?></option>
                                        <option value="0"><?php echo tr('No'); ?></option>
                                    </select>
                                </div>
                                <div class="form-group col-md-2 text-right">
                                    <button type="submit" class="btn btn-success"><i class="fa fa-plus"></i> <?php echo tr('Aggiungi'); ?></button>
                                </div>
                            </form>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
