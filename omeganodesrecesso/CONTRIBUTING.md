# Contribuire

Grazie per l'interesse verso **OmegaNodes — Diritto di Recesso (54-bis)**.

## Principi
- **Nessun dato mock o funzione finta**: il modulo opera su dati ordine reali di
  PrestaShop.
- **File completi**: nessuno stub lasciato a metà.
- **Append-only**: il record probatorio non si modifica né si cancella dal
  codice. Qualsiasi PR che introduca `update()`/`delete()` sui record di recesso
  sarà rifiutata.
- **Compatibilità**: PHP 7.4+ (niente sintassi solo-8.x), PrestaShop 1.7.8.11+.

## Stile
- Header AFL-3.0 su ogni file `.php`.
- Stringhe utente tradotte (`Modules.Omeganodesrecesso.*`).
- Escape/validazione di tutti gli input (`pSQL`, `Tools::getValue`, `Validate::*`).

## Workflow
1. Forka e crea un branch descrittivo.
2. Verifica con `php -l` ogni file modificato.
3. Testa su un'istanza PrestaShop 1.7.8.11 reale con un ordine reale.
4. Aggiorna `CHANGELOG.md` (formato Keep a Changelog).
5. Apri la PR descrivendo il requisito 54-bis coperto.

## Sicurezza
Per vulnerabilità, scrivi in privato via https://omeganodes.ai invece di aprire
una issue pubblica.
