<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

/** « Mon compte > Connexions sociales » : réseaux liés, lier / délier. Toutes les actions sont en POST avec jeton CSRF. */

use Websource\SocialLogin\Support\EventLogger;

class WebsourceSocialLoginAccountModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $auth = true;
    public $authRedirection = 'my-account';

    public function postProcess()
    {
        /** @var WebsourceSocialLogin $module */
        $module = $this->module;
        $idCustomer = (int) $this->context->customer->id;
        if (Tools::isSubmit('wssl_unlink')) {
            $provider = (string) Tools::getValue('provider');
            if (!$module->csrfValid((string) Tools::getValue('token')) || !$module->registry()->has($provider)) {
                $this->errors[] = $module->tr('err_csrf');

                return;
            }
            if ($module->links()->remove($idCustomer, $provider)) {
                EventLogger::log('unlinked', $provider, '', $idCustomer);
                $this->success[] = $module->tr('msg_unlinked');
            }
            Tools::redirect($this->context->link->getModuleLink($module->name, 'account', [], true));
        }
    }

    public function initContent()
    {
        parent::initContent();
        /** @var WebsourceSocialLogin $module */
        $module = $this->module;
        $idCustomer = (int) $this->context->customer->id;
        $linked = [];
        foreach ($module->links()->listForCustomer($idCustomer) as $l) {
            $linked[(string) $l['provider']] = $l;
        }
        $settings = $module->settings();
        $rows = [];
        foreach ($settings->order() as $id) {
            $isLinked = isset($linked[$id]);
            if (!$isLinked && !$settings->isActive($id)) {
                continue;
            }
            $p = $module->registry()->get($id);
            $rows[] = [
                'id' => $id,
                'label' => $p->label(),
                'linked' => $isLinked,
                'since' => $isLinked ? Tools::displayDate((string) $linked[$id]['date_add']) : '',
                'email' => $isLinked ? (string) $linked[$id]['email'] : '',
                'can_link' => $settings->isActive($id),
                'icon' => \Websource\SocialLogin\Render\IconLibrary::svg($id, 'wssl-row__icon'),
            ];
        }
        $this->context->smarty->assign([
            'wssl_rows' => $rows,
            'wssl_token' => $module->csrfToken(),
            'wssl_start_url' => $this->context->link->getModuleLink($module->name, 'start', [], true),
            'wssl_unlink_url' => $this->context->link->getModuleLink($module->name, 'account', [], true),
            'wssl_tx' => $module->translator()->all((string) $this->context->language->iso_code),
            'wssl_account_page' => $this->context->link->getPageLink('my-account', true),
        ]);
        $this->setTemplate('module:websourcesociallogin/views/templates/front/account.tpl');
    }
}
