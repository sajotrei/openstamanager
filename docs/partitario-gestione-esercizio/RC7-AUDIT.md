# Audit RC7

## Baseline

Il branch RC7 è ricostruito direttamente dal commit ufficiale OpenSTAManager v2.10.4:

`fb56ec649fbf8730365f60fa62f60866fff78468`

La precedente cronologia RC5/RC6 non è usata come base applicativa. La fonte runtime di partenza è lo ZIP RC6 con SHA-256:

`73b6b80e610625499e8a3794c2713ea10d9b59afbce90f02baaf8ac0308df18a`

## Codice aggiunto

- `modules/partitario/custom/actions.php`
- `modules/partitario/custom/edit.php`
- `modules/partitario/custom/gestione_esercizio.php`
- `modules/partitario/custom/src/Esercizio.php`
- `modules/partitario/custom/src/Workflow.php`
- test RC7 sotto `modules/partitario/tests/`
- documentazione e snapshot `dist/`

I file nativi della v2.10.4 non sono modificati.

## Limiti conservati

- formula contabile invariata;
- esclusione conti economici invariata;
- `HAVING SUM(m.totale)<>0` invariato;
- nessun `DELETE` diretto;
- nessuna migrazione DB;
- nessuna rigenerazione automatica;
- nessuna compatibilità 2.11 introdotta;
- nessuna decisione della Sezione B implementata.

## Rischi ancora aperti

- il test di concorrenza deve essere eseguito su MySQL/MariaDB reale;
- la numerazione Mastrino nativa resta un limite generale del core per operazioni ordinarie che non condividono il lock del modulo;
- permessi e link devono essere verificati con utenti reali;
- la collisione con personalizzazioni già presenti in `modules/partitario/custom/` richiede preflight manuale;
- la STABLE richiede collaudo OSMLAB.
