<?php

namespace Plugins\ExportFE;

use API\Services;
use Plugins\ExportFE\Providers\ProviderFactory;
use Plugins\ExportFE\Providers\ProviderInterface;

/**
 * Punto di ingresso runtime per l'invio FE e la ricerca delle ricevute.
 *
 * Questa classe resta nel percorso nativo del plugin per essere risolta anche
 * dalle installazioni con autoload Composer gia' ottimizzato. Il provider
 * selezionato decide il trasporto; la logica fiscale resta nel gestionale.
 */
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

    // Mantiene il nome pubblico storico (typo incluso) usato da OSM 2.10.4.
    public static function getInvoiceRecepits($id_record)
    {
        return static::getProvider()->getInvoiceReceipts((int) $id_record);
    }
}
