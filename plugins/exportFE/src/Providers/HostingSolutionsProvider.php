<?php

namespace Plugins\ExportFE\Providers;

use Models\Cache;

/**
 * Provider Hosting Solutions in modalita' simulazione.
 *
 * La struttura HTTP reale verra' implementata esclusivamente sulla base della
 * documentazione API ufficiale e dell'azienda Hosting Solutions in TEST.
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

    private const MOCK_PASSIVE_FILENAME = 'IT01234567890_HSM01.xml';
    private const MOCK_PASSIVE_CACHE = 'Hosting Solutions FE Mock Passive Processed';

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
                'message' => tr('Tracking transazioni FE non installato: applicare gli aggiornamenti prima di inviare con Hosting Solutions'),
            ];
        }

        $this->transactions->recoverStaleSending(ProviderFactory::HOSTING_SOLUTIONS);

        if (!$this->transactions->acquireLock($id_record, ProviderFactory::HOSTING_SOLUTIONS, $payload->hash)) {
            return [
                'code' => 423,
                'message' => tr('Invio FE già in corso per questa fattura'),
            ];
        }

        try {
            $existing = $this->transactions->findReusable(
                $id_record,
                ProviderFactory::HOSTING_SOLUTIONS,
                $payload->hash
            );

            if ($existing) {
                if (($existing['status'] ?? null) === ProviderTransactionRepository::STATUS_FINAL) {
                    return [
                        'code' => 301,
                        'message' => tr('Lo stesso XML risulta già concluso nel tracking del provider'),
                    ];
                }

                $this->markDocumentWaiting($id_record);

                return [
                    'code' => 202,
                    'message' => tr('Invio già registrato o con esito incerto: riconciliare prima di ritentare'),
                ];
            }

            $this->transactions->start(
                $id_record,
                ProviderFactory::HOSTING_SOLUTIONS,
                $payload->filename,
                $payload->hash
            );

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

        $code = $this->receiptCodeForScenario();
        if ($code === null) {
            return [
                'code' => 204,
                'message' => tr('Nessuna ricevuta disponibile'),
                'results' => [],
            ];
        }

        try {
            $payload = InvoicePayload::fromInvoiceId($id_record);
        } catch (\UnexpectedValueException $e) {
            return [
                'code' => $e->getCode() ?: 400,
                'message' => $e->getMessage() ?: tr('Fattura elettronica non valida'),
                'results' => [],
            ];
        }

        $transaction = $this->transactions->findReusable(
            $id_record,
            ProviderFactory::HOSTING_SOLUTIONS,
            $payload->hash
        );

        if (!$transaction || ($transaction['status'] ?? null) === ProviderTransactionRepository::STATUS_FINAL) {
            return [
                'code' => 204,
                'message' => tr('Nessuna ricevuta disponibile per una transazione aperta'),
                'results' => [],
            ];
        }

        return [
            'code' => 200,
            'results' => [$this->receiptName($payload->filename, $code)],
        ];
    }

    public function getReceiptList(): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        $this->transactions->recoverStaleSending(ProviderFactory::HOSTING_SOLUTIONS);

        $code = $this->receiptCodeForScenario();
        if ($code === null) {
            return [];
        }

        $list = [];
        foreach ($this->transactions->openForProvider(ProviderFactory::HOSTING_SOLUTIONS) as $transaction) {
            if (!empty($transaction['filename'])) {
                $list[] = [
                    'name' => $this->receiptName((string) $transaction['filename'], $code),
                ];
            }
        }

        return $list;
    }

    public function getReceipt(string $name): ?string
    {
        if (!$this->isEnabled()) {
            return null;
        }

        // Il provider simulato non deve poter creare ricevute arbitrarie: il nome
        // deve corrispondere a una transazione effettivamente ancora aperta.
        if (!$this->transactions->findOpenByReceiptFilename(ProviderFactory::HOSTING_SOLUTIONS, $name)) {
            return null;
        }

        return $this->mockReceipt($name);
    }

    public function processReceipt(string $filename)
    {
        if (!$this->isEnabled()) {
            return tr('Provider Hosting Solutions non configurato');
        }

        $closed = $this->transactions->markFinalByReceiptFilename(
            ProviderFactory::HOSTING_SOLUTIONS,
            $filename,
            $this->receiptStatusFromFilename($filename)
        );

        return $closed ? true : tr('Ricevuta non associata a una transazione provider aperta');
    }

    public function getPassiveInvoiceList(): array
    {
        if (!$this->isEnabled() || ProviderSettings::hostingSolutionsMockScenario() !== self::SCENARIO_PASSIVE) {
            return [];
        }

        return in_array(self::MOCK_PASSIVE_FILENAME, $this->processedPassiveInvoices(), true)
            ? []
            : [['name' => self::MOCK_PASSIVE_FILENAME]];
    }

    public function getPassiveInvoice(string $name): ?string
    {
        if (!$this->isEnabled()
            || ProviderSettings::hostingSolutionsMockScenario() !== self::SCENARIO_PASSIVE
            || basename($name) !== self::MOCK_PASSIVE_FILENAME
            || in_array(self::MOCK_PASSIVE_FILENAME, $this->processedPassiveInvoices(), true)) {
            return null;
        }

        return Base64Document::decode(base64_encode($this->mockPassiveInvoice()));
    }

    public function processPassiveInvoice(string $filename): string
    {
        if (!$this->isEnabled()) {
            return tr('Provider Hosting Solutions non configurato');
        }

        $filename = basename($filename);
        if ($filename !== self::MOCK_PASSIVE_FILENAME) {
            return tr('Documento passivo non riconosciuto dal provider simulato');
        }

        $processed = $this->processedPassiveInvoices();
        if (!in_array($filename, $processed, true)) {
            $processed[] = $filename;
            $this->setProcessedPassiveInvoices($processed);
        }

        return '';
    }

    private function mockSend(int $id_record, InvoicePayload $payload): array
    {
        $scenario = ProviderSettings::hostingSolutionsMockScenario();

        if ($scenario === self::SCENARIO_DUPLICATE) {
            $remote_id = 'mock-duplicate-'.substr($payload->hash, 0, 12);
            $this->transactions->markSent(
                $id_record,
                ProviderFactory::HOSTING_SOLUTIONS,
                $payload->hash,
                $remote_id,
                'duplicate'
            );
            $this->markDocumentWaiting($id_record);

            return [
                'code' => 301,
                'message' => tr('Documento già presente secondo il provider simulato'),
                'remote_id' => $remote_id,
            ];
        }

        if ($scenario === self::SCENARIO_HTTP_4XX) {
            $this->transactions->markFailed(
                $id_record,
                ProviderFactory::HOSTING_SOLUTIONS,
                $payload->hash,
                'http_4xx'
            );

            return [
                'code' => 400,
                'message' => tr('Errore richiesta simulato dal provider'),
            ];
        }

        if ($scenario === self::SCENARIO_HTTP_5XX) {
            return $this->uncertainResult($id_record, $payload, 'http_5xx');
        }

        if ($scenario === self::SCENARIO_MALFORMED) {
            return $this->uncertainResult($id_record, $payload, 'malformed_response');
        }

        if ($scenario === self::SCENARIO_TIMEOUT) {
            return $this->uncertainResult($id_record, $payload, 'timeout');
        }

        $remote_id = 'mock-'.substr($payload->hash, 0, 16);
        $this->transactions->markSent(
            $id_record,
            ProviderFactory::HOSTING_SOLUTIONS,
            $payload->hash,
            $remote_id,
            $scenario
        );
        $this->markDocumentWaiting($id_record);

        return [
            'code' => 200,
            'message' => tr('Invio simulato acquisito dal provider Hosting Solutions'),
            'remote_id' => $remote_id,
        ];
    }

    private function uncertainResult(int $id_record, InvoicePayload $payload, string $reason): array
    {
        $this->transactions->markUncertain(
            $id_record,
            ProviderFactory::HOSTING_SOLUTIONS,
            $payload->hash,
            $reason
        );
        $this->markDocumentWaiting($id_record);

        return [
            'code' => 202,
            'message' => tr('Esito invio non determinabile: verificare lo stato remoto prima di ritentare'),
        ];
    }

    private function markDocumentWaiting(int $id_record): void
    {
        database()->update('co_documenti', [
            'codice_stato_fe' => 'WAIT',
            'data_stato_fe' => date('Y-m-d H:i:s'),
        ], ['id' => $id_record]);
    }

    /**
     * FatturaPA passiva completa per test d'integrazione.
     * Il destinatario viene costruito dall'Azienda predefinita, cosi' il controllo
     * P.IVA/CF del parser del gestionale resta attivo durante la simulazione.
     */
    private function mockPassiveInvoice(): string
    {
        $azienda = \Modules\Anagrafiche\Anagrafica::find(setting('Azienda predefinita'));
        if (!$azienda) {
            throw new \UnexpectedValueException(tr('Azienda predefinita non configurata per il test della fattura passiva'));
        }

        $country = $azienda->nazione->iso2 ?? 'IT';
        $vat = preg_replace('/^'.preg_quote((string) $country, '/').'/', '', (string) $azienda->piva);
        $cf = trim((string) $azienda->codice_fiscale);
        if ($vat === '' && $cf === '') {
            throw new \UnexpectedValueException(tr('P.IVA o codice fiscale azienda mancanti per il test della fattura passiva'));
        }

        $recipientFiscal = $vat !== ''
            ? '<IdFiscaleIVA><IdPaese>'.$this->xml($country).'</IdPaese><IdCodice>'.$this->xml($vat).'</IdCodice></IdFiscaleIVA>'
            : '';
        if ($cf !== '') {
            $recipientFiscal .= '<CodiceFiscale>'.$this->xml($cf).'</CodiceFiscale>';
        }

        $date = date('Y-m-d');
        $denomination = $azienda->ragione_sociale ?: 'Azienda TEST';
        $address = $azienda->indirizzo ?: 'Via Test 1';
        $cap = $azienda->cap ?: '00000';
        $city = $azienda->citta ?: 'TEST';
        $province = $azienda->provincia ?: 'XX';

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<p:FatturaElettronica xmlns:p="http://ivaservizi.agenziaentrate.gov.it/docs/xsd/fatture/v1.2" versione="FPR12">'
            .'<FatturaElettronicaHeader>'
            .'<DatiTrasmissione><IdTrasmittente><IdPaese>IT</IdPaese><IdCodice>01234567890</IdCodice></IdTrasmittente><ProgressivoInvio>HSM01</ProgressivoInvio><FormatoTrasmissione>FPR12</FormatoTrasmissione><CodiceDestinatario>0000000</CodiceDestinatario></DatiTrasmissione>'
            .'<CedentePrestatore><DatiAnagrafici><IdFiscaleIVA><IdPaese>IT</IdPaese><IdCodice>01234567890</IdCodice></IdFiscaleIVA><CodiceFiscale>01234567890</CodiceFiscale><Anagrafica><Denominazione>Fornitore Mock Hosting Solutions</Denominazione></Anagrafica><RegimeFiscale>RF01</RegimeFiscale></DatiAnagrafici><Sede><Indirizzo>Via Provider Test</Indirizzo><NumeroCivico>1</NumeroCivico><CAP>50100</CAP><Comune>Firenze</Comune><Provincia>FI</Provincia><Nazione>IT</Nazione></Sede></CedentePrestatore>'
            .'<CessionarioCommittente><DatiAnagrafici>'.$recipientFiscal.'<Anagrafica><Denominazione>'.$this->xml($denomination).'</Denominazione></Anagrafica></DatiAnagrafici><Sede><Indirizzo>'.$this->xml($address).'</Indirizzo><CAP>'.$this->xml($cap).'</CAP><Comune>'.$this->xml($city).'</Comune><Provincia>'.$this->xml($province).'</Provincia><Nazione>'.$this->xml($country).'</Nazione></Sede></CessionarioCommittente>'
            .'</FatturaElettronicaHeader>'
            .'<FatturaElettronicaBody>'
            .'<DatiGenerali><DatiGeneraliDocumento><TipoDocumento>TD01</TipoDocumento><Divisa>EUR</Divisa><Data>'.$date.'</Data><Numero>HS-MOCK-001</Numero><ImportoTotaleDocumento>122.00</ImportoTotaleDocumento><Causale>Documento generato esclusivamente per test integrazione Hosting Solutions</Causale></DatiGeneraliDocumento></DatiGenerali>'
            .'<DatiBeniServizi><DettaglioLinee><NumeroLinea>1</NumeroLinea><Descrizione>Servizio mock Hosting Solutions</Descrizione><Quantita>1.00</Quantita><UnitaMisura>NR</UnitaMisura><PrezzoUnitario>100.00</PrezzoUnitario><PrezzoTotale>100.00</PrezzoTotale><AliquotaIVA>22.00</AliquotaIVA></DettaglioLinee><DatiRiepilogo><AliquotaIVA>22.00</AliquotaIVA><ImponibileImporto>100.00</ImponibileImporto><Imposta>22.00</Imposta><EsigibilitaIVA>I</EsigibilitaIVA></DatiRiepilogo></DatiBeniServizi>'
            .'<DatiPagamento><CondizioniPagamento>TP02</CondizioniPagamento><DettaglioPagamento><ModalitaPagamento>MP05</ModalitaPagamento><DataScadenzaPagamento>'.$date.'</DataScadenzaPagamento><ImportoPagamento>122.00</ImportoPagamento></DettaglioPagamento></DatiPagamento>'
            .'</FatturaElettronicaBody>'
            .'</p:FatturaElettronica>';
    }

    private function processedPassiveInvoices(): array
    {
        $cache = Cache::where('name', self::MOCK_PASSIVE_CACHE)->first();
        $content = $cache?->content ?? [];

        return is_array($content) ? array_values(array_unique($content)) : [];
    }

    private function setProcessedPassiveInvoices(array $filenames): void
    {
        $cache = Cache::where('name', self::MOCK_PASSIVE_CACHE)->first();
        if (empty($cache)) {
            // La conferma del provider deve persistere tra le esecuzioni delle task.
            // Il reset esplicito avviene selezionando nuovamente lo scenario passivo.
            $cache = Cache::build(
                self::MOCK_PASSIVE_CACHE,
                null,
                \Carbon\Carbon::now()->addYears(10)
            );
        }

        $cache->set(array_values(array_unique(array_map('basename', $filenames))));
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function receiptCodeForScenario(): ?string
    {
        return match (ProviderSettings::hostingSolutionsMockScenario()) {
            self::SCENARIO_DELIVERED => 'RC',
            self::SCENARIO_NOT_DELIVERED => 'MC',
            self::SCENARIO_REJECTED => 'NS',
            default => null,
        };
    }

    private function receiptName(string $invoice_filename, string $code): string
    {
        return pathinfo(basename($invoice_filename), PATHINFO_FILENAME).'_'.$code.'.xml';
    }

    private function receiptStatusFromFilename(string $filename): string
    {
        $pieces = explode('_', pathinfo(basename($filename), PATHINFO_FILENAME));

        return substr((string) ($pieces[2] ?? 'receipt'), 0, 64);
    }

    private function mockReceipt(string $name): string
    {
        $code = str_contains($name, '_MC') ? 'MC' : (str_contains($name, '_NS') ? 'NS' : 'RC');
        $date = date('c');

        if ($code === 'RC') {
            $xml = '<?xml version="1.0" encoding="UTF-8"?>'
                .'<RicevutaConsegna><DataOraRicezione>'.$date.'</DataOraRicezione>'
                .'<Destinatario><Descrizione>Ricevuta di consegna simulata</Descrizione></Destinatario>'
                .'</RicevutaConsegna>';
        } elseif ($code === 'MC') {
            $xml = '<?xml version="1.0" encoding="UTF-8"?>'
                .'<MancataConsegna><DataOraRicezione>'.$date.'</DataOraRicezione>'
                .'<Descrizione>Mancata consegna simulata</Descrizione>'
                .'</MancataConsegna>';
        } else {
            $xml = '<?xml version="1.0" encoding="UTF-8"?>'
                .'<NotificaScarto><DataOraRicezione>'.$date.'</DataOraRicezione>'
                .'<ListaErrori><Errore>'
                .'<Codice>MOCK01</Codice>'
                .'<Descrizione>Scarto simulato dal provider Hosting Solutions</Descrizione>'
                .'<Suggerimento>Correggere il documento prima di un nuovo invio</Suggerimento>'
                .'</Errore></ListaErrori></NotificaScarto>';
        }

        return Base64Document::decode(base64_encode($xml));
    }

    private function notConfigured(): array
    {
        return [
            'code' => 501,
            'message' => tr('Provider Hosting Solutions non configurato: la modalità reale non è ancora disponibile'),
        ];
    }
}
