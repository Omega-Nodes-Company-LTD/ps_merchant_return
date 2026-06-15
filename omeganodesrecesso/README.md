# OmegaNodes — Diritto di Recesso (54-bis) per PrestaShop

Modulo PrestaShop **1.7.8.11+** che rende un e-commerce B2C conforme al nuovo
**art. 54-bis del Codice del consumo** (Dlgs 209/2025, recepimento della
direttiva UE 2023/2673), in vigore dal **19 giugno 2026**.

Fornisce il *meccanismo tecnico* di conformità: un "pulsante di recesso" ben
evidente, una procedura in **due passaggi distinti**, una **ricevuta immediata
su supporto durevole** (email + PDF con data/ora esatte del click) e la
**conservazione probatoria** in una tabella append-only con catena di hash
SHA-256 tamper-evident.

> **Open-source e gratuito.** Brandizzato OmegaNodes Company Ltd, licenza
> AFL-3.0. La monetizzazione avviene solo tramite supporto/installazione a
> contatto: nessun pagamento è presente nel modulo.
> Installazione e supporto a richiesta → **https://omeganodes.ai**

---

## Cosa fa (mappa di conformità art. 54-bis)

| Requisito normativo | Implementazione |
|---|---|
| Pulsante di recesso ben evidente | Box in area cliente (`displayCustomerAccount`) **e** bottone nel dettaglio ordine (`displayOrderDetail`) |
| Disponibile per tutto il periodo utile | Gating *soft*: oltre il termine il bottone resta visibile con avviso (no dark pattern) |
| Procedura in due passaggi distinti | Step 1 (identificazione + selezione righe) → Step 2 (pagina separata, unico comando) |
| Comando di conferma separato e chiaro | Il record si crea **solo** al click su "Conferma recesso" |
| Ricevuta immediata su supporto durevole | Email automatica con PDF allegato, data/ora esatte del click |
| Conservazione probatoria | Tabella append-only + catena hash SHA-256 + PDF archiviato |
| Divieto di dark pattern | Bottone sempre raggiungibile, label inequivocabili, esattamente 2 step, nessuna casella pre-spuntata |

Supporta inoltre il **recesso parziale** (sottoinsieme di righe/quantità con
gestione dei residui) e l'aggancio al sistema **RMA nativo** di PrestaShop
(`OrderReturn`) per la gestione logistica di reso e rimborso.

---

## Requisiti

- PrestaShop **1.7.8.11+** (target API 1.7.8)
- PHP **7.4+**
- MySQL/MariaDB con supporto `utf8mb4`

---

## Installazione

1. Scarica lo zip della release (cartella `omeganodesrecesso/` alla radice).
2. Back office → **Moduli → Carica un modulo** → seleziona lo zip.
3. Installa. Verranno create le tabelle probatorie, la voce di menu sotto
   **Ordini** e la cartella protetta per le ricevute.
4. Configura il modulo (vedi sotto) e incolla lo snippet legale nelle tue
   condizioni di vendita (`docs/snippet-condizioni-vendita-IT.md`).

---

## Configurazione

Moduli → *OmegaNodes — Diritto di Recesso (54-bis)* → **Configura**.

| Parametro | Default | Note |
|---|---|---|
| Giorni di recesso | 14 | **Minimo 14** (valori inferiori vengono rifiutati). Estendere è sempre lecito (es. 30, 100, 180 giorni). |
| Giorni di grace | 0 | Buffer extra prima dell'avviso/nascondimento. |
| Data di partenza | delivery | `delivery` (consegna, con fallback spedizione/validazione), `shipped`, `validation`. |
| Mostra oltre termine | Sì | Anti dark pattern: bottone visibile oltre il termine con avviso. |
| Nome mittente ricevuta | shop name | |
| Link nel footer | No | Link "Diritto di recesso" sempre visibile nel footer. |
| Powered by OmegaNodes | Sì | Attribuzione open-source discreta. |
| Percorso archiviazione ricevute | interno modulo | Override consigliato per hosting che svuotano la cartella modulo; **fallback automatico su `var/`** se non scrivibile. |
| Nota supporto (solo admin) | — | Mostrata solo in back office. |
| Droppa tabelle alla disinstallazione | No | Se attivo, la disinstallazione cancella i dati probatori (implicazioni legali). |

---

## Sicurezza dello storage ricevute

Le ricevute PDF sono servite **solo** dal back office tramite token; non esiste
un link pubblico indovinabile (nome file = UUID v4). La cartella è protetta con
`.htaccess`, `web.config` e un `index.php` di guardia.

Su **Nginx** `.htaccess` non viene applicato: aggiungi questa regola come difesa
in profondità (il path è comunque non linkato e servito solo via controller):

```nginx
location ~* /modules/omeganodesrecesso/storage/ {
    deny all;
    return 404;
}
```

---

## Disclaimer (responsabilità del merchant)

Il modulo fornisce il **meccanismo tecnico** di conformità. Restano
responsabilità del merchant:

- i **testi legali** (condizioni di vendita, informative precontrattuali);
- i **backup** del database probatorio e dell'archivio PDF (la catena hash è
  *tamper-evident*, non *tamper-proof*);
- la **valutazione finale di conformità** con il proprio consulente legale.

Il dato probatorio resta nel database del merchant (scelta GDPR-corretta: dato
presso il titolare del trattamento).

---

## Supporto

Installazione, adeguamento e supporto su richiesta — preventivo via
**https://omeganodes.ai**. Il modulo è gratuito e open-source; il supporto è un
servizio professionale OmegaNodes.

---

## English (summary)

PrestaShop 1.7.8.11+ module that makes a B2C store compliant with the new
art. 54-bis of the Italian Consumer Code (in force from 19 June 2026): a
prominent withdrawal button, a strict **two-step** procedure, an **immediate
receipt on a durable medium** (email + timestamped PDF) and **append-only
evidential storage** with a SHA-256 hash chain. Supports partial withdrawal and
native `OrderReturn` (RMA) integration. Free and open-source (AFL-3.0).
Paid installation/support on request → https://omeganodes.ai

The module provides the technical compliance mechanism only. Legal texts,
database/PDF backups and the final compliance assessment remain the merchant's
responsibility.
