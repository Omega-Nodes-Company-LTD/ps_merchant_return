<?php
/**
 * OmegaNodes — Diritto di Recesso (54-bis)
 *
 * Upgrade a 1.0.3:
 *  - hook actionObjectShopUpdateAfter (ripristino degli hook dopo un cambio
 *    tema) e ri-registrazione degli hook del modulo eventualmente sganciati;
 *  - archivio ricevute PDF fuori dalla cartella del modulo: per gli shop che
 *    usano ancora il percorso predefinito della 1.0.2 (cartella del modulo) le
 *    ricevute vengono COPIATE in var/omeganodesrecesso/receipts, verificate con
 *    SHA-256, e solo allora si aggiornano i percorsi nei record (campo fuori dal
 *    row_hash: la catena hash resta valida) e la configurazione. Gli originali
 *    restano dove sono. Nessuna modifica se il percorso e' personalizzato o se
 *    anche una sola copia non riesce (resta tutto com'era, errore nei log);
 *  - rimozione delle barre rovesciate aggiunte dal doppio escape (pSQL) agli
 *    apostrofi di nome mittente e nota supporto.
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
function upgrade_module_1_0_3($module)
{
    // PrestaShop 9.0 (upgrade da "Carica un modulo" nel back office): l'istanza
    // passata allo script puo' non avere l'id (Module::$modules_cache contiene
    // solo i dati di upgrade) e registerHook() fallirebbe disattivando il modulo.
    if (!$module->id) {
        $module->id = (int) Module::getModuleIdByName($module->name);
    }

    if (!$module->restoreHooks()) {
        return false;
    }

    // Problemi di archivio o di configurazione non bloccano l'upgrade: il modulo
    // continua a funzionare sul percorso precedente e l'esito e' nei log.
    omeganodesrecesso_upgrade_103_storage($module);
    omeganodesrecesso_upgrade_103_unescape();

    Configuration::loadConfiguration();

    return true;
}

/**
 * Migrazione dell'archivio ricevute dal vecchio predefinito (cartella modulo).
 *
 * @param Omeganodesrecesso $module
 */
function omeganodesrecesso_upgrade_103_storage($module)
{
    $db = Db::getInstance();
    $legacy = rtrim($module->getLegacyStoragePath(), '/');
    $target = rtrim($module->getDefaultStoragePath(), '/');

    // Shop che usano il vecchio predefinito: valore uguale al vecchio percorso
    // oppure vuoto (in 1.0.2 vuoto significava "cartella del modulo").
    $shops = array();
    foreach (Shop::getShops(false) as $shop) {
        $value = Configuration::get('OMEGA_REC_STORAGE_PATH', null, (int) $shop['id_shop_group'], (int) $shop['id_shop']);
        $value = rtrim((string) $value, '/');
        if ($value === '' || $value === $legacy) {
            $shops[] = (int) $shop['id_shop'];
        }
    }

    if (!$shops) {
        return; // percorso personalizzato ovunque: nessuna modifica
    }

    $failed = array();
    $missing = array();
    $copied = 0;
    $relocated = 0;

    if (!$module->createStorage($target)) {
        $failed[] = $target . ' (cartella non scrivibile)';
    }

    // 1) Ricevute referenziate dai record.
    if (!$failed) {
        $rows = $db->executeS(
            'SELECT id_recesso, receipt_pdf_path FROM `' . _DB_PREFIX_ . 'omega_recesso_request`
             WHERE id_shop IN (' . implode(',', $shops) . ') AND receipt_pdf_path <> \'\'
             ORDER BY id_recesso ASC'
        );
        foreach (is_array($rows) ? $rows : array() as $row) {
            $src = (string) $row['receipt_pdf_path'];
            if (strpos($src, $legacy . '/') !== 0) {
                continue; // gia' altrove (es. fallback var/)
            }
            if (!is_file($src)) {
                $missing[] = '#' . (int) $row['id_recesso'] . ' ' . $src;
                continue; // file gia' perso: il percorso resta quello registrato
            }
            $dst = $target . Tools::substr($src, Tools::strlen($legacy));
            if (!omeganodesrecesso_upgrade_103_copy($module, $src, $dst)) {
                $failed[] = $src;
                continue;
            }
            ++$copied;
            if (!OmegaRecessoRequest::relocateReceipt((int) $row['id_recesso'], $src, $dst)) {
                $failed[] = '#' . (int) $row['id_recesso'] . ' (percorso non aggiornato)';
                continue;
            }
            ++$relocated;
        }
    }

    // 2) Altri PDF presenti nell'archivio dello shop (es. ricevute senza record).
    if (!$failed) {
        foreach ($shops as $idShop) {
            $files = glob($legacy . '/' . (int) $idShop . '/*.pdf');
            foreach ($files ? $files : array() as $src) {
                $dst = $target . '/' . (int) $idShop . '/' . basename($src);
                if (is_file($dst) && hash_file('sha256', $dst) === hash_file('sha256', $src)) {
                    continue; // copiato al passo 1
                }
                if (!omeganodesrecesso_upgrade_103_copy($module, $src, $dst)) {
                    $failed[] = $src;
                } else {
                    ++$copied;
                }
            }
        }
    }

    if ($failed) {
        PrestaShopLogger::addLog(
            'OmegaRecesso upgrade 1.0.3: migrazione ricevute in ' . $target . ' NON completata, archivio invariato su '
            . $legacy . '. Errori: ' . implode('; ', $failed),
            3
        );

        return;
    }

    // 3) Tutto copiato e verificato: la configurazione passa al nuovo predefinito.
    $db->execute(
        'UPDATE `' . _DB_PREFIX_ . 'configuration` SET value = \'\', date_upd = NOW()
         WHERE name = \'OMEGA_REC_STORAGE_PATH\'
           AND TRIM(TRAILING \'/\' FROM value) = \'' . pSQL($legacy) . '\''
    );

    PrestaShopLogger::addLog(
        'OmegaRecesso upgrade 1.0.3: archivio ricevute spostato in ' . $target . ' (' . $copied . ' PDF copiati e verificati, '
        . $relocated . ' record aggiornati; originali lasciati in ' . $legacy . ')'
        . ($missing ? '. Ricevute mancanti prima della migrazione: ' . implode('; ', $missing) : ''),
        $missing ? 2 : 1
    );
}

/**
 * Copia con verifica SHA-256 (idempotente: una copia identica gia' presente va bene).
 *
 * @param Omeganodesrecesso $module
 *
 * @return bool
 */
function omeganodesrecesso_upgrade_103_copy($module, $src, $dst)
{
    $dir = dirname($dst);
    if (!$module->createStorage($dir)) {
        return false;
    }

    $hash = hash_file('sha256', $src);
    if ($hash === false) {
        return false;
    }

    if (is_file($dst)) {
        return hash_file('sha256', $dst) === $hash;
    }

    if (!@copy($src, $dst)) {
        return false;
    }

    if (hash_file('sha256', $dst) !== $hash) {
        @unlink($dst);

        return false;
    }

    return true;
}

/**
 * Fino alla 1.0.2 nome mittente e nota supporto passavano da pSQL() prima di
 * Configuration::updateValue() (che fa gia' l'escape): ogni salvataggio
 * aggiungeva una barra rovesciata davanti ad apostrofi e virgolette.
 */
function omeganodesrecesso_upgrade_103_unescape()
{
    $db = Db::getInstance();
    $rows = $db->executeS(
        'SELECT id_configuration, value FROM `' . _DB_PREFIX_ . 'configuration`
         WHERE name IN (\'OMEGA_REC_MAIL_FROM_NAME\', \'OMEGA_REC_SUPPORT_NOTE\')'
    );

    foreach (is_array($rows) ? $rows : array() as $row) {
        $value = (string) $row['value'];
        $clean = $value;
        do {
            $previous = $clean;
            $clean = str_replace(array('\\\'', '\\"'), array('\'', '"'), $clean);
        } while ($clean !== $previous);

        if ($clean !== $value) {
            $db->update(
                'configuration',
                array('value' => pSQL($clean), 'date_upd' => date('Y-m-d H:i:s')),
                'id_configuration = ' . (int) $row['id_configuration']
            );
        }
    }
}
