<?php

use Modules\Backups\BackupManager;
use PHPUnit\Framework\TestCase;

class BackupManagerTest extends TestCase
{
    public function testBackupLockIsExclusive(): void
    {
        $path = sys_get_temp_dir().'/osm-backup-lock-'.bin2hex(random_bytes(6)).'.lock';
        $acquire = new ReflectionMethod(BackupManager::class, 'acquireLock');
        $release = new ReflectionMethod(BackupManager::class, 'releaseLock');

        $first = $acquire->invoke(null, $path);

        try {
            $this->assertIsResource($first);
            $this->assertNull($acquire->invoke(null, $path));
        } finally {
            if (is_resource($first)) {
                $release->invoke(null, $first);
            }
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    public function testCollisionGuardDetectsExistingFileWithoutChangingIt(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'osm-backup-collision-');
        $this->assertNotFalse($path);
        file_put_contents($path, 'preserve-me');

        $collision = new ReflectionMethod(BackupManager::class, 'hasCollision');

        try {
            $this->assertTrue($collision->invoke(null, $path));
            $this->assertSame('preserve-me', file_get_contents($path));
        } finally {
            if (is_string($path) && file_exists($path)) {
                unlink($path);
            }
        }

        $this->assertFalse($collision->invoke(null, $path));
    }
}
