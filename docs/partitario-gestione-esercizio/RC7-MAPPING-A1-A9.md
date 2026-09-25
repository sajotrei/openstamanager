# RC7 — mappatura report, codice e test

| Punto | Causa verificata | File RC7 | Correzione | Test |
|---|---|---|---|---|
| A1 | Snapshot REPEATABLE READ e numerazione Mastrino non aggiornata | `src/Esercizio.php` | lettura `FOR UPDATE` dopo lock; riserva dell'ultimo `idmastrino` con lettura bloccante | simulazione PASS; MySQL reale NOT RUN |
| A2 | Conferma non vincolata a periodo/valori | `gestione_esercizio.php`, `actions.php`, `src/Esercizio.php` | periodo, operazione, data e fingerprint nel POST; ricalcolo e confronto lato server e dentro lock | unit/integration simulation PASS; browser due schede NOT RUN |
| A3 | Contropartita attesa presa dall'impostazione corrente | `src/Esercizio.php` | identificazione della contropartita storica e confronto separato dei conti ordinari | unit PASS |
| A4 | Primo esercizio trattato come Apertura mancante | `src/Workflow.php`, `src/Esercizio.php` | stato `Non necessaria`; continuità richiesta solo in presenza di saldi precedenti | unit PASS |
| A5 | Classe CSS passata come testo alternativo | `gestione_esercizio.php` | permesso Prima nota esplicito e `<a>` manuale corretto | static PASS; browser permessi NOT RUN |
| A6 | Permesso basato sull'`id_module` della richiesta | `gestione_esercizio.php`, `actions.php` | recupero esplicito del modulo Piano dei conti e del relativo permesso | static PASS; utenti reali NOT RUN |
| A7 | Valori ricalcolati mostrati come registrati | `src/Esercizio.php`, `gestione_esercizio.php` | totali registrati separati; tabella Registrato/Atteso/Differenza | unit/static PASS; browser NOT RUN |
| A8 | Periodo malformato non gestito | `src/Esercizio.php`, `edit.php`, `gestione_esercizio.php` | validazione rigorosa date e try/catch | unit/lint PASS |
| A9 | Query duplicate | `src/Esercizio.php` | cache per istanza di saldi, scritture e conti | static PASS |
