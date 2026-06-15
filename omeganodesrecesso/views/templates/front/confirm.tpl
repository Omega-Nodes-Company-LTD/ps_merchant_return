{*
 * OmegaNodes — Diritto di Recesso (54-bis)
 * Esito: estremi della ricevuta dopo la conferma.
 *
 * @author    OmegaNodes Company Ltd <https://omeganodes.ai>
 * @copyright 2026 OmegaNodes Company Ltd
 * @license   https://opensource.org/licenses/AFL-3.0  Academic Free License 3.0 (AFL-3.0)
 *}
<section id="omega-recesso" class="omega-recesso omega-confirm-result">
  <h1 class="page-heading">{l s='Recesso registrato' d='Modules.Omeganodesrecesso.Front'}</h1>

  <div class="alert alert-success" role="status">
    {l s='La tua dichiarazione di recesso e\' stata registrata correttamente.' d='Modules.Omeganodesrecesso.Front'}
  </div>

  <div class="omega-summary card">
    <div class="card-block">
      <p><strong>{l s='Riferimento richiesta' d='Modules.Omeganodesrecesso.Front'}:</strong> #{$omega_record_id|intval}</p>
      <p><strong>{l s='Ordine' d='Modules.Omeganodesrecesso.Front'}:</strong> {$omega_order_reference|escape:'html':'UTF-8'}</p>
      <p><strong>{l s='Data e ora della conferma' d='Modules.Omeganodesrecesso.Front'}:</strong> {$omega_confirmed_at|escape:'html':'UTF-8'}</p>

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
              <td>{$line.product_name|escape:'html':'UTF-8'}</td>
              <td>{$line.product_quantity|intval}</td>
            </tr>
          {/foreach}
        </tbody>
      </table>

      {if $omega_receipt_sent}
        <p class="omega-sent">{l s='Una ricevuta su supporto durevole e\' stata inviata a' d='Modules.Omeganodesrecesso.Front'} {$omega_contact_value|escape:'html':'UTF-8'}.</p>
      {else}
        <p class="omega-sent text-muted">{l s='La richiesta e\' registrata. Se non ricevi l\'email, contatta il venditore: la tua dichiarazione resta comunque valida.' d='Modules.Omeganodesrecesso.Front'}</p>
      {/if}
    </div>
  </div>
</section>
