<?php

namespace Plugins\ExportFE;

use Modules\Fatture\Fattura;
use Tasks\Manager;

class InvoiceHookTask extends Manager
{
    private const MAX_ATTEMPTS = 3;

    public function execute()
    {
        $result = [
            'response' => 1,
            'message' => tr('Fatture elettroniche inviate correttamente!'),
        ];

        $inviate = 0;
        $errori = 0;
        $retry = 0;
        $sospese = 0;
        $fatture_errore = [];

        try {
            $fatture = Fattura::where('hook_send', 1)
                ->where('codice_stato_fe', 'QUEUE')
                ->limit(25)
                ->get();

            if ($fatture->isEmpty()) {
                $result['message'] = tr('Nessuna fattura da inviare');

                return $result;
            }

            foreach ($fatture as $fattura) {
                try {
                    $this->processInvoice($fattura, $inviate, $errori, $retry, $sospese, $fatture_errore);
                } catch (\UnexpectedValueException) {
                    $this->resetInvoice($fattura);
                } catch (\Exception $e) {
                    $this->handleInvoiceException($fattura, $e, $errori, $retry, $fatture_errore);
                }
            }

            $this->buildResultMessage($result, $inviate, $errori, $retry, $sospese, $fatture_errore);
        } catch (\Exception $e) {
            $result['response'] = 2;
            $result['message'] = tr('Errore durante l\'invio delle fatture elettroniche: _ERR_', [
                '_ERR_' => $e->getMessage(),
            ]).'<br>';

            logger_osm()->error('Errore critico nel task invio FE: '.$e->getMessage());
        }

        return $result;
    }

    private function processInvoice($fattura, &$inviate, &$errori, &$retry, &$sospese, &$fatture_errore)
    {
        $fattura_elettronica = new FatturaElettronica($fattura->id);
        if (!$fattura_elettronica->isGenerated()) {
            $this->resetInvoice($fattura);

            return;
        }

        $response_invio = Interaction::sendInvoice($fattura->id);
        $code = (int) ($response_invio['code'] ?? 500);

        if ($code === 200 || $code === 301) {
            $fattura->hook_send = false;
            $fattura->fe_attempt = 0;
            $fattura->save();
            ++$inviate;
        } elseif ($code === 202) {
            $this->markAsPendingProviderOutcome($fattura, $sospese);
        } elseif ($code === 423) {
            $this->postponeInvoice($fattura, $retry);
        } else {
            $this->handleFailedAttempt(
                $fattura,
                (string) ($response_invio['message'] ?? tr('Errore durante l\'invio FE')),
                $errori,
                $retry,
                $fatture_errore
            );
        }
    }

    private function resetInvoice($fattura)
    {
        $fattura->hook_send = false;
        $fattura->codice_stato_fe = 'GEN';
        $fattura->data_stato_fe = date('Y-m-d H:i:s');
        $fattura->fe_attempt = 0;
        $fattura->save();
    }

    private function handleFailedAttempt($fattura, $message, &$errori, &$retry, &$fatture_errore)
    {
        $fattura->fe_attempt = ($fattura->fe_attempt ?? 0) + 1;

        if ($fattura->fe_attempt >= self::MAX_ATTEMPTS) {
            $this->markAsFailed($fattura, $message, $errori, $fatture_errore);
        } else {
            $this->keepInQueue($fattura, $retry);
        }
    }

    private function markAsFailed($fattura, $message, &$errori, &$fatture_errore)
    {
        $fattura->hook_send = false;
        $fattura->codice_stato_fe = 'ERR';
        $fattura->fe_failed_at = date('Y-m-d H:i:s');
        $fattura->save();
        ++$errori;
        $fatture_errore[] = $fattura->numero_esterno.' ('.$message.')';
    }

    private function keepInQueue($fattura, &$retry)
    {
        $fattura->codice_stato_fe = 'QUEUE';
        $fattura->data_stato_fe = date('Y-m-d H:i:s');
        $fattura->save();
        ++$retry;
    }

    private function markAsPendingProviderOutcome($fattura, &$sospese)
    {
        $fattura->hook_send = false;
        $fattura->codice_stato_fe = 'WAIT';
        $fattura->data_stato_fe = date('Y-m-d H:i:s');
        $fattura->fe_attempt = 0;
        $fattura->save();
        ++$sospese;
    }

    private function postponeInvoice($fattura, &$retry)
    {
        $fattura->codice_stato_fe = 'QUEUE';
        $fattura->data_stato_fe = date('Y-m-d H:i:s');
        $fattura->save();
        ++$retry;
    }

    private function handleInvoiceException($fattura, \Exception $e, &$errori, &$retry, &$fatture_errore)
    {
        $fattura->fe_attempt = ($fattura->fe_attempt ?? 0) + 1;

        if ($fattura->fe_attempt >= self::MAX_ATTEMPTS) {
            $this->markAsFailed($fattura, 'errore: '.$e->getMessage(), $errori, $fatture_errore);
            logger_osm()->error('Errore invio FE per fattura '.$fattura->numero_esterno.': '.$e->getMessage());
        } else {
            $this->keepInQueue($fattura, $retry);
            logger_osm()->warning('Tentativo '.$fattura->fe_attempt.'/'.self::MAX_ATTEMPTS.' per fattura '.$fattura->numero_esterno.': '.$e->getMessage());
        }
    }

    private function buildResultMessage(&$result, $inviate, $errori, $retry, $sospese, $fatture_errore)
    {
        $parts = [];

        if ($inviate > 0) {
            $parts[] = tr('_NUM_ inviate', ['_NUM_' => $inviate]);
        }
        if ($retry > 0) {
            $parts[] = tr('_NUM_ in attesa di nuovo tentativo (max _MAX_)', [
                '_NUM_' => $retry,
                '_MAX_' => self::MAX_ATTEMPTS,
            ]);
        }
        if ($sospese > 0) {
            $parts[] = tr('_NUM_ sospese in attesa di riconciliazione provider', ['_NUM_' => $sospese]);
        }
        if ($errori > 0) {
            $parts[] = tr('_NUM_ con errori', ['_NUM_' => $errori]);
        }

        if ($retry > 0 || $sospese > 0 || $errori > 0) {
            $result['response'] = 2;
        }

        $result['message'] = !empty($parts)
            ? tr('Fatture elettroniche: _RESULT_', ['_RESULT_' => implode(', ', $parts)])
            : tr('Nessuna fattura da inviare');

        if (!empty($fatture_errore)) {
            $result['message'] .= ': '.implode(', ', $fatture_errore);
        }
    }
}
