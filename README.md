# ps_merchant_return

Modulo PrestaShop **1.7.8+ e 9.x** per il **diritto di recesso** conforme al nuovo
**art. 54-bis del Codice del consumo** (Dlgs 209/2025, dir. UE 2023/2673), in
vigore dal **19 giugno 2026**.

Il modulo vero e proprio si trova nella cartella **[`omeganodesrecesso/`](omeganodesrecesso/)**
ed è pronto per essere zippato e installato da PrestaShop (Moduli → Carica un
modulo). La documentazione completa è nel
[README del modulo](omeganodesrecesso/README.md).

## Compatibilità e download
Un unico codice sorgente, compatibile con entrambe le versioni di PrestaShop.
Ogni [GitHub Release](https://github.com/Omega-Nodes-Company-LTD/ps_merchant_return/releases) pubblica due zip con lo stesso contenuto:

| PrestaShop | PHP | Zip da scaricare |
|---|---|---|
| 9.x | 8.1 – 8.4 | `omeganodesrecesso-vX.Y.Z-ps9.zip` |
| 1.7.x (1.7.8+) | 7.1 – 7.4 | `omeganodesrecesso-vX.Y.Z-ps1.7.zip` |

## Cos'è
- Pulsante di recesso in area cliente e nel dettaglio ordine.
- Procedura in **due passaggi distinti** con conferma separata.
- **Ricevuta immediata** su supporto durevole (email + PDF con data/ora del click).
- Conservazione probatoria **append-only** con catena hash SHA-256 tamper-evident.
- Recesso parziale (residui per riga/quantità) e aggancio RMA nativo (`OrderReturn`).

Open-source e gratuito (AFL-3.0), brandizzato **OmegaNodes Company Ltd**.
Installazione e supporto a richiesta → https://omeganodes.ai

## Release automatica (GitHub Action)
Ad ogni push su **`main`**, il workflow [`.github/workflows/release.yml`](.github/workflows/release.yml):

1. controlla la sintassi di tutti i file PHP con **PHP 7.1** (PrestaShop 1.7) e
   **PHP 8.4** (PrestaShop 9) tramite i container ufficiali `php:7.1-cli` /
   `php:8.4-cli`: se fallisce, nessun bump e nessuna release;
2. incrementa la versione patch (`$this->version` in `omeganodesrecesso.php`) e
   committa il bump (`chore: release vX.Y.Z [skip ci]`);
3. crea il tag `vX.Y.Z`;
4. costruisce i due zip **`omeganodesrecesso-vX.Y.Z-ps1.7.zip`** e
   **`omeganodesrecesso-vX.Y.Z-ps9.zip`** (stesso contenuto) con la cartella
   `omeganodesrecesso/` alla radice (esclusi i PDF probatori e i file di
   sviluppo);
5. pubblica una **GitHub Release** con i due zip allegati e la tabella di
   download nelle note.

> **Prerequisito.** Se `main` ha *branch protection* che blocca i push diretti,
> il commit di bump del bot fallirà: consenti a **GitHub Actions** di pushare su
> `main` (Settings → Branches → consenti il bypass per GitHub Actions, oppure
> non proteggere i push diretti su `main`). I push effettuati con il
> `GITHUB_TOKEN` non ritriggerano il workflow, quindi non si creano loop.

## Creare lo zip installabile a mano
```bash
cd ps_merchant_return
zip -r omeganodesrecesso.zip omeganodesrecesso \
  -x '*/storage/receipts/*.pdf' '*.git*'
```
Lo zip avrà la cartella `omeganodesrecesso/` alla radice, come richiesto da
PrestaShop.
