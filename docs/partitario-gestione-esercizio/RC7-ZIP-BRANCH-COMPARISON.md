# Confronto runtime RC7

## Pacchetto definitivo per il collaudo

`Piano-dei-conti-Gestione-esercizio-1.0.0-RC7-OSM-2.10.4.zip`

SHA-256 deterministico:

`352d882809cb54c6e19ae13cdd224f987429ec7462ce2e80f63efc46bb25d0c7`

Il pacchetto è stato ricostruito dal commit RC7 tramite GitHub Actions, usando timestamp e ordine dei file deterministici.

Workflow run verificato:

`https://github.com/sajotrei/openstamanager/actions/runs/36204394016`

| Percorso | SHA-256 |
|---|---|
| `partitario/MODULE` | `e5183d4117ed6c29cf62d2b4ae866956ee0bbb026d944f94b8316f28accb5f33` |
| `partitario/custom/actions.php` | `54ef663b55e9d52be215b46f156df9f1c0f154484d604c79bcb052c043315220` |
| `partitario/custom/edit.php` | `26b0feb789b3a08aabce138b46431e715667fb34d2e6f869aab2ff4eb6b33780` |
| `partitario/custom/gestione_esercizio.php` | `dcc0420b45c7f9cc3ce86be749851677457c814541a7b50224cf139aa49c97e7` |
| `partitario/custom/src/Esercizio.php` | `e17d3a8ce129f3b2e1dcf405efba783d4788ba9dce4e924f81622c3c4d7d5cb4` |
| `partitario/custom/src/Workflow.php` | `f2d2ec34b5ad4ef5766733627b17c243077d194251079b0c8fb4bc6127538635` |

Gate verificati nel workflow:

- PHP lint runtime: PASS;
- test logici standalone: 22/22 PASS;
- gate statici: 21/21 PASS;
- simulazione esecuzione/rollback: 9/9 PASS;
- sorgente applicativo ↔ snapshot `dist/`: PASS;
- contenuto ZIP: esattamente 6 file runtime.

Lo snapshot corrispondente è conservato nel branch sotto:

`dist/partitario-gestione-esercizio/1.0.0-RC7/`

Il precedente hash `3f632c...` identificava una build non deterministica degli stessi file runtime. Il pacchetto indicato sopra è quello da usare per il collaudo RC7.