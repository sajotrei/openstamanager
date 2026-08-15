<?php

namespace Plugins\ImportFE;

use API\Services;
use Models\Cache;
use Plugins\ExportFE\Providers\HostingSolutionsProvider;
use Plugins\ExportFE\Providers\ProviderFactory;
use Plugins\ExportFE\Providers\ProviderSettings;
use Util\XML;

class Interaction extends Services
{
    private const MOCK_PASSIVE_CACHE = 'Hosting Solutions FE Mock Passive Processed';

    protected static function getProvider()
    {
        return ProviderFactory::make();
    }

    public static function isEnabled()
    {
        return static::getProvider()->isEnabled();
    }

    public static function getInvoiceList($directory = null, $plugin = null)
    {
        $list = self::getRemoteList();
        $result = self::getFileList($list, $directory, $plugin);

        $cache = Cache::where('name', 'Fatture Elettroniche')->first();
        if (empty($cache)) {
            $cache = Cache::build('Fatture Elettroniche');
        }
        $cache->set($result);

        return $result;
    }

    public static function getRemoteList()
    {
        if (!self::isEnabled()) {
            return [];
        }

        if (self::isProcessedPassiveMock()) {
            return [];
        }

        $result = [];
        foreach ((array) static::getProvider()->getPassiveInvoiceList() as $item) {
            $name = is_array($item) ? ($item['name'] ?? '') : $item;
            $name = self::sanitizeRemoteName((string) $name);
            if ($name !== null) {
                $result[] = ['name' => $name];
            }
        }

        return $result;
    }

    public static function getFileList($list = [], $directory = null, $plugin = null)
    {
        $names = array_column($list, 'name');
        $directory = FatturaElettronica::getImportDirectory($directory, $plugin);

        $files = glob($directory.'/*.xml*');
        if (!empty($files) && is_array($files)) {
            foreach ($files as $id => $file) {
                $name = basename($file);
                $pos = array_search($name, $names, true);

                if ($pos === false) {
                    $list[] = [
                        'id' => $id,
                        'name' => $name,
                        'file' => true,
                    ];
                } else {
                    $list[$pos]['id'] = $id;
                }
            }
        }

        return $list;
    }

    public static function getInvoiceFile($name)
    {
        $name = self::sanitizeRemoteName((string) $name);
        if ($name === null) {
            throw new \UnexpectedValueException(tr('Nome fattura elettronica non valido'));
        }

        $directory = FatturaElettronica::getImportDirectory();
        $file = $directory.'/'.$name;

        if (!file_exists($file)) {
            $content = static::getProvider()->getPassiveInvoice($name);

            if ($content !== null && $content !== '') {
                if (str_ends_with(strtolower($name), '.xml')) {
                    XML::read($content);
                }
                FatturaElettronica::store($name, $content);
            }
        }

        return $name;
    }

    public static function processInvoice($filename)
    {
        $filename = self::sanitizeRemoteName((string) $filename);
        if ($filename === null) {
            return tr('Nome fattura elettronica non valido');
        }

        $result = static::getProvider()->processPassiveInvoice($filename);

        if ($result === '' && self::isHostingSolutionsPassiveMock()) {
            $cache = Cache::where('name', self::MOCK_PASSIVE_CACHE)->first();
            if (empty($cache)) {
                $cache = Cache::build(self::MOCK_PASSIVE_CACHE);
            }
            $cache->set(true);
        }

        return $result;
    }

    private static function isProcessedPassiveMock(): bool
    {
        if (!self::isHostingSolutionsPassiveMock()) {
            return false;
        }

        $cache = Cache::where('name', self::MOCK_PASSIVE_CACHE)->first();
        if (empty($cache)) {
            return false;
        }

        return in_array($cache->content, [true, 1, '1', 'true'], true);
    }

    private static function isHostingSolutionsPassiveMock(): bool
    {
        return ProviderSettings::selectedProvider() === ProviderFactory::HOSTING_SOLUTIONS
            && ProviderSettings::isHostingSolutionsMockEnabled()
            && ProviderSettings::hostingSolutionsMockScenario() === HostingSolutionsProvider::SCENARIO_PASSIVE;
    }

    private static function sanitizeRemoteName(string $name): ?string
    {
        $name = trim(str_replace('\\', '/', $name));
        if ($name === '' || basename($name) !== $name) {
            return null;
        }

        if (!preg_match('/^[A-Za-z0-9._-]+$/', $name)) {
            return null;
        }

        $lower = strtolower($name);
        if (!str_ends_with($lower, '.xml') && !str_ends_with($lower, '.xml.p7m') && !str_ends_with($lower, '.p7m')) {
            return null;
        }

        return $name;
    }
}
