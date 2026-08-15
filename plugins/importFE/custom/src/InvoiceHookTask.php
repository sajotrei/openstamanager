<?php

namespace Plugins\ImportFE;

use Tasks\Manager;

/**
 * Aggiornamento automatico dell'elenco delle fatture passive.
 * Mantiene il comportamento nativo ma isola errori provider e dati inattesi.
 */
class InvoiceHookTask extends Manager
{
    public function execute()
    {
        if (!Interaction::isEnabled()) {
            return [
                'response' => 1,
                'message' => tr('Canale di fatturazione elettronica non attivo'),
            ];
        }

        try {
            $list = Interaction::getInvoiceList();
            $count = is_array($list) ? count($list) : 0;

            return [
                'response' => 1,
                'message' => $count > 0
                    ? tr('_NUM_ fatture passive disponibili', ['_NUM_' => $count])
                    : tr('Nessuna fattura passiva da importare'),
            ];
        } catch (\Throwable $e) {
            $this->task->log('error', 'Errore aggiornamento fatture passive FE', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'response' => 2,
                'message' => tr('Errore durante l’aggiornamento delle fatture passive: _ERR_', [
                    '_ERR_' => $e->getMessage(),
                ]),
            ];
        }
    }
}
