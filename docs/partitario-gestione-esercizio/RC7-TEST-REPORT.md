# Test report — Piano dei conti Gestione esercizio 1.0.0 RC7

## Baseline

- Target: OpenSTAManager 2.10.4
- Runtime di partenza: ZIP RC6 SHA-256 `73b6b80e610625499e8a3794c2713ea10d9b59afbce90f02baaf8ac0308df18a`
- Report indipendente usato come baseline dei difetti: `REPORT-pdc-gestione-esercizio-RC6.md`

## Risultati eseguiti

| Area | Esito | Evidenza |
|---|---|---|
| PHP lint dei 5 file PHP runtime | PASS | 5/5 |
| Formula Apertura invariata | PASS | test puro |
| Formula Chiusura invariata | PASS | test puro |
| Pareggio Dare/Avere | PASS | test puro |
| Fingerprint deterministico | PASS | test puro |
| Fingerprint vincolato al periodo | PASS | test puro |
| Cambio conto tecnico non `stale` | PASS | test puro |
| Avviso conto tecnico cambiato | PASS | test puro |
| Movimento retroattivo → `stale` | PASS | test puro |
| Primo esercizio → Apertura non necessaria | PASS | test Workflow |
| Primo esercizio chiuso senza falso allarme | PASS | test Workflow |
| Date impossibili/formato/ordine | PASS | test puro |
| Conti tecnici mancanti/uguali/economici | PASS | test puro |
| Lettura esistenza con `FOR UPDATE` | PASS strutturale/simulato | fake DB |
| Numerazione Mastrino con lettura bloccante | PASS strutturale/simulato | fake DB |
| Seconda esecuzione dopo lock | PASS simulato | fake DB |
| Periodo diverso al POST | PASS simulato | zero righe |
| Fingerprint diverso al POST | PASS simulato | zero righe |
| Errore durante INSERT | PASS simulato | eccezione |
| Rollback a zero righe parziali | PASS simulato | fake transaction |
| Nessun `DELETE FROM co_movimenti` | PASS | scansione sorgente |
| Nessuna transazione PDO manuale | PASS | scansione sorgente |
| Transazione Laravel preservata | PASS | scansione sorgente |
| `HAVING SUM(m.totale)<>0` preservato | PASS | 2 occorrenze |
| Schema OSM 2.10.4 | PASS | tabelle/colonne verificate |
| Permesso esplicito Piano dei conti | PASS statico | sorgente modale/actions |
| Permesso Prima nota e link manuale | PASS statico | sorgente modale |
| Registrato / Atteso / Differenza | PASS statico e logico | sorgente + test confronto |
| Try/catch modale e validazione date | PASS | sorgente + test |
| Cache per istanza | PASS statico | sorgente |
| ZIP ricostruito identico al worktree | PASS | `diff -ru` |
| File runtime nello ZIP | PASS | 6 file applicativi |

## Test non eseguiti

| Test | Stato | Motivo |
|---|---|---|
| Due connessioni MySQL in REPEATABLE READ | NOT RUN — MYSQL REQUIRED | nessun server MySQL/MariaDB disponibile |
| Concorrenza reale con due richieste PHP | NOT RUN — MYSQL/OSMLAB REQUIRED | richiede server e sessioni indipendenti |
| Modale 2025 + cambio periodo in altra scheda | NOT RUN — BROWSER/OSMLAB REQUIRED | richiede browser reale |
| Utente senza permesso Piano dei conti | NOT RUN — OSMLAB REQUIRED | richiede gruppo/utente reale |
| Utente senza permesso Prima nota | NOT RUN — OSMLAB REQUIRED | richiede gruppo/utente reale |
| Rendering tabella differenze | NOT RUN — BROWSER REQUIRED | richiede UI reale |
| Catena coerente di tre esercizi | NOT RUN — MYSQL/OSMLAB REQUIRED | richiede dati contabili reali |
| Regressione Fatture/Scadenzario/Prima nota | NOT RUN — OSMLAB REQUIRED | richiede installazione completa |
| Installazione sopra RC6 | NOT RUN — OSMLAB REQUIRED | pacchetto pronto |
| Installazione sopra OSM 2.10.4 originale | NOT RUN — OSMLAB REQUIRED | pacchetto pronto |
| Collisione con custom preesistenti | NOT RUN — INSTALLATION PREFLIGHT REQUIRED | verifica manuale necessaria |

## Totali automatici

- test unitari/logici: **22/22 PASS**;
- gate statici: **21/21 PASS**;
- simulazione esecuzione/rollback: **9/9 PASS**;
- PHP lint: **5/5 PASS**.

## Giudizio

**RC7 PRONTA PER COLLAUDO OSMLAB**.

Non è dichiarata STABLE e non è dichiarata idonea alla produzione. Il gate principale ancora aperto è la concorrenza reale MySQL insieme ai test browser e permessi.
