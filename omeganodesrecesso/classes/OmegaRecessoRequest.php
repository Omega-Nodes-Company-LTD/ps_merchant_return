<?php
/**
 * OmegaNodes — Diritto di Recesso (54-bis)
 *
 * ObjectModel del record probatorio di recesso (append-only, catena hash
 * SHA-256, tamper-evident). Gestisce il record padre + le righe figlie in
 * un'unica transazione. Nessun update/delete e' consentito dal codice del
 * modulo: i metodi sono sovrascritti per lanciare eccezione.
 *
 * @author    OmegaNodes Company Ltd <https://omeganodes.ai>
 * @copyright 2026 OmegaNodes Company Ltd
 * @license   https://opensource.org/licenses/AFL-3.0  Academic Free License 3.0 (AFL-3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class OmegaRecessoRequest extends ObjectModel
{
    /** @var int */
    public $id_recesso;
    /** @var int */
    public $id_shop;
    /** @var int */
    public $id_order;
    /** @var int */
    public $id_customer;
    /** @var string */
    public $order_reference;
    /** @var string */
    public $customer_firstname;
    /** @var string */
    public $customer_lastname;
    /** @var string */
    public $customer_email;
    /** @var string */
    public $customer_contact;
    /** @var string JSON snapshot del contratto al momento del recesso */
    public $contract_snapshot;
    /** @var string */
    public $client_ip;
    /** @var string */
    public $user_agent;
    /** @var string */
    public $confirmed_at;
    /** @var string */
    public $receipt_pdf_path;
    /** @var string|null */
    public $receipt_sent_at;
    /** @var int */
    public $id_order_return;
    /** @var string */
    public $prev_hash;
    /** @var string */
    public $row_hash;
    /** @var string */
    public $created_at;

    public static $definition = array(
        'table' => 'omega_recesso_request',
        'primary' => 'id_recesso',
        'multilang' => false,
        'fields' => array(
            'id_shop' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true),
            'id_order' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true),
            'id_customer' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
            'order_reference' => array('type' => self::TYPE_STRING, 'validate' => 'isAnything', 'size' => 16, 'required' => true),
            'customer_firstname' => array('type' => self::TYPE_STRING, 'validate' => 'isAnything', 'size' => 255, 'required' => true),
            'customer_lastname' => array('type' => self::TYPE_STRING, 'validate' => 'isAnything', 'size' => 255, 'required' => true),
            'customer_email' => array('type' => self::TYPE_STRING, 'validate' => 'isEmail', 'size' => 255, 'required' => true),
            'customer_contact' => array('type' => self::TYPE_STRING, 'validate' => 'isAnything', 'size' => 255, 'required' => true),
            'contract_snapshot' => array('type' => self::TYPE_STRING, 'validate' => 'isAnything', 'required' => true),
            'client_ip' => array('type' => self::TYPE_STRING, 'validate' => 'isAnything', 'size' => 45, 'required' => true),
            'user_agent' => array('type' => self::TYPE_STRING, 'validate' => 'isAnything', 'size' => 512),
            'confirmed_at' => array('type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true),
            'receipt_pdf_path' => array('type' => self::TYPE_STRING, 'validate' => 'isAnything', 'size' => 255),
            'receipt_sent_at' => array('type' => self::TYPE_DATE, 'validate' => 'isDate', 'allow_null' => true),
            'id_order_return' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
            'prev_hash' => array('type' => self::TYPE_STRING, 'validate' => 'isAnything', 'size' => 64),
            'row_hash' => array('type' => self::TYPE_STRING, 'validate' => 'isAnything', 'size' => 64, 'required' => true),
            'created_at' => array('type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true),
        ),
    );

    /* =========================================================================
     * PROTEZIONE APPEND-ONLY
     * ===================================================================== */

    public function update($null_values = false)
    {
        throw new PrestaShopException('Append-only: modifica del record probatorio di recesso non consentita.');
    }

    public function delete()
    {
        throw new PrestaShopException('Append-only: cancellazione del record probatorio di recesso non consentita.');
    }

    /* =========================================================================
     * CREAZIONE (padre + righe, transazione atomica, hash chain)
     * ===================================================================== */

    /**
     * Crea il record di recesso a partire dai dati della conferma.
     * Persiste padre + righe in un'unica transazione e calcola prev_hash/row_hash.
     *
     * Chiavi attese in $data:
     *   id_shop, id_order, id_customer, order_reference, customer_firstname,
     *   customer_lastname, customer_email, customer_contact, contract_snapshot
     *   (array o stringa JSON), client_ip, user_agent, receipt_pdf_path,
     *   receipt_sent_at (nullable), id_order_return, lines (array di righe).
     *   Ogni riga: id_order_detail, id_customization, product_name,
     *   product_reference, product_quantity.
     *
     * @throws PrestaShopException in caso di fallimento (con rollback)
     * @return OmegaRecessoRequest
     */
    public static function createFromConfirmation(array $data)
    {
        $db = Db::getInstance();

        $idShop = (int) $data['id_shop'];
        $confirmedAt = isset($data['confirmed_at']) && $data['confirmed_at']
            ? $data['confirmed_at']
            : date('Y-m-d H:i:s');

        // Snapshot contratto: JSON canonico deterministico (chiavi ordinate).
        if (is_array($data['contract_snapshot'])) {
            $snapshotJson = self::canonicalJson($data['contract_snapshot']);
        } else {
            $snapshotJson = (string) $data['contract_snapshot'];
        }

        // Righe normalizzate e ordinate per id_order_detail.
        $lines = isset($data['lines']) && is_array($data['lines']) ? $data['lines'] : array();
        usort($lines, function ($a, $b) {
            return (int) $a['id_order_detail'] - (int) $b['id_order_detail'];
        });
        $linesCanonical = self::buildLinesCanonical($lines);

        $obj = new self();
        $obj->id_shop = $idShop;
        $obj->id_order = (int) $data['id_order'];
        $obj->id_customer = isset($data['id_customer']) ? (int) $data['id_customer'] : 0;
        $obj->order_reference = (string) $data['order_reference'];
        $obj->customer_firstname = (string) $data['customer_firstname'];
        $obj->customer_lastname = (string) $data['customer_lastname'];
        $obj->customer_email = (string) $data['customer_email'];
        $obj->customer_contact = (string) $data['customer_contact'];
        $obj->contract_snapshot = $snapshotJson;
        $obj->client_ip = (string) $data['client_ip'];
        $obj->user_agent = isset($data['user_agent']) ? Tools::substr((string) $data['user_agent'], 0, 512) : '';
        $obj->confirmed_at = $confirmedAt;
        $obj->receipt_pdf_path = isset($data['receipt_pdf_path']) ? (string) $data['receipt_pdf_path'] : '';
        $obj->receipt_sent_at = isset($data['receipt_sent_at']) && $data['receipt_sent_at'] ? $data['receipt_sent_at'] : null;
        $obj->id_order_return = isset($data['id_order_return']) ? (int) $data['id_order_return'] : 0;
        $obj->created_at = date('Y-m-d H:i:s');

        $db->execute('START TRANSACTION');

        try {
            // prev_hash = row_hash dell'ultimo record dello stesso shop (genesi = '').
            $obj->prev_hash = self::getLastRowHash($idShop);

            // Catena hash (§3.1): stringa canonica con separatore '|'.
            $obj->row_hash = self::computeRowHash(array(
                'id_shop' => $obj->id_shop,
                'id_order' => $obj->id_order,
                'order_reference' => $obj->order_reference,
                'customer_email' => $obj->customer_email,
                'customer_contact' => $obj->customer_contact,
                'confirmed_at' => $obj->confirmed_at,
                'contract_snapshot' => $obj->contract_snapshot,
                'lines_canonical' => $linesCanonical,
                'client_ip' => $obj->client_ip,
                'prev_hash' => $obj->prev_hash,
            ));

            // Insert padre (ObjectModel::add — NON usa update()).
            if (!$obj->add()) {
                throw new PrestaShopException('Inserimento record padre fallito.');
            }

            // Insert righe figlie nella stessa transazione.
            foreach ($lines as $line) {
                $ok = $db->insert('omega_recesso_request_line', array(
                    'id_recesso' => (int) $obj->id,
                    'id_order_detail' => (int) $line['id_order_detail'],
                    'id_customization' => isset($line['id_customization']) ? (int) $line['id_customization'] : 0,
                    'product_name' => pSQL(Tools::substr((string) $line['product_name'], 0, 255)),
                    'product_reference' => pSQL(Tools::substr(isset($line['product_reference']) ? (string) $line['product_reference'] : '', 0, 64)),
                    'product_quantity' => (int) $line['product_quantity'],
                ));
                if (!$ok) {
                    throw new PrestaShopException('Inserimento riga recesso fallito.');
                }
            }

            $db->execute('COMMIT');
        } catch (Exception $e) {
            $db->execute('ROLLBACK');
            throw new PrestaShopException('Creazione recesso fallita: ' . $e->getMessage());
        }

        return $obj;
    }

    /* =========================================================================
     * HASHING
     * ===================================================================== */

    /**
     * Serializzazione deterministica delle righe: "id_order_detail:qty" unite da ';'.
     * Le righe devono essere gia' ordinate per id_order_detail.
     */
    public static function buildLinesCanonical(array $lines)
    {
        $parts = array();
        foreach ($lines as $line) {
            $parts[] = (int) $line['id_order_detail'] . ':' . (int) $line['product_quantity'];
        }

        return implode(';', $parts);
    }

    /**
     * Calcola il row_hash dalla stringa canonica (§3.1).
     */
    public static function computeRowHash(array $f)
    {
        $canonical = implode('|', array(
            $f['id_shop'],
            $f['id_order'],
            $f['order_reference'],
            $f['customer_email'],
            $f['customer_contact'],
            $f['confirmed_at'],
            $f['contract_snapshot'],
            $f['lines_canonical'],
            $f['client_ip'],
            $f['prev_hash'],
        ));

        return hash('sha256', $canonical);
    }

    /**
     * JSON canonico con chiavi ordinate ricorsivamente (deterministico).
     */
    public static function canonicalJson($data)
    {
        $data = self::ksortRecursive($data);

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected static function ksortRecursive($data)
    {
        if (is_array($data)) {
            ksort($data);
            foreach ($data as $key => $value) {
                $data[$key] = self::ksortRecursive($value);
            }
        }

        return $data;
    }

    /**
     * row_hash dell'ultimo record dello shop, o '' (genesi).
     */
    public static function getLastRowHash($idShop)
    {
        $value = Db::getInstance()->getValue(
            'SELECT row_hash FROM ' . _DB_PREFIX_ . 'omega_recesso_request
             WHERE id_shop = ' . (int) $idShop . '
             ORDER BY id_recesso DESC LIMIT 1'
        );

        return $value ? $value : '';
    }

    /* =========================================================================
     * QUANTITA' / RESIDUI
     * ===================================================================== */

    /**
     * Per ogni id_order_detail dell'ordine: ordered / already_requested / returnable.
     * already_requested = somma delle nostre righe (fonte probatoria, non RMA).
     */
    public static function getReturnableQuantities($idOrder)
    {
        $details = Db::getInstance()->executeS(
            'SELECT od.id_order_detail, od.product_name, od.product_reference, od.product_quantity
             FROM ' . _DB_PREFIX_ . 'order_detail od
             WHERE od.id_order = ' . (int) $idOrder
        );

        $requested = self::getRequestedQuantitiesByDetail($idOrder);

        $out = array();
        if (is_array($details)) {
            foreach ($details as $d) {
                $idDetail = (int) $d['id_order_detail'];
                $ordered = (int) $d['product_quantity'];
                $already = isset($requested[$idDetail]) ? (int) $requested[$idDetail] : 0;
                $returnable = $ordered - $already;
                if ($returnable < 0) {
                    $returnable = 0;
                }
                $out[$idDetail] = array(
                    'id_order_detail' => $idDetail,
                    'product_name' => $d['product_name'],
                    'product_reference' => $d['product_reference'],
                    'ordered' => $ordered,
                    'already_requested' => $already,
                    'returnable' => $returnable,
                );
            }
        }

        return $out;
    }

    /**
     * Mappa id_order_detail => somma quantita' gia' richieste (nostre righe).
     */
    public static function getRequestedQuantitiesByDetail($idOrder)
    {
        $rows = Db::getInstance()->executeS(
            'SELECT rl.id_order_detail, SUM(rl.product_quantity) AS qty
             FROM ' . _DB_PREFIX_ . 'omega_recesso_request_line rl
             INNER JOIN ' . _DB_PREFIX_ . 'omega_recesso_request r ON r.id_recesso = rl.id_recesso
             WHERE r.id_order = ' . (int) $idOrder . '
             GROUP BY rl.id_order_detail'
        );

        $map = array();
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $map[(int) $row['id_order_detail']] = (int) $row['qty'];
            }
        }

        return $map;
    }

    /**
     * True se almeno una riga dell'ordine ha quantita' residua recedibile.
     */
    public static function hasReturnableQuantity($idOrder)
    {
        foreach (self::getReturnableQuantities($idOrder) as $row) {
            if ((int) $row['returnable'] > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ultima data/ora di conferma per un ordine (per lo stato "gia' richiesto").
     */
    public static function getLastConfirmedAt($idOrder)
    {
        return Db::getInstance()->getValue(
            'SELECT confirmed_at FROM ' . _DB_PREFIX_ . 'omega_recesso_request
             WHERE id_order = ' . (int) $idOrder . '
             ORDER BY id_recesso DESC LIMIT 1'
        );
    }

    /* =========================================================================
     * LETTURA RIGHE / RECORD (admin)
     * ===================================================================== */

    public static function getLines($idRecesso)
    {
        $rows = Db::getInstance()->executeS(
            'SELECT * FROM ' . _DB_PREFIX_ . 'omega_recesso_request_line
             WHERE id_recesso = ' . (int) $idRecesso . '
             ORDER BY id_order_detail ASC'
        );

        return is_array($rows) ? $rows : array();
    }

    /* =========================================================================
     * VERIFICA INTEGRITA' CATENA
     * ===================================================================== */

    /**
     * Ricostruisce l'intera catena per shop e verifica row_hash + link prev_hash.
     *
     * @return array ['ok' => bool, 'first_broken_id' => int|null, 'checked' => int]
     */
    public static function verifyChain($idShop)
    {
        $rows = Db::getInstance()->executeS(
            'SELECT * FROM ' . _DB_PREFIX_ . 'omega_recesso_request
             WHERE id_shop = ' . (int) $idShop . '
             ORDER BY id_recesso ASC'
        );

        $prevHash = '';
        $checked = 0;

        if (is_array($rows)) {
            foreach ($rows as $row) {
                $checked++;

                // 1) Link di catena: prev_hash deve combaciare col row_hash precedente.
                if ((string) $row['prev_hash'] !== (string) $prevHash) {
                    return array('ok' => false, 'first_broken_id' => (int) $row['id_recesso'], 'checked' => $checked);
                }

                // 2) Ricalcolo del row_hash con i dati memorizzati (incluse le righe).
                $lines = self::getLines((int) $row['id_recesso']);
                usort($lines, function ($a, $b) {
                    return (int) $a['id_order_detail'] - (int) $b['id_order_detail'];
                });
                $linesCanonical = self::buildLinesCanonical($lines);

                $recomputed = self::computeRowHash(array(
                    'id_shop' => $row['id_shop'],
                    'id_order' => $row['id_order'],
                    'order_reference' => $row['order_reference'],
                    'customer_email' => $row['customer_email'],
                    'customer_contact' => $row['customer_contact'],
                    'confirmed_at' => $row['confirmed_at'],
                    'contract_snapshot' => $row['contract_snapshot'],
                    'lines_canonical' => $linesCanonical,
                    'client_ip' => $row['client_ip'],
                    'prev_hash' => $row['prev_hash'],
                ));

                if (!hash_equals((string) $row['row_hash'], $recomputed)) {
                    return array('ok' => false, 'first_broken_id' => (int) $row['id_recesso'], 'checked' => $checked);
                }

                $prevHash = $row['row_hash'];
            }
        }

        return array('ok' => true, 'first_broken_id' => null, 'checked' => $checked);
    }
}
