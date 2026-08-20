<?php

use Modules\Backups\BackupDestination;
use Modules\Backups\BackupDistributor;
use Modules\FileAdapters\Adapters\FTPAdapter;
use Modules\FileAdapters\FileAdapter;
use Modules\FileAdapters\OSMFilesystem;
use PHPUnit\Framework\TestCase;

class BackupFtpIntegrationTest extends TestCase
{
    public function testFtpDestinationEndToEndWhenConfigured(): void
    {
        $host = getenv('OSM_TEST_FTP_HOST');
        $username = getenv('OSM_TEST_FTP_USERNAME');
        $password = getenv('OSM_TEST_FTP_PASSWORD');

        if ($host === false || $host === '' || $username === false || $username === '' || $password === false) {
            $this->markTestSkipped('FTP integration environment variables are not configured.');
        }

        include_once __DIR__.'/../../core.php';

        $options = [
            'host' => $host,
            'username' => $username,
            'password' => $password,
            'root' => getenv('OSM_TEST_FTP_ROOT') ?: '/',
            'port' => (int) (getenv('OSM_TEST_FTP_PORT') ?: 21),
            'ssl' => filter_var(getenv('OSM_TEST_FTP_SSL') ?: false, FILTER_VALIDATE_BOOL),
            'timeout' => (int) (getenv('OSM_TEST_FTP_TIMEOUT') ?: 15),
            'passive' => true,
        ];

        $adapter_config = new FileAdapter();
        $adapter_config->name = 'FTP integration test';
        $adapter_config->class = FTPAdapter::class;
        $adapter_config->options = json_encode($options);

        $directory = 'osm-backup-integration-'.bin2hex(random_bytes(6));
        $destination = new BackupDestination();
        $destination->path = $directory;
        $destination->retention = 2;
        $destination->setRelation('adapter', $adapter_config);

        $source = sys_get_temp_dir().'/OSM backup 2026-08-20 00_00_01 FULL.zip';
        file_put_contents($source, random_bytes(64 * 1024));

        $filesystem = new OSMFilesystem(new FTPAdapter($adapter_config->options));
        $remote_path = $directory.'/'.basename($source);

        try {
            $connection_test = BackupDistributor::test($destination);
            $this->assertTrue($connection_test['success'], $connection_test['message']);

            $result = BackupDistributor::distributeTo($source, $destination);
            $this->assertTrue($result['success'], $result['message']);
            $this->assertTrue($filesystem->fileExists($remote_path));
            $this->assertSame(filesize($source), $filesystem->fileSize($remote_path));
            $this->assertFalse($filesystem->fileExists($remote_path.'.part'));
        } finally {
            try {
                if ($filesystem->fileExists($remote_path.'.part')) {
                    $filesystem->delete($remote_path.'.part');
                }
                if ($filesystem->fileExists($remote_path)) {
                    $filesystem->delete($remote_path);
                }
                if ($filesystem->directoryExists($directory)) {
                    $filesystem->deleteDirectory($directory);
                }
            } catch (Throwable) {
            }

            if (file_exists($source)) {
                unlink($source);
            }
        }
    }
}
