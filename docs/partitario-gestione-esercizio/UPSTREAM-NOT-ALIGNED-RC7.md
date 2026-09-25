# Stato del porting upstream rispetto alla RC7

La RC7 di questa branch è una build installabile esclusivamente per OpenSTAManager 2.10.4 tramite `modules/partitario/custom/`.

Il codice upstream sperimentale presente nei file non-custom del repository non è stato riallineato in questa attività. Il riallineamento verso la master corrente è intenzionalmente rinviato perché:

1. il report indipendente impone prima la correzione e il collaudo della build 2.10.4;
2. i test MySQL, browser e permessi non sono ancora completati;
3. non deve essere aperta alcuna PR prima dell'autorizzazione dell'utente.

Per questa RC7:

- fonte runtime: `modules/partitario/custom/`;
- snapshot pacchetto: `dist/partitario-gestione-esercizio/1.0.0-RC7/`;
- codice upstream: fuori perimetro e non certificato.

Codex non deve copiare automaticamente la RC7 nei file upstream né aprire PR.
