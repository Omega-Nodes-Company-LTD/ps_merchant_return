<?php
/**
 * OmegaNodes — Diritto di Recesso (54-bis)
 *
 * Placeholder di upgrade verso 1.0.1. Aggiungere qui eventuali migrazioni
 * future (es. nuove colonne) mantenendo l'integrita' append-only dei dati.
 *
 * @author    OmegaNodes Company Ltd <https://omeganodes.ai>
 * @copyright 2026 OmegaNodes Company Ltd
 * @license   https://opensource.org/licenses/AFL-3.0  Academic Free License 3.0 (AFL-3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * @param Omeganodesrecesso $module
 *
 * @return bool
 */
function upgrade_module_1_0_1($module)
{
    // Nessuna migrazione necessaria in questa versione.
    return true;
}
