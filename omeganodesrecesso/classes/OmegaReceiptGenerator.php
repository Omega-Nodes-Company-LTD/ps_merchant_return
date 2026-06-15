<?php
/**
 * OmegaNodes — Diritto di Recesso (54-bis)
 *
 * Generatore della ricevuta PDF su supporto durevole. Usa il sistema PDF
 * nativo di PrestaShop (PDF + HTMLTemplateOmegaRecessoReceipt) e salva il file
 * con nome UUID v4 nella cartella protetta (o nel fallback var/).
 *
 * @author    OmegaNodes Company Ltd <https://omeganodes.ai>
 * @copyright 2026 OmegaNodes Company Ltd
 * @license   https://opensource.org/licenses/AFL-3.0  Academic Free License 3.0 (AFL-3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/HTMLTemplateOmegaRecessoReceipt.php';

class OmegaReceiptGenerator
{
    /**
     * Genera il PDF e lo scrive su disco.
     *
     * @param array  $data         dati da assegnare al template
     * @param string $absolutePath path completo del file PDF da scrivere
     *
     * @return bool true se il file e' stato scritto
     */
    public static function createPdf(array $data, $absolutePath)
    {
        // Il sottosistema PDF non deve MAI far fallire la registrazione legale:
        // qualunque errore qui viene catturato e segnalato come "PDF non generato".
        try {
            $smarty = Context::getContext()->smarty;

            $pdf = new PDF((object) $data, 'OmegaRecessoReceipt', $smarty);
            $content = $pdf->render(false);

            if (!is_string($content) || $content === '') {
                return false;
            }

            $bytes = @file_put_contents($absolutePath, $content);

            return $bytes !== false && $bytes > 0;
        } catch (Throwable $e) {
            PrestaShopLogger::addLog('OmegaRecesso: eccezione generazione PDF: ' . $e->getMessage(), 3);

            return false;
        }
    }

    /**
     * UUID v4 (RFC 4122) per il nome file della ricevuta.
     */
    public static function uuidv4()
    {
        $bytes = function_exists('random_bytes')
            ? random_bytes(16)
            : openssl_random_pseudo_bytes(16);

        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            Tools::substr($hex, 0, 8),
            Tools::substr($hex, 8, 4),
            Tools::substr($hex, 12, 4),
            Tools::substr($hex, 16, 4),
            Tools::substr($hex, 20, 12)
        );
    }
}
