{extends file='page.tpl'}

{block name='page_title'}{$wssl_tx.account_title|escape:'html':'UTF-8'}{/block}

{block name='page_content'}
<section class="wssl-page">
  <p>{$wssl_tx.account_intro|escape:'html':'UTF-8'}</p>
  {if $wssl_rows}
  <ul class="wssl-rows">
    {foreach from=$wssl_rows item=r}
      <li class="wssl-row">
        <span class="wssl-row__logo">{$r.icon nofilter}</span>
        <span class="wssl-row__main">
          <strong>{$r.label|escape:'html':'UTF-8'}</strong>
          <small>{if $r.linked}{$wssl_tx.account_linked_since|replace:'{date}':$r.since|escape:'html':'UTF-8'}{if $r.email} · {$r.email|escape:'html':'UTF-8'}{/if}{else}{$wssl_tx.account_not_linked|escape:'html':'UTF-8'}{/if}</small>
        </span>
        {if $r.linked}
          <form method="post" action="{$wssl_unlink_url|escape:'html':'UTF-8'}" class="wssl-row__action">
            <input type="hidden" name="token" value="{$wssl_token|escape:'html':'UTF-8'}">
            <input type="hidden" name="provider" value="{$r.id|escape:'html':'UTF-8'}">
            <button type="submit" name="wssl_unlink" value="1" class="btn btn-secondary" onclick="return confirm('{$wssl_tx.account_unlink_confirm|escape:'javascript':'UTF-8'}');">{$wssl_tx.account_unlink|escape:'html':'UTF-8'}</button>
          </form>
        {elseif $r.can_link}
          <form method="post" action="{$wssl_start_url|escape:'html':'UTF-8'}" class="wssl-row__action">
            <input type="hidden" name="token" value="{$wssl_token|escape:'html':'UTF-8'}">
            <input type="hidden" name="provider" value="{$r.id|escape:'html':'UTF-8'}">
            <input type="hidden" name="back" value="my-account">
            <button type="submit" name="wssl_link" value="1" class="btn btn-primary">{$wssl_tx.account_link|escape:'html':'UTF-8'}</button>
          </form>
        {/if}
      </li>
    {/foreach}
  </ul>
  <p class="wssl-page__note">{$wssl_tx.account_unlink_note|escape:'html':'UTF-8'}</p>
  {else}
    <p>{$wssl_tx.account_none|escape:'html':'UTF-8'}</p>
  {/if}
  <p><a href="{$wssl_account_page|escape:'html':'UTF-8'}">&larr; {$wssl_tx.account_back|escape:'html':'UTF-8'}</a></p>
</section>
{/block}
