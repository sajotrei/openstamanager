# Test report upstream — Gestione esercizio RC5

## Baseline

- master ufficiale corrente al momento dell'allineamento: `dff2d87f9c5b45d92a58d1dab77b8d39a82b390d`;
- nessuna nuova dipendenza;
- nessuna migrazione database;
- nessun override `custom` nel codice destinato upstream.

## Test automatici eseguiti sulla logica RC5

| Area | Esito |
|---|---|
| PHP lint dei file RC5 | PASS |
| Calcolo anno solare | PASS |
| Calcolo anno non solare | PASS |
| Blocco periodo trimestrale | PASS |
| Apertura prima/alla data iniziale | PASS |
| Chiusura prima/alla data finale | PASS |
| Apertura 184 conti + contropartita | PASS |
| Chiusura 208 conti + contropartita | PASS |
| Pareggio e precisione a 6 decimali | PASS |
| Nessuna contropartita senza saldi | PASS |
| Conti tecnici configurati, distinti e patrimoniali | PASS |
| Scritture esistenti coincidenti | PASS |
| Importo o conto modificato retroattivamente | PASS — `Da verificare` |
| Più Mastrini, flag incoerenti o mancato pareggio | PASS — `Anomalia contabile` |
| Apertura non proposta dopo Chiusura | PASS |
| Chiusura in attesa dell'Apertura | PASS |
| Chiusura bloccata con Apertura non coerente | PASS |
| Operazione già eseguita non ripetibile | PASS |
| Nessun uso diretto di PDO | PASS |
| Nessun `DELETE FROM co_movimenti` | PASS |
| Rollback sintetico su errore INSERT | PASS |
| Stato operativo + permesso RW rende l'azione | PASS |

## Validazione reale ereditata dalla 2.10.4

La logica contabile equivalente ha prodotto su MySQL/MariaDB reale:

- Apertura: 184 conti + contropartita, 185 righe, saldo Mastrino zero;
- Chiusura: 208 conti + contropartita, 209 righe, saldo Mastrino zero;
- flag tecnici coerenti;
- nessun documento o scadenza collegati;
- seconda esecuzione bloccata;
- transazione Laravel senza `There is no active transaction`.

## Gate ancora necessari sulla master

Non sono dichiarati PASS:

- suite PHPUnit completa della master;
- integrazione MySQL/MariaDB reale sulla master corrente;
- rendering browser desktop/notebook/tablet;
- concorrenza reale con due richieste simultanee;
- errore forzato durante INSERT con rollback reale;
- regressione manuale Fatture, Scadenzario e Prima nota ordinaria;
- prova reale del cambio a `Da verificare` dopo una modifica retroattiva.

## Esito

Il porting è allineato funzionalmente alla RC5 ed è pronto per un ambiente di collaudo master, non ancora per l'apertura della PR pubblica.
