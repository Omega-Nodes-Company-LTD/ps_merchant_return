# Mappa di conformità art. 54-bis → feature del modulo

Questo documento mappa ogni requisito normativo a una feature implementata ed è
il contratto di accettazione del modulo.

| Requisito art. 54-bis | Feature del modulo |
|---|---|
| "Pulsante" di recesso ben evidente e accessibile | Bottone in area cliente (dashboard) **e** nel dettaglio del singolo ordine |
| Disponibile per tutto il periodo utile | Gating *soft*: visibile entro `periodo+grace`, oltre il termine resta visibile con avviso (no dark pattern) |
| Procedura in due passaggi distinti | Step 1 = identificazione ordine + dati; Step 2 = pagina separata con unico comando **"Conferma recesso"** |
| Comando di conferma separato e chiaro | Il record si crea **solo** al click su "Conferma recesso" dello step 2 |
| Ricevuta immediata su supporto durevole con data/ora esatte del click | Email automatica immediata + PDF allegato con timestamp del click |
| Conservazione probatoria (timestamp, IP, dati contratto, copia ricevuta) | Tabella append-only `*_request` + catena hash SHA-256 tamper-evident + PDF archiviato |
| Coerenza con condizioni di vendita / informativa precontrattuale | Snippet di testo (IT) fornito al merchant da incollare in CMS/condizioni |
| Divieto di dark pattern | Bottone sempre raggiungibile, label inequivocabili, esattamente 2 step, nessuna casella pre-spuntata o frizione aggiuntiva |

## Ambito applicativo
Shop B2C, beni fisici non personalizzati (es. attrezzatura montagna/sci,
abbigliamento non sigillato). Nessuna eccezione dell'art. 59 si applica. Recesso
ordinario **14 giorni dalla consegna**, senza obbligo di motivazione.

## Disclaimer
Il modulo fornisce il *meccanismo tecnico* di conformità. Restano responsabilità
del merchant: i testi legali (condizioni di vendita, informative), i backup del
database probatorio e dell'archivio PDF, e la valutazione finale di conformità con
il proprio consulente legale. La catena hash è *tamper-evident*, non
*tamper-proof*: l'integrità dello storage resta in capo al titolare del
trattamento.
