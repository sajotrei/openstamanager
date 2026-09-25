# Decisioni utente successive alla RC7

Nessuno dei punti seguenti è implementato nella RC7.

## 1. Rigenerazione controllata

- Problema: registrazioni retrodatate rendono Apertura o Chiusura `Da verificare`.
- Frequenza prevista: ricorrente, soprattutto a inizio anno.
- Rischio: una rigenerazione automatica può alterare lo storico e rompere la catena degli esercizi.
- Opzioni: mantenere solo l'avviso; procedura guidata con anteprima; storno e nuova scrittura.
- Raccomandazione: progettare una funzione separata, mai una ripetizione silenziosa.
- Impatto PR: elevato; meglio una PR distinta.
- Decisione richiesta: scegliere se aprire un progetto separato dopo la STABLE.

## 2. Modifica dei Mastrini tecnici in Prima nota

- Problema: il salvataggio nativo può perdere i flag tecnici.
- Rischio: doppio conteggio e riproposizione dell'operazione.
- Opzioni: blocco modifica; riconoscimento alternativo; sola segnalazione.
- Raccomandazione: blocco esplicito dei Mastrini tecnici, da valutare separatamente.
- Impatto PR: medio-alto, coinvolge Prima nota.
- Decisione richiesta: autorizzare o meno una protezione cross-module.

## 3. Continuità pluriennale

- Problema: il nativo legge soltanto N-1 e può perdere saldi più vecchi se mancano riporti intermedi.
- Rischio: falsa continuità patrimoniale.
- Opzioni: avviso; blocco; ricostruzione assistita.
- Raccomandazione: iniziare da un controllo read-only e da un avviso.
- Impatto PR: alto.
- Decisione richiesta: scegliere tra avviso e blocco.

## 4. Conti economici e utile/perdita

- Problema: limite ereditato dal nativo; i conti economici non vengono epilogati.
- Rischio: estendere il perimetro contabile oltre la funzione attuale.
- Opzioni: documentare il limite; implementare epilogo e risultato d'esercizio.
- Raccomandazione: non includere nella prima PR.
- Impatto PR: molto alto.
- Decisione richiesta: eventuale progetto contabile separato.

## 5. Compatibilità OSM 2.11+

- Problema: schema e colonne rinominati.
- Opzioni: pacchetto separato; riconoscimento runtime; blocco esplicito.
- Raccomandazione: versione separata per ogni baseline, con porting upstream sulla master.
- Impatto PR: medio.
- Decisione richiesta: stabilire il target successivo dopo la 2.10.4.

## 6. Giorno di disponibilità della Chiusura

- Problema: chiudere il giorno finale non impedisce registrazioni retrodatate successive.
- Opzioni: data finale; giorno successivo; calendario configurabile.
- Raccomandazione: mantenere il comportamento attuale e affidarsi al controllo `Da verificare`.
- Impatto PR: basso ma semanticamente sensibile.
- Decisione richiesta: nessuna modifica senza indicazione esplicita.
