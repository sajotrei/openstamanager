# Handoff Codex — Gestione esercizio RC7

## Identificazione

- Repository: `sajotrei/openstamanager`
- Baseline branch: OpenSTAManager v2.10.4 (`fb56ec649fbf8730365f60fa62f60866fff78468`)
- Branch: `feature/partitario-gestione-esercizio-1.0.0-rc7`
- Target: OpenSTAManager 2.10.4
- ZIP: `Piano-dei-conti-Gestione-esercizio-1.0.0-RC7-OSM-2.10.4.zip`
- SHA-256 ZIP deterministico: `352d882809cb54c6e19ae13cdd224f987429ec7462ce2e80f63efc46bb25d0c7`
- Workflow di verifica e packaging: `https://github.com/sajotrei/openstamanager/actions/runs/36204394016`
- Report autorevole: `REPORT-pdc-gestione-esercizio-RC6.md`

## Stato validato precedente

Su OSMLAB RC4:

- Apertura: 185 righe, saldo zero;
- Chiusura: 209 righe, saldo zero;
- nessun documento collegato;
- nessuna scadenza collegata;
- transazione Laravel funzionante;
- seconda esecuzione bloccata nel flusso normale.

## Correzioni RC7

- lettura bloccante dopo il lock e prenotazione dell'identificativo Mastrino;
- binding della conferma a periodo, data e fingerprint;
- cambio del conto tecnico non classificato falsamente come `stale`;
- primo esercizio con Apertura `Non necessaria`;
- link Mastrino e permessi corretti;
- permesso esplicito sul Piano dei conti;
- valori registrati separati dai valori attesi;
- tabella differenze;
- date e modale robuste;
- cache delle letture ripetute.

## Test eseguiti

Nel workflow GitHub Actions:

- PHP lint runtime: PASS;
- test logici standalone: 22/22 PASS;
- gate statici: 21/21 PASS;
- simulazioni esecuzione/rollback: 9/9 PASS;
- sorgente applicativo ↔ snapshot `dist/`: PASS;
- packaging deterministico: PASS.

## Test ancora necessari

- concorrenza reale MySQL in `REPEATABLE READ` con due connessioni;
- due schede browser con cambio periodo;
- permessi reali Piano dei conti/Prima nota;
- rendering browser della tabella differenze;
- catena coerente di tre esercizi;
- regressione Fatture, Scadenzario e Prima nota;
- installazione, rollback e reinstallazione su OSMLAB.

## Vincoli

- non implementare la Sezione B del report senza approvazione esplicita;
- non cambiare la formula contabile;
- non creare RC8 senza difetto riproducibile;
- non promuovere a STABLE;
- non aprire PR o issue;
- lavorare su un branch Codex separato;
- classificare correttamente come `NOT RUN` i test che richiedono MySQL, browser o OSMLAB.