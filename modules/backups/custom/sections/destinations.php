<?php

use Modules\Backups\BackupAdapterService;
use Modules\Backups\BackupDestination;
use Modules\FileAdapters\Adapters\FTPAdapter;
use Modules\FileAdapters\Adapters\LocalAdapter;
use Modules\FileAdapters\FileAdapter;

try {
    $destinations = BackupDestination::with('adapter')->orderBy('id')->get();
    $primary_adapter = Backup::getStorageAdapter();
    $adapters = FileAdapter::orderBy('name')->get()->filter(function ($adapter) use ($primary_adapter) {
        return empty($primary_adapter) || (int) $adapter->id !== (int) $primary_adapter->id;
    });
    $can_manage_adapters = BackupAdapterService::canManageAdapters();
} catch (Throwable) {
    return;
}

$esc = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$formatDate = static function ($value): string {
    if (empty($value)) {
        return '-';
    }

    try {
        return $value instanceof DateTimeInterface ? $value->format('d/m/Y H:i') : (new DateTime((string) $value))->format('d/m/Y H:i');
    } catch (Throwable) {
        return (string) $value;
    }
};
$wizard_configs = [];

include __DIR__.'/destination_list.php';
include __DIR__.'/destination_wizard.php';
