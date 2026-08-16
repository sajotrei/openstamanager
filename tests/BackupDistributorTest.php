<?php

use Modules\Backups\BackupDestination;
use Modules\Backups\BackupDistributor;
use Modules\FileAdapters\Adapters\LocalAdapter;
use Modules\FileAdapters\FileAdapter;
use PHPUnit\Framework\TestCase;

class BackupDistributorTest extends TestCase
{
    protected string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = 'tmp/backup-distributor-'.bin2hex(random_bytes(6));
        mkdir(base_dir().'/'.$this->root, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory(base_dir().'/'.$this->root);

        parent::tearDown();
    }

    public function testDestinationReadWriteCheck(): void
    {
        $result = BackupDistributor::test($this->destination());

        $this->assertTrue($result['success']);
        $this->assertDirectoryExists(base_dir().'/'.$this->root.'/remote');
        $this->assertSame([], glob(base_dir().'/'.$this->root.'/remote/.osm-backup-test-*') ?: []);
    }

    public function testBackupIsDistributedAndVerified(): void
    {
        $source = $this->createBackup('OSM backup 2026-08-17 00_00_01 FULL.zip', 'backup-content');
        $result = BackupDistributor::distributeTo($source, $this->destination());

        $this->assertTrue($result['success']);
        $this->assertSame(strlen('backup-content'), $result['size']);
        $this->assertFileExists(base_dir().'/'.$this->root.'/remote/'.basename($source));
        $this->assertSame('backup-content', file_get_contents(base_dir().'/'.$this->root.'/remote/'.basename($source)));
    }

    public function testRetentionRemovesOldestBackups(): void
    {
        $destination = $this->destination(2);
        $names = [
            'OSM backup 2026-08-17 00_00_01 FULL.zip',
            'OSM backup 2026-08-17 00_00_02 FULL.zip',
            'OSM backup 2026-08-17 00_00_03 FULL.zip',
        ];

        foreach ($names as $index => $name) {
            $source = $this->createBackup($name, 'backup-'.$index);
            $result = BackupDistributor::distributeTo($source, $destination);
            $this->assertTrue($result['success']);
        }

        $this->assertFileDoesNotExist(base_dir().'/'.$this->root.'/remote/'.$names[0]);
        $this->assertFileExists(base_dir().'/'.$this->root.'/remote/'.$names[1]);
        $this->assertFileExists(base_dir().'/'.$this->root.'/remote/'.$names[2]);
    }

    public function testMissingAdapterDoesNotThrow(): void
    {
        $source = $this->createBackup('OSM backup 2026-08-17 00_00_01 FULL.zip', 'backup-content');
        $destination = new BackupDestination();
        $destination->path = 'remote';
        $destination->retention = 2;

        $result = BackupDistributor::distributeTo($source, $destination);

        $this->assertFalse($result['success']);
    }

    protected function destination(int $retention = 10): BackupDestination
    {
        $adapter = new FileAdapter();
        $adapter->name = 'Test locale';
        $adapter->class = LocalAdapter::class;
        $adapter->options = json_encode(['directory' => $this->root]);

        $destination = new BackupDestination();
        $destination->path = 'remote';
        $destination->retention = $retention;
        $destination->setRelation('adapter', $adapter);

        return $destination;
    }

    protected function createBackup(string $name, string $contents): string
    {
        $path = base_dir().'/'.$this->root.'/'.$name;
        file_put_contents($path, $contents);

        return $path;
    }

    protected function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($path);
    }
}
