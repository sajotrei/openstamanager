<?php

namespace Plugins\ReceiptFE;

use API\Services;
use Models\Cache;
use Plugins\ExportFE\Providers\ProviderFactory;
use Util\XML;

/**
 * Punto di ingresso runtime per ricevute FE, provider-aware.
 * Resta nel percorso nativo per funzionare anche con autoload gia' ottimizzato.
 */
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

        $cache = Cache::where('name', 'Ricevute Elettroniche')->first();
        if (empty($cache)) {
            $cache = Cache::build('Ricevute Elettroniche');
        }
        $cache->set($result);

        return $result;
    }

    public static function getRemoteList()
    {
        if (!self::isEnabled()) {
            return [];
        }

        $result = [];
        foreach ((array) static::getProvider()->getReceiptList() as $item) {
            $name = is_array($item) ? ($item['name'] ?? '') : $item;
            $name = self::sanitizeRemoteName((string) $name);
            if ($name !== null) {
                $result[] = ['name' => $name];
            }
        }

        return $result;
    }

    public static function getFileList($list = [])
    {
        $names = array_column($list, 'name');
        $directory = Ricevuta::getImportDirectory();

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

    public static function getReceipt($name)
    {
        $name = self::sanitizeRemoteName((string) $name);
        if ($name === null) {
            throw new \UnexpectedValueException(tr('Nome ricevuta non valido'));
        }

        $directory = Ricevuta::getImportDirectory();
        $file = $directory.'/'.$name;

        if (!file_exists($file)) {
            $content = static::getProvider()->getReceipt($name);

            if ($content !== null && $content !== '') {
                if (str_ends_with(strtolower($name), '.xml')) {
                    XML::read($content);
                }
                Ricevuta::store($name, $content);
            }
        }

        return $name;
    }

    public static function processReceipt($filename)
    {
        $filename = self::sanitizeRemoteName((string) $filename);
        if ($filename === null) {
            return tr('Nome ricevuta non valido');
        }

        return static::getProvider()->processReceipt($filename);
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
        if (!str_ends_with($lower, '.xml') && !str_ends_with($lower, '.zip')) {
            return null;
        }

        return $name;
    }
}
