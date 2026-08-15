# Integrazione Hosting Solutions FE

Base: OpenSTAManager `v2.10.4` (`fb56ec649fbf8730365f60fa62f60866fff78468`).

Branch: `feature/hosting-solutions-fe-provider`.

## Scelte architetturali

- OSM resta sorgente di fatture, XML, progressivo invio, stati FE, ricevute e import passive.
- Il provider sostituisce solo il trasporto usato da `Interaction`.
- `OSMCloudProvider` mantiene gli endpoint OSMCloud originali: `invio_fattura_xml`, `notifiche_fattura`, `notifiche_da_importare`, `notifica_da_importare`, `notifica_xml_salvata`, `fatture_da_importare`, `fattura_da_importare`, `fattura_xml_salvata`.
- `HostingSolutionsProvider` resta mock/stub finche mancano documentazione API ufficiale e azienda HS in modalita TEST.
- `Terzo intermediario` non viene toccato dal provider: in OSM 2.10.4 modifica `IdTrasmittente`, `TerzoIntermediarioOSoggettoEmittente` e `SoggettoEmittente=TZ`, quindi e' dato fiscale/XML, non configurazione del canale.
- Non viene introdotta una seconda coda FE: viene riutilizzato `InvoiceHookTask` nativo.

## Impostazioni aggiunte

Le impostazioni sono nella sezione nativa `Fatturazione Elettronica`:

- `Fatturazione Elettronica Provider`: select `osmcloud` / `hosting_solutions`.
- `Hosting Solutions FE Abilitato`: abilita il provider per la singola installazione.
- `Hosting Solutions FE Modalita mock`: deve restare attiva finche non ci sono API ufficiali.
- `Hosting Solutions FE Mock Scenario`: select degli scenari simulati consentiti.
- `Hosting Solutions FE Minuti polling`: intero, minimo 15 e massimo 1440 minuti.

La validazione e' applicata sia dall'interfaccia OSM sia da `ProviderSettings`, in modo che valori DB non validi tornino a fallback sicuri.

## Pannello di gestione

La sezione `Fatturazione Elettronica` mostra un riepilogo locale del gateway con:

- provider selezionato;
- modalita nativa/mock/API reale;
- intervallo polling;
- numero transazioni aperte;
- conteggi attesa, esito incerto, concluse ed errori;
- avviso evidente quando Hosting Solutions e' in simulazione;
- avviso sugli invii `UNCERTAIN` sospesi per sicurezza.

Il pannello non effettua chiamate remote durante il caricamento della pagina Impostazioni. Lo stato visualizzato e' locale/configurativo; i controlli remoti restano nei task e nelle azioni FE.

## Anti doppio invio

La tabella `fe_provider_transactions` conserva provider, filename, hash XML, tentativi, remote id/status, errore, date richiesta/risposta e prossimo polling.

Prima di inviare con Hosting Solutions il codice cerca una transazione aperta per stessa fattura, provider e hash XML. Se trova stati `SENDING`, `SENT`, `UNCERTAIN` o `WAITING`, blocca il nuovo POST e richiede riconciliazione.

Timeout e casi ambigui vengono marcati `UNCERTAIN`: non devono essere ritentati automaticamente senza verifica remota.

`InvoiceHookTask` tratta l'esito incerto come `WAIT` e disabilita il retry automatico dell'invio.

## Polling

`ProviderPollingTask` usa `next_poll_at` e processa soltanto transazioni `WAITING` o `UNCERTAIN` scadute per il controllo.

- ricevuta disponibile -> transazione `FINAL`;
- nessuna ricevuta -> pianifica il controllo successivo;
- errore provider -> registra l'errore e riprogramma;
- batch limitato per evitare carico eccessivo.

Il mapping reale degli esiti Hosting Solutions sara completato con la documentazione API ufficiale.

## Ciclo passivo e ricevute

`plugins/receiptFE/custom/src/Interaction.php` e `plugins/importFE/custom/src/Interaction.php` delegano al provider mantenendo il flusso nativo OSM per salvataggio e importazione dei file.

Il mock Hosting Solutions supporta anche documenti Base64 e una fattura passiva simulata per verificare il plumbing prima delle API reali.

## Stato test

Presente `tests/ExportFE/provider_smoke.php`, che copre almeno:

- provider default/fallback;
- selezione Hosting Solutions;
- abilitazione mock;
- fallback scenario mock non valido;
- limite minimo/massimo polling;
- decode Base64 valido e invalido.

Restano necessari test di integrazione su una copia OSM 2.10.4 con DB aggiornato e successivamente test end-to-end con azienda Hosting Solutions in modalita TEST.

## Informazioni mancanti HS

Servono endpoint, autenticazione, payload, codici risposta, idempotency key o ricerca remota per filename/progressivo/hash, mapping stati SDI, formato elenco ricevute, formato download documenti Base64, formato elenco/download passive e rate limit.
