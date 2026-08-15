<?php

namespace Plugins\ImportFE;

use API\Services;
use Models\Cache;
use Plugins\ExportFE\Providers\ProviderFactory;
use Util\XML;

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
        if (!self::isEnabled()) {
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
                // I documenti XML non firmati vengono validati prima della
                // persistenza. I P7M restano al decoder nativo del gestionale.
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

        return static::getProvider()->processPassiveInvoice($filename);
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
