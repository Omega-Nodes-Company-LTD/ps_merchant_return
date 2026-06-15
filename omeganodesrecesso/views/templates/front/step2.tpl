{*
 * OmegaNodes — Diritto di Recesso (54-bis)
 * Step 2: pagina separata di riepilogo con UNICO comando "Conferma recesso".
 *
 * @author    OmegaNodes Company Ltd <https://omeganodes.ai>
 * @copyright 2026 OmegaNodes Company Ltd
 * @license   https://opensource.org/licenses/AFL-3.0  Academic Free License 3.0 (AFL-3.0)
 *}
<section id="omega-recesso" class="omega-recesso omega-step2">
  <h1 class="page-heading">{l s='Conferma del recesso' d='Modules.Omeganodesrecesso.Front'}</h1>

  {if $omega_warning}
    <div class="alert alert-warning" role="alert">
      {l s='Il termine di recesso di %d giorni potrebbe essere scaduto; la richiesta verra\' comunque registrata e valutata dal venditore.' sprintf=[$omega_order_status.periodo] d='Modules.Omeganodesrecesso.Front'}
    </div>
  {/if}

  <p class="omega-intro">{l s='Controlla il riepilogo e premi "Conferma recesso" per inviare la dichiarazione. Riceverai subito una ricevuta via email.' d='Modules.Omeganodesrecesso.Front'}</p>

  <div class="omega-summary card">
    <div class="card-block">
      <p><strong>{l s='Ordine' d='Modules.Omeganodesrecesso.Front'}:</strong> {$omega_order.reference|escape:'html':'UTF-8'} ({$omega_order.date|escape:'html':'UTF-8'})</p>
      <p><strong>{l s='Intestatario' d='Modules.Omeganodesrecesso.Front'}:</strong> {$omega_firstname|escape:'html':'UTF-8'} {$omega_lastname|escape:'html':'UTF-8'}</p>
      <p><strong>{l s='Ricevuta a' d='Modules.Omeganodesrecesso.Front'}:</strong> {$omega_contact_value|escape:'html':'UTF-8'}</p>

      <table class="table omega-lines">
        <thead>
          <tr>
            <th>{l s='Prodotto' d='Modules.Omeganodesrecesso.Front'}</th>
            <th>{l s='Quantita\'' d='Modules.Omeganodesrecesso.Front'}</th>
          </tr>
        </thead>
        <tbody>
          {foreach from=$omega_selection item=line}
            <tr>
              <td>{$line.product_name|escape:'html':'UTF-8'}{if $line.product_reference} <span class="omega-ref">({$line.product_reference|escape:'html':'UTF-8'})</span>{/if}</td>
              <td>{$line.product_quantity|intval}</td>
            </tr>
          {/foreach}
        </tbody>
      </table>

      <p class="omega-total"><strong>{l s='Importo corrispondente' d='Modules.Omeganodesrecesso.Front'}:</strong> {$omega_total_formatted}</p>
    </div>
  </div>

  <form method="post" action="{$omega_action_url}" class="omega-confirm-form">
    <input type="hidden" name="omega_token" value="{$omega_token}">
    <input type="hidden" name="id_order" value="{$omega_order.id_order|intval}">
    <input type="hidden" name="customer_contact" value="{$omega_contact_value|escape:'html':'UTF-8'}">
    {if !$omega_is_logged}
      <input type="hidden" name="order_reference" value="{$omega_reference_value|escape:'html':'UTF-8'}">
      <input type="hidden" name="email" value="{$omega_email_value|escape:'html':'UTF-8'}">
    {/if}
    {foreach from=$omega_selection item=line}
      <input type="hidden" name="line_select[]" value="{$line.id_order_detail|intval}">
      <input type="hidden" name="line_qty[{$line.id_order_detail|intval}]" value="{$line.product_quantity|intval}">
    {/foreach}

    <button type="submit" name="submitConfirm" class="btn btn-primary btn-lg omega-confirm">
      {l s='Conferma recesso' d='Modules.Omeganodesrecesso.Front'}
    </button>
  </form>
</section>
