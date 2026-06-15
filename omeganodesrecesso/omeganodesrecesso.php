<?php
/**
 * OmegaNodes — Diritto di Recesso (54-bis)
 *
 * Modulo PrestaShop per la conformita' al nuovo art. 54-bis del Codice del
 * consumo (Dlgs 209/2025, dir. UE 2023/2673) in vigore dal 19 giugno 2026.
 *
 * @author    OmegaNodes Company Ltd <https://omeganodes.ai>
 * @copyright 2026 OmegaNodes Company Ltd
 * @license   https://opensource.org/licenses/AFL-3.0  Academic Free License 3.0 (AFL-3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/classes/OmegaRecessoRequest.php';

class Omeganodesrecesso extends Module
{
    /** @var string[] hook registrati all'install */
    protected $moduleHooks = array(
        'displayCustomerAccount',
        'displayOrderDetail',
        'actionFrontControllerSetMedia',
        'displayFooter',
    );

    public function __construct()
    {
        $this->name = 'omeganodesrecesso';
        $this->tab = 'administration';
        $this->version = '1.0.1';
        $this->author = 'OmegaNodes Company Ltd';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.7.8.0', 'max' => '1.7.99.99');
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('OmegaNodes — Diritto di Recesso (54-bis)');
        $this->description = $this->l('Pulsante di recesso conforme all\'art. 54-bis: procedura in due passaggi, ricevuta immediata su supporto durevole (email + PDF) e conservazione probatoria append-only con catena hash.');
        $this->confirmUninstall = $this->l('Sei sicuro di voler disinstallare? I dati probatori NON verranno cancellati salvo opt-in esplicito nelle impostazioni.');

        $this->author_uri = 'https://omeganodes.ai';
    }

    /* =========================================================================
     * INSTALL / UNINSTALL
     * ===================================================================== */

    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        if (!$this->registerHook($this->moduleHooks)) {
            return false;
        }

        if (!$this->installSql()) {
            return false;
        }

        if (!$this->installTab()) {
            return false;
        }

        $this->installDefaultConfig();

        // La storage non deve bloccare l'install: se non scrivibile, warning in config.
        $this->createStorage();

        return true;
    }

    public function uninstall()
    {
        if (!$this->uninstallTab()) {
            return false;
        }

        $dropData = (bool) Configuration::get('OMEGA_REC_DROP_ON_UNINSTALL');

        $this->uninstallConfig();

        if ($dropData) {
            $this->uninstallSql();
        }

        return parent::uninstall();
    }

    protected function installSql()
    {
        return (bool) (include dirname(__FILE__) . '/sql/install.php');
    }

    protected function uninstallSql()
    {
        return (bool) (include dirname(__FILE__) . '/sql/uninstall.php');
    }

    protected function installTab()
    {
        $tab = new Tab();
        $tab->class_name = 'AdminOmegaRecesso';
        $tab->module = $this->name;
        $tab->id_parent = (int) Tab::getIdFromClassName('AdminParentOrders');
        if (!$tab->id_parent) {
            $tab->id_parent = (int) Tab::getIdFromClassName('SELL');
        }
        $tab->icon = 'gavel';
        $tab->name = array();
        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[$lang['id_lang']] = 'Diritto di Recesso (54-bis)';
        }

        return (bool) $tab->add();
    }

    protected function uninstallTab()
    {
        $idTab = (int) Tab::getIdFromClassName('AdminOmegaRecesso');
        if ($idTab) {
            $tab = new Tab($idTab);

            return (bool) $tab->delete();
        }

        return true;
    }

    protected function installDefaultConfig()
    {
        $defaults = array(
            'OMEGA_REC_PERIODO_GIORNI' => 14,
            'OMEGA_REC_GRACE_GIORNI' => 0,
            'OMEGA_REC_DATA_PARTENZA' => 'delivery',
            'OMEGA_REC_MOSTRA_OLTRE' => 1,
            'OMEGA_REC_MAIL_FROM_NAME' => Configuration::get('PS_SHOP_NAME'),
            'OMEGA_REC_SHOW_FOOTER_LINK' => 0,
            'OMEGA_REC_POWERED_BY' => 1,
            'OMEGA_REC_STORAGE_PATH' => $this->getDefaultStoragePath(),
            'OMEGA_REC_SUPPORT_NOTE' => 'Hai bisogno di installazione o supporto? Contatta OmegaNodes — https://omeganodes.ai',
            'OMEGA_REC_DROP_ON_UNINSTALL' => 0,
        );

        foreach ($defaults as $key => $value) {
            // Non sovrascrivere valori gia' presenti (reinstall soft).
            if (Configuration::get($key) === false) {
                Configuration::updateValue($key, $value);
            }
        }
    }

    protected function uninstallConfig()
    {
        $keys = array(
            'OMEGA_REC_PERIODO_GIORNI',
            'OMEGA_REC_GRACE_GIORNI',
            'OMEGA_REC_DATA_PARTENZA',
            'OMEGA_REC_MOSTRA_OLTRE',
            'OMEGA_REC_MAIL_FROM_NAME',
            'OMEGA_REC_SHOW_FOOTER_LINK',
            'OMEGA_REC_POWERED_BY',
            'OMEGA_REC_STORAGE_PATH',
            'OMEGA_REC_SUPPORT_NOTE',
            'OMEGA_REC_DROP_ON_UNINSTALL',
        );
        foreach ($keys as $key) {
            Configuration::deleteByName($key);
        }
    }

    /* =========================================================================
     * STORAGE RICEVUTE PDF
     * ===================================================================== */

    public function getDefaultStoragePath()
    {
        return rtrim(_PS_MODULE_DIR_, '/') . '/' . $this->name . '/storage/receipts';
    }

    /**
     * Ritorna il path base di storage configurato (assoluto). Se vuoto -> default.
     */
    public function getStorageBasePath()
    {
        $path = Configuration::get('OMEGA_REC_STORAGE_PATH');
        if (!$path) {
            $path = $this->getDefaultStoragePath();
        }

        return rtrim($path, '/');
    }

    /**
     * Crea la cartella di storage e la protegge (.htaccess + web.config + index.php).
     * Non blocca mai l'install: ritorna true/false sulla scrivibilita'.
     */
    public function createStorage($base = null)
    {
        if ($base === null) {
            $base = $this->getStorageBasePath();
        }

        if (!is_dir($base)) {
            @mkdir($base, 0755, true);
        }

        if (!is_dir($base)) {
            return false;
        }

        $this->protectDirectory($base);

        return is_writable($base);
    }

    /**
     * Difesa in profondita': nega l'accesso diretto via Apache/IIS e guard index.
     */
    public function protectDirectory($dir)
    {
        $htaccess = $dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents(
                $htaccess,
                "# OmegaNodes Recesso — ricevute probatorie: nessun accesso diretto\n"
                . "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
                . "<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\n"
            );
        }

        $webconfig = $dir . '/web.config';
        if (!file_exists($webconfig)) {
            @file_put_contents(
                $webconfig,
                "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration>\n  <system.webServer>\n"
                . "    <authorization>\n      <deny users=\"*\" />\n    </authorization>\n"
                . "  </system.webServer>\n</configuration>\n"
            );
        }

        $index = $dir . '/index.php';
        if (!file_exists($index)) {
            @copy(dirname(__FILE__) . '/index.php', $index);
        }
    }

    /**
     * Path effettivo dove salvare la ricevuta per uno shop, con fallback su var/.
     * Ritorna array('dir' => string, 'fallback' => bool).
     */
    public function resolveReceiptDir($idShop)
    {
        $base = $this->getStorageBasePath() . '/' . (int) $idShop;

        if ($this->createStorage($base) && is_writable($base)) {
            return array('dir' => $base, 'fallback' => false);
        }

        // Fallback su var/ di PrestaShop (sempre scrivibile): mai perdere la ricevuta.
        $fallback = rtrim(_PS_ROOT_DIR_, '/') . '/var/omeganodesrecesso/receipts/' . (int) $idShop;
        $this->createStorage($fallback);

        return array('dir' => $fallback, 'fallback' => true);
    }

    /* =========================================================================
     * GATING / IDONEITA' ORDINI
     * ===================================================================== */

    /**
     * Periodo di recesso effettivo (minimo 14, mai inferiore per legge).
     */
    public function getPeriodoGiorni()
    {
        $periodo = (int) Configuration::get('OMEGA_REC_PERIODO_GIORNI');
        if ($periodo < 14) {
            $periodo = 14;
        }

        return $periodo;
    }

    /**
     * Data di partenza del conteggio secondo configurazione.
     */
    public function getOrderStartDate(Order $order)
    {
        $mode = Configuration::get('OMEGA_REC_DATA_PARTENZA');
        if (!$mode) {
            $mode = 'delivery';
        }

        if ($mode === 'validation') {
            return $order->date_add;
        }

        if ($mode === 'shipped') {
            $date = $this->getHistoryDateByFlag((int) $order->id, 'shipped');

            return $date ? $date : $order->date_add;
        }

        // delivery (default): consegna -> fallback spedizione -> validazione (date_add)
        $date = $this->getHistoryDateByFlag((int) $order->id, 'delivery');
        if ($date) {
            return $date;
        }
        $date = $this->getHistoryDateByFlag((int) $order->id, 'shipped');
        if ($date) {
            return $date;
        }

        return $order->date_add;
    }

    /**
     * Ultima data di history per uno stato con il flag indicato (delivery|shipped).
     */
    protected function getHistoryDateByFlag($idOrder, $flag)
    {
        $allowed = array('delivery', 'shipped', 'paid');
        if (!in_array($flag, $allowed, true)) {
            return null;
        }

        $sql = 'SELECT oh.date_add
                FROM ' . _DB_PREFIX_ . 'order_history oh
                INNER JOIN ' . _DB_PREFIX_ . 'order_state os ON os.id_order_state = oh.id_order_state
                WHERE oh.id_order = ' . (int) $idOrder . ' AND os.`' . pSQL($flag) . '` = 1
                ORDER BY oh.date_add DESC, oh.id_order_history DESC';

        $value = Db::getInstance()->getValue($sql);

        return $value ? $value : null;
    }

    /**
     * Stato completo del recesso per un ordine (idoneita', deadline, visibilita').
     * Usato sia dagli hook sia dal front controller.
     */
    public function getOrderRecessoStatus(Order $order)
    {
        $periodo = $this->getPeriodoGiorni();
        $grace = (int) Configuration::get('OMEGA_REC_GRACE_GIORNI');
        $showOver = (bool) Configuration::get('OMEGA_REC_MOSTRA_OLTRE');

        $status = array(
            'periodo' => $periodo,
            'eligible_order' => false,
            'has_returnable' => false,
            'fully_requested' => false,
            'requested_at' => null,
            'over_deadline' => false,
            'deadline' => null,
            'show_button' => false,
            'show_warning' => false,
        );

        // Ordine non valido -> non idoneo.
        if (!$order->valid) {
            return $status;
        }

        // Ordine completamente rimborsato -> non idoneo.
        $refundState = (int) Configuration::get('PS_OS_REFUND');
        if ($refundState && (int) $order->getCurrentState() === $refundState) {
            return $status;
        }

        $status['eligible_order'] = true;
        $status['has_returnable'] = OmegaRecessoRequest::hasReturnableQuantity((int) $order->id);

        if (!$status['has_returnable']) {
            $status['fully_requested'] = true;
            $status['requested_at'] = OmegaRecessoRequest::getLastConfirmedAt((int) $order->id);
        }

        $start = $this->getOrderStartDate($order);
        if ($start) {
            $deadlineTs = strtotime($start) + (($periodo + $grace) * 86400);
            $status['deadline'] = date('Y-m-d H:i:s', $deadlineTs);
            $status['over_deadline'] = (time() > $deadlineTs);
        }

        if ($status['fully_requested']) {
            $status['show_button'] = false;
        } elseif (!$status['over_deadline']) {
            $status['show_button'] = true;
        } elseif ($showOver) {
            $status['show_button'] = true;
            $status['show_warning'] = true;
        } else {
            $status['show_button'] = false;
        }

        return $status;
    }

    /**
     * Ordini idonei al recesso per un cliente (dropdown step 1).
     *
     * @return array di array ['id_order','reference','date','status']
     */
    public function getEligibleOrdersForCustomer($idCustomer, $idShop)
    {
        $rows = Db::getInstance()->executeS(
            'SELECT o.id_order, o.reference, o.date_add
             FROM ' . _DB_PREFIX_ . 'orders o
             WHERE o.id_customer = ' . (int) $idCustomer . '
               AND o.id_shop = ' . (int) $idShop . '
               AND o.valid = 1
             ORDER BY o.date_add DESC'
        );

        $eligible = array();
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $order = new Order((int) $row['id_order']);
                if (!Validate::isLoadedObject($order)) {
                    continue;
                }
                $st = $this->getOrderRecessoStatus($order);
                if ($st['eligible_order'] && $st['has_returnable']) {
                    $eligible[] = array(
                        'id_order' => (int) $row['id_order'],
                        'reference' => $row['reference'],
                        'date' => $row['date_add'],
                        'status' => $st,
                    );
                }
            }
        }

        return $eligible;
    }

    /* =========================================================================
     * HOOKS FRONT
     * ===================================================================== */

    public function hookActionFrontControllerSetMedia($params)
    {
        $this->context->controller->registerStylesheet(
            'omeganodesrecesso-front',
            'modules/' . $this->name . '/views/css/front.css',
            array('media' => 'all', 'priority' => 150)
        );
        $this->context->controller->registerJavascript(
            'omeganodesrecesso-front',
            'modules/' . $this->name . '/views/js/front.js',
            array('position' => 'bottom', 'priority' => 150)
        );
    }

    public function hookDisplayCustomerAccount($params)
    {
        $this->context->smarty->assign(array(
            'omega_recesso_link' => $this->context->link->getModuleLink($this->name, 'recesso', array(), true),
            'omega_powered_by' => (bool) Configuration::get('OMEGA_REC_POWERED_BY'),
        ));

        return $this->fetch('module:' . $this->name . '/views/templates/front/account-link.tpl');
    }

    public function hookDisplayOrderDetail($params)
    {
        if (empty($params['order']) || !Validate::isLoadedObject($params['order'])) {
            return '';
        }

        /** @var Order $order */
        $order = $params['order'];
        $status = $this->getOrderRecessoStatus($order);

        if (!$status['eligible_order']) {
            return '';
        }

        $this->context->smarty->assign(array(
            'omega_status' => $status,
            'omega_order_id' => (int) $order->id,
            'omega_recesso_link' => $this->context->link->getModuleLink(
                $this->name,
                'recesso',
                array('id_order' => (int) $order->id),
                true
            ),
            'omega_periodo' => $status['periodo'],
        ));

        return $this->fetch('module:' . $this->name . '/views/templates/front/order-button.tpl');
    }

    public function hookDisplayFooter($params)
    {
        if (!Configuration::get('OMEGA_REC_SHOW_FOOTER_LINK')) {
            return '';
        }

        $this->context->smarty->assign(array(
            'omega_recesso_link' => $this->context->link->getModuleLink($this->name, 'recesso', array(), true),
        ));

        return '<div class="omega-recesso-footer-link"><a href="'
            . Tools::safeOutput($this->context->link->getModuleLink($this->name, 'recesso', array(), true))
            . '">' . $this->l('Diritto di recesso') . '</a></div>';
    }

    /* =========================================================================
     * CONFIGURAZIONE (getContent)
     * ===================================================================== */

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitOmegaRecesso')) {
            $output .= $this->postProcessConfig();
        }

        $output .= $this->renderStorageWarning();
        $output .= $this->renderSupportNote();

        return $output . $this->renderConfigForm();
    }

    protected function postProcessConfig()
    {
        $periodo = (int) Tools::getValue('OMEGA_REC_PERIODO_GIORNI');
        if ($periodo < 14) {
            return $this->displayError(
                $this->l('Il periodo di recesso non puo\' essere inferiore a 14 giorni (vincolo di legge). Valore rifiutato.')
            );
        }

        $partenza = Tools::getValue('OMEGA_REC_DATA_PARTENZA');
        if (!in_array($partenza, array('delivery', 'shipped', 'validation'), true)) {
            $partenza = 'delivery';
        }

        $storagePath = trim((string) Tools::getValue('OMEGA_REC_STORAGE_PATH'));
        if ($storagePath === '') {
            $storagePath = $this->getDefaultStoragePath();
        }

        $values = array(
            'OMEGA_REC_PERIODO_GIORNI' => $periodo,
            'OMEGA_REC_GRACE_GIORNI' => max(0, (int) Tools::getValue('OMEGA_REC_GRACE_GIORNI')),
            'OMEGA_REC_DATA_PARTENZA' => $partenza,
            'OMEGA_REC_MOSTRA_OLTRE' => (int) (bool) Tools::getValue('OMEGA_REC_MOSTRA_OLTRE'),
            'OMEGA_REC_MAIL_FROM_NAME' => pSQL(Tools::getValue('OMEGA_REC_MAIL_FROM_NAME')),
            'OMEGA_REC_SHOW_FOOTER_LINK' => (int) (bool) Tools::getValue('OMEGA_REC_SHOW_FOOTER_LINK'),
            'OMEGA_REC_POWERED_BY' => (int) (bool) Tools::getValue('OMEGA_REC_POWERED_BY'),
            'OMEGA_REC_STORAGE_PATH' => $storagePath,
            'OMEGA_REC_SUPPORT_NOTE' => pSQL(Tools::getValue('OMEGA_REC_SUPPORT_NOTE')),
            'OMEGA_REC_DROP_ON_UNINSTALL' => (int) (bool) Tools::getValue('OMEGA_REC_DROP_ON_UNINSTALL'),
        );

        // Salvataggio per shop in multistore.
        $idShop = (int) $this->context->shop->id;
        $idShopGroup = (int) $this->context->shop->id_shop_group;

        foreach ($values as $key => $value) {
            if (Shop::isFeatureActive()) {
                Configuration::updateValue($key, $value, false, $idShopGroup, $idShop);
            } else {
                Configuration::updateValue($key, $value);
            }
        }

        // Ricrea/protegge lo storage al nuovo path.
        $this->createStorage($storagePath);

        return $this->displayConfirmation($this->l('Impostazioni salvate.'));
    }

    protected function renderStorageWarning()
    {
        $base = $this->getStorageBasePath();
        if ($this->createStorage($base) && is_writable($base)) {
            return '';
        }

        return $this->displayWarning(
            $this->l('La cartella di archiviazione ricevute non e\' scrivibile:') . ' ' . $base . '. '
            . $this->l('Le ricevute verranno salvate automaticamente nel fallback var/ di PrestaShop. Configura un percorso scrivibile per evitare il fallback.')
        );
    }

    protected function renderSupportNote()
    {
        $note = Configuration::get('OMEGA_REC_SUPPORT_NOTE');
        if (!$note) {
            return '';
        }

        return '<div class="alert alert-info">' . Tools::safeOutput($note) . '</div>';
    }

    protected function renderConfigForm()
    {
        $fields = array(
            array(
                'type' => 'text',
                'label' => $this->l('Giorni di recesso (minimo 14)'),
                'name' => 'OMEGA_REC_PERIODO_GIORNI',
                'desc' => $this->l('Minimo 14 per legge; estendere oltre e\' sempre lecito (es. 30, 100, 180) ed e\' un claim di marketing valido. Valori sotto 14 vengono rifiutati.'),
                'class' => 'fixed-width-sm',
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Giorni di grace (buffer extra)'),
                'name' => 'OMEGA_REC_GRACE_GIORNI',
                'desc' => $this->l('Buffer aggiuntivo prima dell\'avviso/nascondimento del bottone.'),
                'class' => 'fixed-width-sm',
            ),
            array(
                'type' => 'select',
                'label' => $this->l('Data di partenza del conteggio'),
                'name' => 'OMEGA_REC_DATA_PARTENZA',
                'options' => array(
                    'query' => array(
                        array('id' => 'delivery', 'name' => $this->l('Consegna (default, con fallback spedizione/validazione)')),
                        array('id' => 'shipped', 'name' => $this->l('Spedizione')),
                        array('id' => 'validation', 'name' => $this->l('Validazione ordine')),
                    ),
                    'id' => 'id',
                    'name' => 'name',
                ),
            ),
            array(
                'type' => 'switch',
                'label' => $this->l('Mostra bottone oltre il termine (con avviso)'),
                'name' => 'OMEGA_REC_MOSTRA_OLTRE',
                'desc' => $this->l('Anti dark pattern: il bottone resta visibile oltre il termine con un avviso. Disattivare solo se espressamente richiesto.'),
                'is_bool' => true,
                'values' => $this->switchValues(),
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Nome mittente ricevuta'),
                'name' => 'OMEGA_REC_MAIL_FROM_NAME',
            ),
            array(
                'type' => 'switch',
                'label' => $this->l('Link recesso nel footer'),
                'name' => 'OMEGA_REC_SHOW_FOOTER_LINK',
                'is_bool' => true,
                'values' => $this->switchValues(),
            ),
            array(
                'type' => 'switch',
                'label' => $this->l('Mostra "Powered by OmegaNodes"'),
                'name' => 'OMEGA_REC_POWERED_BY',
                'is_bool' => true,
                'values' => $this->switchValues(),
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Percorso archiviazione ricevute PDF'),
                'name' => 'OMEGA_REC_STORAGE_PATH',
                'desc' => $this->l('Percorso assoluto. Override consigliato per hosting che svuotano la cartella modulo a ogni deploy. Fallback automatico su var/ se non scrivibile.'),
            ),
            array(
                'type' => 'textarea',
                'label' => $this->l('Nota supporto (solo admin)'),
                'name' => 'OMEGA_REC_SUPPORT_NOTE',
            ),
            array(
                'type' => 'switch',
                'label' => $this->l('Droppa le tabelle probatorie alla disinstallazione'),
                'name' => 'OMEGA_REC_DROP_ON_UNINSTALL',
                'desc' => $this->l('ATTENZIONE: se attivo, la disinstallazione cancella TUTTI i dati probatori. Puo\' avere implicazioni legali. Default disattivo.'),
                'is_bool' => true,
                'values' => $this->switchValues(),
            ),
        );

        $form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Impostazioni Diritto di Recesso (54-bis)'),
                    'icon' => 'icon-cogs',
                ),
                'input' => $fields,
                'submit' => array(
                    'title' => $this->l('Salva'),
                ),
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = (int) $this->context->language->id;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitOmegaRecesso';
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($form));
    }

    protected function switchValues()
    {
        return array(
            array('id' => 'active_on', 'value' => 1, 'label' => $this->l('Si')),
            array('id' => 'active_off', 'value' => 0, 'label' => $this->l('No')),
        );
    }

    protected function getConfigFormValues()
    {
        return array(
            'OMEGA_REC_PERIODO_GIORNI' => Tools::getValue('OMEGA_REC_PERIODO_GIORNI', $this->getPeriodoGiorni()),
            'OMEGA_REC_GRACE_GIORNI' => Tools::getValue('OMEGA_REC_GRACE_GIORNI', (int) Configuration::get('OMEGA_REC_GRACE_GIORNI')),
            'OMEGA_REC_DATA_PARTENZA' => Tools::getValue('OMEGA_REC_DATA_PARTENZA', Configuration::get('OMEGA_REC_DATA_PARTENZA')),
            'OMEGA_REC_MOSTRA_OLTRE' => Tools::getValue('OMEGA_REC_MOSTRA_OLTRE', (int) Configuration::get('OMEGA_REC_MOSTRA_OLTRE')),
            'OMEGA_REC_MAIL_FROM_NAME' => Tools::getValue('OMEGA_REC_MAIL_FROM_NAME', Configuration::get('OMEGA_REC_MAIL_FROM_NAME')),
            'OMEGA_REC_SHOW_FOOTER_LINK' => Tools::getValue('OMEGA_REC_SHOW_FOOTER_LINK', (int) Configuration::get('OMEGA_REC_SHOW_FOOTER_LINK')),
            'OMEGA_REC_POWERED_BY' => Tools::getValue('OMEGA_REC_POWERED_BY', (int) Configuration::get('OMEGA_REC_POWERED_BY')),
            'OMEGA_REC_STORAGE_PATH' => Tools::getValue('OMEGA_REC_STORAGE_PATH', $this->getStorageBasePath()),
            'OMEGA_REC_SUPPORT_NOTE' => Tools::getValue('OMEGA_REC_SUPPORT_NOTE', Configuration::get('OMEGA_REC_SUPPORT_NOTE')),
            'OMEGA_REC_DROP_ON_UNINSTALL' => Tools::getValue('OMEGA_REC_DROP_ON_UNINSTALL', (int) Configuration::get('OMEGA_REC_DROP_ON_UNINSTALL')),
        );
    }
}
