# Changelog

Tutte le modifiche rilevanti a questo progetto sono documentate in questo file.

Il formato si basa su [Keep a Changelog](https://keepachangelog.com/it/1.0.0/)
e il progetto aderisce al [Semantic Versioning](https://semver.org/lang/it/).

## [1.0.3] - 2026-10-30
### Corretto
- Conferma del recesso: il record probatorio viene salvato **prima** di
  ricevuta PDF, reso nativo (`OrderReturn`) ed email. Prima, se il salvataggio
  falliva (es. recapito più lungo di 255 caratteri), restavano un reso orfano,
  un PDF e un'email "abbiamo registrato la tua dichiarazione" senza alcun
  record. Dopo il salvataggio ogni passo registra subito il proprio esito sul
  record (percorso PDF, reso, data di invio: campi esclusi dalla catena hash,
  scritti una sola volta) e gli errori finiscono nel log di PrestaShop, anche
  quelli non intercettabili (es. memoria esaurita durante il PDF).
- Conferme concorrenti (doppio invio, più schede) serializzate per shop con un
  lock: i residui vengono ricontrollati al momento del salvataggio, quindi non
  si possono più dichiarare quantità oltre l'ordinato.
- Il recapito per la ricevuta deve essere un'email valida (max 255 caratteri),
  verificata già al passo 1; la pagina di esito indica gli indirizzi a cui la
  ricevuta è stata davvero inviata.
- Email della ricevuta su PrestaShop 1.7: mancava l'ora della conferma
  (`Tools::displayDate()` ha firme diverse in 1.7 e 9). Oggetto traducibile,
  riferimento della richiesta nell'email e nel PDF; se il PDF non è generabile
  l'email non annuncia più un allegato inesistente, e se non è archiviabile su
  disco viene comunque allegato.
- Download della ricevuta dal back office: errori mostrati nella pagina invece
  di `Tools::displayError()` (deprecato in PrestaShop 9: pagina 500 / eccezione).
- Export CSV: la colonna integrità riporta `OK`/`KO` invece dell'hash grezzo;
  l'hash SHA-256 è in una colonna separata.
- Titolo (meta title) delle pagine della procedura: "Diritto di recesso",
  "Conferma del recesso", "Recesso registrato" invece del nome dello shop.
- Configurazione: nome mittente e nota supporto non vengono più salvati con una
  barra rovesciata davanti agli apostrofi (doppio escape); l'aggiornamento
  ripulisce i valori già salvati.
- Il cambio tema non sgancia più il modulo da `displayCustomerAccount` e
  `displayFooter`: gli hook vengono ripristinati automaticamente
  (l'aggiornamento li ripristina anche sugli shop dove erano già spariti).

### Modificato
- Archivio predefinito delle ricevute PDF in `var/omeganodesrecesso/receipts`,
  fuori dalla cartella del modulo (prima andava perso cancellando la cartella).
  Aggiornando da 1.0.2 sul vecchio percorso predefinito le ricevute vengono
  copiate e verificate (SHA-256) e i percorsi nei record aggiornati senza
  toccare la catena hash; con un percorso personalizzato non cambia nulla.
  Dettagli nel README.

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
