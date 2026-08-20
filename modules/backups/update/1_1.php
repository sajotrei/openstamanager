<?php

if (!$database->tableExists('zz_backup_destinations')) {
    return;
}

$columns = array_column($database->fetchArray('SHOW COLUMNS FROM `zz_backup_destinations`'), 'Field');
$missing_columns = [
    'last_success_file' => '`last_success_file` VARCHAR(255) NULL DEFAULT NULL AFTER `retention`',
    'last_attempt_at' => '`last_attempt_at` DATETIME NULL DEFAULT NULL AFTER `last_success_file`',
    'last_success_at' => '`last_success_at` DATETIME NULL DEFAULT NULL AFTER `last_attempt_at`',
    'last_error' => '`last_error` TEXT NULL AFTER `last_success_at`',
];

foreach ($missing_columns as $column => $definition) {
    if (!in_array($column, $columns, true)) {
        $database->query('ALTER TABLE `zz_backup_destinations` ADD COLUMN '.$definition);
    }
}

$indexes = array_column($database->fetchArray('SHOW INDEX FROM `zz_backup_destinations`'), 'Key_name');
if (!in_array('idx_backup_destination_last_success_file', $indexes, true)) {
    $database->query('ALTER TABLE `zz_backup_destinations` ADD KEY `idx_backup_destination_last_success_file` (`last_success_file`)');
}
