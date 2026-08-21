<?php

use Modules\Backups\BackupRetryService;
use PHPUnit\Framework\TestCase;

class BackupRetryPolicyTest extends TestCase
{
    public function testRetryBackoffIsBounded(): void
    {
        $this->assertSame(300, BackupRetryService::retryDelaySeconds(1));
        $this->assertSame(900, BackupRetryService::retryDelaySeconds(2));
        $this->assertSame(1800, BackupRetryService::retryDelaySeconds(3));
        $this->assertSame(3600, BackupRetryService::retryDelaySeconds(4));
        $this->assertSame(21600, BackupRetryService::retryDelaySeconds(5));
        $this->assertSame(21600, BackupRetryService::retryDelaySeconds(20));
    }
}
