# Changelog

Tutte le modifiche rilevanti a questo progetto sono documentate in questo file.

Il formato si basa su [Keep a Changelog](https://keepachangelog.com/it/1.0.0/)
e il progetto aderisce al [Semantic Versioning](https://semver.org/lang/it/).

## [1.0.2] - 2026-10-30
### Aggiunto
- Compatibilità **PrestaShop 9.x** (PHP 8.1 – 8.4) mantenendo PrestaShop 1.7.8.x
  con lo stesso codice (`ps_versions_compliancy` max `9.99.99`). Verificato su
  PrestaShop 9.0.1 (temi Classic e Hummingbird) e 1.7.8.11.
- Release: due zip con lo stesso contenuto, `omeganodesrecesso-vX.Y.Z-ps1.7.zip`
  e `omeganodesrecesso-vX.Y.Z-ps9.zip`; controllo sintassi PHP 7.1 / 8.4 prima
  del bump di versione.

### Corretto
- PrestaShop 9: sostituite le API rimosse `Tools::displayPrice()` (ora
  `Locale::formatPrice()`), `Tools::encrypt()` (token CSRF via HMAC),
  `AdminController::l()` (traduzioni del controller admin via `Module::l()`) e il
  validatore `isAnything` (ora `isString`, prima impediva il salvataggio del
  record di recesso).
- La conferma del recesso falliva su tutte le versioni (query con doppio
  `LIMIT 1`: `Db::getValue()` lo aggiunge già); stessa correzione per la data
  "recesso già richiesto".
- Le pagine della procedura (step 1, step 2, esito) ora usano il layout del tema
  (`page.tpl`): prima venivano mostrate senza header, footer, CSS e JS.
- Back office: i link all'ordine aprono il dettaglio (prima la lista ordini),
  caricato il CSS admin del modulo (logo sovrapposto al titolo) e rimosso il
  pulsante "Aggiungi" dalla toolbar della lista (i record nascono solo dal front).

## [1.0.1] - 2026-06-15
### Aggiunto
- Workflow GitHub Actions di release automatica (bump patch, tag, zip).

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
