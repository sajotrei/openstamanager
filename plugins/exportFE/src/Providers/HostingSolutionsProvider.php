<?php

namespace Plugins\ExportFE\Providers;

/**
 * Provider Hosting Solutions.
 *
 * La struttura e' predisposta prima dell'accesso alla documentazione API
 * riservata. Le chiamate reali verranno implementate solo sulla base della
 * documentazione ufficiale e dell'azienda impostata in modalita' TEST.
 */
class HostingSolutionsProvider implements ProviderInterface
{
    public function isEnabled(): bool
    {
        return false;
    }

    public function sendInvoice(int $id_record): array
    {
        return [
            'code' => 501,
            'message' => tr('Provider Hosting Solutions non ancora configurato'),
        ];
    }

    public function getInvoiceReceipts(int $id_record): array
    {
        return [
            'code' => 501,
            'message' => tr('Provider Hosting Solutions non ancora configurato'),
            'results' => [],
        ];
    }
}
