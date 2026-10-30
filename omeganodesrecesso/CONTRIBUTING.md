# Contribuire

Grazie per l'interesse verso **OmegaNodes — Diritto di Recesso (54-bis)**.

## Principi
- **Nessun dato mock o funzione finta**: il modulo opera su dati ordine reali di
  PrestaShop.
- **File completi**: nessuno stub lasciato a metà.
- **Append-only**: il record probatorio non si modifica né si cancella dal
  codice. Qualsiasi PR che introduca `update()`/`delete()` sui record di recesso
  sarà rifiutata.
- **Compatibilità**: un solo codice per PrestaShop 1.7.8.x e 9.x. Sintassi
  PHP 7.1 (niente sintassi solo-8.x) e nessun warning/deprecation su PHP
  8.1 – 8.4. Dove un'API differisce tra le versioni usa feature detection
  (`method_exists`, `class_exists`, `version_compare(_PS_VERSION_, ...)`).

## Stile
- Header AFL-3.0 su ogni file `.php`.
- Stringhe utente tradotte (`Modules.Omeganodesrecesso.*`).
- Escape/validazione di tutti gli input (`pSQL`, `Tools::getValue`, `Validate::*`).

## Workflow
1. Forka e crea un branch descrittivo.
2. Verifica con `php -l` ogni file modificato, con PHP 7.1 **e** PHP 8.4 (la CI
   blocca la release se uno dei due fallisce).
3. Testa su istanze PrestaShop 1.7.8.x e 9.x reali con un ordine reale.
4. Aggiorna `CHANGELOG.md` (formato Keep a Changelog).
5. Apri la PR descrivendo il requisito 54-bis coperto.

## Sicurezza
Per vulnerabilità, scrivi in privato via https://omeganodes.ai invece di aprire
una issue pubblica.
