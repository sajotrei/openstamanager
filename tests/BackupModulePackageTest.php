<?php

use PHPUnit\Framework\TestCase;

class BackupModulePackageTest extends TestCase
{
    public function testModuleDescriptorTargetsOsm2104(): void
    {
        $module = parse_ini_file(__DIR__.'/../modules/backups/MODULE');

        $this->assertSame('Backup', $module['name'] ?? null);
        $this->assertSame('1.2.0', $module['version'] ?? null);
        $this->assertSame('2.10.4', $module['compatibility'] ?? null);
        $this->assertSame('backups', $module['directory'] ?? null);
    }

    public function testP0CustomLayerFilesArePresent(): void
    {
        $required = [
            'custom/actions.php',
            'custom/controller_before.php',
            'custom/controller_after.php',
            'custom/sections/automation.php',
            'custom/sections/destinations.php',
            'custom/sections/destination_list.php',
            'custom/sections/destination_wizard.php',
            'custom/src/BackupAdapterService.php',
            'custom/src/BackupDestination.php',
            'custom/src/BackupDistributor.php',
            'custom/src/BackupManager.php',
            'custom/src/BackupRetryService.php',
            'custom/src/BackupTask.php',
            'update/1_0.sql',
            'update/1_1.php',
            'update/1_2.php',
            'update/tables.php',
        ];

        foreach ($required as $file) {
            $this->assertFileExists(__DIR__.'/../modules/backups/'.$file);
        }
    }

    public function testP0MigrationAddsRetryAndTestStateWithoutSecondTask(): void
    {
        $script = file_get_contents(__DIR__.'/../modules/backups/update/1_2.php');

        foreach (['managed_adapter', 'retry_count', 'next_retry_at', 'last_test_at', 'last_test_success', 'last_test_error'] as $column) {
            $this->assertStringContainsString("'{$column}'", $script);
        }
        $this->assertStringContainsString('idx_backup_destination_next_retry_at', $script);
        $this->assertStringNotContainsString('INSERT INTO `zz_tasks`', $script);
    }

    public function testWizardDoesNotExposeRawAdapterJson(): void
    {
        $section = file_get_contents(__DIR__.'/../modules/backups/custom/sections/destination_wizard.php');

        $this->assertStringContainsString('FTP / FTPS guidato', $section);
        $this->assertStringContainsString('Salva e testa', $section);
        $this->assertStringNotContainsString('name="options"', $section);
        $this->assertStringNotContainsString('formato JSON', $section);
        $this->assertStringNotContainsString('password\' =>', $section);
    }

    public function testAutomationCardReusesNativeBackupTask(): void
    {
        $automation = file_get_contents(__DIR__.'/../modules/backups/custom/sections/automation.php');
        $task = file_get_contents(__DIR__.'/../modules/backups/custom/src/BackupTask.php');

        $this->assertStringContainsString("Task::where('class', BackupTask::class)", $automation);
        $this->assertStringContainsString('Gestisci pianificazione', $automation);
        $this->assertStringNotContainsString('function needsExecution', $task);
    }

    public function testCustomActionsProtectWritesAndOfferManualRetry(): void
    {
        $actions = file_get_contents(__DIR__.'/../modules/backups/custom/actions.php');

        $this->assertStringContainsString("include dirname(__DIR__).'/actions.php';", $actions);
        $this->assertStringContainsString("'backup_destination_wizard_save'", $actions);
        $this->assertStringContainsString("'backup_destination_retry'", $actions);
        $this->assertStringContainsString('$structure->permission !== \'rw\'', $actions);
        $this->assertStringContainsString("\$_SERVER['REQUEST_METHOD']", $actions);
        $this->assertStringContainsString("!== 'POST'", $actions);
    }

    public function testDistributorStreamsAndRetryServiceOwnsBackoff(): void
    {
        $distributor = file_get_contents(__DIR__.'/../modules/backups/custom/src/BackupDistributor.php');
        $retry = file_get_contents(__DIR__.'/../modules/backups/custom/src/BackupRetryService.php');
        $manager = file_get_contents(__DIR__.'/../modules/backups/custom/src/BackupManager.php');

        $this->assertStringContainsString('writeStream(', $distributor);
        $this->assertStringNotContainsString('file_get_contents($backup_path', $distributor);
        $this->assertStringContainsString('primary_adapter_resolved', $distributor);
        $this->assertStringContainsString('retryDelaySeconds', $retry);
        $this->assertStringContainsString('next_retry_at', $retry);
        $this->assertStringContainsString('last_test_success', $retry);
        $this->assertStringContainsString('BackupRetryService::distribute($backup)', $manager);
    }
}
