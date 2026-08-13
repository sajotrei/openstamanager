<?php

namespace Plugins\ReceiptFE;

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

    public static function getReceiptList()
    {
        $list = self::getRemoteList();
        $result = self::getFileList($list);

        Cache::where('name', 'Ricevute Elettroniche')->first()->set($result);

        return $result;
    }

    public static function getRemoteList()
    {
        return self::isEnabled() ? static::getProvider()->getReceiptList() : [];
    }

    public static function getFileList($list = [])
    {
        $names = array_column($list, 'name');
        $directory = Ricevuta::getImportDirectory();

        $files = glob($directory.'/*.xml*');
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

        return $list;
    }

    public static function getReceipt($name)
    {
        $directory = Ricevuta::getImportDirectory();
        $file = $directory.'/'.$name;

        if (!file_exists($file)) {
            $content = static::getProvider()->getReceipt($name);

            if ($content !== null && $content !== '') {
                Ricevuta::store($name, $content);
            }
        }

        return $name;
    }

    public static function processReceipt($filename)
    {
        return static::getProvider()->processReceipt($filename);
    }
}
