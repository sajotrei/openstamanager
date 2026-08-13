<?php

namespace Plugins\ExportFE;

use Plugins\ExportFE\Providers\ProviderFactory;
use Tasks\Manager;

class ProviderPollingTask extends Manager
{
    public function execute()
    {
        $result = [
            'response' => 1,
            'message' => tr('Polling FE provider completato'),
        ];

        $provider = ProviderFactory::make();

        if (!$provider->isEnabled()) {
            $result['message'] = tr('Provider FE non abilitato');

            return $result;
        }

        // Il polling reale richiede il mapping ufficiale degli stati Hosting Solutions.
        // Le ricevute restano importate dal flusso nativo ReceiptFE quando disponibili.
        $result['message'] = tr('Polling FE provider predisposto; mapping stati remoto in attesa API ufficiali');

        return $result;
    }
}
