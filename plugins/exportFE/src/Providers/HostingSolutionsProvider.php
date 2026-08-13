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
    public const SCENARIO_OK = 'send_ok';
    public const SCENARIO_WAIT = 'wait';
    public const SCENARIO_DELIVERED = 'delivered';
    public const SCENARIO_NOT_DELIVERED = 'not_delivered';
    public const SCENARIO_REJECTED = 'rejected';
    public const SCENARIO_TIMEOUT = 'timeout';
    public const SCENARIO_HTTP_4XX = 'http_4xx';
    public const SCENARIO_HTTP_5XX = 'http_5xx';
    public const SCENARIO_MALFORMED = 'malformed';
    public const SCENARIO_PASSIVE = 'passive_invoice';
    public const SCENARIO_DUPLICATE = 'duplicate';

    private ProviderTransactionRepository $transactions;

    public function __construct(?ProviderTransactionRepository $transactions = null)
    {
        $this->transactions = $transactions ?: new ProviderTransactionRepository();
    }

    public function isEnabled(): bool
    {
        return ProviderSettings::isHostingSolutionsEnabled() && ProviderSettings::isHostingSolutionsMockEnabled();
    }

    public function sendInvoice(int $id_record): array
    {
        if (!$this->isEnabled()) {
            return $this->notConfigured();
        }

        try {
            $payload = InvoicePayload::fromInvoiceId($id_record);
        } catch (\UnexpectedValueException $e) {
            return [
                'code' => $e->getCode() ?: 400,
                'message' => $e->getMessage() ?: tr('Fattura elettronica non valida'),
            ];
        }

        if (!$this->transactions->tableAvailable()) {
            return [
                'code' => 503,
                'message' => tr('Tracking transazioni FE non installato: applicare gli update prima di inviare con Hosting Solutions'),
            ];
        }

        if (!$this->transactions->acquireLock($id_record, ProviderFactory::HOSTING_SOLUTIONS, $payload->hash)) {
            return [
                'code' => 423,
                'message' => tr('Invio FE gia in corso per questa fattura'),
            ];
        }

        try {
            $existing = $this->transactions->findReusable(
                $id_record,
                ProviderFactory::HOSTING_SOLUTIONS,
                $payload->hash
            );

            if ($existing) {
                return [
                    'code' => 202,
                    'message' => tr('Invio gia registrato o con esito incerto: riconciliare prima di ritentare'),
                ];
            }

            $this->transactions->start($id_record, ProviderFactory::HOSTING_SOLUTIONS, $payload->filename, $payload->hash);

            return $this->mockSend($id_record, $payload);
        } finally {
            $this->transactions->releaseLock($id_record, ProviderFactory::HOSTING_SOLUTIONS, $payload->hash);
        }
    }

    public function getInvoiceReceipts(int $id_record): array
    {
        if (!$this->isEnabled()) {
            return $this->notConfigured() + ['results' => []];
        }

        $scenario = ProviderSettings::hostingSolutionsMockScenario();

        if ($scenario === self::SCENARIO_DELIVERED) {
            return [
                'code' => 200,
                'results' => ['IT00000000000_TEST_RC.xml'],
            ];
        }

        if ($scenario === self::SCENARIO_NOT_DELIVERED) {
            return [
                'code' => 200,
                'results' => ['IT00000000000_TEST_MC.xml'],
            ];
        }

        if ($scenario === self::SCENARIO_REJECTED) {
            return [
                'code' => 200,
                'results' => ['IT00000000000_TEST_NS.xml'],
            ];
        }

        return [
            'code' => 204,
            'message' => tr('Nessuna ricevuta disponibile'),
            'results' => [],
        ];
    }

    public function getReceiptList(): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        return $this->getInvoiceReceipts(0)['results']
            ? array_map(fn ($name) => ['name' => $name], $this->getInvoiceReceipts(0)['results'])
            : [];
    }

    public function getReceipt(string $name): ?string
    {
        if (!$this->isEnabled()) {
            return null;
        }

        return $this->mockReceipt($name);
    }

    public function processReceipt(string $filename)
    {
        return $this->isEnabled() ? true : tr('Provider Hosting Solutions non configurato');
    }

    public function getPassiveInvoiceList(): array
    {
        if (!$this->isEnabled() || ProviderSettings::hostingSolutionsMockScenario() !== self::SCENARIO_PASSIVE) {
            return [];
        }

        return [
            [
                'name' => 'IT00000000000_PASSIVE_TEST.xml',
            ],
        ];
    }

    public function getPassiveInvoice(string $name): ?string
    {
        if (!$this->isEnabled() || ProviderSettings::hostingSolutionsMockScenario() !== self::SCENARIO_PASSIVE) {
            return null;
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?><FatturaElettronica versione="FPR12"></FatturaElettronica>';

        return Base64Document::decode(base64_encode($xml));
    }

    public function processPassiveInvoice(string $filename): string
    {
        return $this->isEnabled() ? '' : tr('Provider Hosting Solutions non configurato');
    }

    private function mockSend(int $id_record, InvoicePayload $payload): array
    {
        $scenario = ProviderSettings::hostingSolutionsMockScenario();

        if ($scenario === self::SCENARIO_DUPLICATE) {
            $this->transactions->markFailed($id_record, ProviderFactory::HOSTING_SOLUTIONS, $payload->hash, 'duplicate');

            return [
                'code' => 409,
                'message' => tr('Fattura gia trasmessa secondo il provider mock'),
            ];
        }

        if ($scenario === self::SCENARIO_HTTP_4XX) {
            $this->transactions->markFailed($id_record, ProviderFactory::HOSTING_SOLUTIONS, $payload->hash, 'http_4xx');

            return [
                'code' => 400,
                'message' => tr('Errore HTTP 4xx simulato dal provider mock'),
            ];
        }

        if ($scenario === self::SCENARIO_HTTP_5XX) {
            $this->transactions->markFailed($id_record, ProviderFactory::HOSTING_SOLUTIONS, $payload->hash, 'http_5xx');

            return [
                'code' => 503,
                'message' => tr('Errore HTTP 5xx simulato dal provider mock'),
            ];
        }

        if ($scenario === self::SCENARIO_MALFORMED) {
            $this->transactions->markFailed($id_record, ProviderFactory::HOSTING_SOLUTIONS, $payload->hash, 'malformed_response');

            return [
                'code' => 500,
                'message' => tr('Risposta non valida dal provider mock'),
            ];
        }

        if ($scenario === self::SCENARIO_TIMEOUT) {
            $this->transactions->markUncertain($id_record, ProviderFactory::HOSTING_SOLUTIONS, $payload->hash, 'timeout');

            return [
                'code' => 202,
                'message' => tr('Esito invio incerto: riconciliare prima di ritentare'),
            ];
        }

        $remote_id = 'mock-'.substr($payload->hash, 0, 16);
        $this->transactions->markSent($id_record, ProviderFactory::HOSTING_SOLUTIONS, $payload->hash, $remote_id, $scenario);

        database()->update('co_documenti', [
            'codice_stato_fe' => 'WAIT',
            'data_stato_fe' => date('Y-m-d H:i:s'),
        ], ['id' => $id_record]);

        return [
            'code' => 200,
            'message' => tr('Invio simulato correttamente dal provider Hosting Solutions'),
            'remote_id' => $remote_id,
        ];
    }

    private function mockReceipt(string $name): string
    {
        $code = 'RC';

        if (str_contains($name, '_MC')) {
            $code = 'MC';
        } elseif (str_contains($name, '_NS')) {
            $code = 'NS';
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?><'.$code.'></'.$code.'>';

        return Base64Document::decode(base64_encode($xml));
    }

    private function notConfigured(): array
    {
        return [
            'code' => 501,
            'message' => tr('Provider Hosting Solutions non configurato: abilitarlo solo in modalita mock finche mancano le API ufficiali'),
        ];
    }
}
