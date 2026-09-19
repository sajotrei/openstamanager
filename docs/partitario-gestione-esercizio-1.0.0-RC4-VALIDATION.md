# Piano dei conti — Gestione esercizio 1.0.0 RC4

## Validazione reale OpenSTAManager 2.10.4

Ambiente: OSMLAB, database MySQL/MariaDB reale, periodo 01/01/2026–31/12/2026.

La validazione è stata eseguita tramite il workflow completo dell'interfaccia:

1. anteprima Apertura;
2. conferma esplicita;
3. creazione Mastrino e movimenti;
4. aggiornamento stato a **Eseguita**;
5. blocco della seconda Apertura;
6. disponibilità della Chiusura;
7. conferma Chiusura;
8. creazione Mastrino e movimenti;
9. aggiornamento stato a **Eseguita**;
10. blocco della seconda Chiusura.

## Risultato SQL read-only

```text
idmastrino  data        righe  saldo_mastrino  righe_apertura  righe_chiusura  documenti_collegati  scadenze_collegate
10000001    2026-01-01  185    0.000000         185              0                0                    0
10000002    2026-12-31  209    0.000000         0                209              0                    0
```

### Apertura

- Mastrino: `#10000001`
- Data: `01/01/2026`
- 184 conti patrimoniali + 1 contropartita = **185 righe**
- Saldo Mastrino: **0,000000**
- Tutte le righe marcate `is_apertura=1`
- Nessun documento collegato
- Nessuna scadenza collegata

### Chiusura

- Mastrino: `#10000002`
- Data: `31/12/2026`
- 208 conti patrimoniali + 1 contropartita = **209 righe**
- Saldo Mastrino: **0,000000**
- Tutte le righe marcate `is_chiusura=1`
- Nessun documento collegato
- Nessuna scadenza collegata

## Caso negativo verificato

Su una seconda installazione OSM 2.10.4 era già presente una Chiusura senza Apertura rilevata.

Comportamento ottenuto:

- Apertura: **Non rilevata — esercizio già chiuso**
- nessuna Apertura retroattiva proposta;
- Chiusura esistente preservata;
- nessuna correzione automatica dello storico.

Questo comportamento è intenzionale e impedisce di alterare retroattivamente un esercizio già chiuso.

## Correzione transazionale RC4

OSM 2.10.4 apre una transazione globale in `actions.php` tramite Laravel Capsule.
La RC4 usa la stessa connessione Laravel e una transazione annidata/savepoint, evitando l'errore:

```text
PDOException: There is no active transaction
```

Non vengono più usati `PDO::beginTransaction()`, `PDO::commit()` o `PDO::rollBack()` direttamente.

## Esito

**PASS OSMLAB reale** per:

- Apertura completa;
- Chiusura completa;
- pareggio dei Mastrini;
- flag tecnici;
- assenza di collegamenti a fatture e scadenze;
- idempotenza;
- sequenza Apertura → Chiusura;
- protezione del caso storico già chiuso senza Apertura;
- transazione Laravel/OSM coerente.

La RC4 è la baseline collaudata per la successiva pulizia upstream e preparazione della PR.
