# Partitario: gestione guidata e non distruttiva dell'esercizio

## Sintesi

La modifica sostituisce i comandi immediati di Apertura e Chiusura bilancio con una gestione guidata, idempotente e transazionale.

La nuova implementazione separa:

- calcolo dell'anteprima;
- validazione contabile e temporale;
- verifica delle scritture esistenti;
- stato del workflow;
- esecuzione transazionale;
- interfaccia.

## Comportamento precedente

- Apertura e Chiusura erano implementate direttamente in `actions.php`;
- una seconda esecuzione eliminava e ricreava automaticamente le righe tecniche;
- non era disponibile una preview completa Dare/Avere;
- non era rappresentata la dipendenza Apertura → Chiusura;
- era possibile chiudere un esercizio prima della data finale;
- la sola presenza dei flag era considerata sufficiente per ritenere valida una scrittura storica.

## Nuovo comportamento

- anteprima read-only con conti, righe, Dare, Avere e pareggio;
- conferma esplicita prima del POST;
- ricalcolo completo server-side nella sezione critica;
- nessuna cancellazione o rigenerazione implicita;
- operazione già presente non ripetibile;
- Apertura disponibile dalla data iniziale;
- Chiusura disponibile dalla data finale compresa;
- esercizi annuali non solari supportati, periodi non annuali bloccati;
- Chiusura in attesa dell'Apertura quando esistono saldi precedenti;
- nessuna Apertura retroattiva automatica su un esercizio già chiuso;
- validazione dei conti tecnici configurati, distinti e patrimoniali;
- scritture esistenti ricontrollate per data, flag, Mastrino, pareggio, conti e importi;
- stato `Da verificare` dopo modifiche retroattive;
- stato `Anomalia contabile` per più Mastrini, flag incoerenti o Mastrino non in pareggio;
- transazione Laravel/savepoint sulla connessione del core;
- lock transazionale tramite `SELECT ... FOR UPDATE`;
- nessun collegamento a fatture o scadenze.

## File principali

- `modules/partitario/src/Esercizio.php`
- `modules/partitario/src/Workflow.php`
- `modules/partitario/gestione_esercizio.php`
- `modules/partitario/actions.php`
- `modules/partitario/edit.php`
- `modules/partitario/tests/EsercizioTest.php`
- `modules/partitario/tests/WorkflowTest.php`

## Test

Sono coperti:

- anno solare, anno non solare e trimestre;
- blocchi temporali Apertura/Chiusura;
- 184 conti + contropartita e 208 conti + contropartita;
- Dare/Avere e precisione a sei decimali;
- validazione conti tecnici;
- operazione già eseguita;
- Apertura assente con Chiusura presente;
- Chiusura in attesa dell'Apertura;
- modifiche retroattive e anomalie strutturali;
- assenza di uso diretto di PDO;
- assenza di `DELETE FROM co_movimenti` per le operazioni tecniche.

La logica equivalente è stata validata su OpenSTAManager 2.10.4 reale. Prima della pubblicazione restano necessari suite completa, collaudo MySQL/MariaDB e browser sulla master corrente.

## Indicazioni per il reviewer

1. Verificare l'equivalenza dei filtri dei saldi con la logica nativa.
2. Verificare la transazione annidata rispetto alla transazione globale di `actions.php`.
3. Provare una seconda Apertura/Chiusura e confermare che non avvengano modifiche.
4. Provare le date di confine e un esercizio annuale non solare.
5. Modificare retroattivamente un movimento e verificare lo stato `Da verificare`.
6. Verificare utente read-only, responsive e assenza di effetti su Fatture/Scadenzario.
