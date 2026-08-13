<?php

namespace Plugins\ExportFE\Providers;

use Modules\Fatture\Fattura;
use Plugins\ExportFE\FatturaElettronica;

class InvoicePayload
{
    public Fattura $fattura;
    public FatturaElettronica $fattura_elettronica;
    public string $filename;
    public string $xml;
    public string $hash;

    public static function fromInvoiceId(int $id_record): self
    {
        $payload = new self();
        $fattura_elettronica = new FatturaElettronica($id_record);
        $fattura = Fattura::find($id_record);

        if (!$fattura) {
            throw new \UnexpectedValueException(tr('Fattura non trovata'), 404);
        }

        $file = $fattura->getFatturaElettronica();

        if (!$file) {
            throw new \UnexpectedValueException(tr('File della fattura elettronica non trovato'), 400);
        }

        if (!$fattura_elettronica->isGenerated()) {
            throw new \UnexpectedValueException(tr('Fattura elettronica non generata correttamente'), 400);
        }

        $payload->fattura_elettronica = $fattura_elettronica;
        $payload->fattura = $fattura;
        $payload->filename = $fattura_elettronica->getFilename();
        $payload->xml = $file->getContent();
        $payload->hash = hash('sha256', $payload->xml);

        return $payload;
    }
}
