{*
 * OmegaNodes — Diritto di Recesso (54-bis)
 * Step 1: identificazione ordine + dati + selezione righe/quantita'.
 *
 * @author    OmegaNodes Company Ltd <https://omeganodes.ai>
 * @copyright 2026 OmegaNodes Company Ltd
 * @license   https://opensource.org/licenses/AFL-3.0  Academic Free License 3.0 (AFL-3.0)
 *}
<section id="omega-recesso" class="omega-recesso omega-step1">
  <h1 class="page-heading">{l s='Diritto di recesso' d='Modules.Omeganodesrecesso.Front'}</h1>

  {if $omega_errors}
    <div class="alert alert-danger" role="alert">
      <ul class="omega-errors">
        {foreach from=$omega_errors item=err}<li>{$err}</li>{/foreach}
      </ul>
    </div>
  {/if}

  <p class="omega-intro">
    {l s='Puoi esercitare il diritto di recesso entro %d giorni, senza obbligo di motivazione. La procedura si svolge in due passaggi: prima identifichi l\'ordine e selezioni i prodotti, poi confermi.' sprintf=[$omega_periodo] d='Modules.Omeganodesrecesso.Front'}
  </p>

  {if !$omega_selected_order_id}
    {* ---- IDENTIFICAZIONE ---- *}
    {if $omega_is_logged}
      <form method="post" action="{$omega_action_url}" class="omega-identify form-horizontal">
        <input type="hidden" name="omega_token" value="{$omega_token}">
        <div class="form-group">
          <label for="omega_id_order">{l s='Seleziona l\'ordine' d='Modules.Omeganodesrecesso.Front'}</label>
          <select name="id_order" id="omega_id_order" class="form-control omega-order-select">
            <option value="">{l s='— scegli un ordine —' d='Modules.Omeganodesrecesso.Front'}</option>
            {foreach from=$omega_eligible_orders item=ord}
              <option value="{$ord.id_order|intval}">{$ord.reference|escape:'html':'UTF-8'} — {$ord.date|escape:'html':'UTF-8'|truncate:10:''}</option>
            {/foreach}
          </select>
        </div>
        <button type="submit" name="submitFind" class="btn btn-primary">{l s='Mostra i prodotti' d='Modules.Omeganodesrecesso.Front'}</button>
        {if !$omega_eligible_orders}
          <p class="omega-empty">{l s='Non risultano ordini idonei al recesso.' d='Modules.Omeganodesrecesso.Front'}</p>
        {/if}
      </form>
    {else}
      <form method="post" action="{$omega_action_url}" class="omega-identify form-horizontal">
        <input type="hidden" name="omega_token" value="{$omega_token}">
        <div class="form-group">
          <label for="omega_reference">{l s='Numero ordine' d='Modules.Omeganodesrecesso.Front'}</label>
          <input type="text" id="omega_reference" name="order_reference" class="form-control" value="{$omega_reference_value|escape:'html':'UTF-8'}" required>
        </div>
        <div class="form-group">
          <label for="omega_email">{l s='Email' d='Modules.Omeganodesrecesso.Front'}</label>
          <input type="email" id="omega_email" name="email" class="form-control" value="{$omega_email_value|escape:'html':'UTF-8'}" required>
        </div>
        <button type="submit" name="submitFind" class="btn btn-primary">{l s='Trova l\'ordine' d='Modules.Omeganodesrecesso.Front'}</button>
      </form>
    {/if}
  {/if}

  {if $omega_fully_requested}
    <div class="alert alert-info" role="status">
      {l s='Recesso gia\' richiesto per tutte le quantita\' di questo ordine il %s.' sprintf=[$omega_requested_at] d='Modules.Omeganodesrecesso.Front'}
    </div>
  {/if}

  {if $omega_selected_order_id && $omega_lines}
    {* ---- SELEZIONE RIGHE ---- *}
    {if $omega_order_status.show_warning}
      <div class="alert alert-warning" role="alert">
        {l s='Il termine di recesso di %d giorni potrebbe essere scaduto; la richiesta verra\' comunque registrata e valutata dal venditore.' sprintf=[$omega_periodo] d='Modules.Omeganodesrecesso.Front'}
      </div>
    {/if}

    <form method="post" action="{$omega_action_url}" class="omega-selection-form">
      <input type="hidden" name="omega_token" value="{$omega_token}">
      <input type="hidden" name="id_order" value="{$omega_selected_order_id|intval}">
      {if !$omega_is_logged}
        <input type="hidden" name="order_reference" value="{$omega_reference_value|escape:'html':'UTF-8'}">
        <input type="hidden" name="email" value="{$omega_email_value|escape:'html':'UTF-8'}">
      {/if}

      <p class="omega-order-ref">{l s='Ordine' d='Modules.Omeganodesrecesso.Front'}: <strong>{$omega_order_reference|escape:'html':'UTF-8'}</strong></p>

      <table class="table omega-lines">
        <thead>
          <tr>
            <th>{l s='Recedi' d='Modules.Omeganodesrecesso.Front'}</th>
            <th>{l s='Prodotto' d='Modules.Omeganodesrecesso.Front'}</th>
            <th>{l s='Prezzo' d='Modules.Omeganodesrecesso.Front'}</th>
            <th>{l s='Residuo' d='Modules.Omeganodesrecesso.Front'}</th>
            <th>{l s='Quantita\'' d='Modules.Omeganodesrecesso.Front'}</th>
          </tr>
        </thead>
        <tbody>
          {foreach from=$omega_lines item=line}
            <tr class="{if $line.returnable <= 0}omega-line-disabled{/if}">
              <td>
                <input type="checkbox" name="line_select[]" value="{$line.id_order_detail|intval}"
                  {if $line.returnable > 0}checked{else}disabled{/if}>
              </td>
              <td>
                {$line.product_name|escape:'html':'UTF-8'}
                {if $line.product_reference} <span class="omega-ref">({$line.product_reference|escape:'html':'UTF-8'})</span>{/if}
                {if $line.returnable <= 0}<br><em class="omega-note">{l s='gia\' oggetto di recesso' d='Modules.Omeganodesrecesso.Front'}</em>{/if}
              </td>
              <td>{$line.unit_price_formatted}</td>
              <td>{$line.returnable|intval}{if $line.already_requested > 0} / {$line.ordered|intval}{/if}</td>
              <td>
                <input type="number" name="line_qty[{$line.id_order_detail|intval}]" class="form-control omega-qty"
                  min="1" max="{$line.returnable|intval}" value="{$line.returnable|intval}"
                  {if $line.returnable <= 0}disabled{/if}>
              </td>
            </tr>
          {/foreach}
        </tbody>
      </table>

      <div class="form-group">
        <label for="omega_contact">{l s='Recapito per la ricevuta (email)' d='Modules.Omeganodesrecesso.Front'}</label>
        <input type="text" id="omega_contact" name="customer_contact" class="form-control" value="{$omega_contact_value|escape:'html':'UTF-8'}">
      </div>

      <button type="submit" name="submitStep1" class="btn btn-primary btn-lg omega-next">
        {l s='Richiedi il recesso' d='Modules.Omeganodesrecesso.Front'}
      </button>
    </form>
  {/if}

  {if $omega_powered_by}
    <p class="omega-powered text-muted"><small>{l s='Powered by OmegaNodes' d='Modules.Omeganodesrecesso.Front'}</small></p>
  {/if}
</section>
