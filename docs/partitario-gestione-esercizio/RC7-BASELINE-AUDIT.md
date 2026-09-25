# RC7 — audit della baseline

## Fonti verificate

- Repository: `sajotrei/openstamanager`
- Branch dichiarato RC6: `feature/partitario-gestione-esercizio-1.0.0-rc6`
- HEAD remoto rilevato: `c7a79603610080cdfc229e5757fefcc59fd3abb9`
- Messaggio del commit: `feat(partitario): harden exercise workflow for RC5`
- ZIP consegnato: `Piano-dei-conti-Gestione-esercizio-1.0.0-RC6-OSM-2.10.4.zip`
- SHA-256 verificato: `73b6b80e610625499e8a3794c2713ea10d9b59afbce90f02baaf8ac0308df18a`
- Report indipendente: `REPORT-pdc-gestione-esercizio-RC6.md`

## Esito del confronto

La baseline RC6 non era univoca:

- il branch denominato RC6 puntava ancora al commit RC5;
- lo ZIP conteneva la correzione RC6 della registrazione temporalmente anticipata;
- il report indipendente descrive il codice runtime dello ZIP e i relativi difetti.

Per la RC7 è stata quindi adottata questa regola:

1. lo ZIP RC6 verificato è la baseline runtime installabile;
2. il report indipendente è la baseline dei difetti A1–A9;
3. il branch RC7 nasce senza modificare i branch precedenti;
4. il nuovo snapshot runtime del branch deve coincidere file per file con lo ZIP RC7.

## Corrispondenza del report

I punti del report sono stati riscontrati nel runtime RC6:

- A1: lock su `zz_settings` seguito da letture non bloccanti e `Mastrino::build()`;
- A2: conferma priva di periodo e fingerprint;
- A3: confronto basato sul conto tecnico configurato al momento;
- A4: falso stato di continuità nel primo esercizio;
- A5: uso errato del quarto argomento di `Modules::link()`;
- A6: permesso della modale dipendente dall'`id_module` della richiesta;
- A7: riepilogo dei valori ricalcolati accanto al Mastrino registrato;
- A8: assenza di gestione errori nella modale;
- A9: query duplicate prive di cache per istanza.

## Decisione

La RC7 non deriva da una ricostruzione a memoria. Deriva dai sei file runtime dello ZIP RC6 verificato, corretti esclusivamente per i punti A1–A9 autorizzati.
