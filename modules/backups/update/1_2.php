<?php

if (!$database->tableExists('zz_backup_destinations')) {
    return;
}

$columns = array_column($database->fetchArray('SHOW COLUMNS FROM `zz_backup_destinations`'), 'Field');
$missing_columns = [
    'managed_adapter' => '`managed_adapter` TINYINT(1) NOT NULL DEFAULT 0 AFTER `retention`',
    'retry_count' => '`retry_count` INT(11) NOT NULL DEFAULT 0 AFTER `last_error`',
    'next_retry_at' => '`next_retry_at` DATETIME NULL DEFAULT NULL AFTER `retry_count`',
    'last_test_at' => '`last_test_at` DATETIME NULL DEFAULT NULL AFTER `next_retry_at`',
    'last_test_success' => '`last_test_success` TINYINT(1) NULL DEFAULT NULL AFTER `last_test_at`',
    'last_test_error' => '`last_test_error` TEXT NULL AFTER `last_test_success`',
];

foreach ($missing_columns as $column => $definition) {
    if (!in_array($column, $columns, true)) {
        $database->query('ALTER TABLE `zz_backup_destinations` ADD COLUMN '.$definition);
    }
}

$indexes = array_column($database->fetchArray('SHOW INDEX FROM `zz_backup_destinations`'), 'Key_name');
if (!in_array('idx_backup_destination_next_retry_at', $indexes, true)) {
    $database->query('ALTER TABLE `zz_backup_destinations` ADD KEY `idx_backup_destination_next_retry_at` (`next_retry_at`)');
}
