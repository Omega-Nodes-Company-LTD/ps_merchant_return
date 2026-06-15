{*
 * OmegaNodes — Diritto di Recesso (54-bis)
 * Dettaglio record probatorio (read-only) con righe recedute.
 *
 * @author    OmegaNodes Company Ltd <https://omeganodes.ai>
 * @copyright 2026 OmegaNodes Company Ltd
 * @license   https://opensource.org/licenses/AFL-3.0
 *}
<div class="panel">
  <h3><i class="icon-gavel"></i> {l s='Recesso' mod='omeganodesrecesso'} #{$record->id|intval}</h3>

  <table class="table">
    <tr><th style="width:30%;">{l s='Ordine' mod='omeganodesrecesso'}</th>
      <td><a href="{$order_link|escape:'html':'UTF-8'}">{$record->order_reference|escape:'html':'UTF-8'}</a> (ID {$record->id_order|intval})</td></tr>
    <tr><th>{l s='Cliente' mod='omeganodesrecesso'}</th><td>{$record->customer_firstname|escape:'html':'UTF-8'} {$record->customer_lastname|escape:'html':'UTF-8'}</td></tr>
    <tr><th>{l s='Email' mod='omeganodesrecesso'}</th><td>{$record->customer_email|escape:'html':'UTF-8'}</td></tr>
    <tr><th>{l s='Recapito ricevuta' mod='omeganodesrecesso'}</th><td>{$record->customer_contact|escape:'html':'UTF-8'}</td></tr>
    <tr><th>{l s='Confermato il' mod='omeganodesrecesso'}</th><td><strong>{$record->confirmed_at|escape:'html':'UTF-8'}</strong></td></tr>
    <tr><th>{l s='IP' mod='omeganodesrecesso'}</th><td>{$record->client_ip|escape:'html':'UTF-8'}</td></tr>
    <tr><th>{l s='OrderReturn (RMA)' mod='omeganodesrecesso'}</th><td>{if $record->id_order_return}#{$record->id_order_return|intval}{else}—{/if}</td></tr>
    <tr><th>{l s='Ricevuta inviata' mod='omeganodesrecesso'}</th><td>{if $record->receipt_sent_at}{$record->receipt_sent_at|escape:'html':'UTF-8'}{else}{l s='No' mod='omeganodesrecesso'}{/if}</td></tr>
    <tr><th>{l s='Hash riga' mod='omeganodesrecesso'}</th><td><code>{$record->row_hash|escape:'html':'UTF-8'}</code></td></tr>
    <tr><th>{l s='Hash precedente' mod='omeganodesrecesso'}</th><td><code>{if $record->prev_hash}{$record->prev_hash|escape:'html':'UTF-8'}{else}(genesi){/if}</code></td></tr>
  </table>

  {if $has_receipt}
    <a href="{$download_link|escape:'html':'UTF-8'}" class="btn btn-default"><i class="icon-download"></i> {l s='Scarica ricevuta PDF' mod='omeganodesrecesso'}</a>
  {else}
    <p class="text-muted">{l s='Ricevuta PDF non disponibile sul server.' mod='omeganodesrecesso'}</p>
  {/if}
</div>

<div class="panel">
  <h3>{l s='Righe recedute' mod='omeganodesrecesso'}</h3>
  <table class="table">
    <thead>
      <tr>
        <th>{l s='Prodotto' mod='omeganodesrecesso'}</th>
        <th>{l s='Riferimento' mod='omeganodesrecesso'}</th>
        <th>{l s='id_order_detail' mod='omeganodesrecesso'}</th>
        <th>{l s='Quantita\'' mod='omeganodesrecesso'}</th>
      </tr>
    </thead>
    <tbody>
      {foreach from=$lines item=line}
        <tr>
          <td>{$line.product_name|escape:'html':'UTF-8'}</td>
          <td>{$line.product_reference|escape:'html':'UTF-8'}</td>
          <td>{$line.id_order_detail|intval}</td>
          <td>{$line.product_quantity|intval}</td>
        </tr>
      {/foreach}
    </tbody>
  </table>
</div>

<div class="panel">
  <h3>{l s='Snapshot contratto (JSON)' mod='omeganodesrecesso'}</h3>
  <pre style="max-height:300px; overflow:auto;">{$snapshot_pretty|escape:'html':'UTF-8'}</pre>
</div>
