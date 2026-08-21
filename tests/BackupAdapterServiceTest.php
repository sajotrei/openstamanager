<?php

use Modules\Backups\BackupAdapterService;
use PHPUnit\Framework\TestCase;

class BackupAdapterServiceTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        include_once __DIR__.'/../core.php';
    }

    public function testFtpOptionsAreBuiltWithoutManualJson(): void
    {
        $options = json_decode(BackupAdapterService::buildFtpOptions([
            'host' => 'nas.example.test',
            'port' => 2121,
            'username' => 'backup-user',
            'password' => 'secret',
            'ssl' => '1',
            'passive' => '1',
            'timeout' => 45,
        ]), true);

        $this->assertSame('nas.example.test', $options['host']);
        $this->assertSame('/', $options['root']);
        $this->assertSame(2121, $options['port']);
        $this->assertSame('backup-user', $options['username']);
        $this->assertSame('secret', $options['password']);
        $this->assertTrue($options['ssl']);
        $this->assertTrue($options['passive']);
        $this->assertSame(45, $options['timeout']);
    }

    public function testBlankPasswordPreservesExistingFtpPassword(): void
    {
        $current = json_encode([
            'host' => 'old.example.test',
            'root' => '/',
            'username' => 'old-user',
            'password' => 'keep-me',
        ]);

        $options = json_decode(BackupAdapterService::buildFtpOptions([
            'host' => 'new.example.test',
            'port' => 21,
            'username' => 'new-user',
            'password' => '',
            'ssl' => '0',
            'passive' => '1',
            'timeout' => 30,
        ], $current), true);

        $this->assertSame('keep-me', $options['password']);
        $this->assertSame('new.example.test', $options['host']);
    }

    public function testLocalDirectoryMustStayInsideOsmRoot(): void
    {
        $this->assertSame('files/backups-secondary', BackupAdapterService::normalizeLocalDirectory('files\\backups-secondary/'));

        $this->expectException(RuntimeException::class);
        BackupAdapterService::normalizeLocalDirectory('../outside');
    }

    public function testAbsoluteLocalDirectoryIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        BackupAdapterService::normalizeLocalDirectory('/mnt/nas');
    }
}
