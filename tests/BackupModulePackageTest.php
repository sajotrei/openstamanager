<?php

use PHPUnit\Framework\TestCase;

class BackupModulePackageTest extends TestCase
{
    public function testModuleDescriptorTargetsOsm2104(): void
    {
        $module = parse_ini_file(__DIR__.'/../modules/backups/MODULE');

        $this->assertSame('Backup', $module['name'] ?? null);
        $this->assertSame('1.1.0', $module['version'] ?? null);
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
            'update/1_1.php',
            'update/tables.php',
        ];

        foreach ($required as $file) {
            $this->assertFileExists(__DIR__.'/../modules/backups/'.$file);
        }

        $this->assertFileDoesNotExist(__DIR__.'/../modules/backups/update/1_0.php');
    }

    public function testMigrationSupportsCleanInstallAndLegacyUpgrade(): void
    {
        $sql = file_get_contents(__DIR__.'/../modules/backups/update/1_0.sql');
        $script = file_get_contents(__DIR__.'/../modules/backups/update/1_1.php');
        $tables = include __DIR__.'/../modules/backups/update/tables.php';

        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `zz_backup_destinations`', $sql);
        $this->assertStringContainsString('unique_backup_destination_adapter_path', $sql);
        $this->assertStringContainsString('zz_backup_destinations_ibfk_1', $sql);
        $this->assertStringContainsString('SHOW COLUMNS FROM `zz_backup_destinations`', $script);
        $this->assertStringContainsString("'last_success_file'", $script);
        $this->assertStringContainsString('idx_backup_destination_last_success_file', $script);
        $this->assertSame(['zz_backup_destinations'], $tables);
    }

    public function testCustomActionsDelegateOriginalBackupOperationsAndProtectWrites(): void
    {
        $actions = file_get_contents(__DIR__.'/../modules/backups/custom/actions.php');

        $this->assertStringContainsString("include dirname(__DIR__).'/actions.php';", $actions);
        $this->assertStringContainsString("'backup_destination_test'", $actions);
        $this->assertStringContainsString('$structure->permission !== \'rw\'', $actions);
        $this->assertStringContainsString("\$_SERVER['REQUEST_METHOD']", $actions);
        $this->assertStringContainsString("!== 'POST'", $actions);
    }

    public function testBackupManagerContainsCollisionGuardBeforeCreation(): void
    {
        $manager = file_get_contents(__DIR__.'/../modules/backups/custom/src/BackupManager.php');

        $collision = strpos($manager, 'self::hasCollision($expected_backup)');
        $creation = strpos($manager, '$created = (bool) $creator();');

        $this->assertNotFalse($collision);
        $this->assertNotFalse($creation);
        $this->assertLessThan($creation, $collision);
    }

    public function testDestinationModelRejectsMassAssignmentByDefault(): void
    {
        $model = file_get_contents(__DIR__.'/../modules/backups/custom/src/BackupDestination.php');

        $this->assertStringContainsString("protected \$guarded = ['*'];", $model);
    }

    public function testDistributorStreamsBackupAndCachesPrimaryAdapterLookup(): void
    {
        $distributor = file_get_contents(__DIR__.'/../modules/backups/custom/src/BackupDistributor.php');

        $this->assertStringContainsString('writeStream(', $distributor);
        $this->assertStringNotContainsString('file_get_contents($backup_path', $distributor);
        $this->assertStringContainsString('primary_adapter_resolved', $distributor);
        $this->assertStringContainsString('getPrimaryAdapterId()', $distributor);
    }
}
