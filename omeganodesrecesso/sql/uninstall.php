<?php
/**
 * OmegaNodes — Diritto di Recesso (54-bis)
 *
 * Drop OPT-IN delle tabelle probatorie. Eseguito SOLO se la configurazione
 * `OMEGA_REC_DROP_ON_UNINSTALL` e' impostata a 1 (default 0).
 *
 * ATTENZIONE: la cancellazione dei dati probatori puo' avere implicazioni
 * legali. Il merchant e' titolare del trattamento e responsabile dei backup.
 *
 * @author    OmegaNodes Company Ltd <https://omeganodes.ai>
 * @copyright 2026 OmegaNodes Company Ltd
 * @license   https://opensource.org/licenses/AFL-3.0  Academic Free License 3.0 (AFL-3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

$sql = array();

// Prima la figlia (per coerenza referenziale logica), poi la padre.
$sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'omega_recesso_request_line`;';
$sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'omega_recesso_request`;';

foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}

return true;
