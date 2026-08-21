<?php

use PHPUnit\Framework\TestCase;

class BackupSectionRuntimeSmokeTest extends TestCase
{
    public function testDestinationSectionsResolveClassesAcrossIncludes(): void
    {
        $section = realpath(__DIR__.'/../modules/backups/custom/sections/destinations.php');
        $this->assertNotFalse($section);

        $script = <<<'PHP_SCRIPT'
<?php
namespace Modules\Backups {
    class FakeQuery {
        public function orderBy($field){ return $this; }
        public function get(){ return new \FakeCollection([\FakeFactory::destination()]); }
    }
    class BackupDestination { public static function with($relation){ return new FakeQuery(); } }
    class BackupAdapterService {
        public static function canManageAdapters(): bool { return false; }
        public static function describe($destination): array { return ['mode'=>'existing','id_adapter'=>2,'path'=>'backups']; }
    }
}
namespace Modules\FileAdapters {
    class FakeQuery { public function get(){ return new \FakeCollection([\FakeFactory::adapter()]); } }
    class FileAdapter { public static function orderBy($field){ return new FakeQuery(); } }
}
namespace Modules\FileAdapters\Adapters { class FTPAdapter {}; class LocalAdapter {}; }
namespace {
    function tr($value){ return $value; }
    class Backup { public static function getStorageAdapter(){ return (object)['id'=>1]; } }
    class FakeCollection implements IteratorAggregate {
        public function __construct(private array $items){}
        public function getIterator(): Traversable { return new ArrayIterator($this->items); }
        public function isEmpty(): bool { return $this->items === []; }
        public function filter(callable $callback): self { return new self(array_values(array_filter($this->items, $callback))); }
    }
    class FakeFactory {
        public static function adapter(){ return (object)['id'=>2,'class'=>'Modules\\FileAdapters\\Adapters\\FTPAdapter','name'=>'Smoke FTP','options'=>'{"ssl":false}']; }
        public static function destination(){ return (object)[
            'id'=>1,'adapter'=>self::adapter(),'path'=>'backups','retention'=>10,'enabled'=>false,
            'last_test_success'=>null,'last_test_at'=>null,'last_error'=>null,'next_retry_at'=>null,
            'retry_count'=>0,'last_success_at'=>null,'last_success_file'=>null,'managed_adapter'=>false,
        ]; }
    }
    include __SECTION__;
}
PHP_SCRIPT;

        $result = $this->runPhpSmoke(str_replace('__SECTION__', var_export($section, true), $script));
        $this->assertSame(0, $result['status'], $result['output']);
        $this->assertStringContainsString('Smoke FTP', $result['output']);
    }

    public function testAutomationSectionResolvesItsOwnClasses(): void
    {
        $section = realpath(__DIR__.'/../modules/backups/custom/sections/automation.php');
        $this->assertNotFalse($section);

        $script = <<<'PHP_SCRIPT'
<?php
namespace Models {
    class CacheQuery { public function first(){ return (object)['content'=>'2026-08-21 16:00:00']; } }
    class Cache { public static function where($field,$value){ return new CacheQuery(); } }
    class ModuleQuery { public function first(){ return (object)['id'=>99]; } }
    class Module { public static function where($field,$value){ return new ModuleQuery(); } }
}
namespace Tasks {
    class TaskQuery {
        public function first(){ return (object)['id'=>7,'enabled'=>1,'expression'=>'0 1 * * *','last_executed_at'=>new \DateTime('2026-08-21 01:00'),'next_execution_at'=>new \DateTime('2026-08-22 01:00')]; }
        public function count(){ return 1; }
    }
    class Task { public static function where($field,$value){ return new TaskQuery(); } }
}
namespace Modules\Backups {
    class BackupTask {}
    class BackupRetryService { public static function pendingRetryCount($due=false){ return $due ? 1 : 2; } }
}
namespace {
    function tr($value){ return $value; }
    function setting($name){ return true; }
    function base_path_osm(){ return ''; }
    $structure=(object)['permission'=>'rw'];
    include __SECTION__;
}
PHP_SCRIPT;

        $result = $this->runPhpSmoke(str_replace('__SECTION__', var_export($section, true), $script));
        $this->assertSame(0, $result['status'], $result['output']);
        $this->assertStringContainsString('Ogni giorno alle 01:00', $result['output']);
    }

    private function runPhpSmoke(string $script): array
    {
        $file = tempnam(sys_get_temp_dir(), 'osm1670-section-');
        file_put_contents($file, $script);

        $output = [];
        $status = 0;
        exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($file).' 2>&1', $output, $status);
        @unlink($file);

        return [
            'status' => $status,
            'output' => implode("\n", $output),
        ];
    }
}
