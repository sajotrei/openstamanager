<?php

use Models\Cache;
use Models\Module;
use Modules\Backups\BackupRetryService;
use Modules\Backups\BackupTask;
use Tasks\Task;

$esc = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

try {
    $task = Task::where('class', BackupTask::class)->first();
    $named_task = $task ?: Task::where('name', 'Backup automatico')->first();
    $task_count = Task::where('class', BackupTask::class)->count();
    $task_module = Module::where('name', 'Gestione task')->first();
    $last_cron = Cache::where('name', 'Ultima esecuzione del cron')->first()?->content;
    $pending_retries = BackupRetryService::pendingRetryCount(false);
    $due_retries = BackupRetryService::pendingRetryCount(true);
} catch (Throwable) {
    $task = $named_task = $task_module = null;
    $task_count = 0;
    $last_cron = null;
    $pending_retries = $due_retries = 0;
}

$automatic_enabled = (bool) setting('Backup automatico');
$binding_ok = !empty($task) && $task_count === 1;
$task_enabled = $binding_ok && (bool) $task->enabled;
$expression = $named_task?->expression ?: '-';
$expression_label = $expression === '0 1 * * *' ? tr('Ogni giorno alle 01:00') : $expression;
$last_execution = !empty($named_task?->last_executed_at) ? $named_task->last_executed_at->format('d/m/Y H:i') : '-';
$next_execution = !empty($named_task?->next_execution_at) ? $named_task->next_execution_at->format('d/m/Y H:i') : '-';
$task_url = !empty($task_module?->id) && !empty($named_task?->id)
    ? base_path_osm().'/editor.php?id_module='.(int) $task_module->id.'&id_record='.(int) $named_task->id
    : null;
?>

<div class="row mt-3">
    <div class="col-md-12">
        <div class="card card-outline card-info">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-clock-o mr-2"></i><?php echo tr('Automazione backup'); ?></h3>
                <div class="card-tools">
                    <?php if ($binding_ok && $automatic_enabled && $task_enabled) { ?>
                        <span class="badge badge-success"><?php echo tr('Operativa'); ?></span>
                    <?php } else { ?>
                        <span class="badge badge-warning"><?php echo tr('Da verificare'); ?></span>
                    <?php } ?>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-3 col-md-6 mb-2">
                        <small class="text-muted d-block"><?php echo tr('Backup automatico'); ?></small>
                        <strong><?php echo $automatic_enabled ? tr('Attivo') : tr('Disattivato'); ?></strong>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-2">
                        <small class="text-muted d-block"><?php echo tr('Task OSM'); ?></small>
                        <strong><?php echo $task_enabled ? tr('Attivo') : tr('Non attivo'); ?></strong>
                        <?php if (!$binding_ok) { ?><span class="text-warning ml-1"><i class="fa fa-exclamation-triangle"></i></span><?php } ?>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-2">
                        <small class="text-muted d-block"><?php echo tr('Pianificazione'); ?></small>
                        <strong><?php echo $esc($expression_label); ?></strong>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-2">
                        <small class="text-muted d-block"><?php echo tr('Repliche da ritentare'); ?></small>
                        <strong><?php echo (int) $pending_retries; ?></strong>
                        <?php if ($due_retries > 0) { ?><span class="badge badge-warning ml-1"><?php echo (int) $due_retries; ?> <?php echo tr('dovute'); ?></span><?php } ?>
                    </div>
                </div>
                <hr class="my-2">
                <div class="row">
                    <div class="col-lg-4 col-md-6 mb-2">
                        <small class="text-muted d-block"><?php echo tr('Ultima esecuzione task'); ?></small>
                        <span><?php echo $esc($last_execution); ?></span>
                    </div>
                    <div class="col-lg-4 col-md-6 mb-2">
                        <small class="text-muted d-block"><?php echo tr('Prossima esecuzione task'); ?></small>
                        <span><?php echo $esc($next_execution); ?></span>
                    </div>
                    <div class="col-lg-4 col-md-12 mb-2">
                        <small class="text-muted d-block"><?php echo tr('Ultima esecuzione cron OSM'); ?></small>
                        <span><?php echo $esc($last_cron ?: '-'); ?></span>
                    </div>
                </div>

                <?php if (!$binding_ok) { ?>
                    <div class="alert alert-warning mt-2 mb-2">
                        <i class="fa fa-exclamation-triangle mr-1"></i>
                        <?php echo tr('Il task Backup automatico non risulta collegato in modo univoco alla classe Modules\\Backups\\BackupTask. Verifica Gestione task prima di affidarti al backup automatico.'); ?>
                    </div>
                <?php } ?>

                <p class="text-muted mb-2">
                    <?php echo tr('La multi-destinazione riutilizza il task Backup automatico già presente in OSM: non viene creato un secondo scheduler. I retry rispettano il backoff e vengono eseguiti alla prima esecuzione utile del task.'); ?>
                </p>

                <?php if ($task_url && $structure->permission === 'rw') { ?>
                    <a class="btn btn-sm btn-outline-info" href="<?php echo $esc($task_url); ?>">
                        <i class="fa fa-calendar mr-1"></i><?php echo tr('Gestisci pianificazione'); ?>
                    </a>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
