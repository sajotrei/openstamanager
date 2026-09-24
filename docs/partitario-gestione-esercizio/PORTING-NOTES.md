# Note di porting — Gestione esercizio RC5

## Baseline

Il porting RC5 è riallineato alla master ufficiale:

`dff2d87f9c5b45d92a58d1dab77b8d39a82b390d`

## Differenze principali rispetto a OSM 2.10.4

| 2.10.4 | Master |
|---|---|
| `co_pianodeiconti1/2/3` | `co_piano_dei_conti1/2/3` |
| `idconto` | `id_conto` |
| `idmastrino` | `id_mastrino` |
| `iddocumento` | `id_documento` |
| `primanota` | `prima_nota` |

La master utilizza inoltre modelli Eloquent dedicati nel Partitario e una versione più recente di Laravel. La logica transazionale continua a usare la connessione del core e una transazione annidata/savepoint.

## Adattamenti effettuati

- query dei saldi convertite al nuovo schema;
- identificativi dei movimenti e dei Mastrini convertiti;
- creazione dei movimenti adeguata alla firma corrente di `Mastrino::build()` e `Movimento::build()`;
- controlli di coerenza aggiornati ai nomi correnti;
- test collocati in `modules/partitario/tests/`, inclusi dalla suite ufficiale;
- modale e Workflow mantenuti indipendenti dallo schema DB.

## Regole RC5 portate sulla master

- blocco temporale Apertura/Chiusura;
- validazione dei conti tecnici;
- confronto delle scritture esistenti con il ricalcolo;
- stato `Da verificare` dopo modifiche retroattive;
- stato `Anomalia contabile` per incoerenze strutturali;
- nessuna cancellazione o rigenerazione automatica;
- Chiusura bloccata se l'Apertura esistente non è coerente;
- nessun effetto su fatture e scadenzario.

## Punto aperto sulla vista

Il branch mantiene temporaneamente la separazione tra `edit.php` e `piano_dei_conti.php` per isolare il workflow dalla vista complessa. Prima della PR pubblica va deciso se mantenere questa struttura o ridurre ulteriormente il diff con una modifica diretta minima della vista corrente.

## Migrazione dalla 2.10.4

Non è prevista una migrazione dati o schema. La versione 2.10.4 installabile usa un adattatore `custom/`; il porting upstream integra gli stessi servizi nel modulo ufficiale senza override o installer.
