<?php

use PHPUnit\Framework\TestCase;

class BackupSectionScopeTest extends TestCase
{
    public function testDestinationListDeclaresItsOwnClassAliases(): void
    {
        $section = file_get_contents(__DIR__.'/../modules/backups/custom/sections/destination_list.php');

        $this->assertStringContainsString('use Modules\\Backups\\BackupAdapterService;', $section);
        $this->assertStringContainsString('use Modules\\FileAdapters\\Adapters\\FTPAdapter;', $section);
        $this->assertStringContainsString('use Modules\\FileAdapters\\Adapters\\LocalAdapter;', $section);
    }

    public function testIncludedSectionsDoNotDependOnParentUseAliases(): void
    {
        $requirements = [
            'automation.php' => [
                'use Models\\Cache;',
                'use Models\\Module;',
                'use Modules\\Backups\\BackupRetryService;',
                'use Modules\\Backups\\BackupTask;',
                'use Tasks\\Task;',
            ],
            'destination_list.php' => [
                'use Modules\\Backups\\BackupAdapterService;',
                'use Modules\\FileAdapters\\Adapters\\FTPAdapter;',
                'use Modules\\FileAdapters\\Adapters\\LocalAdapter;',
            ],
        ];

        foreach ($requirements as $file => $imports) {
            $content = file_get_contents(__DIR__.'/../modules/backups/custom/sections/'.$file);
            foreach ($imports as $import) {
                $this->assertStringContainsString($import, $content, $file.' deve dichiarare autonomamente '.$import);
            }
        }
    }

    public function testWizardPostedFieldNamesAreUnique(): void
    {
        $wizard = file_get_contents(__DIR__.'/../modules/backups/custom/sections/destination_wizard.php');
        preg_match_all('/name="([^"]+)"/', $wizard, $matches);
        $counts = array_count_values($matches[1]);
        $duplicates = array_filter($counts, static fn ($count) => $count > 1);

        $this->assertSame([], $duplicates, 'Il wizard non deve dipendere dal JavaScript per disambiguare input POST duplicati.');
    }

    public function testDatabaseOnlyLabelMatchesActualBehavior(): void
    {
        $controller = file_get_contents(__DIR__.'/../modules/backups/custom/controller_after.php');

        $this->assertStringContainsString("tr('Solo database')", $controller);
        $this->assertStringNotContainsString("tr('Tutto tranne database')", $controller);
    }
}
