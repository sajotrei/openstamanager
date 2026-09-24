# Piano dei conti: rendere sicure e comprensibili Apertura e Chiusura esercizio

## Problema

Nel Piano dei conti i comandi nativi di Apertura e Chiusura:

- eseguono immediatamente le scritture senza anteprima strutturata;
- in caso di ripetizione eliminano e ricreano automaticamente le registrazioni tecniche esistenti;
- non distinguono chiaramente operazione non eseguita, già eseguita e situazione incoerente;
- non guidano l'utente nella sequenza Apertura → Chiusura;
- utilizzano il periodo globale senza esplicitare che l'operazione rappresenta un esercizio annuale.

Il comportamento può produrre operazioni involontarie e rende difficile capire cosa verrà registrato.

## Comportamento atteso

- anteprima read-only dei conti patrimoniali interessati;
- stato deterministico di Apertura e Chiusura;
- conferma esplicita prima delle scritture;
- operazione già presente non ripetibile automaticamente;
- nessuna cancellazione implicita delle scritture esistenti;
- transazione coerente con la gestione database del core;
- Chiusura disponibile dopo Apertura quando esistono saldi precedenti;
- nessuna apertura retroattiva automatica su un esercizio già chiuso;
- nessun effetto su fatture e scadenzario.

## Ambito

La proposta riguarda esclusivamente il Piano dei conti e le scritture tecniche identificate da `is_apertura` e `is_chiusura`. Non introduce ricostruzioni automatiche dello storico né nuove dipendenze.
