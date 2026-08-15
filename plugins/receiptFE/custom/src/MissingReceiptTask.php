<?php

namespace Plugins\ReceiptFE;

use Carbon\Carbon;
use Modules\Fatture\Fattura;
use Plugins\ExportFE\Interaction;
use Tasks\Manager;

/**
 * Recupero di sicurezza per fatture FE rimaste in WAIT oltre 7 giorni.
 *
 * Normalizza la risposta provider (code/results), funziona anche senza una
 * sessione utente attiva e restituisce sempre l'esito atteso dal task manager.
 */
class MissingReceiptTask extends Manager
{
    public function execute()
    {
        $result = [
            'response' => 1,
            'message' => tr('Controllo ricevute FE mancanti completato'),
        ];

        if (!Interaction::isEnabled()) {
            $result['response'] = 2;
            $result['message'] = tr('Importazione automatica ricevute FE disattivata');

            return $result;
        }

        $data_limite = (new Carbon())->subDays(7);
        $period_start = $_SESSION['period_start'] ?? '2000-01-01';

        $in_attesa = Fattura::vendita()
            ->where('codice_stato_fe', 'WAIT')
            ->where('data_stato_fe', '>=', $period_start)
            ->where('data_stato_fe', '<', $data_limite)
            ->orderBy('data_stato_fe')
            ->get();

        $checked = 0;
        $imported = 0;
        $errors = 0;

        foreach ($in_attesa as $fattura) {
            ++$checked;

            try {
                $response = Interaction::getInvoiceRecepits($fattura->id);
                $code = (int) ($response['code'] ?? 500);
                $ricevute = $response['results'] ?? [];

                if ($code !== 200 || empty($ricevute)) {
                    continue;
                }

                foreach ($ricevute as $ricevuta) {
                    $name = is_array($ricevuta) ? ($ricevuta['name'] ?? null) : $ricevuta;
                    if (empty($name)) {
                        continue;
                    }

                    try {
                        if (Ricevuta::process($name, true, $fattura->id) !== null) {
                            ++$imported;
                        }
                    } catch (\Throwable $e) {
                        ++$errors;
                        $this->task->log('error', 'Errore importazione ricevuta FE mancante', [
                            'file' => $name,
                            'id_fattura' => $fattura->id,
                            'message' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                ++$errors;
                $this->task->log('error', 'Errore recupero ricevute FE mancanti', [
                    'id_fattura' => $fattura->id,
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        if ($errors > 0) {
            $result['response'] = 2;
        }

        $result['message'] = tr('Ricevute FE mancanti: _CHECKED_ fatture controllate, _IMPORTED_ ricevute importate, _ERRORS_ errori', [
            '_CHECKED_' => $checked,
            '_IMPORTED_' => $imported,
            '_ERRORS_' => $errors,
        ]);

        return $result;
    }
}
