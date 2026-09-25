# Checklist OSMLAB RC7

1. Installazione sopra RC6 senza errori.
2. Piano dei conti, ricerca, aggiunta/modifica conti ancora funzionanti.
3. Primo esercizio senza saldi precedenti: Apertura `Non necessaria`.
4. Cambio del conto tecnico dopo Apertura: avviso specifico, non `stale`.
5. Modale 2025, cambio periodo in altra scheda a 2026, conferma vecchia: POST rifiutato e zero scritture.
6. Utente senza permesso Piano dei conti: nessun saldo visibile.
7. Utente con sola lettura: nessuna azione.
8. Utente senza permesso Prima nota: nessun pulsante Mastrino e nessun testo CSS.
9. Modifica retroattiva: stato `Da verificare` e tabella Registrato/Atteso/Differenza.
10. Mastrino non in pareggio o flag incoerenti: `Anomalia contabile`.
11. Due sessioni MySQL concorrenti: una sola operazione, zero duplicati.
12. Fatture, Scadenzario e Prima nota ordinaria invariati.
13. Rollback: nessun movimento contabile cancellato.
