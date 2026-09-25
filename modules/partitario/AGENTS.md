# AGENTS.md — Piano dei conti Gestione esercizio RC7

## Ambito

- Repository: `sajotrei/openstamanager`
- Branch installabile: `feature/partitario-gestione-esercizio-1.0.0-rc7`
- Baseline del branch: tag/commit ufficiale OpenSTAManager `v2.10.4` (`fb56ec649fbf8730365f60fa62f60866fff78468`)
- Target esclusivo runtime: OpenSTAManager 2.10.4
- Fonte tecnica dei difetti: `REPORT-pdc-gestione-esercizio-RC6.md`

## Vincoli inderogabili

- Non modificare la formula Dare/Avere già validata.
- Non modificare i filtri patrimoniali e non includere conti economici.
- Non cambiare `HAVING SUM(m.totale)<>0`.
- Non escludere semplicemente il conto di Apertura.
- Non introdurre `DELETE FROM co_movimenti` per Apertura/Chiusura.
- Non rigenerare, stornare o ricostruire automaticamente lo storico.
- Usare la transazione Laravel sulla connessione OSM; vietate transazioni PDO manuali.
- Non modificare fatture, Scadenzario o Prima nota ordinaria.
- Non usare nomi di tabelle o colonne OSM 2.11 nella build 2.10.4.
- Non implementare i punti della Sezione B senza autorizzazione esplicita.
- Non aprire issue o PR.
- Non promuovere a STABLE senza collaudo reale OSMLAB.

## Branch protetti

Non modificare:

- `release/partitario-gestione-esercizio-1.0.0-rc4`
- `feature/partitario-gestione-esercizio-1.0.0-rc5`
- `feature/partitario-gestione-esercizio-1.0.0-rc6`
- `upstream/partitario-gestione-esercizio-2.10.4-clean`
- `feature/partitario-gestione-esercizio-master`
- `master`
- `main`

## Fonti del codice

- `modules/partitario/custom/`: unica fonte applicativa della RC7 installabile.
- `modules/partitario/tests/RC7*.php`: test specifici della recovery RC7.
- `dist/partitario-gestione-esercizio/1.0.0-RC7/`: snapshot byte-per-byte del contenuto dello ZIP.
- I file nativi in `modules/partitario/` restano quelli ufficiali della v2.10.4 e non devono essere modificati per la RC installabile.

## Test

Classificare ogni risultato come:

- PASS;
- FAIL;
- NOT RUN — MYSQL REQUIRED;
- NOT RUN — OSMLAB REQUIRED;
- NOT RUN — BROWSER REQUIRED.

Non trasformare un controllo statico in un PASS di integrazione.
