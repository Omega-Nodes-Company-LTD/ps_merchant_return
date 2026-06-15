<?php
/**
 * OmegaNodes — Diritto di Recesso (54-bis)
 *
 * Front controller della procedura di recesso in DUE PASSAGGI DISTINTI:
 *   Step 1 = identificazione ordine + dati + selezione righe/quantita'.
 *   Step 2 = pagina separata con unico comando "Conferma recesso".
 * Il record probatorio viene creato SOLO alla conferma dello step 2.
 *
 * @author    OmegaNodes Company Ltd <https://omeganodes.ai>
 * @copyright 2026 OmegaNodes Company Ltd
 * @license   https://opensource.org/licenses/AFL-3.0  Academic Free License 3.0 (AFL-3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'omeganodesrecesso/classes/OmegaRecessoRequest.php';
require_once _PS_MODULE_DIR_ . 'omeganodesrecesso/classes/OmegaReceiptGenerator.php';

class OmeganodesrecessoRecessoModuleFrontController extends ModuleFrontController
{
    /** @var bool */
    public $auth = false; // accessibile anche ai guest (identificazione via reference+email)

    /** @var string */
    public $page_name = 'omeganodesrecesso';

    public function initContent()
    {
        parent::initContent();

        $this->context->smarty->assign(array(
            'omega_action_url' => $this->context->link->getModuleLink('omeganodesrecesso', 'recesso', array(), true),
            'omega_token' => $this->getCsrfToken(),
            'omega_powered_by' => (bool) Configuration::get('OMEGA_REC_POWERED_BY'),
            'omega_periodo' => $this->module->getPeriodoGiorni(),
        ));

        if (Tools::isSubmit('submitConfirm')) {
            $this->processConfirm();
        } elseif (Tools::isSubmit('submitStep1')) {
            $this->processStep1();
        } else {
            // Visualizzazione step 1 (default) ed eventuale "carica righe"/"trova ordine".
            $this->displayStep1();
        }
    }

    /* =========================================================================
     * CSRF
     * ===================================================================== */

    protected function getCsrfToken()
    {
        $seed = 'omeganodesrecesso'
            . (int) $this->context->cookie->id_customer
            . (string) $this->context->cookie->id_guest
            . (int) $this->context->shop->id;

        return Tools::substr(Tools::encrypt($seed), 0, 40);
    }

    protected function checkCsrf()
    {
        return (bool) Tools::getValue('omega_token')
            && hash_equals($this->getCsrfToken(), (string) Tools::getValue('omega_token'));
    }

    /* =========================================================================
     * IDENTIFICAZIONE ORDINE (logged + guest)
     * ===================================================================== */

    /**
     * Risolve l'ordine e l'identita' dalla richiesta. Messaggi generici per i
     * guest (nessuna enumeration di reference/email).
     *
     * @return array struttura con ok|order|id_customer|firstname|lastname|email|is_guest|error
     */
    protected function resolveContext()
    {
        $result = array(
            'ok' => false,
            'order' => null,
            'id_customer' => 0,
            'firstname' => '',
            'lastname' => '',
            'email' => '',
            'is_guest' => true,
            'error' => '',
        );

        $idOrder = (int) Tools::getValue('id_order');

        if ($this->context->customer->isLogged()) {
            $result['is_guest'] = false;

            if (!$idOrder) {
                return $result; // nessun ordine ancora selezionato (non e' un errore)
            }

            $order = new Order($idOrder);
            if (!Validate::isLoadedObject($order)
                || (int) $order->id_customer !== (int) $this->context->customer->id
                || (int) $order->id_shop !== (int) $this->context->shop->id) {
                $result['error'] = $this->module->l('Ordine non trovato.', 'recesso');

                return $result;
            }

            $result['order'] = $order;
            $result['id_customer'] = (int) $this->context->customer->id;
            $result['firstname'] = $this->context->customer->firstname;
            $result['lastname'] = $this->context->customer->lastname;
            $result['email'] = $this->context->customer->email;
            $result['ok'] = true;

            return $result;
        }

        // Guest: serve reference + email.
        $reference = trim((string) Tools::getValue('order_reference'));
        $email = trim((string) Tools::getValue('email'));

        if ($reference === '' || $email === '') {
            return $result; // form ancora da compilare
        }

        if (!Validate::isEmail($email)) {
            $result['error'] = $this->module->l('Verifica i dati inseriti e riprova.', 'recesso');

            return $result;
        }

        $idFound = (int) Db::getInstance()->getValue(
            'SELECT o.id_order
             FROM ' . _DB_PREFIX_ . 'orders o
             INNER JOIN ' . _DB_PREFIX_ . 'customer c ON c.id_customer = o.id_customer
             WHERE o.reference = \'' . pSQL($reference) . '\'
               AND LOWER(c.email) = \'' . pSQL(Tools::strtolower($email)) . '\'
               AND o.id_shop = ' . (int) $this->context->shop->id . '
             ORDER BY o.id_order DESC'
        );

        if (!$idFound) {
            // Messaggio generico: non rivelare se la reference esiste.
            $result['error'] = $this->module->l('Non e\' stato possibile trovare un ordine con i dati forniti. Verifica numero ordine ed email.', 'recesso');

            return $result;
        }

        $order = new Order($idFound);
        $customer = new Customer((int) $order->id_customer);

        $result['order'] = $order;
        $result['id_customer'] = (int) $order->id_customer;
        $result['firstname'] = $customer->firstname;
        $result['lastname'] = $customer->lastname;
        $result['email'] = $customer->email;
        $result['ok'] = true;

        return $result;
    }

    /* =========================================================================
     * RIGHE ORDINE PER DISPLAY (residui + prezzo)
     * ===================================================================== */

    /**
     * Righe dell'ordine con residuo recedibile e prezzo unitario formattato.
     */
    protected function getOrderLinesForDisplay(Order $order)
    {
        $returnable = OmegaRecessoRequest::getReturnableQuantities((int) $order->id);

        // Prezzo unitario tax incl per id_order_detail.
        $prices = array();
        $rows = Db::getInstance()->executeS(
            'SELECT id_order_detail, unit_price_tax_incl
             FROM ' . _DB_PREFIX_ . 'order_detail
             WHERE id_order = ' . (int) $order->id
        );
        if (is_array($rows)) {
            foreach ($rows as $r) {
                $prices[(int) $r['id_order_detail']] = (float) $r['unit_price_tax_incl'];
            }
        }

        $currency = new Currency((int) $order->id_currency);

        $lines = array();
        foreach ($returnable as $idDetail => $row) {
            $unit = isset($prices[$idDetail]) ? $prices[$idDetail] : 0.0;
            $lines[] = array(
                'id_order_detail' => (int) $idDetail,
                'product_name' => $row['product_name'],
                'product_reference' => $row['product_reference'],
                'ordered' => (int) $row['ordered'],
                'already_requested' => (int) $row['already_requested'],
                'returnable' => (int) $row['returnable'],
                'unit_price' => $unit,
                'unit_price_formatted' => Tools::displayPrice($unit, $currency),
            );
        }

        return $lines;
    }

    /* =========================================================================
     * STEP 1 — display
     * ===================================================================== */

    protected function displayStep1($errors = array(), $prefill = array())
    {
        $isLogged = $this->context->customer->isLogged();

        $eligibleOrders = array();
        if ($isLogged) {
            $eligibleOrders = $this->module->getEligibleOrdersForCustomer(
                (int) $this->context->customer->id,
                (int) $this->context->shop->id
            );
        }

        $ctx = $this->resolveContext();
        if ($ctx['error'] !== '') {
            $errors[] = $ctx['error'];
        }

        $order = $ctx['order'];
        $lines = array();
        $orderStatus = null;
        $fullyRequested = false;
        $requestedAt = null;
        $selectedOrderId = 0;
        $orderReference = '';

        if ($order && Validate::isLoadedObject($order)) {
            $orderStatus = $this->module->getOrderRecessoStatus($order);
            $selectedOrderId = (int) $order->id;
            $orderReference = $order->reference;

            if (!$orderStatus['eligible_order']) {
                $errors[] = $this->module->l('Questo ordine non e\' idoneo al recesso.', 'recesso');
            } elseif (!$orderStatus['has_returnable']) {
                $fullyRequested = true;
                $requestedAt = $orderStatus['requested_at'];
            } else {
                $lines = $this->getOrderLinesForDisplay($order);
            }
        }

        $defaultContact = $prefill ? (isset($prefill['customer_contact']) ? $prefill['customer_contact'] : '') : '';
        if ($defaultContact === '' && $isLogged) {
            $defaultContact = $this->context->customer->email;
        }

        $this->context->smarty->assign(array(
            'omega_errors' => $errors,
            'omega_is_logged' => $isLogged,
            'omega_eligible_orders' => $eligibleOrders,
            'omega_selected_order_id' => $selectedOrderId,
            'omega_order_reference' => $orderReference,
            'omega_lines' => $lines,
            'omega_order_status' => $orderStatus,
            'omega_fully_requested' => $fullyRequested,
            'omega_requested_at' => $requestedAt,
            'omega_firstname' => $isLogged ? $this->context->customer->firstname : Tools::getValue('firstname', ''),
            'omega_lastname' => $isLogged ? $this->context->customer->lastname : Tools::getValue('lastname', ''),
            'omega_email_value' => Tools::getValue('email', ''),
            'omega_reference_value' => Tools::getValue('order_reference', ''),
            'omega_contact_value' => $defaultContact,
        ));

        $this->setTemplate('module:omeganodesrecesso/views/templates/front/step1.tpl');
    }

    /* =========================================================================
     * STEP 1 -> STEP 2 (validazione, nessuna scrittura)
     * ===================================================================== */

    protected function processStep1()
    {
        if (!$this->checkCsrf()) {
            $this->displayStep1(array($this->module->l('Sessione scaduta, riprova.', 'recesso')));

            return;
        }

        $ctx = $this->resolveContext();
        if (!$ctx['ok']) {
            $this->displayStep1(array($ctx['error'] !== '' ? $ctx['error'] : $this->module->l('Seleziona o identifica un ordine valido.', 'recesso')));

            return;
        }

        /** @var Order $order */
        $order = $ctx['order'];
        $status = $this->module->getOrderRecessoStatus($order);

        if (!$status['eligible_order'] || !$status['has_returnable']) {
            $this->displayStep1(array($this->module->l('Questo ordine non ha quantita\' recedibili residue.', 'recesso')));

            return;
        }

        $contact = trim((string) Tools::getValue('customer_contact'));
        if ($contact === '') {
            $contact = $ctx['email'];
        }

        $selection = $this->parseSelection($order);
        if (empty($selection)) {
            $this->displayStep1(
                array($this->module->l('Seleziona almeno una riga con quantita\' valida.', 'recesso')),
                array('customer_contact' => $contact)
            );

            return;
        }

        // Calcolo importo totale selezionato (display).
        $currency = new Currency((int) $order->id_currency);
        $total = 0.0;
        foreach ($selection as $line) {
            $total += $line['unit_price'] * $line['product_quantity'];
        }

        $this->context->smarty->assign(array(
            'omega_is_logged' => !$ctx['is_guest'],
            'omega_order' => array(
                'id_order' => (int) $order->id,
                'reference' => $order->reference,
                'date' => $order->date_add,
            ),
            'omega_firstname' => $ctx['firstname'],
            'omega_lastname' => $ctx['lastname'],
            'omega_email_value' => $ctx['email'],
            'omega_contact_value' => $contact,
            'omega_reference_value' => $order->reference,
            'omega_selection' => $selection,
            'omega_total_formatted' => Tools::displayPrice($total, $currency),
            'omega_order_status' => $status,
            'omega_warning' => $status['show_warning'],
        ));

        $this->setTemplate('module:omeganodesrecesso/views/templates/front/step2.tpl');
    }

    /**
     * Estrae e valida la selezione righe/quantita' dalla richiesta contro i residui.
     *
     * @return array righe valide (vuoto se nessuna valida o quantita' eccedente)
     */
    protected function parseSelection(Order $order)
    {
        $returnable = OmegaRecessoRequest::getReturnableQuantities((int) $order->id);

        $prices = array();
        $rows = Db::getInstance()->executeS(
            'SELECT id_order_detail, unit_price_tax_incl
             FROM ' . _DB_PREFIX_ . 'order_detail
             WHERE id_order = ' . (int) $order->id
        );
        if (is_array($rows)) {
            foreach ($rows as $r) {
                $prices[(int) $r['id_order_detail']] = (float) $r['unit_price_tax_incl'];
            }
        }

        $selectedIds = Tools::getValue('line_select');
        $qtys = Tools::getValue('line_qty');
        if (!is_array($selectedIds)) {
            return array();
        }

        $selection = array();
        foreach ($selectedIds as $idDetail) {
            $idDetail = (int) $idDetail;
            if (!isset($returnable[$idDetail])) {
                continue;
            }
            $max = (int) $returnable[$idDetail]['returnable'];
            if ($max <= 0) {
                continue;
            }
            $qty = isset($qtys[$idDetail]) ? (int) $qtys[$idDetail] : $max;
            if ($qty < 1) {
                $qty = 1;
            }
            if ($qty > $max) {
                // Quantita' eccedente il residuo: selezione non valida.
                return array();
            }
            $selection[] = array(
                'id_order_detail' => $idDetail,
                'product_name' => $returnable[$idDetail]['product_name'],
                'product_reference' => $returnable[$idDetail]['product_reference'],
                'product_quantity' => $qty,
                'unit_price' => isset($prices[$idDetail]) ? $prices[$idDetail] : 0.0,
            );
        }

        return $selection;
    }

    /* =========================================================================
     * STEP 2 -> CONFERMA (scrittura atomica del record probatorio)
     * ===================================================================== */

    protected function processConfirm()
    {
        if (!$this->checkCsrf()) {
            $this->displayStep1(array($this->module->l('Sessione scaduta, riprova.', 'recesso')));

            return;
        }

        // Rate-limit minimale per i guest.
        if (!$this->context->customer->isLogged() && !$this->checkGuestThrottle()) {
            $this->displayStep1(array($this->module->l('Troppe richieste ravvicinate. Attendi qualche minuto e riprova.', 'recesso')));

            return;
        }

        $ctx = $this->resolveContext();
        if (!$ctx['ok']) {
            $this->displayStep1(array($ctx['error'] !== '' ? $ctx['error'] : $this->module->l('Identificazione ordine non riuscita.', 'recesso')));

            return;
        }

        /** @var Order $order */
        $order = $ctx['order'];

        // Re-validazione idoneita' e residui (no fiducia al client).
        $status = $this->module->getOrderRecessoStatus($order);
        if (!$status['eligible_order']) {
            $this->displayStep1(array($this->module->l('Questo ordine non e\' idoneo al recesso.', 'recesso')));

            return;
        }

        $selection = $this->parseSelection($order);
        if (empty($selection)) {
            // Residui esauriti o quantita' eccedenti: riproporre step 1 aggiornato.
            $this->displayStep1(array($this->module->l('Le quantita\' selezionate non sono piu\' disponibili. Verifica i residui aggiornati.', 'recesso')));

            return;
        }

        $contact = trim((string) Tools::getValue('customer_contact'));
        if ($contact === '') {
            $contact = $ctx['email'];
        }

        // Momento ESATTO del click di conferma (coerente tra PDF, record ed email).
        $confirmedAt = date('Y-m-d H:i:s');

        $idShop = (int) $order->id_shop;
        $currency = new Currency((int) $order->id_currency);
        $idLang = (int) $order->id_lang;

        // 1) contract_snapshot
        $snapshot = $this->buildContractSnapshot($order, $selection, $currency);

        // 2) PDF ricevuta (UUID) con fallback storage
        $uuid = OmegaReceiptGenerator::uuidv4();
        $dirInfo = $this->module->resolveReceiptDir($idShop);
        $pdfPath = $dirInfo['dir'] . '/' . $uuid . '.pdf';

        $receiptData = $this->buildReceiptData($order, $ctx, $contact, $selection, $confirmedAt, $currency);
        $pdfOk = OmegaReceiptGenerator::createPdf($receiptData, $pdfPath);
        if (!$pdfOk) {
            PrestaShopLogger::addLog('OmegaRecesso: generazione PDF ricevuta fallita per ordine ' . (int) $order->id, 2, null, 'OmegaRecessoRequest', (int) $order->id);
            $pdfPath = '';
        }

        // 3) OrderReturn parziale nativo (best-effort, non blocca la conformita')
        $idOrderReturn = $this->createOrderReturn($order, $ctx['id_customer'], $selection);

        // 4) Email immediata con PDF allegato
        $sentAt = null;
        if ($this->sendReceiptEmail($order, $ctx, $contact, $confirmedAt, $pdfPath, $idLang)) {
            $sentAt = date('Y-m-d H:i:s');
        } else {
            PrestaShopLogger::addLog('OmegaRecesso: invio email ricevuta fallito per ordine ' . (int) $order->id, 2, null, 'OmegaRecessoRequest', (int) $order->id);
        }

        // 5) Insert atomico padre + righe (append-only, hash chain)
        try {
            $record = OmegaRecessoRequest::createFromConfirmation(array(
                'id_shop' => $idShop,
                'id_order' => (int) $order->id,
                'id_customer' => (int) $ctx['id_customer'],
                'order_reference' => $order->reference,
                'customer_firstname' => $ctx['firstname'],
                'customer_lastname' => $ctx['lastname'],
                'customer_email' => $ctx['email'],
                'customer_contact' => $contact,
                'contract_snapshot' => $snapshot,
                'client_ip' => Tools::getRemoteAddr(),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
                'confirmed_at' => $confirmedAt,
                'receipt_pdf_path' => $pdfPath,
                'receipt_sent_at' => $sentAt,
                'id_order_return' => (int) $idOrderReturn,
                'lines' => $selection,
            ));
        } catch (Exception $e) {
            PrestaShopLogger::addLog('OmegaRecesso: errore creazione record: ' . $e->getMessage(), 3, null, 'OmegaRecessoRequest', (int) $order->id);
            $this->displayStep1(array($this->module->l('Si e\' verificato un errore durante la registrazione. Riprova o contatta il venditore.', 'recesso')));

            return;
        }

        // 6) Esito
        $this->context->smarty->assign(array(
            'omega_record_id' => (int) $record->id,
            'omega_order_reference' => $order->reference,
            'omega_confirmed_at' => $confirmedAt,
            'omega_email_value' => $ctx['email'],
            'omega_contact_value' => $contact,
            'omega_selection' => $selection,
            'omega_receipt_sent' => ($sentAt !== null),
        ));

        $this->setTemplate('module:omeganodesrecesso/views/templates/front/confirm.tpl');
    }

    /* =========================================================================
     * COSTRUZIONE DATI
     * ===================================================================== */

    protected function buildContractSnapshot(Order $order, array $selection, Currency $currency)
    {
        $deliveryDate = $this->module->getOrderStartDate($order);

        $address = '';
        if ((int) $order->id_address_delivery) {
            $addr = new Address((int) $order->id_address_delivery);
            if (Validate::isLoadedObject($addr)) {
                $address = trim(
                    $addr->firstname . ' ' . $addr->lastname . ', '
                    . $addr->address1 . ' ' . $addr->address2 . ', '
                    . $addr->postcode . ' ' . $addr->city . ' (' . $addr->country . ')'
                );
            }
        }

        $lines = array();
        foreach ($selection as $line) {
            $lines[] = array(
                'id_order_detail' => (int) $line['id_order_detail'],
                'product_name' => $line['product_name'],
                'product_reference' => $line['product_reference'],
                'quantity' => (int) $line['product_quantity'],
            );
        }

        return array(
            'id_order' => (int) $order->id,
            'reference' => $order->reference,
            'order_date' => $order->date_add,
            'total_paid' => (float) $order->total_paid,
            'currency' => $currency->iso_code,
            'delivery_address' => $address,
            'delivery_date' => $deliveryDate,
            'lines' => $lines,
        );
    }

    protected function buildReceiptData(Order $order, array $ctx, $contact, array $selection, $confirmedAt, Currency $currency)
    {
        $shop = $this->context->shop;
        $logoRect = _PS_MODULE_DIR_ . 'omeganodesrecesso/views/img/logo-omeganodes-rect.png';

        $lines = array();
        $total = 0.0;
        foreach ($selection as $line) {
            $lineTotal = $line['unit_price'] * $line['product_quantity'];
            $total += $lineTotal;
            $lines[] = array(
                'product_name' => $line['product_name'],
                'product_reference' => $line['product_reference'],
                'quantity' => (int) $line['product_quantity'],
                'unit_price_formatted' => Tools::displayPrice($line['unit_price'], $currency),
                'line_total_formatted' => Tools::displayPrice($lineTotal, $currency),
            );
        }

        return array(
            'shop' => $shop,
            'title' => 'Ricevuta di esercizio del diritto di recesso (art. 54-bis Cod. consumo)',
            'shop_name' => Configuration::get('PS_SHOP_NAME'),
            'logo_path' => file_exists($logoRect) ? $logoRect : '',
            'logo_powered' => (bool) Configuration::get('OMEGA_REC_POWERED_BY'),
            'order_reference' => $order->reference,
            'order_date' => $order->date_add,
            'confirmed_at' => $confirmedAt,
            'confirmed_at_formatted' => $confirmedAt,
            'customer_name' => trim($ctx['firstname'] . ' ' . $ctx['lastname']),
            'customer_email' => $ctx['email'],
            'customer_contact' => $contact,
            'lines' => $lines,
            'total_formatted' => Tools::displayPrice($total, $currency),
        );
    }

    /* =========================================================================
     * ORDER RETURN (RMA nativo parziale)
     * ===================================================================== */

    protected function createOrderReturn(Order $order, $idCustomer, array $selection)
    {
        try {
            $orderReturn = new OrderReturn();
            $orderReturn->id_customer = (int) $idCustomer;
            $orderReturn->id_order = (int) $order->id;
            $orderReturn->state = 1; // "In attesa di conferma" (standard)
            $orderReturn->question = 'Dichiarazione di recesso ex art. 54-bis Cod. consumo';

            if (!$orderReturn->add()) {
                return 0;
            }

            foreach ($selection as $line) {
                Db::getInstance()->insert('order_return_detail', array(
                    'id_order_return' => (int) $orderReturn->id,
                    'id_order_detail' => (int) $line['id_order_detail'],
                    'id_customization' => 0,
                    'product_quantity' => (int) $line['product_quantity'],
                ));
            }

            return (int) $orderReturn->id;
        } catch (Exception $e) {
            PrestaShopLogger::addLog('OmegaRecesso: creazione OrderReturn fallita: ' . $e->getMessage(), 2, null, 'Order', (int) $order->id);

            return 0;
        }
    }

    /* =========================================================================
     * EMAIL RICEVUTA
     * ===================================================================== */

    protected function sendReceiptEmail(Order $order, array $ctx, $contact, $confirmedAt, $pdfPath, $idLang)
    {
        $recipients = array();
        if (Validate::isEmail($ctx['email'])) {
            $recipients[Tools::strtolower($ctx['email'])] = $ctx['email'];
        }
        if (Validate::isEmail($contact)) {
            $recipients[Tools::strtolower($contact)] = $contact;
        }
        if (empty($recipients)) {
            return false;
        }

        $fromName = Configuration::get('OMEGA_REC_MAIL_FROM_NAME');
        if (!$fromName) {
            $fromName = Configuration::get('PS_SHOP_NAME');
        }

        $templateVars = array(
            '{firstname}' => $ctx['firstname'],
            '{lastname}' => $ctx['lastname'],
            '{order_reference}' => $order->reference,
            '{confirmed_at}' => Tools::displayDate($confirmedAt, true),
            '{shop_name}' => Configuration::get('PS_SHOP_NAME'),
        );

        $attachment = null;
        if ($pdfPath !== '' && file_exists($pdfPath)) {
            $attachment = array(
                'content' => Tools::file_get_contents($pdfPath),
                'name' => 'ricevuta-recesso-' . $order->reference . '.pdf',
                'mime' => 'application/pdf',
            );
        }

        $subject = sprintf('Conferma recesso ordine %s', $order->reference);

        $toList = array_values($recipients);

        return (bool) Mail::Send(
            (int) $idLang,
            'recesso_receipt',
            $subject,
            $templateVars,
            $toList,
            null,
            Configuration::get('PS_SHOP_EMAIL'),
            $fromName,
            $attachment,
            null,
            _PS_MODULE_DIR_ . 'omeganodesrecesso/mails/',
            false,
            (int) $order->id_shop
        );
    }

    /* =========================================================================
     * RATE LIMIT GUEST (minimale, cookie-based)
     * ===================================================================== */

    protected function checkGuestThrottle()
    {
        $now = time();
        $window = 3600; // 1 ora
        $maxInWindow = 10;

        $raw = $this->context->cookie->__get('omega_rec_throttle');
        $timestamps = array();
        if ($raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $ts) {
                    if (($now - (int) $ts) < $window) {
                        $timestamps[] = (int) $ts;
                    }
                }
            }
        }

        if (count($timestamps) >= $maxInWindow) {
            return false;
        }

        $timestamps[] = $now;
        $this->context->cookie->__set('omega_rec_throttle', json_encode($timestamps));
        $this->context->cookie->write();

        return true;
    }
}
