{**
 * Boutons « Continuer avec … ». Toutes les valeurs sont échappées ; les icônes SVG viennent de IconLibrary (balisage de confiance).
 * Aucun secret ni jeton de réseau n'apparaît ici : les liens pointent vers le contrôleur « start » du module.
 *}
{if $wssl}
<div class="{$wssl.class|escape:'html':'UTF-8'}" style="{$wssl.style|escape:'html':'UTF-8'}" data-wssl role="group" aria-label="{$wssl.title|escape:'html':'UTF-8'}">
  {if $wssl.separator && $wssl.separator_pos == 'before'}<div class="wssl__sep" role="separator"><span>{$wssl.separator_text|escape:'html':'UTF-8'}</span></div>{/if}
  <div class="wssl__list">
    {foreach from=$wssl.buttons item=b}
      <a class="wssl-btn wssl-btn--{$b.id|escape:'html':'UTF-8'}{if $b.last} is-last{/if}" href="{$b.url|escape:'html':'UTF-8'}" rel="nofollow" data-wssl-provider="{$b.id|escape:'html':'UTF-8'}" aria-label="{$b.aria|escape:'html':'UTF-8'}" title="{$b.label|escape:'html':'UTF-8'}">
        <span class="wssl-btn__logo">{$b.icon nofilter}</span>
        <span class="wssl-btn__label">{$b.label|escape:'html':'UTF-8'}</span>
        {if $b.last}<span class="wssl-btn__badge">{$wssl.last_label|escape:'html':'UTF-8'}</span>{/if}
        <span class="wssl-btn__spinner" aria-hidden="true"></span>
      </a>
    {/foreach}
  </div>
  {if $wssl.gdpr}<p class="wssl__legal">{$wssl.gdpr|escape:'html':'UTF-8'}</p>{/if}
  {if $wssl.separator && $wssl.separator_pos == 'after'}<div class="wssl__sep" role="separator"><span>{$wssl.separator_text|escape:'html':'UTF-8'}</span></div>{/if}
</div>
{/if}
