# ps_merchant_return

Modulo PrestaShop **1.7.8+** per il **diritto di recesso** conforme al nuovo
**art. 54-bis del Codice del consumo** (Dlgs 209/2025, dir. UE 2023/2673), in
vigore dal **19 giugno 2026**.

Il modulo vero e proprio si trova nella cartella **[`omeganodesrecesso/`](omeganodesrecesso/)**
ed è pronto per essere zippato e installato da PrestaShop (Moduli → Carica un
modulo). La documentazione completa è nel
[README del modulo](omeganodesrecesso/README.md).

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

1. incrementa la versione patch (`$this->version` in `omeganodesrecesso.php`) e
   committa il bump (`chore: release vX.Y.Z [skip ci]`);
2. crea il tag `vX.Y.Z`;
3. costruisce lo zip **`omeganodesrecesso-X.Y.Z.zip`** con la cartella
   `omeganodesrecesso/` alla radice (esclusi solo i PDF probatori);
4. pubblica una **GitHub Release** con lo zip allegato.

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
