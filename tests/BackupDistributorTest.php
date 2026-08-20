<?php

use League\Flysystem\Config;
use Modules\Backups\BackupDestination;
use Modules\Backups\BackupDistributor;
use Modules\FileAdapters\Adapters\LocalAdapter;
use Modules\FileAdapters\FileAdapter;
use PHPUnit\Framework\TestCase;

class TestableBackupDistributor extends BackupDistributor
{
    public static function distributeDestinationsForTest(string $backup_path, iterable $destinations): array
    {
        return parent::distributeDestinations($backup_path, $destinations);
    }

    public static function safeErrorForTest(Throwable $exception, ?object $adapter = null): string
    {
        return parent::safeErrorMessage($exception, $adapter);
    }
}

class FailingMoveLocalAdapter extends LocalAdapter
{
    public function move(string $source, string $destination, Config $config): void
    {
        throw new RuntimeException('forced move failure');
    }
}

class BackupDistributorTest extends TestCase
{
    protected string $root;

    public static function setUpBeforeClass(): void
    {
        include_once __DIR__.'/../core.php';
    }

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
        $remote = base_dir().'/'.$this->root.'/remote/'.basename($source);

        $this->assertTrue($result['success']);
        $this->assertSame(strlen('backup-content'), $result['size']);
        $this->assertFileExists($remote);
        $this->assertFileDoesNotExist($remote.'.part');
        $this->assertSame('backup-content', file_get_contents($remote));
    }

    public function testExistingPartFileIsReplacedAndRemoved(): void
    {
        $source = $this->createBackup('OSM backup 2026-08-17 00_00_02 FULL.zip', 'new-content');
        $remote_directory = base_dir().'/'.$this->root.'/remote';
        mkdir($remote_directory, 0777, true);
        $part = $remote_directory.'/'.basename($source).'.part';
        file_put_contents($part, 'stale-partial-content');

        $result = BackupDistributor::distributeTo($source, $this->destination());

        $this->assertTrue($result['success']);
        $this->assertFileDoesNotExist($part);
        $this->assertSame('new-content', file_get_contents($remote_directory.'/'.basename($source)));
    }

    public function testFinalizationFailureRemovesPartFile(): void
    {
        $source = $this->createBackup('OSM backup 2026-08-17 00_00_03 FULL.zip', 'backup-content');
        $destination = $this->destination(10, 'remote', FailingMoveLocalAdapter::class);
        $remote = base_dir().'/'.$this->root.'/remote/'.basename($source);

        $result = BackupDistributor::distributeTo($source, $destination);

        $this->assertFalse($result['success']);
        $this->assertFileDoesNotExist($remote.'.part');
        $this->assertFileDoesNotExist($remote);
    }

    public function testRedistributionOfExistingBackupIsIdempotent(): void
    {
        $source = $this->createBackup('OSM backup 2026-08-17 00_00_04 FULL.zip', 'backup-content');
        $destination = $this->destination();

        $first = BackupDistributor::distributeTo($source, $destination);
        $second = BackupDistributor::distributeTo($source, $destination);

        $this->assertTrue($first['success']);
        $this->assertTrue($second['success']);
        $this->assertFileDoesNotExist(base_dir().'/'.$this->root.'/remote/'.basename($source).'.part');
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

    public function testRetentionOneKeepsOnlyLatestBackup(): void
    {
        $destination = $this->destination(1);
        $first = $this->createBackup('OSM backup 2026-08-17 00_01_01 FULL.zip', 'one');
        $second = $this->createBackup('OSM backup 2026-08-17 00_01_02 FULL.zip', 'two');

        $this->assertTrue(BackupDistributor::distributeTo($first, $destination)['success']);
        $this->assertTrue(BackupDistributor::distributeTo($second, $destination)['success']);

        $this->assertFileDoesNotExist(base_dir().'/'.$this->root.'/remote/'.basename($first));
        $this->assertFileExists(base_dir().'/'.$this->root.'/remote/'.basename($second));
    }

    public function testHighRetentionDoesNotRemoveBackups(): void
    {
        $destination = $this->destination(100);
        $names = [
            'OSM backup 2026-08-17 00_02_01 FULL.zip',
            'OSM backup 2026-08-17 00_02_02 FULL.zip',
            'OSM backup 2026-08-17 00_02_03 FULL.zip',
        ];

        foreach ($names as $name) {
            $source = $this->createBackup($name, $name);
            $this->assertTrue(BackupDistributor::distributeTo($source, $destination)['success']);
        }

        foreach ($names as $name) {
            $this->assertFileExists(base_dir().'/'.$this->root.'/remote/'.$name);
        }
    }

    public function testMissingAdapterDoesNotThrow(): void
    {
        $source = $this->createBackup('OSM backup 2026-08-17 00_03_01 FULL.zip', 'backup-content');
        $destination = new BackupDestination();
        $destination->path = 'remote';
        $destination->retention = 2;

        $result = BackupDistributor::distributeTo($source, $destination);

        $this->assertFalse($result['success']);
    }

    public function testInvalidAdapterClassIsRejected(): void
    {
        $source = $this->createBackup('OSM backup 2026-08-17 00_03_02 FULL.zip', 'backup-content');
        $destination = $this->destination(10, 'remote', stdClass::class);

        $result = BackupDistributor::distributeTo($source, $destination);

        $this->assertFalse($result['success']);
    }

    public function testErrorIsolationContinuesWithNextDestination(): void
    {
        $source = $this->createBackup('OSM backup 2026-08-17 00_04_01 FULL.zip', 'backup-content');
        $valid = $this->destination(10, 'remote-good');

        $results = TestableBackupDistributor::distributeDestinationsForTest($source, [new stdClass(), $valid]);

        $this->assertCount(2, $results);
        $this->assertFalse($results[0]['success']);
        $this->assertTrue($results[1]['success']);
        $this->assertFileExists(base_dir().'/'.$this->root.'/remote-good/'.basename($source));
    }

    public function testNoDestinationsReturnsEmptyResults(): void
    {
        $source = $this->createBackup('OSM backup 2026-08-17 00_04_02 FULL.zip', 'backup-content');

        $this->assertSame([], TestableBackupDistributor::distributeDestinationsForTest($source, []));
    }

    public function testSameAdapterCanUseDifferentPaths(): void
    {
        $source = $this->createBackup('OSM backup 2026-08-17 00_04_03 FULL.zip', 'backup-content');
        $adapter = $this->adapter();
        $first = $this->destinationWithAdapter($adapter, 10, 'remote-a');
        $second = $this->destinationWithAdapter($adapter, 10, 'remote-b');

        $results = TestableBackupDistributor::distributeDestinationsForTest($source, [$first, $second]);

        $this->assertTrue($results[0]['success']);
        $this->assertTrue($results[1]['success']);
        $this->assertFileExists(base_dir().'/'.$this->root.'/remote-a/'.basename($source));
        $this->assertFileExists(base_dir().'/'.$this->root.'/remote-b/'.basename($source));
    }

    public function testInvalidRelativePathIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BackupDistributor::normalizeDirectory('../outside');
    }

    public function testAbsoluteUnixPathIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BackupDistributor::normalizeDirectory('/outside');
    }

    public function testAbsoluteWindowsPathIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BackupDistributor::normalizeDirectory('C:\\outside');
    }

    public function testUrlLikePathIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BackupDistributor::normalizeDirectory('ftp://server/backups');
    }

    public function testTooLongPathIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BackupDistributor::normalizeDirectory(str_repeat('a', 256));
    }

    public function testEmptyPathIsAllowed(): void
    {
        $this->assertSame('', BackupDistributor::normalizeDirectory(''));
    }

    public function testBackslashesAreNormalized(): void
    {
        $this->assertSame('customer/backups', BackupDistributor::normalizeDirectory('customer\\backups'));
    }

    public function testTrailingSeparatorsAreNormalized(): void
    {
        $this->assertSame('customer/backups', BackupDistributor::normalizeDirectory('customer/backups/'));
        $this->assertSame('customer/backups', BackupDistributor::normalizeDirectory('customer\\backups\\'));
    }

    public function testSensitiveAdapterOptionsAreRedactedFromErrors(): void
    {
        $adapter = $this->adapter();
        $adapter->options = json_encode([
            'host' => 'backup.example.test',
            'username' => 'backup-user',
            'password' => 'super-secret-password',
        ]);

        $message = TestableBackupDistributor::safeErrorForTest(
            new RuntimeException('Connection failed using super-secret-password'),
            $adapter
        );

        $this->assertStringNotContainsString('super-secret-password', $message);
        $this->assertStringContainsString('***', $message);
    }

    protected function destination(int $retention = 10, string $path = 'remote', string $adapter_class = LocalAdapter::class): BackupDestination
    {
        return $this->destinationWithAdapter($this->adapter($adapter_class), $retention, $path);
    }

    protected function destinationWithAdapter(FileAdapter $adapter, int $retention = 10, string $path = 'remote'): BackupDestination
    {
        $destination = new BackupDestination();
        $destination->path = $path;
        $destination->retention = $retention;
        $destination->setRelation('adapter', $adapter);

        return $destination;
    }

    protected function adapter(string $adapter_class = LocalAdapter::class): FileAdapter
    {
        $adapter = new FileAdapter();
        $adapter->name = 'Test locale';
        $adapter->class = $adapter_class;
        $adapter->options = json_encode(['directory' => $this->root]);

        return $adapter;
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
