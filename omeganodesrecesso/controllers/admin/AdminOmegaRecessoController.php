<?php
/**
 * OmegaNodes — Diritto di Recesso (54-bis)
 *
 * Controller admin READ-ONLY: lista dei record probatori, dettaglio righe,
 * verifica integrita' della catena hash, download ricevuta PDF (con token) ed
 * export CSV. Nessuna azione di modifica/cancellazione (protezione probatoria).
 *
 * @author    OmegaNodes Company Ltd <https://omeganodes.ai>
 * @copyright 2026 OmegaNodes Company Ltd
 * @license   https://opensource.org/licenses/AFL-3.0  Academic Free License 3.0 (AFL-3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'omeganodesrecesso/classes/OmegaRecessoRequest.php';

class AdminOmegaRecessoController extends ModuleAdminController
{
    /** @var bool true durante l'export CSV (le callback restituiscono testo semplice) */
    protected $omegaExporting = false;

    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'omega_recesso_request';
        $this->className = 'OmegaRecessoRequest';
        $this->identifier = 'id_recesso';
        $this->lang = false;
        $this->allow_export = true; // export CSV nativo

        parent::__construct();

        // Nessun bulk delete / inline edit.
        $this->bulk_actions = array();
        $this->list_no_link = false;

        // Colonne computate.
        $this->_select = '
            CONCAT(a.customer_firstname, \' \', a.customer_lastname) AS customer_fullname,
            (SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'omega_recesso_request_line l WHERE l.id_recesso = a.id_recesso) AS n_lines,
            (SELECT COALESCE(SUM(l.product_quantity), 0) FROM ' . _DB_PREFIX_ . 'omega_recesso_request_line l WHERE l.id_recesso = a.id_recesso) AS tot_qty';

        // Multistore: limita allo shop corrente se feature attiva.
        if (Shop::isFeatureActive() && Shop::getContext() === Shop::CONTEXT_SHOP) {
            $this->_where = ' AND a.id_shop = ' . (int) $this->context->shop->id;
        }

        $this->fields_list = array(
            'id_recesso' => array(
                'title' => $this->l('ID'),
                'align' => 'text-center',
                'class' => 'fixed-width-xs',
            ),
            'id_order' => array(
                'title' => $this->l('Ordine'),
                'align' => 'text-center',
                'callback' => 'callbackOrderLink',
            ),
            'customer_fullname' => array(
                'title' => $this->l('Cliente'),
                'havingFilter' => true,
            ),
            'customer_email' => array(
                'title' => $this->l('Email'),
            ),
            'confirmed_at' => array(
                'title' => $this->l('Confermato il'),
                'type' => 'datetime',
                'align' => 'text-center',
            ),
            'n_lines' => array(
                'title' => $this->l('Righe'),
                'align' => 'text-center',
                'search' => false,
                'callback' => 'callbackLinesSummary',
            ),
            'receipt_sent_at' => array(
                'title' => $this->l('Ricevuta inviata'),
                'align' => 'text-center',
                'callback' => 'callbackReceiptSent',
                'search' => false,
            ),
            'row_hash' => array(
                'title' => $this->l('Integrita\' riga'),
                'align' => 'text-center',
                'search' => false,
                'orderby' => false,
                'callback' => 'callbackRowIntegrity',
            ),
        );

        $this->_defaultOrderBy = 'id_recesso';
        $this->_defaultOrderWay = 'DESC';
    }

    /**
     * Traduzione compatibile PS 1.7 / PS 9: AdminController::l() non esiste piu'
     * in PrestaShop 9, si delega a Module::l() (presente in entrambe le versioni).
     * Firma identica a quella di AdminController::l() di PS 1.7.
     */
    protected function l($string, $class = null, $addslashes = false, $htmlentities = true)
    {
        return $this->module->l($string, 'AdminOmegaRecessoController');
    }

    /* =========================================================================
     * TOOLBAR — niente "Aggiungi", piu' "Verifica integrita' catena"
     * ===================================================================== */

    public function initPageHeaderToolbar()
    {
        parent::initPageHeaderToolbar();

        unset($this->page_header_toolbar_btn['new']);

        $this->page_header_toolbar_btn['verifychain'] = array(
            'href' => self::$currentIndex . '&action=verifychain&token=' . $this->token,
            'desc' => $this->l('Verifica integrita\' catena'),
            'icon' => 'process-icon-refresh',
        );
    }

    public function initToolbar()
    {
        parent::initToolbar();

        // Anche nella toolbar della lista: nessun "Aggiungi" (record creati solo dal front).
        unset($this->toolbar_btn['new']);
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);

        $this->addCSS(_MODULE_DIR_ . 'omeganodesrecesso/views/css/admin.css');
    }

    /* =========================================================================
     * AZIONI CUSTOM (verifica catena, download ricevuta)
     * ===================================================================== */

    public function postProcess()
    {
        $action = Tools::getValue('action');

        // action=downloadReceipt -> processDownloadReceipt() e' invocato dal core
        // (AdminController::initProcess/postProcess, PS 1.7 e 9): chiamarlo anche
        // qui duplicava gli errori ora che non termina piu' con die().

        if ($action === 'verifychain') {
            $this->runChainVerification(true);
        }

        return parent::postProcess();
    }

    protected function runChainVerification($flash = false)
    {
        $idShop = (int) $this->context->shop->id;
        $result = OmegaRecessoRequest::verifyChain($idShop);

        if ($flash) {
            if ($result['ok']) {
                $this->confirmations[] = sprintf(
                    $this->l('Integrita\' catena OK: %d record verificati.'),
                    (int) $result['checked']
                );
            } else {
                $this->errors[] = sprintf(
                    $this->l('Catena COMPROMESSA: prima discrepanza al record #%d (record verificati: %d).'),
                    (int) $result['first_broken_id'],
                    (int) $result['checked']
                );
            }
        }

        return $result;
    }

    /**
     * Download della ricevuta. Gli errori vanno in $this->errors e la pagina
     * del back office viene mostrata normalmente (lista con l'avviso):
     * Tools::displayError() e' deprecato in PrestaShop 9 (in debug lancia
     * un'eccezione, altrimenti una pagina 500 senza layout).
     */
    protected function processDownloadReceipt()
    {
        $id = (int) Tools::getValue('id_recesso');
        $record = new OmegaRecessoRequest($id);

        if (!Validate::isLoadedObject($record) || !$record->receipt_pdf_path) {
            $this->errors[] = $this->l('Ricevuta non disponibile.');

            return;
        }

        // Multistore: impedisci accesso cross-shop.
        if (Shop::isFeatureActive()
            && Shop::getContext() === Shop::CONTEXT_SHOP
            && (int) $record->id_shop !== (int) $this->context->shop->id) {
            $this->errors[] = $this->l('Accesso non consentito.');

            return;
        }

        $path = $record->receipt_pdf_path;
        if (!file_exists($path) || !is_readable($path)) {
            $this->errors[] = $this->l('File ricevuta non trovato sul server.');

            return;
        }

        $filename = 'ricevuta-recesso-' . $record->order_reference . '.pdf';

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($path));
        header('Pragma: public');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        readfile($path);
        exit;
    }

    /* =========================================================================
     * LISTA — header con banner integrita'/logo/nota
     * ===================================================================== */

    public function renderList()
    {
        // Azione di riga: solo dettaglio (read-only).
        $this->addRowAction('view');

        $header = $this->renderChainBanner();

        return $header . parent::renderList();
    }

    protected function renderChainBanner()
    {
        $result = $this->runChainVerification(false);

        $this->context->smarty->assign(array(
            'omega_chain_ok' => $result['ok'],
            'omega_chain_first_broken' => $result['first_broken_id'],
            'omega_chain_checked' => $result['checked'],
            'omega_support_note' => Configuration::get('OMEGA_REC_SUPPORT_NOTE'),
            'omega_powered_by' => (bool) Configuration::get('OMEGA_REC_POWERED_BY'),
            'omega_logo' => _MODULE_DIR_ . 'omeganodesrecesso/views/img/logo-omeganodes.svg',
        ));

        return $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . 'omeganodesrecesso/views/templates/admin/list_header.tpl'
        );
    }

    /* =========================================================================
     * DETTAGLIO RECORD (read-only)
     * ===================================================================== */

    public function renderView()
    {
        $id = (int) Tools::getValue('id_recesso');
        $record = new OmegaRecessoRequest($id);

        if (!Validate::isLoadedObject($record)) {
            $this->errors[] = $this->l('Record non trovato.');

            return '';
        }

        $lines = OmegaRecessoRequest::getLines($id);
        $snapshot = json_decode($record->contract_snapshot, true);

        $hasReceipt = ($record->receipt_pdf_path && file_exists($record->receipt_pdf_path));

        $this->context->smarty->assign(array(
            'record' => $record,
            'lines' => $lines,
            'snapshot_pretty' => json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'has_receipt' => $hasReceipt,
            'download_link' => self::$currentIndex . '&id_recesso=' . (int) $id . '&action=downloadReceipt&token=' . $this->token,
            'order_link' => $this->getOrderViewLink((int) $record->id_order),
        ));

        return $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . 'omeganodesrecesso/views/templates/admin/view_record.tpl'
        );
    }

    /* =========================================================================
     * CALLBACK COLONNE
     * ===================================================================== */

    public function callbackOrderLink($value, $row)
    {
        $url = $this->getOrderViewLink((int) $value);

        return '<a href="' . Tools::safeOutput($url) . '">' . (int) $value . '</a>';
    }

    /**
     * Link al dettaglio ordine in BO. La pagina ordini e' Symfony (PS 1.7.7+ e 9):
     * i parametri vanno passati a getAdminLink() per ottenere la rotta di dettaglio
     * (accodarli all'URL porterebbe alla lista ordini).
     */
    protected function getOrderViewLink($idOrder)
    {
        return $this->context->link->getAdminLink(
            'AdminOrders',
            true,
            array(),
            array('id_order' => (int) $idOrder, 'vieworder' => 1)
        );
    }

    public function callbackLinesSummary($value, $row)
    {
        $nLines = isset($row['n_lines']) ? (int) $row['n_lines'] : 0;
        $totQty = isset($row['tot_qty']) ? (int) $row['tot_qty'] : 0;

        return sprintf($this->l('%1$d righe / %2$d pz'), $nLines, $totQty);
    }

    public function callbackReceiptSent($value, $row)
    {
        if ($value && $value !== '0000-00-00 00:00:00') {
            return '<span class="badge badge-success">' . $this->l('Si') . '</span>';
        }

        return '<span class="badge badge-danger">' . $this->l('No') . '</span>';
    }

    /**
     * Export CSV: la colonna integrita' riporta l'esito leggibile (OK/KO) e
     * l'hash SHA-256 della riga va in una colonna separata. processExport() del
     * core usa il valore grezzo quando la callback restituisce HTML: in export
     * le callback restituiscono quindi testo semplice.
     */
    public function processExport($text_delimiter = '"')
    {
        $this->omegaExporting = true;
        $this->_select .= ', a.row_hash AS row_hash_export';
        $this->fields_list['row_hash_export'] = array(
            'title' => $this->l('Hash riga (SHA-256)'),
            'search' => false,
            'orderby' => false,
        );

        return parent::processExport($text_delimiter);
    }

    public function callbackRowIntegrity($value, $row)
    {
        $lines = OmegaRecessoRequest::getLines((int) $row['id_recesso']);
        usort($lines, function ($a, $b) {
            return (int) $a['id_order_detail'] - (int) $b['id_order_detail'];
        });
        $linesCanonical = OmegaRecessoRequest::buildLinesCanonical($lines);

        $recomputed = OmegaRecessoRequest::computeRowHash(array(
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

        $ok = hash_equals((string) $row['row_hash'], $recomputed);

        if ($this->omegaExporting) {
            return $ok ? 'OK' : 'KO';
        }

        if ($ok) {
            return '<span class="badge badge-success">' . $this->l('OK') . '</span>';
        }

        return '<span class="badge badge-danger">' . $this->l('ALTERATA') . '</span>';
    }
}
