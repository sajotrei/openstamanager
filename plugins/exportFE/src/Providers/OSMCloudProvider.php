<?php

namespace Plugins\ExportFE\Providers;

use API\Services;
use Modules\Fatture\Fattura;
use Plugins\ExportFE\FatturaElettronica;

/**
 * Provider compatibile con il trasporto OSMCloud originale di OSM 2.10.4.
 *
 * Mantiene invariato il comportamento preesistente mentre il livello di
 * trasporto viene separato dalla logica FE del gestionale.
 */
class OSMCloudProvider extends Services implements ProviderInterface
{
    public function isEnabled(): bool
    {
        return parent::isEnabled() && self::verificaRisorsaAttiva('Fatturazione Elettronica');
    }

    public function sendInvoice(int $id_record): array
    {
        try {
            $fattura_elettronica = new FatturaElettronica($id_record);
            $fattura = Fattura::find($id_record);

            if (!$fattura) {
                return [
                    'code' => 404,
                    'message' => tr('Fattura non trovata'),
                ];
            }

            $file = $fattura->getFatturaElettronica();

            if (!$file) {
                return [
                    'code' => 400,
                    'message' => tr('File della fattura elettronica non trovato'),
                ];
            }

            if (!$fattura_elettronica->isGenerated()) {
                return [
                    'code' => 400,
                    'message' => tr('Fattura elettronica non generata correttamente'),
                ];
            }

            $response = static::request('POST', 'invio_fattura_xml', [
                'xml' => $file->getContent(),
                'filename' => $fattura_elettronica->getFilename(),
            ]);
            $body = static::responseBody($response);

            if (empty($body) || !isset($body['status'])) {
                logger_osm()->error('Risposta API non valida per fattura '.$fattura->numero_esterno.': '.json_encode($body));

                return [
                    'code' => 500,
                    'message' => tr('Risposta non valida dal server'),
                ];
            }

            $status_code = (int) $body['status'];

            if ($status_code == 200 || $status_code == 301) {
                database()->update('co_documenti', [
                    'codice_stato_fe' => 'WAIT',
                    'data_stato_fe' => date('Y-m-d H:i:s'),
                ], ['id' => $id_record]);
            } else {
                database()->update('co_documenti', [
                    'codice_stato_fe' => 'ERR',
                    'data_stato_fe' => date('Y-m-d H:i:s'),
                ], ['id' => $id_record]);

                logger_osm()->warning('Errore invio FE fattura '.$fattura->numero_esterno.': '.($body['message'] ?? ''));
            }

            return [
                'code' => $status_code,
                'message' => $body['message'] ?? tr('Risposta non valida dal server'),
            ];
        } catch (\UnexpectedValueException $e) {
            logger_osm()->error('Fattura elettronica non valida per ID '.$id_record.': '.$e->getMessage());

            return [
                'code' => 400,
                'message' => tr('Fattura elettronica non valida'),
            ];
        } catch (\Exception $e) {
            logger_osm()->error('Errore durante invio fattura elettronica ID '.$id_record.': '.$e->getMessage());

            return [
                'code' => 500,
                'message' => tr('Errore interno durante l\'invio: _ERR_', ['_ERR_' => $e->getMessage()]),
            ];
        }
    }

    public function getInvoiceReceipts(int $id_record): array
    {
        try {
            $fattura_elettronica = new FatturaElettronica($id_record);
            $filename = $fattura_elettronica->getFilename();

            $response = static::request('POST', 'notifiche_fattura', [
                'name' => $filename,
            ]);
            $body = static::responseBody($response);

            if (empty($body) || !isset($body['status'])) {
                logger_osm()->error('Risposta API non valida per ricevute fattura ID '.$id_record.': '.json_encode($body));

                return [
                    'code' => 500,
                    'message' => tr('Risposta non valida dal server'),
                ];
            }

            return [
                'code' => (int) $body['status'],
                'results' => $body['results'] ?? [],
            ];
        } catch (\UnexpectedValueException) {
        }

        return [
            'code' => 400,
            'message' => tr('Fattura non generata correttamente'),
        ];
    }

    public function getReceiptList(): array
    {
        $response = static::request('POST', 'notifiche_da_importare');
        $body = static::responseBody($response);
        $list = [];

        if (!empty($body) && isset($body['status']) && (int) $body['status'] == 200) {
            foreach ($body['results'] ?? [] as $result) {
                $list[] = [
                    'name' => $result,
                ];
            }
        }

        return $list;
    }

    public function getReceipt(string $name): ?string
    {
        $response = static::request('POST', 'notifica_da_importare', [
            'name' => $name,
        ]);
        $body = static::responseBody($response);

        if (!empty($body['content'])) {
            return $body['content'];
        }

        return null;
    }

    public function processReceipt(string $filename)
    {
        $response = static::request('POST', 'notifica_xml_salvata', [
            'filename' => $filename,
        ]);
        $body = static::responseBody($response);

        if (empty($body) || !isset($body['status']) || (int) $body['status'] != 200) {
            $status = $body['status'] ?? 'unknown';
            $msg = $body['message'] ?? 'Errore sconosciuto';

            return $status.' - '.$msg;
        }

        return true;
    }

    public function getPassiveInvoiceList(): array
    {
        $response = static::request('POST', 'fatture_da_importare');
        $body = static::responseBody($response);

        if (!empty($body) && isset($body['status']) && (int) $body['status'] == 200) {
            return $body['results'] ?? [];
        }

        return [];
    }

    public function getPassiveInvoice(string $name): ?string
    {
        $response = static::request('POST', 'fattura_da_importare', [
            'name' => $name,
        ]);
        $body = static::responseBody($response);

        if (!empty($body['content'])) {
            return $body['content'];
        }

        return null;
    }

    public function processPassiveInvoice(string $filename): string
    {
        $response = static::request('POST', 'fattura_xml_salvata', [
            'filename' => $filename,
        ]);
        $body = static::responseBody($response);

        if (empty($body) || !isset($body['status']) || (int) $body['status'] != 200) {
            $status = $body['status'] ?? 'unknown';
            $msg = $body['message'] ?? 'Errore sconosciuto';

            return $status.' - '.$msg;
        }

        return '';
    }
}
