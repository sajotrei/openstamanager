<?php

use Modules\Backups\BackupDestination;
use Modules\Backups\BackupDistributor;
use Modules\FileAdapters\Adapters\LocalAdapter;
use Modules\FileAdapters\FileAdapter;
use PHPUnit\Framework\TestCase;

class BackupDistributorConflictTest extends TestCase
{
    protected string $root;

    public static function setUpBeforeClass(): void
    {
        include_once __DIR__.'/../core.php';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = 'tmp/backup-distributor-conflict-'.bin2hex(random_bytes(6));
        mkdir(base_dir().'/'.$this->root.'/remote', 0777, true);
    }

    protected function tearDown(): void
    {
        $path = base_dir().'/'.$this->root;
        if (is_dir($path)) {
            $items = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($items as $item) {
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }

            rmdir($path);
        }

        parent::tearDown();
    }

    public function testExistingBackupWithDifferentSizeIsPreserved(): void
    {
        $name = 'OSM backup 2026-08-17 00_05_01 FULL.zip';
        $source = base_dir().'/'.$this->root.'/'.$name;
        $remote = base_dir().'/'.$this->root.'/remote/'.$name;

        file_put_contents($source, 'new-backup-content');
        file_put_contents($remote, 'old');

        $adapter = new FileAdapter();
        $adapter->name = 'Test locale conflitto';
        $adapter->class = LocalAdapter::class;
        $adapter->options = json_encode(['directory' => $this->root]);

        $destination = new BackupDestination();
        $destination->path = 'remote';
        $destination->retention = 10;
        $destination->setRelation('adapter', $adapter);

        $result = BackupDistributor::distributeTo($source, $destination);

        $this->assertFalse($result['success']);
        $this->assertSame('old', file_get_contents($remote));
        $this->assertFileDoesNotExist($remote.'.part');
    }
}
