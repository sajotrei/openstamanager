# Integrazione Hosting Solutions FE

Base: OpenSTAManager `v2.10.4` (`fb56ec649fbf8730365f60fa62f60866fff78468`).

## Scelte architetturali

- OSM resta sorgente di fatture, XML, progressivo invio, stati FE, ricevute e import passive.
- Il provider sostituisce solo il trasporto usato da `Interaction`.
- `OSMCloudProvider` mantiene gli endpoint OSMCloud originali: `invio_fattura_xml`, `notifiche_fattura`, `notifiche_da_importare`, `notifica_da_importare`, `notifica_xml_salvata`, `fatture_da_importare`, `fattura_da_importare`, `fattura_xml_salvata`.
- `HostingSolutionsProvider` e' solo mock/stub finche mancano documentazione API ufficiale e azienda HS in modalita TEST.
- `Terzo intermediario` non viene toccato dal provider: in OSM 2.10.4 modifica `IdTrasmittente`, `TerzoIntermediarioOSoggettoEmittente` e `SoggettoEmittente=TZ`, quindi e' dato fiscale/XML, non configurazione del canale.

## Impostazioni aggiunte

- `Fatturazione Elettronica Provider`: `osmcloud` o `hosting_solutions`.
- `Hosting Solutions FE Abilitato`: abilita il provider per la singola installazione.
- `Hosting Solutions FE Modalita mock`: deve restare attiva finche non ci sono API ufficiali.
- `Hosting Solutions FE Mock Scenario`: scenari simulati.
- `Hosting Solutions FE Minuti polling`: intervallo minimo, con floor a 15 minuti.

## Anti doppio invio

La tabella `fe_provider_transactions` conserva provider, filename, hash XML, tentativi, remote id/status, errore, date richiesta/risposta e prossimo polling.

Prima di inviare con Hosting Solutions il codice cerca una transazione aperta per stessa fattura, provider e hash XML. Se trova stati `SENDING`, `SENT`, `UNCERTAIN` o `WAITING`, blocca il nuovo POST e richiede riconciliazione.

Timeout e casi ambigui vengono marcati `UNCERTAIN`: non devono essere ritentati automaticamente senza verifica remota.

## Informazioni mancanti HS

Servono endpoint, autenticazione, payload, codici risposta, idempotency key o ricerca remota per filename/progressivo/hash, mapping stati SDI, formato elenco ricevute, formato download documenti Base64, formato elenco/download passive e rate limit.
