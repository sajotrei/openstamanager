<?php

use PHPUnit\Framework\TestCase;

class BackupModulePackageTest extends TestCase
{
    public function testModuleDescriptorTargetsOsm2104(): void
    {
        $module = parse_ini_file(__DIR__.'/../modules/backups/MODULE');

        $this->assertSame('Backup', $module['name'] ?? null);
        $this->assertSame('1.0', $module['version'] ?? null);
        $this->assertSame('2.10.4', $module['compatibility'] ?? null);
        $this->assertSame('backups', $module['directory'] ?? null);
    }

    public function testCustomLayerContainsOnlyModuleOverrides(): void
    {
        $required = [
            'custom/actions.php',
            'custom/controller_before.php',
            'custom/controller_after.php',
            'custom/sections/destinations.php',
            'custom/src/BackupDestination.php',
            'custom/src/BackupDistributor.php',
            'custom/src/BackupManager.php',
            'custom/src/BackupTask.php',
            'update/1_0.sql',
            'update/tables.php',
        ];

        foreach ($required as $file) {
            $this->assertFileExists(__DIR__.'/../modules/backups/'.$file);
        }
    }

    public function testMigrationRegistersOnlyModuleTable(): void
    {
        $sql = file_get_contents(__DIR__.'/../modules/backups/update/1_0.sql');
        $tables = include __DIR__.'/../modules/backups/update/tables.php';

        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `zz_backup_destinations`', $sql);
        $this->assertStringContainsString('unique_backup_destination_adapter_path', $sql);
        $this->assertStringContainsString('zz_backup_destinations_ibfk_1', $sql);
        $this->assertSame(['zz_backup_destinations'], $tables);
    }

    public function testCustomActionsDelegateOriginalBackupOperations(): void
    {
        $actions = file_get_contents(__DIR__.'/../modules/backups/custom/actions.php');

        $this->assertStringContainsString("include dirname(__DIR__).'/actions.php';", $actions);
        $this->assertStringContainsString("'backup_destination_test'", $actions);
    }
}
