# Automezzi attività 1.2.0

Plugin per OpenSTAManager 2.10.4 collegato al modulo Interventi.

## Funzioni
- associazione automezzo alla singola sessione attività;
- proposta automatica quando l'operatore ha un solo automezzo associato;
- override manuale;
- dati del plugin separati dalle tabelle core OSM;
- salvataggio solo delle modifiche;
- letture aggregate e scritture batch;
- validazione che sessione e automezzo appartengano ai dati ammessi.

## Compatibilità OSM 2.10.4
Il caricatore componenti 2.10.4 non valorizza il campo `option` dei plugin custom. `controller_before.php` imposta il tipo `custom` già alla prima apertura e persiste la configurazione in `zz_plugins`, permettendo a `manager.php` di includere regolarmente `edit.php`.

La tabella `zz_automezzi_attivita_sessioni` viene creata in modo idempotente alla prima apertura del plugin. Il plugin non modifica CSS, colori delle righe o file core di OSM.
