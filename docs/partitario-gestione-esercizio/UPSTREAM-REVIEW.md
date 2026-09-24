# Review upstream — Gestione esercizio RC5

## Esito dell'analisi

La master corrente conserva ancora la logica distruttiva: una nuova Apertura o Chiusura elimina prima le scritture tecniche esistenti e poi le ricrea. Non risultano issue o pull request aperte che affrontino organicamente lo stesso problema.

## Architettura

- logica contabile e validazioni isolate in `Esercizio`;
- stato UI isolato in `Workflow`;
- `actions.php` ridotto a integrazione e gestione degli esiti;
- anteprima e modale separate dalla scrittura;
- transazione coerente con il core;
- nessuna dipendenza o migrazione aggiuntiva.

## Hardening RC5

- Apertura non eseguibile prima della data iniziale;
- Chiusura non eseguibile prima della data finale;
- validazione dei conti tecnici configurati, distinti e patrimoniali;
- verifica delle scritture già presenti per data, flag, Mastrino, pareggio, conti e importi;
- modifiche retroattive classificate `Da verificare`;
- anomalie strutturali classificate `Anomalia contabile`;
- nessuna correzione automatica dello storico.

## SQL e performance

- le query dei saldi restano equivalenti ai filtri nativi;
- l'anteprima viene calcolata all'apertura della pagina/modale e ricalcolata al POST;
- il controllo delle scritture tecniche è limitato al periodo selezionato e ai Mastrini rilevati;
- il lock utilizza le due righe di configurazione e non blocca l'intera tabella;
- gli INSERT crescono linearmente con il numero di conti, come nella logica nativa.

## Sicurezza

- permesso `rw` verificato server-side;
- nessuna fiducia nei dati visualizzati nella conferma;
- nessuna cancellazione automatica;
- rollback sulla stessa connessione Laravel del core;
- nessun collegamento a documenti o scadenze;
- operazioni già presenti non ripetibili.

## Diff e manutenibilità

Il porting conserva la separazione `edit.php` / `piano_dei_conti.php` per mantenere intatta la vista corrente. È funzionalmente conservativo ma aumenta il diff percepito. Prima della PR pubblica va valutato con i maintainer se preferire:

1. la separazione attuale, più isolata;
2. una patch minima diretta di `edit.php`, con rimozione esplicita dei vecchi pulsanti.

Non vengono usati override `custom`, installer, pacchetti MODULE, ID reali o riferimenti a installazioni private.

## Gate ancora aperti

1. suite PHPUnit completa sulla master;
2. collaudo MySQL/MariaDB e browser sulla master corrente;
3. rollback reale con errore simulato;
4. prova di concorrenza con due richieste;
5. verifica responsive e tema;
6. regressione Fatture, Scadenzario e Prima nota;
7. scelta finale sulla struttura della vista.

## Valutazione di accettazione

La probabilità di accettazione è **medio-buona** dopo il superamento dei gate reali sulla master. I principali punti di discussione saranno la non ripetibilità delle operazioni, la semantica dell'esercizio annuale non solare e la dimensione del diff UI.
