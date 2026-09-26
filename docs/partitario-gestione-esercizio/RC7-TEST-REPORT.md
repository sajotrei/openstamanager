# Test report — Piano dei conti Gestione esercizio 1.0.0 RC7

## Baseline

- Target: OpenSTAManager 2.10.4
- Baseline ufficiale: commit `fb56ec649fbf8730365f60fa62f60866fff78468`
- Runtime di partenza: ZIP RC6 SHA-256 `73b6b80e610625499e8a3794c2713ea10d9b59afbce90f02baaf8ac0308df18a`
- Report indipendente usato come baseline dei difetti: `REPORT-pdc-gestione-esercizio-RC6.md`
- Workflow CI/packaging RC7: `https://github.com/sajotrei/openstamanager/actions/runs/36204394016`
- ZIP RC7 deterministico SHA-256: `352d882809cb54c6e19ae13cdd224f987429ec7462ce2e80f63efc46bb25d0c7`

## Risultati realmente eseguiti

| Area | Esito | Evidenza |
|---|---|---|
| PHP lint dei 5 file PHP runtime | PASS | GitHub Actions 5/5 |
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
| Lettura esistenza con `FOR UPDATE` presente | PASS strutturale/simulato | fake DB + gate statico |
| Numerazione Mastrino con lettura bloccante presente | PASS strutturale/simulato | fake DB + gate statico |
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
| Permesso esplicito Piano dei conti | PASS statico | modale/actions |
| Permesso Prima nota e link manuale | PASS statico | modale |
| Registrato / Atteso / Differenza | PASS statico e logico | sorgente + confronto |
| Try/catch modale e validazione date | PASS | sorgente + test |
| Cache per istanza | PASS statico | sorgente |
| Sorgente applicativo ↔ snapshot runtime | PASS | `diff -u` in CI |
| File runtime nello ZIP | PASS | esattamente 6 file |
| Packaging deterministico | PASS | artifact GitHub Actions |

## Test non eseguiti

| Test | Stato | Motivo |
|---|---|---|
| Due connessioni MySQL in `REPEATABLE READ` | NOT RUN — MYSQL REQUIRED | il workflow corrente non avvia MySQL/MariaDB |
| Concorrenza reale con due richieste PHP | NOT RUN — MYSQL/OSMLAB REQUIRED | richiede server e sessioni indipendenti |
| Concorrenza con una Prima nota ordinaria simultanea | NOT RUN — MYSQL/OSMLAB REQUIRED | limite generale della numerazione nativa `MAX(idmastrino)+1` |
| Modale 2025 + cambio periodo in altra scheda | NOT RUN — BROWSER/OSMLAB REQUIRED | richiede browser reale |
| Utente senza permesso Piano dei conti | NOT RUN — OSMLAB REQUIRED | richiede gruppo/utente reale |
| Utente senza permesso Prima nota | NOT RUN — OSMLAB REQUIRED | richiede gruppo/utente reale |
| Rendering tabella differenze | NOT RUN — BROWSER REQUIRED | richiede UI reale |
| Catena coerente di tre esercizi | NOT RUN — MYSQL/OSMLAB REQUIRED | richiede dati contabili reali |
| Regressione Fatture/Scadenzario/Prima nota | NOT RUN — OSMLAB REQUIRED | richiede installazione completa |
| Installazione sopra RC6 | NOT RUN — OSMLAB REQUIRED | pacchetto pronto |
| Installazione sopra OSM 2.10.4 originale | NOT RUN — OSMLAB REQUIRED | pacchetto pronto |
| Collisione con custom preesistenti | NOT RUN — INSTALLATION PREFLIGHT REQUIRED | verifica manuale necessaria |
| Modulo Piano dei conti disabilitato | NOT RUN — INSTALLATION PREFLIGHT REQUIRED | deve essere abilitato prima dell'installazione |

## Totali automatici

- test unitari/logici: **22/22 PASS**;
- gate statici: **21/21 PASS**;
- simulazione esecuzione/rollback: **9/9 PASS**;
- PHP lint: **5/5 PASS**;
- identità sorgente/snapshot: **PASS**;
- packaging: **PASS**.

## Giudizio

**RC7 PRONTA PER COLLAUDO OSMLAB**.

Non è dichiarata STABLE e non è dichiarata idonea alla produzione. I gate principali ancora aperti sono la concorrenza reale MySQL, il cambio periodo in browser, i permessi reali, l'installazione/rollback e le regressioni applicative su OSMLAB.