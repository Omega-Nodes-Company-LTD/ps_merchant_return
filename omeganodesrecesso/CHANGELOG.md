# Changelog

Tutte le modifiche rilevanti a questo progetto sono documentate in questo file.

Il formato si basa su [Keep a Changelog](https://keepachangelog.com/it/1.0.0/)
e il progetto aderisce al [Semantic Versioning](https://semver.org/lang/it/).

## [1.0.0] - 2026-06-15
### Aggiunto
- Procedura di recesso in due passaggi distinti (identificazione/selezione →
  conferma separata) con creazione del record **solo** alla conferma.
- Pulsante di recesso in area cliente (`displayCustomerAccount`) e nel dettaglio
  ordine (`displayOrderDetail`).
- Ricevuta immediata su supporto durevole: email automatica con PDF allegato e
  data/ora esatte del click.
- Conservazione probatoria append-only con catena hash SHA-256 tamper-evident
  (record padre + righe).
- Recesso parziale con gestione dei residui (`getReturnableQuantities`).
- Aggancio al sistema RMA nativo PrestaShop (`OrderReturn` parziale).
- Gating configurabile (periodo ≥ 14 giorni, grace, data di partenza,
  visibilità oltre termine con avviso).
- Storage ricevute protetto (`.htaccess` + `web.config` + `index.php`) con
  fallback automatico su `var/`; download solo da admin con token.
- Back office read-only: lista, dettaglio righe, verifica integrità catena,
  download ricevuta, export CSV.
- Multistore, i18n IT/EN, snippet legale per le condizioni di vendita.
