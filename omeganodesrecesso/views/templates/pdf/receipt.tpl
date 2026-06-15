{*
 * OmegaNodes — Diritto di Recesso (54-bis)
 * Template PDF della ricevuta su supporto durevole (reso da PDFGenerator/TCPDF).
 *
 * @author    OmegaNodes Company Ltd <https://omeganodes.ai>
 * @copyright 2026 OmegaNodes Company Ltd
 * @license   https://opensource.org/licenses/AFL-3.0  Academic Free License 3.0 (AFL-3.0)
 *}
<style>
  body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #222; }
  .omega-pdf-header { border-bottom: 2px solid #222; padding-bottom: 8px; margin-bottom: 12px; }
  .omega-pdf-title { font-size: 15px; font-weight: bold; margin: 10px 0; }
  table.omega-pdf { width: 100%; border-collapse: collapse; margin-top: 8px; }
  table.omega-pdf th, table.omega-pdf td { border: 1px solid #999; padding: 5px; text-align: left; }
  .omega-pdf-meta td { border: none; padding: 2px 4px; }
  .omega-pdf-note { margin-top: 14px; font-style: italic; color: #444; }
  .omega-pdf-foot { margin-top: 18px; font-size: 9px; color: #777; }
</style>

<div class="omega-pdf-header">
  {if $logo_path}<img src="{$logo_path}" height="34" alt="OmegaNodes"><br>{/if}
  <strong>{$shop_name|escape:'html':'UTF-8'}</strong>
</div>

<div class="omega-pdf-title">{$title|escape:'html':'UTF-8'}</div>

<table class="omega-pdf-meta">
  <tr><td><strong>{l s='Riferimento ordine' d='Modules.Omeganodesrecesso.Pdf'}:</strong></td><td>{$order_reference|escape:'html':'UTF-8'}</td></tr>
  <tr><td><strong>{l s='Data ordine' d='Modules.Omeganodesrecesso.Pdf'}:</strong></td><td>{$order_date|escape:'html':'UTF-8'}</td></tr>
  <tr><td><strong>{l s='Data e ora della conferma' d='Modules.Omeganodesrecesso.Pdf'}:</strong></td><td><strong>{$confirmed_at_formatted|escape:'html':'UTF-8'}</strong></td></tr>
  <tr><td><strong>{l s='Intestatario' d='Modules.Omeganodesrecesso.Pdf'}:</strong></td><td>{$customer_name|escape:'html':'UTF-8'}</td></tr>
  <tr><td><strong>{l s='Email' d='Modules.Omeganodesrecesso.Pdf'}:</strong></td><td>{$customer_email|escape:'html':'UTF-8'}</td></tr>
  <tr><td><strong>{l s='Recapito ricevuta' d='Modules.Omeganodesrecesso.Pdf'}:</strong></td><td>{$customer_contact|escape:'html':'UTF-8'}</td></tr>
</table>

<table class="omega-pdf">
  <thead>
    <tr>
      <th>{l s='Prodotto' d='Modules.Omeganodesrecesso.Pdf'}</th>
      <th>{l s='Riferimento' d='Modules.Omeganodesrecesso.Pdf'}</th>
      <th>{l s='Quantita\'' d='Modules.Omeganodesrecesso.Pdf'}</th>
      <th>{l s='Prezzo unitario' d='Modules.Omeganodesrecesso.Pdf'}</th>
      <th>{l s='Totale riga' d='Modules.Omeganodesrecesso.Pdf'}</th>
    </tr>
  </thead>
  <tbody>
    {foreach from=$lines item=line}
      <tr>
        <td>{$line.product_name|escape:'html':'UTF-8'}</td>
        <td>{$line.product_reference|escape:'html':'UTF-8'}</td>
        <td>{$line.quantity|intval}</td>
        <td>{$line.unit_price_formatted}</td>
        <td>{$line.line_total_formatted}</td>
      </tr>
    {/foreach}
  </tbody>
</table>

<p style="text-align:right;"><strong>{l s='Totale' d='Modules.Omeganodesrecesso.Pdf'}: {$total_formatted}</strong></p>

<p class="omega-pdf-note">
  {l s='La presente costituisce ricevuta su supporto durevole della dichiarazione di recesso trasmessa ai sensi dell\'art. 54-bis del Codice del consumo.' d='Modules.Omeganodesrecesso.Pdf'}
</p>

{if $logo_powered}
  <div class="omega-pdf-foot">{l s='Powered by OmegaNodes — https://omeganodes.ai' d='Modules.Omeganodesrecesso.Pdf'}</div>
{/if}
