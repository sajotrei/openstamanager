<?php

use PHPUnit\Framework\TestCase;

class BackupNameTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        include_once __DIR__.'/../core.php';
    }

    public function testBackupNameUsesRealSecondsAndAvoidsCollision(): void
    {
        $method = new ReflectionMethod(Backup::class, 'getNextName');
        $before = new DateTimeImmutable();
        $first = $method->invoke(null, 'TSTUNIT');
        $after = new DateTimeImmutable();
        $info = Backup::readName($first);

        $this->assertContains($info['s'], [$before->format('s'), $after->format('s')]);

        $path = Backup::getDirectory().'/'.$first.'.zip';
        file_put_contents($path, 'backup-name-test');

        try {
            $second = $method->invoke(null, 'TSTUNIT');
            $this->assertNotSame($first, $second);
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }
}
