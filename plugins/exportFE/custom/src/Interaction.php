<?php

namespace Plugins\ExportFE;

use API\Services;
use Plugins\ExportFE\Providers\ProviderFactory;
use Plugins\ExportFE\Providers\ProviderInterface;

class Interaction extends Services
{
    protected static function getProvider(): ProviderInterface
    {
        return ProviderFactory::make();
    }

    public static function isEnabled()
    {
        return static::getProvider()->isEnabled();
    }

    public static function sendInvoice($id_record)
    {
        return static::getProvider()->sendInvoice((int) $id_record);
    }

    public static function getInvoiceRecepits($id_record)
    {
        return static::getProvider()->getInvoiceReceipts((int) $id_record);
    }
}
