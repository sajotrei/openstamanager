# Report verificato — "Piano dei conti · Gestione esercizio" 1.0.0-RC6 (OSM 2.10.4)

> **Per l'agente che riceve questo report.** Ogni punto è stato verificato da verificatori indipendenti in modo avverso: prima sul codice (righe esatte del modulo e del sorgente ufficiale OSM `v2.10.4`), poi con esecuzione reale del codice del modulo. L'esecuzione è avvenuta su SQLite con stub fedeli a OSM, perché MySQL non era avviabile nell'ambiente di test.
> - **Sezione A:** correzioni già definite, implementabili.
> - **Sezione B:** decisioni di design **da NON implementare** senza approvazione esplicita dell'utente.
> - **Sezione C:** cose verificate come corrette, da **non toccare**.
> - **Sezione D:** trappole da evitare.

## 0. Contesto

- **Modulo:** file installati in `modules/partitario/custom/`:
  - `MODULE`
  - `actions.php`: intercetta `op=apri-bilancio|chiudi-bilancio`; per le altre operazioni include `../actions.php`.
  - `edit.php`: riepilogo sopra la pagina nativa, include `../edit.php`, nasconde via CSS i pulsanti nativi `button.btn-lg[data-op=...]`.
  - `gestione_esercizio.php`: pagina modale con anteprima e conferma.
  - `src/Esercizio.php`: calcolo, classificazione delle scritture esistenti, lock, esecuzione.
  - `src/Workflow.php`: stati e badge.
- **Metodo nativo 2.10.4** (`modules/partitario/actions.php:167-273`):
  - fa `DELETE FROM co_movimenti WHERE is_apertura=1 AND data=<period_start>` (e l'equivalente per la chiusura), poi rigenera;
  - chiude e riapre solo i conti *Patrimoniale* contro i conti tecnici indicati nelle impostazioni `Conto per Apertura conti patrimoniali` / `Conto per Chiusura conti patrimoniali`;
  - non ha anteprima, né controlli preliminari, né lock.
- **Obiettivo del modulo:** eliminare la cancellazione e rigenerazione silenziosa. Operazione una sola volta, con anteprima, controlli preliminari, sequenza Apertura→Chiusura, transazione con lock e verifica finale. Le incoerenze vengono segnalate ("Da verificare" / "Anomalia"), mai corrette in automatico.
- **Schema 2.10.4 usato:**
  - tabelle `co_pianodeiconti1/2/3`, colonne `idpianodeiconti1/2`;
  - `co_movimenti` con `idconto`, `idmastrino`, `iddocumento`, `id_scadenza`, `is_apertura`, `is_chiusura`, `data` (DATE), `totale`.
- **Fatti OSM 2.10.4 rilevanti:**
  - `message(this)` (`assets/src/js/functions/functions.js:207-309`) fa POST a `window.location.href`, cioè `controller.php?id_module=N` anche dentro la modale. Invia come campi nascosti tutti i `data-*` tranne `msg` (`functions.js:316-320`). jQuery converte in camelCase i trattini, non gli underscore: `data-period_start` arriva come `period_start`.
  - `$_SESSION['period_start'/'period_end']` è condiviso tra le schede del browser. Viene sovrascritto da `core.php:244-246` a ogni richiesta con `?period_start=`: il selettore periodo lo fa via `custom.js:82`.
  - `Modules::link($modulo, $id_record, $testo, $alternativo = true, $extra = null, ...)` (`src/Modules.php:313-344`). Il 4° argomento è il testo mostrato a chi non ha permesso; gli attributi HTML vanno nel 5°.
  - `Permissions::check()` controlla solo il modulo registrato da `core.php:277` tramite `id_module` della richiesta. Senza `id_module` la lista è vuota e il controllo passa (`Permissions.php:113`).
  - Isolamento MySQL: default del server (REPEATABLE READ). OSM non imposta `isolation_level` (`src/Database.php:73-98`).
  - La modifica di un mastrino in Prima nota (`modules/primanota/actions.php:111-129`) esegue `cleanup()` e poi `Movimento::build()`, che **non copia** `is_apertura`/`is_chiusura`: i flag si perdono.
  - L'eliminazione del mastrino da Prima nota funziona: nessuna protezione su `is_apertura`/`is_chiusura` (`primanota/actions.php:138-139`).

---

## A. Correzioni definite (implementabili)

Ordinate per priorità. Riferimenti = file del modulo, salvo diversa indicazione.

### A1. Concorrenza: possibile apertura/chiusura doppia — **MEDIA**
- **Dove:** `src/Esercizio.php:366-415` (`execute`), `:434-449` (`lockExercise`).
- **Causa:**
  - Prima del `SELECT … FROM zz_settings … FOR UPDATE` la transazione OSM ha già eseguito una lettura non bloccante: `Permissions::check('rw')` in `actions.php:12` porta a `Modules::get`, poi a `Module::find` e a un SELECT su `zz_modules`.
  - In REPEATABLE READ quella lettura fissa lo snapshot. Dopo aver ottenuto il lock, `getPreview()` non vede il mastrino appena committato da un'altra richiesta.
  - Anche `Mastrino::getNextMastrino()` legge lo snapshot vecchio, quindi può riusare lo stesso `idmastrino`.
- **Quando succede:** due sessioni PHP diverse (utenti o browser diversi) confermano la stessa operazione nello stesso momento. Il doppio clic dello stesso utente è serializzato dal lock di sessione PHP, quindi è impossibile.
- **Effetto:** righe duplicate. La verifica finale del modulo passa lo stesso, perché vede solo il proprio snapshot.
- **Correzione:** subito dopo `lockExercise()`, controllare l'esistenza con una **lettura bloccante**, che legge sempre l'ultimo dato committato, e lanciare `DomainException` se trova righe. Per esempio:
  ```sql
  SELECT id FROM co_movimenti WHERE is_apertura=1 AND data BETWEEN :start AND :end FOR UPDATE
  ```
  (analoga con `is_chiusura` per la chiusura). Valutare la stessa lettura bloccante anche per `MAX(idmastrino)`.

### A2. La conferma non è legata al periodo e ai valori mostrati — **MEDIO-BASSA**
- **Dove:**
  - `gestione_esercizio.php:85-92`: il pulsante invia solo `op, title, backto, button, class`.
  - `actions.php:15`: usa il periodo in sessione al momento del POST.
- **Scenario riprodotto:** in una scheda la modale mostra "Apertura 2025"; in un'altra scheda l'utente passa al 2026; la conferma crea l'**Apertura 2026**, mentre il 2025 resta non aperto.
  - Per la Chiusura succede solo se l'anno di destinazione è già aperto. Negli altri casi la protezione in `Esercizio.php:307-309` blocca l'operazione.
- **Correzione:**
  - aggiungere al pulsante `data-period_start`, `data-period_end` e `data-fingerprint`. Il fingerprint è un hash di operazione + data + righe `idconto:totale` arrotondate al centesimo;
  - lato server, rifiutare se il periodo inviato ≠ periodo in sessione, oppure se il fingerprint ricalcolato ≠ quello inviato. Messaggio: "L'anteprima è cambiata, riapri Gestione esercizio". Non scrivere nulla.

### A3. Il cambio di un conto tecnico segnala falsamente "modifiche retroattive" e blocca la Chiusura — **MEDIO-BASSA**
- **Dove:**
  - `Esercizio.php:249` e `:271`: la contropartita attesa è presa **dall'impostazione attuale**;
  - `:137`: `compareEntries` richiede le stesse chiavi conto;
  - `:167-174`: `classifyExisting` restituisce 'stale';
  - `:310-316`: blocca la Chiusura.
- **Scenario riprodotto:** Apertura registrata con conto 10 ("consistent"). L'amministratore cambia l'impostazione a 12. Risultato: "Da verificare" con messaggio "movimenti successivi o modifiche retroattive", Chiusura bloccata, `execute('chiusura')` lancia eccezione. Il libro giornale non è cambiato.
- **Correzione:**
  - riconoscere la contropartita dal mastrino esistente, non dall'impostazione;
  - confrontare separatamente: i conti ordinari per importo, il conto tecnico per quadratura;
  - se il conto tecnico registrato ≠ quello configurato, mostrare un messaggio specifico ("Il conto tecnico configurato è cambiato dopo la registrazione") come avviso **non bloccante** quando il libro giornale è coerente.
- **Da fare in tutto il file:** il conto va ricavato allo stesso modo ovunque serva, anche per l'apertura verificata dentro l'anteprima di chiusura (`:264-277`).

### A4. Falso allarme nel primo esercizio (nessun saldo precedente) — **BASSA**
- **Sintomo:** dopo una Chiusura legittima senza saldi precedenti:
  - la scheda Apertura mostra "Non rilevata – esercizio già chiuso" con un avviso giallo;
  - il riepilogo mostra "Chiuso – continuità da verificare", in modo permanente.
- **Correzione (tutti e tre i punti insieme):**
  1. `Workflow.php:39`: `closed_without_opening` solo se `$hasPreviousBalances`. Altrimenti nuovo stato "Non necessaria" (grigio).
  2. `Workflow.php:120` (`summary`): non segnalare la continuità se `$opening['accounts_count'] === 0`.
  3. `Esercizio.php:304-306`: aggiungere l'errore "Apertura non rilevata…" solo se ci sono saldi da riaprire (`!empty($rows)`).

### A5. "Visualizza Mastrino" costruito con argomenti sbagliati — **BASSA**
- **Dove:** `gestione_esercizio.php:83`. La classe CSS è passata come 4° argomento di `Modules::link`.
- **Effetto:**
  - chi ha accesso vede solo l'icona linkata e il testo non cliccabile;
  - chi non ha accesso a Prima nota vede il testo letterale `btn btn-sm btn-info mb-1 ml-1`.
- **Correzione:** controllare il permesso con `Modules::get('Prima nota')->permission` ∈ {r, rw} e costruire a mano `<a class="btn btn-sm btn-info" href="…editor.php?id_module=…&id_record=…">`. Senza permesso, non mostrare nulla.
- `actions.php:22` è corretto: non toccarlo.

### A6. I controlli dei permessi della modale non proteggono il modulo — **BASSA**
- **Dove:** `gestione_esercizio.php:9-10`.
- **Causa:** con `id_module` assente, non valido o di un altro modulo leggibile, il controllo passa. Qualunque utente autenticato vede i saldi dei conti patrimoniali.
- **Scrittura:** resta protetta dal controllo `rw` di `actions.php:476` alla radice.
- **Correzione:** controllare il modulo per nome, per esempio `Modules::getPermission('Piano dei conti')` confrontato con `['r','rw']`. `$canWrite` è vero solo per `'rw'`.

### A7. Cifre fuorvianti quando l'operazione è registrata ma non coerente — **BASSA**
- **Dove:**
  - `gestione_esercizio.php:64-72`: accanto a "Mastrino #N" mostra i totali **ricalcolati**;
  - `gestione_esercizio.php:131-172`: il Dettaglio usa gli stessi ricalcoli, con "Pareggio: Verificato";
  - `Esercizio.php:569-570`: i valori registrati (`existing['account_totals']`, `existing['balance']`) non vengono mai mostrati.
- **Correzione:**
  - se l'operazione esiste, mostrare i **valori registrati**;
  - se è 'stale', 'anomaly' o 'future', aggiungere una tabella "Registrato / Atteso oggi / Differenza" per conto, con evidenziati solo i conti diversi.

### A8. Robustezza della modale — **INFORMATIVA**
- **Dove:** `gestione_esercizio.php` non ha try/catch, mentre `edit.php` sì.
- **Effetto:** un periodo in sessione malformato (raggiungibile solo modificando l'URL a mano) causa un'eccezione non gestita.
- **Correzione:** stesso try/catch di `edit.php`, più validazione del formato `Y-m-d` e verifica che la data sia valida (`DateTime::createFromFormat` con controllo degli errori).

### A9. Pulizie a bassa priorità — **INFORMATIVA**
- **Prestazioni:** 7–11 query su `co_movimenti` a ogni caricamento del Piano dei conti, di cui 3 aggregate. Memorizzare dentro l'istanza di `Esercizio` i risultati di `getOpeningBalances()` e `getExisting()`, oggi ricalcolati 2 volte. Non aggiungere indici: è una modifica di schema.
- **Controllo di pareggio:** `round($debit-$credit, 6)` (`Esercizio.php:318`) è sempre vero per costruzione. Somme in float, falsi errori solo oltre circa 250 M€. Fare i calcoli in centesimi interi o con bcmath. Vedi D3 prima di toccare `HAVING`.
- **`MODULE`:** `options = ""`. Se il modulo "Piano dei conti" è **disabilitato**, l'installer (`isComponentInstalled` filtra gli abilitati) inserisce un modulo duplicato. Documentare nelle istruzioni: "abilitare il modulo prima dell'installazione".

---

## B. Decisioni di design — NON implementare senza approvazione dell'utente

1. **Rimedio per l'operazione "Da verificare"** (problema principale, frequente ogni anno).
   - **Il problema:** registrazioni retrodatate sull'anno precedente dopo l'Apertura (fatture di dicembre registrate a gennaio, assestamenti, ammortamenti, ratei/risconti) rendono l'Apertura 'stale'. La Chiusura si blocca (`Esercizio.php:310-317`).
   - **Oggi:** il modulo non offre altro che "Visualizza Mastrino". Il rimedio manuale esiste ma nessuno lo indica: Visualizza Mastrino → Elimina in Prima nota → "Apri esercizio" di nuovo.
   - **Proposta in valutazione:** "Rigenera" controllata, permessa solo se l'operazione successiva non esiste ancora:
     - Apertura N rigenerabile se la Chiusura N non esiste;
     - Chiusura N rigenerabile se l'Apertura N+1 non esiste;
     - sotto lock, con anteprima delle differenze;
     - cancellazione con `Mastrino::delete()`, **mai** un `DELETE` diretto.
   - La ricerca su come lo fanno altri software è ancora in corso.
2. **Trappola "modifica invece di elimina".** Salvare il mastrino tecnico in Prima nota toglie i flag (vedi Contesto). Il modulo allora ripropone l'operazione e i saldi vengono contati due volte. Possibili soluzioni: un blocco in `modules/primanota/custom/`, oppure il riconoscimento dei mastrini "Apertura conto" / "Chiusura conto" senza flag. Da decidere.
3. **Continuità tra esercizi.**
   - **Il problema:** `getOpeningBalances()` legge solo i movimenti di N-1. Se N-1 non ha avuto l'Apertura, i saldi di N-2 e degli anni precedenti vengono persi senza alcun avviso. È un comportamento ereditato dal nativo.
   - **Opzioni da decidere:** avviso o blocco, e con quale regola.
   - Caso particolare: se N-1 non ha movimenti, l'Apertura N mostra un messaggio fuorviante ("Nessun saldo patrimoniale…") e la Chiusura N viene permessa comunque.
4. **Utile/perdita e conti economici** (limite ereditato, identico al nativo riga per riga).
   - I conti *Economico* non vengono mai chiusi e il risultato non va mai a Patrimonio netto.
   - Il risultato finisce, cumulato negli anni, nel conto tecnico di Apertura.
   - Serve una decisione di perimetro: documentarlo come limite, oppure aggiungere epilogo e rilevazione del risultato.
5. **Compatibilità con OSM 2.11.**
   - `update/2_11.sql` (nella v2.11 ufficiale) rinomina:
     - `co_pianodeiconti*` → `co_piano_dei_conti*`;
     - `idpianodeiconti1/2` → `id_piano_dei_conti1/2`;
     - `co_movimenti.idconto` → `id_conto`, `iddocumento` → `id_documento`, `idmastrino` → `id_mastrino`.
   - Dopo l'aggiornamento il modulo lancia eccezioni: gestite, nessun dato corrotto. Però continua a intercettare le operazioni e a nascondere i pulsanti nativi, quindi **né il modulo né la funzione nativa** funzionano più.
   - Da decidere: versione separata per la 2.11, riconoscimento dello schema a runtime, oppure blocco dell'intercettazione se lo schema non corrisponde.
6. **Chiusura permessa dal giorno di fine esercizio.** Spostarla a fine+1 non risolve: qualsiasi registrazione retrodatata dopo la chiusura ha lo stesso effetto. Non modificare come correzione isolata.

---

## C. Verificato come corretto — NON toccare

- **Override:** OSM 2.10.4 risolve `custom/actions.php` e `custom/edit.php` prima dei file nativi (`App::filepath` con marcatore `|custom|`, `PathTrait::filepath`, `ManagerTrait::getEditFile`). Il `DELETE` nativo non è più raggiungibile. Il CSS che nasconde i pulsanti è solo estetico: la protezione vera è l'intercettazione in `actions.php`.
- **Transazione:** `Database::beginTransaction()` usa `Capsule`, quindi `$connection->transaction()` in `execute()` diventa un savepoint e fa rollback correttamente. `fetchArray` ed Eloquent usano lo stesso PDO. Verificato eseguendo Laravel 12.58.
- **Segni Dare/Avere:** identici al nativo per entrambe le operazioni. Le righe `co_movimenti` prodotte dal modulo e dal nativo sono identiche.
- **Flusso della modale:** l'accesso diretto a `modules/*/custom/*.php` è permesso. `message()` fa POST a `controller.php?id_module=N`, che arriva ad `actions.php` e da lì a `custom/actions.php`. `backto=record-list` ricarica la pagina. Verificato anche simulando il JS reale.
- **Rilevazione 'stale':** funziona. Si attiva solo con movimenti che toccano conti patrimoniali (escluso il conto di Chiusura); i movimenti solo tra conti economici non la attivano. Resta valida: correggere solo il rimedio (B1) e i falsi positivi (A3, A4).
- **Sicurezza generale:** query parametrizzate (l'unico `IN(...)` usa valori `intval`) e output con escaping.

## D. Trappole da evitare

1. **Non escludere il conto di Apertura** da `getOpeningBalances()` per eliminare la riga doppia sul conto Apertura. È un problema estetico ed ereditato: il saldo netto è corretto. Le aperture già registrate hanno quella riga in più, quindi con quella modifica il controllo sul numero di righe (`classifyExisting`) le classificherebbe 'anomaly' e **bloccherebbe le relative Chiusure** (`:310-316`). Se si interviene, il confronto va fatto per totale di conto, non per numero di righe, e deve accettare lo schema esistente.
2. **Non reintrodurre `DELETE FROM co_movimenti`** diretti. Qualsiasi cancellazione passa da `Mastrino::delete()`, sotto lock, con conferma esplicita.
3. **Non cambiare `HAVING SUM(m.totale)<>0`** (per esempio arrotondando al centesimo) senza gestire le scritture già registrate: se contenevano residui sotto il centesimo, diventerebbero 'stale'.
4. **Compatibilità con la 2.10.4:** non usare nomi di tabelle o colonne della 2.11 in questa versione.

## E. Test di regressione richiesti

Dove MySQL non è disponibile, usare SQLite con stub fedeli a OSM (`database()->fetchArray/fetchOne`, `setting()`, `tr()`, `Mastrino::build`, `Movimento::build/setTotale`). Scenari minimi:

1. Primo esercizio senza saldi precedenti: Chiusura → nessun avviso di continuità (A4).
2. Apertura N, poi fattura retrodatata al 20/12 di N-1 → 'stale' e Chiusura bloccata, con messaggio chiaro (B1 quando approvato).
3. Cambio dell'impostazione del conto tecnico dopo l'Apertura → non 'stale', avviso specifico, Chiusura non bloccata (A3).
4. POST con periodo o fingerprint diverso da quello mostrato → rifiutato, nessuna scrittura (A2).
5. Lettura bloccante di esistenza dopo il lock → la seconda esecuzione viene rifiutata (A1; logica verificabile anche senza MySQL simulando il caso "righe già presenti dopo il lock").
6. Catena di 3 anni (N-1 aperto e chiuso, N aperto): tutti i saldi coerenti, righe identiche al nativo, stato 'consistent' (regressione su C e D1).
7. Utente senza permesso su "Piano dei conti" → la modale non mostra i dati (A6). Utente senza permesso su Prima nota → nessun testo `btn …` visibile (A5).
