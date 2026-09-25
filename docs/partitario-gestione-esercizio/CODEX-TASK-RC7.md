# Task Codex — review RC7

Lavora sul repository `sajotrei/openstamanager`, partendo dal branch:

`feature/partitario-gestione-esercizio-1.0.0-rc7`

Prima di modificare qualsiasi file:

1. leggi `modules/partitario/AGENTS.md`;
2. leggi `docs/partitario-gestione-esercizio/REPORT-pdc-gestione-esercizio-RC6.md`;
3. leggi `docs/partitario-gestione-esercizio/RC7-BASELINE-AUDIT.md`;
4. leggi `docs/partitario-gestione-esercizio/RC7-MAPPING-A1-A9.md`;
5. leggi `docs/partitario-gestione-esercizio/CODEX-HANDOFF-RC7.md`;
6. verifica gli SHA del runtime in `RC7-ZIP-BRANCH-COMPARISON.md`.

Crea un branch separato:

`codex/partitario-gestione-esercizio-rc7-review`

Non modificare i branch protetti elencati in AGENTS.md.

## Obiettivo

Eseguire una review avversa della RC7 e produrre:

`docs/partitario-gestione-esercizio/CODEX-REVIEW-RC7.md`

Non introdurre nuove funzioni. Correggi soltanto difetti concreti riprodotti da un test fallito.

## Verifiche prioritarie

- concorrenza e snapshot MySQL;
- periodo/fingerprint al POST;
- cambio conto tecnico;
- primo esercizio;
- permessi modale;
- permessi e link Mastrino;
- valori Registrato/Atteso/Differenza;
- validazione date;
- assenza di DELETE;
- formula Dare/Avere invariata;
- packaging e snapshot runtime.

## Classificazione

Usa soltanto:

- PASS;
- FAIL;
- NOT RUN — MYSQL REQUIRED;
- NOT RUN — OSMLAB REQUIRED;
- NOT RUN — BROWSER REQUIRED.

Non aprire issue o PR. Non promuovere a STABLE.

Giudizio finale richiesto:

- `RC7 PRONTA PER COLLAUDO OSMLAB`, oppure
- `RC7 NON PRONTA PER COLLAUDO OSMLAB`.
