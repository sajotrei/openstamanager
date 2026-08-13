<?php

namespace Plugins\ExportFE\Providers;

/**
 * Contratto comune per i provider di trasporto della fatturazione elettronica.
 *
 * Il provider si occupa esclusivamente della comunicazione con il servizio
 * esterno. Generazione XML, validazione e gestione documentale restano in OSM.
 */
interface ProviderInterface
{
    public function isEnabled(): bool;

    /**
     * Invia una fattura elettronica gia generata da OSM.
     *
     * @return array{code:int,message:string}
     */
    public function sendInvoice(int $id_record): array;

    /**
     * Recupera le ricevute/esiti disponibili per una fattura.
     *
     * @return array{code:int,results?:array,message?:string}
     */
    public function getInvoiceReceipts(int $id_record): array;
}
