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

    /**
     * Elenca le ricevute remote ancora da importare.
     *
     * @return array<int,array{name:string}>
     */
    public function getReceiptList(): array;

    public function getReceipt(string $name): ?string;

    /**
     * Conferma al provider che la ricevuta e' stata salvata in OSM.
     *
     * @return true|string
     */
    public function processReceipt(string $filename);

    /**
     * Elenca le fatture passive remote ancora da importare.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getPassiveInvoiceList(): array;

    public function getPassiveInvoice(string $name): ?string;

    /**
     * Conferma al provider che la fattura passiva e' stata salvata in OSM.
     */
    public function processPassiveInvoice(string $filename): string;
}
