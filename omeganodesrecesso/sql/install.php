<?php
/**
 * OmegaNodes — Diritto di Recesso (54-bis)
 *
 * Creazione delle tabelle probatorie del modulo.
 * Tabella padre `omega_recesso_request` (append-only, catena hash SHA-256) +
 * tabella figlia `omega_recesso_request_line` (righe oggetto della dichiarazione).
 *
 * @author    OmegaNodes Company Ltd <https://omeganodes.ai>
 * @copyright 2026 OmegaNodes Company Ltd
 * @license   https://opensource.org/licenses/AFL-3.0  Academic Free License 3.0 (AFL-3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

$sql = array();

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'omega_recesso_request` (
  `id_recesso`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_shop`           INT UNSIGNED NOT NULL DEFAULT 1,
  `id_order`          INT UNSIGNED NOT NULL,
  `id_customer`       INT UNSIGNED NOT NULL DEFAULT 0,
  `order_reference`   VARCHAR(16) NOT NULL,
  `customer_firstname` VARCHAR(255) NOT NULL,
  `customer_lastname`  VARCHAR(255) NOT NULL,
  `customer_email`    VARCHAR(255) NOT NULL,
  `customer_contact`  VARCHAR(255) NOT NULL,
  `contract_snapshot` MEDIUMTEXT NOT NULL,
  `client_ip`         VARCHAR(45) NOT NULL,
  `user_agent`        VARCHAR(512) NOT NULL DEFAULT \'\',
  `confirmed_at`      DATETIME NOT NULL,
  `receipt_pdf_path`  VARCHAR(255) NOT NULL DEFAULT \'\',
  `receipt_sent_at`   DATETIME NULL DEFAULT NULL,
  `id_order_return`   INT UNSIGNED NOT NULL DEFAULT 0,
  `prev_hash`         CHAR(64) NOT NULL DEFAULT \'\',
  `row_hash`          CHAR(64) NOT NULL,
  `created_at`        DATETIME NOT NULL,
  PRIMARY KEY (`id_recesso`),
  KEY `idx_order` (`id_order`),
  KEY `idx_customer` (`id_customer`),
  KEY `idx_confirmed` (`confirmed_at`),
  KEY `idx_shop` (`id_shop`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'omega_recesso_request_line` (
  `id_recesso_line`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_recesso`        INT UNSIGNED NOT NULL,
  `id_order_detail`   INT UNSIGNED NOT NULL,
  `id_customization`  INT UNSIGNED NOT NULL DEFAULT 0,
  `product_name`      VARCHAR(255) NOT NULL,
  `product_reference` VARCHAR(64) NOT NULL DEFAULT \'\',
  `product_quantity`  INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id_recesso_line`),
  KEY `idx_recesso` (`id_recesso`),
  KEY `idx_order_detail` (`id_order_detail`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}

return true;
