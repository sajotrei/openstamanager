# Automezzi attività

Plugin per OpenSTAManager 2.10.4 collegato al modulo Interventi.

## Funzioni
- associazione automezzo alla singola sessione attività;
- proposta automatica quando l'operatore ha un solo automezzo associato;
- override manuale;
- dati del plugin separati dalle tabelle core OSM;
- salvataggio solo delle modifiche;
- letture aggregate e scritture batch;
- validazione che sessione e automezzo appartengano ai dati ammessi.

La tabella `zz_automezzi_attivita_sessioni` viene creata in modo idempotente alla prima apertura del plugin.
