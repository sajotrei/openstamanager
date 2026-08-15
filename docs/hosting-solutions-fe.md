# Integrazione Hosting Solutions FE

Base verificata: OpenSTAManager `v2.10.4` (`fb56ec649fbf8730365f60fa62f60866fff78468`).

Branch: `feature/hosting-solutions-fe-provider`.

## Principio architetturale verificato

- OSM resta sorgente di fatture, XML, progressivo invio, stati FE, ricevute e import passive.
- Hosting Solutions sostituisce soltanto il trasporto usato dalle classi `Interaction`.
- Non vengono introdotte code o scheduler paralleli: invio, ricevute e passive usano le task native OSM.
- `OSMCloudProvider` mantiene gli endpoint OSMCloud originali.
- `HostingSolutionsProvider` resta mock/stub finché mancano documentazione API ufficiale e azienda HS in modalità TEST.
- `Terzo intermediario` non è un selettore provider: in OSM 2.10.4 influenza nome file/trasmittente e nodi fiscali dell'XML, quindi non viene impostato automaticamente.

## Task native OSM 2.10.4 verificate

L'update ufficiale `update/2_9_1.sql` configura:

- invio FE (`Plugins\\ExportFE\\InvoiceHookTask`): `*/30 * * * *`;
- importazione automatica ricevute FE: `0 */4 * * *`;
- ricerca fatture passive (`Plugins\\ImportFE\\InvoiceHookTask`): `0 */24 * * *`.

`cron.php` esegue le righe abilitate di `zz_tasks`; il commento nativo raccomanda un richiamo di sistema ogni 5 minuti.

Queste frequenze sono quelle OSM 2.10.4, non sono ancora dichiarate ottimali per Hosting Solutions. Verranno rivalutate solo dopo aver verificato i rate limit/API ufficiali.

## Hook/notifiche native riutilizzate

- `Plugins\\ExportFE\\InvoiceHook`: stato invio FE in coda/errori.
- `Plugins\\ImportFE\\InvoiceHook`: conteggio e link alle fatture passive da importare.
- `Plugins\\ReceiptFE\\ReceiptHook`: ricevute importate/totali.
- `Plugins\\ReceiptFE\\NotificheRicevuteHook`: scarti/errori e fatture `WAIT` da oltre 7 giorni.

Gli hook restano UI/notifiche; le operazioni automatiche sono affidate alle task.

## Impostazioni aggiunte

Le impostazioni sono nella sezione nativa `Fatturazione Elettronica`:

- `Fatturazione Elettronica Provider`: select `osmcloud` / `hosting_solutions`;
- `Hosting Solutions FE Abilitato`;
- `Hosting Solutions FE Modalita mock`;
- `Hosting Solutions FE Mock Scenario`.

Titolo e help sono registrati in `zz_settings_lang` (IT/EN), come richiesto dal modello `Models\\Setting` di OSM 2.10.4.

L'impostazione sperimentale di polling provider è stata rimossa: non esiste un secondo scheduler FE.

## Pannello di gestione

La sezione `Fatturazione Elettronica` mostra un riepilogo locale del gateway con:

- provider;
- modalità nativa/mock/API reale;
- indicazione `Task native OSM`;
- transazioni aperte;
- conteggi attesa, esito incerto, concluse ed errori;
- avviso modalità mock;
- avviso `UNCERTAIN`.

Il pannello non effettua chiamate remote in apertura pagina.

## Anti doppio invio

La tabella `fe_provider_transactions` conserva solo dati tecnici di lifecycle:

- provider;
- filename;
- hash XML;
- tentativi;
- remote id/status;
- ultimo errore;
- date richiesta/risposta;
- stato tecnico.

Prima dell'invio HS il codice acquisisce un `GET_LOCK` MySQL per fattura/provider/hash e cerca transazioni già aperte (`SENDING`, `SENT`, `UNCERTAIN`, `WAITING`).

Un timeout ambiguo viene marcato `UNCERTAIN`, la fattura OSM viene posta in `WAIT` e non viene ritentata automaticamente. La protezione vale anche per l'invio manuale.

Anche una perdita della risposta AJAX lato browser viene trattata in modo conservativo nell'UI: il pulsante viene disabilitato e la pagina viene ricaricata prima di consentire ulteriori azioni, perché il documento potrebbe essere già arrivato al provider.

## UX invio manuale

È presente `plugins/exportFE/custom/edit.php` come override minimale.

L'override include integralmente il template nativo e ridefinisce soltanto `inviaFE()`, evitando di duplicare il grande `plugins/exportFE/edit.php`.

Sono distinti gli esiti:

- `200`: inviato;
- `202`: invio sospeso/in attesa di riconciliazione, nessun retry immediato;
- `301`: già inviato;
- `423`: invio già in elaborazione;
- `500/503`: errore provider/server;
- errore di trasporto AJAX: stato remoto non verificabile, pulsante bloccato e rilettura stato.

## Ricevute SDI

Il provider restituisce il file; la gestione fiscale rimane in `Plugins\\ReceiptFE\\Ricevuta`:

1. download tramite provider;
2. salvataggio/allegato nativo OSM;
3. parsing stato SDI nativo (`RC`, `MC`, `NS`, ecc.);
4. aggiornamento `codice_stato_fe`, data, descrizione e ricevuta principale;
5. cleanup file temporaneo;
6. `Interaction::processReceipt()` verso il provider.

Il tracking provider viene marcato `FINAL` soltanto al punto 6, cioè dopo il completamento locale OSM.

Il mock genera nomi ricevuta dal filename XML realmente trasmesso: `<trasmittente>_<progressivo>_<RC|MC|NS>.xml`, coerentemente con il parser OSM che ricava progressivo e stato dal nome file.

## Recupero ricevute mancanti

È presente un override `plugins/receiptFE/custom/src/MissingReceiptTask.php` perché il task nativo 2.10.4 non normalizza correttamente la struttura `code/results` usata da `Interaction` e non restituisce il normale risultato `response/message` atteso da `Tasks\\Task`.

L'override mantiene il recupero delle fatture `WAIT` oltre 7 giorni, forza l'associazione alla fattura già nota e registra gli errori.

## Fatture passive

`plugins/importFE/custom/src/Interaction.php` delega lista/download/conferma al provider e conserva il motore nativo OSM.

Verificato nel codice 2.10.4:

- controllo P.IVA/CF destinatario/azienda;
- controllo duplicato con progressivo invio, numero, data e fornitore;
- creazione fattura tramite parser nativo;
- conferma al provider con `processInvoice()` soltanto dopo il salvataggio locale riuscito.

### Fixture passiva mock

Il vecchio XML vuoto è stato sostituito con una FatturaPA FPR12 completa per test di integrazione.

La fixture contiene:

- `DatiTrasmissione` e progressivo `HSM01`;
- cedente/fornitore mock;
- cessionario costruito dinamicamente dall'`Azienda predefinita` OSM;
- P.IVA e/o codice fiscale reali dell'azienda locale, così il controllo destinatario nativo resta attivo;
- `TD01`, data e numero documento;
- una riga da 100,00 EUR + IVA 22%;
- riepilogo IVA;
- totale 122,00 EUR;
- pagamento `MP05`.

La fixture è destinata al collaudo OSMLAB, non rappresenta un documento fiscale reale e non viene inviata a SDI.

## Update DB dell'integrazione

L'update è `plugins/exportFE/update/1_0_0.sql`.

È volutamente versionato come componente `1.0.0`, non come `2.10.4.1`: il motore OSM scopre autonomamente gli update nelle cartelle `plugins/*/update`, mentre la compatibilità target resta OSM 2.10.4.

## Stato test

`tests/ExportFE/provider_smoke.php` copre:

- provider default/fallback;
- selezione Hosting Solutions;
- abilitazione mock;
- fallback scenario non valido;
- assenza del vecchio scheduler provider parallelo;
- Base64 valido/invalido.

L'audit statico del codice OSM 2.10.4 è completato per invio, task, ricevute, passive, impostazioni e override custom.

Non dichiarare ancora il ramo "testato end-to-end": resta necessario eseguire il collaudo runtime su una copia OSMLAB 2.10.4 con database aggiornato e, successivamente, il test con azienda Hosting Solutions in modalità TEST.

## Informazioni mancanti Hosting Solutions

Servono ancora documentazione ufficiale per:

- endpoint;
- autenticazione;
- payload;
- codici risposta;
- identificativo/idempotency o ricerca remota per riconciliare timeout ambigui;
- mapping stati;
- formato elenco/download ricevute;
- formato elenco/download passive Base64;
- rate limit e frequenze consigliate.
