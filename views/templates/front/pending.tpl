{extends file='page.tpl'}

{block name='page_title'}
  {if $wssl_sent}{$wssl_tx.pending_sent_title|escape:'html':'UTF-8'}
  {elseif $wssl_type == 'confirm'}{$wssl_tx.pending_confirm_title|escape:'html':'UTF-8'}
  {else}{$wssl_tx.pending_email_title|escape:'html':'UTF-8'}{/if}
{/block}

{block name='page_content'}
<section class="wssl-page">
  {if $wssl_sent}
    <p>{$wssl_tx.pending_sent_text|escape:'html':'UTF-8'}</p>
    <p class="wssl-page__note">{$wssl_tx.pending_sent_note|escape:'html':'UTF-8'}</p>
  {elseif $wssl_type == 'confirm'}
    <p>{$wssl_tx.pending_confirm_text|replace:'{network}':$wssl_provider_label|replace:'{email}':$wssl_email|escape:'html':'UTF-8'}</p>
    <form method="post" action="{$wssl_action|escape:'html':'UTF-8'}" class="wssl-page__form">
      <input type="hidden" name="wssl_csrf" value="{$wssl_csrf|escape:'html':'UTF-8'}">
      <div class="form-group">
        <label for="wssl-password">{$wssl_tx.field_password|escape:'html':'UTF-8'}</label>
        <input id="wssl-password" class="form-control" type="password" name="password" autocomplete="current-password" required>
      </div>
      <p><a href="{$urls.pages.password|escape:'html':'UTF-8'}" rel="nofollow">{$wssl_tx.forgot_password|escape:'html':'UTF-8'}</a></p>
      <button type="submit" name="wssl_confirm" value="1" class="btn btn-primary">{$wssl_tx.pending_confirm_btn|escape:'html':'UTF-8'}</button>
    </form>
  {else}
    <p>{$wssl_tx.pending_email_text|replace:'{network}':$wssl_provider_label|escape:'html':'UTF-8'}</p>
    <form method="post" action="{$wssl_action|escape:'html':'UTF-8'}" class="wssl-page__form">
      <input type="hidden" name="wssl_csrf" value="{$wssl_csrf|escape:'html':'UTF-8'}">
      <div class="form-group">
        <label for="wssl-email">{$wssl_tx.field_email|escape:'html':'UTF-8'}</label>
        <input id="wssl-email" class="form-control" type="email" name="email" value="{$wssl_email|escape:'html':'UTF-8'}" autocomplete="email" required maxlength="254">
      </div>
      <button type="submit" name="wssl_email" value="1" class="btn btn-primary">{$wssl_tx.pending_email_btn|escape:'html':'UTF-8'}</button>
    </form>
  {/if}
</section>
{/block}
