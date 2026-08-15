<?php

namespace Plugins\ExportFE\Providers;

/**
 * Contratto tecnico tra il provider FE e le future API Hosting Solutions.
 *
 * Non contiene URL, autenticazione, payload o codici inventati: definisce solo
 * le operazioni che il gestionale ha gia' dimostrato di richiedere nei flussi
 * attivo, ricevute, passivo e riconciliazione.
 */
interface HostingSolutionsClientInterface
{
    /**
     * Invia un XML FatturaPA gia' generato e validato dal gestionale.
     *
     * @return array{accepted:bool,remote_id?:string,remote_status?:string,message?:string,uncertain?:bool}
     */
    public function sendInvoiceXml(string $filename, string $xml, string $xml_hash): array;

    /**
     * @return array<int,array{name:string}>
     */
    public function listReceipts(): array;

    public function downloadReceipt(string $filename): ?string;

    /**
     * Conferma che la ricevuta e' stata salvata ed elaborata localmente.
     */
    public function confirmReceipt(string $filename): bool;

    /**
     * @return array<int,array{name:string}>
     */
    public function listPassiveInvoices(): array;

    public function downloadPassiveInvoice(string $filename): ?string;

    /**
     * Conferma che la fattura passiva e' stata acquisita localmente.
     */
    public function confirmPassiveInvoice(string $filename): bool;

    /**
     * Riconcilia una richiesta con esito locale incerto senza reinviare l'XML.
     * La strategia reale (remote id, filename, progressivo, hash...) dipendera'
     * esclusivamente dalle API ufficiali Hosting Solutions.
     *
     * @return array{found:bool,remote_id?:string,remote_status?:string,final?:bool,message?:string}
     */
    public function reconcileInvoice(string $filename, string $xml_hash): array;
}
