# Hosting Solutions FE Connector — stato 1.0.2-test

Target verificato: OpenSTAManager 2.10.4 (`fb56ec649fbf8730365f60fa62f60866fff78468`).

## Chiuso prima delle API reali

- provider abstraction con fallback OSMCloud;
- invio manuale e da coda tramite provider;
- tracking tecnico con hash XML e lock MySQL;
- protezione da doppio invio per transazioni SENDING/WAITING/UNCERTAIN/FINAL;
- recupero SENDING interrotto come UNCERTAIN;
- timeout, HTTP 5xx e risposta non interpretabile trattati come esito incerto;
- HTTP 4xx trattato come errore deterministico nel mock;
- ricevute RC/MC/NS simulate e processate dal parser fiscale nativo;
- chiusura tracking soltanto dopo import locale della ricevuta;
- task ricevute robusto con creazione cache e batch 25;
- task passive robusto con logging errori provider;
- lista/download passive tramite provider;
- fixture TD01 passiva completa costruita sull'Azienda predefinita;
- acknowledgement mock: una passiva importata non viene riproposta;
- validazione locale dei filename remoti e protezione path traversal;
- pannello Gateway FE con provider, modalità, versione connettore, stato task e tracking;
- etichette UI leggibili e campi provider contestuali;
- modalità Hosting Solutions reale bloccata: simulazione obbligatoria fino alle API;
- niente scheduler parallelo: vengono riutilizzate le task FE esistenti del gestionale;
- `Terzo intermediario` lasciato indipendente dal provider perché modifica XML/nome file/trasmittente.

## Da collaudare su copia 2.10.4

1. caricamento update diretto;
2. layout Impostazioni FE senza caratteri `\\n` visibili;
3. selezione Hosting Solutions e visibilità campi;
4. scenari send_ok / wait / delivered / not_delivered / rejected;
5. timeout / HTTP 5xx / malformed => WAIT + UNCERTAIN e nessun retry;
6. duplicate => nessun secondo POST;
7. task ReceiptTask su batch mock;
8. fattura passiva mock: lista -> download -> parser -> import -> acknowledgement -> sparizione lista;
9. ritorno a OSMCloud senza regressioni.

## Dipendenze esterne ancora mancanti

L'adapter reale non deve essere implementato per supposizione. Servono dalla documentazione Hosting Solutions:

- URL endpoint;
- autenticazione e ciclo credenziali;
- payload invio XML;
- response schema e remote/document ID;
- ricerca documento per riconciliazione dopo timeout;
- elenco/download ricevute;
- elenco/download passive Base64;
- acknowledgement dei documenti acquisiti;
- mapping degli stati;
- timeout e retry raccomandati;
- rate limit;
- ambiente/azienda TEST.

Quando questi dati saranno disponibili, la parte restante deve essere confinata principalmente al client HTTP Hosting Solutions e al mapping delle risposte verso il contratto provider già predisposto.
