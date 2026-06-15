<?php
/**
 * OmegaNodes — Diritto di Recesso (54-bis)
 *
 * HTMLTemplate per la ricevuta PDF su supporto durevole. Renderizzato dal
 * sistema PDF nativo di PrestaShop (classe PDF + PDFGenerator/TCPDF).
 *
 * @author    OmegaNodes Company Ltd <https://omeganodes.ai>
 * @copyright 2026 OmegaNodes Company Ltd
 * @license   https://opensource.org/licenses/AFL-3.0  Academic Free License 3.0 (AFL-3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class HTMLTemplateOmegaRecessoReceipt extends HTMLTemplate
{
    /** @var array dati ricevuta */
    public $data;

    public function __construct($object, $smarty)
    {
        $this->smarty = $smarty;
        $this->data = (array) $object;

        $this->shop = isset($this->data['shop']) && $this->data['shop']
            ? $this->data['shop']
            : Context::getContext()->shop;

        $this->title = isset($this->data['title']) ? $this->data['title'] : 'Ricevuta recesso';

        // Niente header/footer di tema: tutto nel corpo (indipendenza dal tema).
        $this->date = isset($this->data['confirmed_at']) ? $this->data['confirmed_at'] : date('Y-m-d H:i:s');
    }

    /**
     * Header vuoto: il template e' autonomo dal tema attivo.
     */
    public function getHeader()
    {
        return '';
    }

    /**
     * Footer vuoto.
     */
    public function getFooter()
    {
        return '';
    }

    public function getContent()
    {
        $this->smarty->assign($this->data);

        return $this->smarty->fetch(
            _PS_MODULE_DIR_ . 'omeganodesrecesso/views/templates/pdf/receipt.tpl'
        );
    }

    public function getFilename()
    {
        $ref = isset($this->data['order_reference']) ? $this->data['order_reference'] : 'recesso';

        return 'ricevuta-recesso-' . $ref . '.pdf';
    }

    public function getBulkFilename()
    {
        return 'ricevute-recesso.pdf';
    }
}
