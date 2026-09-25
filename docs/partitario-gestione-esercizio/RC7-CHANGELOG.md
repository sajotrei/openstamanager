# Changelog RC6 → RC7

- aggiunta lettura bloccante dell'esistenza dell'operazione dopo il lock;
- aggiunta riserva dell'`idmastrino` tramite lettura `FOR UPDATE` dell'ultimo Mastrino;
- conferma vincolata a periodo, operazione, data e fingerprint dell'anteprima;
- rifiuto del POST se periodo o anteprima sono cambiati;
- verifica dello storico usando la contropartita realmente registrata;
- cambio del conto tecnico classificato con avviso non bloccante;
- primo esercizio senza saldi precedenti classificato `Apertura non necessaria`;
- link Visualizza Mastrino corretto e subordinato al permesso Prima nota;
- permesso della modale verificato esplicitamente sul modulo Piano dei conti;
- riepilogo dei valori realmente registrati;
- vista Registrato / Atteso oggi / Differenza nei casi incoerenti;
- validazione rigorosa delle date e gestione errori della modale;
- cache per istanza delle letture ripetute;
- nessuna modifica alla formula Dare/Avere;
- nessuna cancellazione automatica;
- nessuna migrazione database.
