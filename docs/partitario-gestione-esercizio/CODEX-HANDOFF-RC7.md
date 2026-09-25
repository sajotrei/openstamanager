# Handoff Codex — Gestione esercizio RC7

## Identificazione

- Repository: `sajotrei/openstamanager`
- Baseline branch: OpenSTAManager v2.10.4 (`fb56ec649fbf8730365f60fa62f60866fff78468`)
- Branch: `feature/partitario-gestione-esercizio-1.0.0-rc7`
- Target: OpenSTAManager 2.10.4
- ZIP: `Piano-dei-conti-Gestione-esercizio-1.0.0-RC7-OSM-2.10.4.zip`
- SHA-256: `3f632c89645a899c6865cff970334342263717ea968153387b962865677c8e9b`
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

- lettura bloccante dopo il lock e riserva dell'ultimo Mastrino;
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

- 22/22 test logici PASS;
- 21/21 gate statici PASS;
- 9/9 simulazioni esecuzione/rollback PASS;
- 5/5 PHP lint PASS;
- package/worktree identity PASS.

## Test ancora necessari

- concorrenza reale MySQL REPEATABLE READ;
- due schede con cambio periodo;
- permessi reali Piano dei conti/Prima nota;
- rendering browser della tabella differenze;
- catena di tre esercizi;
- regressione Fatture, Scadenzario, Prima nota;
- installazione e rollback OSMLAB.

## Vincoli

- non implementare la Sezione B;
- non cambiare la formula contabile;
- non creare RC8 senza difetto riproducibile;
- non promuovere a STABLE;
- non aprire PR o issue;
- lavorare su un branch Codex separato.
