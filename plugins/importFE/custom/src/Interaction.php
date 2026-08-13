<?php

namespace Plugins\ImportFE;

use API\Services;
use Models\Cache;
use Plugins\ExportFE\Providers\ProviderFactory;

class Interaction extends Services
{
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
        return self::isEnabled() ? static::getProvider()->getPassiveInvoiceList() : [];
    }

    public static function getFileList($list = [], $directory = null, $plugin = null)
    {
        $names = array_column($list, 'name');
        $directory = FatturaElettronica::getImportDirectory($directory, $plugin);

        $files = glob($directory.'/*.xml*');
        if (!empty($files) && is_array($files)) {
            foreach ($files as $id => $file) {
                $name = basename($file);
                $pos = array_search($name, $names);

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
        $directory = FatturaElettronica::getImportDirectory();
        $file = $directory.'/'.$name;

        if (!file_exists($file)) {
            $content = static::getProvider()->getPassiveInvoice($name);

            if ($content !== null && $content !== '') {
                FatturaElettronica::store($name, $content);
            }
        }

        return $name;
    }

    public static function processInvoice($filename)
    {
        return static::getProvider()->processPassiveInvoice($filename);
    }
}
