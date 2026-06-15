{*
 * OmegaNodes — Diritto di Recesso (54-bis)
 * Bottone nel dettaglio del singolo ordine (displayOrderDetail).
 *
 * @author    OmegaNodes Company Ltd <https://omeganodes.ai>
 * @copyright 2026 OmegaNodes Company Ltd
 * @license   https://opensource.org/licenses/AFL-3.0  Academic Free License 3.0 (AFL-3.0)
 *}
<div class="box omega-order-recesso">
  <h4>{l s='Diritto di recesso' d='Modules.Omeganodesrecesso.Front'}</h4>

  {if $omega_status.fully_requested}
    <p class="omega-note">
      {l s='Recesso gia\' richiesto per tutte le quantita\' il %s.' sprintf=[$omega_status.requested_at] d='Modules.Omeganodesrecesso.Front'}
    </p>
  {elseif $omega_status.show_button}
    {if $omega_status.show_warning}
      <p class="omega-note text-warning">
        {l s='Il termine di recesso di %d giorni potrebbe essere scaduto; la richiesta verra\' comunque registrata e valutata dal venditore.' sprintf=[$omega_periodo] d='Modules.Omeganodesrecesso.Front'}
      </p>
    {/if}
    <a href="{$omega_recesso_link}" class="btn btn-primary omega-order-btn">
      {l s='Richiedi il recesso per questo ordine' d='Modules.Omeganodesrecesso.Front'}
    </a>
  {/if}
</div>
