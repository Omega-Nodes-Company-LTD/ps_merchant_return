{*
 * OmegaNodes — Diritto di Recesso (54-bis)
 * Banner header admin: logo, stato integrita' catena, nota supporto.
 *
 * @author    OmegaNodes Company Ltd <https://omeganodes.ai>
 * @copyright 2026 OmegaNodes Company Ltd
 * @license   https://opensource.org/licenses/AFL-3.0
 *}
<div class="panel omega-admin-header">
  <div class="row">
    <div class="col-lg-2 col-md-3 text-center">
      <img src="{$omega_logo|escape:'html':'UTF-8'}" alt="OmegaNodes" class="omega-admin-logo" onerror="this.style.display='none'">
    </div>
    <div class="col-lg-10 col-md-9">
      <h3 style="margin-top:0;">{l s='Diritto di Recesso (54-bis) — registro probatorio' mod='omeganodesrecesso'}</h3>
      {if $omega_chain_ok}
        <div class="alert alert-success" style="margin-bottom:8px;">
          <i class="icon-check"></i>
          {l s='Integrita\' catena hash: OK' mod='omeganodesrecesso'} ({$omega_chain_checked|intval} {l s='record' mod='omeganodesrecesso'})
        </div>
      {else}
        <div class="alert alert-danger" style="margin-bottom:8px;">
          <i class="icon-warning"></i>
          {l s='Integrita\' catena hash COMPROMESSA' mod='omeganodesrecesso'}:
          {l s='prima discrepanza al record' mod='omeganodesrecesso'} #{$omega_chain_first_broken|intval}
          ({$omega_chain_checked|intval} {l s='verificati' mod='omeganodesrecesso'})
        </div>
      {/if}
      {if $omega_support_note}
        <div class="alert alert-info" style="margin-bottom:0;">{$omega_support_note|escape:'html':'UTF-8'}</div>
      {/if}
    </div>
  </div>
  {if $omega_powered_by}
    <p class="text-muted text-right" style="margin:6px 0 0;"><small>{l s='Powered by OmegaNodes — https://omeganodes.ai' mod='omeganodesrecesso'}</small></p>
  {/if}
</div>
