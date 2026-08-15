<?php

namespace Plugins\ExportFE;

use Plugins\ExportFE\Providers\ProviderFactory;
use Plugins\ExportFE\Providers\ProviderSettings;
use Plugins\ExportFE\Providers\ProviderTransactionRepository;
use Tasks\Manager;

class ProviderPollingTask extends Manager
{
    private const LIMIT = 25;

    public function execute()
    {
        $result = [
            'response' => 1,
            'message' => tr('Polling FE provider completato'),
        ];

        $provider_name = ProviderSettings::selectedProvider();
        $provider = ProviderFactory::make($provider_name);
        $transactions = new ProviderTransactionRepository();

        if (!$provider->isEnabled()) {
            $result['message'] = tr('Provider FE non abilitato');

            return $result;
        }

        if (!$transactions->tableAvailable()) {
            $result['response'] = 2;
            $result['message'] = tr('Tracking transazioni FE non disponibile');

            return $result;
        }

        $rows = $transactions->dueForPolling($provider_name, self::LIMIT);
        if (empty($rows)) {
            $result['message'] = tr('Nessuna transazione FE da controllare');

            return $result;
        }

        $checked = 0;
        $final = 0;
        $waiting = 0;
        $errors = 0;

        foreach ($rows as $row) {
            ++$checked;

            try {
                $id_documento = (int) $row['id_documento'];
                $xml_hash = (string) $row['xml_hash'];
                $response = $provider->getInvoiceReceipts($id_documento);
                $code = (int) ($response['code'] ?? 500);
                $results = $response['results'] ?? [];

                if ($code === 200 && !empty($results)) {
                    $transactions->markFinal(
                        $id_documento,
                        $provider_name,
                        $xml_hash,
                        $this->remoteStatusFromResults($results)
                    );
                    ++$final;
                    continue;
                }

                if ($code === 204 || ($code === 200 && empty($results))) {
                    $transactions->scheduleNextPoll(
                        $id_documento,
                        $provider_name,
                        $xml_hash,
                        (string) ($row['remote_status'] ?? 'waiting')
                    );
                    ++$waiting;
                    continue;
                }

                $message = (string) ($response['message'] ?? tr('Errore durante il polling FE'));
                $transactions->recordPollingError($id_documento, $provider_name, $xml_hash, $message);
                ++$errors;
            } catch (\Throwable $e) {
                $transactions->recordPollingError(
                    (int) $row['id_documento'],
                    $provider_name,
                    (string) $row['xml_hash'],
                    $e->getMessage()
                );
                ++$errors;
                logger_osm()->warning('Polling FE provider fallito per documento '.(int) $row['id_documento'].': '.$e->getMessage());
            }
        }

        if ($errors > 0) {
            $result['response'] = 2;
        }

        $result['message'] = tr('Polling FE: _CHECKED_ controllate, _FINAL_ definitive, _WAITING_ in attesa, _ERRORS_ errori', [
            '_CHECKED_' => $checked,
            '_FINAL_' => $final,
            '_WAITING_' => $waiting,
            '_ERRORS_' => $errors,
        ]);

        return $result;
    }

    private function remoteStatusFromResults(array $results): string
    {
        $last = end($results);

        if (is_array($last)) {
            foreach (['status', 'code', 'type', 'name'] as $key) {
                if (!empty($last[$key])) {
                    return substr((string) $last[$key], 0, 64);
                }
            }

            return 'receipt_available';
        }

        return substr(basename((string) $last), 0, 64) ?: 'receipt_available';
    }
}
