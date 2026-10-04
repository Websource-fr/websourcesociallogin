<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

/**
 * Retour du réseau (GET ; POST pour Apple) : state à usage unique lié au navigateur, échange du code, validation, rattachement.
 */

use Websource\SocialLogin\Linking\Result;
use Websource\SocialLogin\Provider\ProviderException;
use Websource\SocialLogin\Support\CookieJar;
use Websource\SocialLogin\Support\EventLogger;

class WebsourceSocialLoginCallbackModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $auth = false;

    public function postProcess()
    {
        /** @var WebsourceSocialLogin $module */
        $module = $this->module;
        $logged = (bool) $this->context->customer->isLogged();
        $bind = CookieJar::bindValue($_COOKIE);
        $authPage = $this->context->link->getPageLink('authentication', true);
        $accountUrl = $this->context->link->getModuleLink($module->name, 'account', [], true);
        $fallback = $logged ? $accountUrl : $authPage;

        $oauthError = (string) Tools::getValue('error');
        if ($oauthError !== '') {
            $code = preg_match('/^[a-z_]{1,40}$/', $oauthError) ? $oauthError : 'error';
            EventLogger::log('error', '', 'oauth_' . $code);
            $this->errors[] = $module->tr(in_array($code, ['access_denied', 'user_cancelled_login', 'user_cancelled_authorize'], true) ? 'err_cancelled' : 'err_provider');
            $this->redirectWithNotifications($fallback);
        }

        $extra = [];
        if (is_string(Tools::getValue('user'))) {
            $extra['user'] = (string) Tools::getValue('user');
        }
        $provider = '';
        try {
            $outcome = $module->flow()->callback(
                (string) Tools::getValue('state'),
                (string) Tools::getValue('code'),
                $bind,
                $extra,
                $logged ? (int) $this->context->customer->id : null,
                (int) $this->context->shop->id
            );
        } catch (ProviderException $e) {
            $msg = $e->getMessage();
            EventLogger::log($msg === 'rate_limited' ? 'rate_limited' : 'error', $provider, $msg);
            $key = ['state_invalid' => 'err_session', 'session_changed' => 'err_session', 'rate_limited' => 'err_rate'][$msg] ?? 'err_provider';
            $this->errors[] = $module->tr($key);
            $this->redirectWithNotifications($fallback);
        }

        $r = $outcome->result;
        $provider = $outcome->provider;
        switch ($r->action) {
            case Result::LOGIN:
            case Result::CREATED:
                $this->loginAndGo($r->customerId, $provider, $outcome->back, $r->action === Result::CREATED);
                break;
            case Result::LINKED:
                EventLogger::log('linked', $provider, '', $r->customerId);
                $this->success[] = $module->tr('msg_linked');
                $this->redirectWithNotifications($accountUrl);
                break;
            case Result::CONFIRM_PASSWORD:
                Tools::redirect($this->context->link->getModuleLink($module->name, 'pending', ['type' => 'confirm', 't' => $outcome->pendingToken], true));
                break;
            case Result::NEED_EMAIL:
            case Result::VERIFY_EMAIL:
                Tools::redirect($this->context->link->getModuleLink($module->name, 'pending', ['type' => 'email', 't' => $outcome->pendingToken], true));
                break;
            default:
                EventLogger::log('error', $provider, $r->error);
                $map = ['account_disabled' => 'err_disabled', 'linked_to_other' => 'err_linked_other', 'already_has_provider' => 'err_already_provider'];
                $this->errors[] = $module->tr($map[$r->error] ?? 'err_provider');
                $this->redirectWithNotifications($fallback);
        }
        exit;
    }

    private function loginAndGo(int $idCustomer, string $provider, string $back, bool $created): void
    {
        /** @var WebsourceSocialLogin $module */
        $module = $this->module;
        $customer = new Customer($idCustomer);
        if (!Validate::isLoadedObject($customer) || !$customer->active || $customer->deleted) {
            $this->errors[] = $module->tr('err_disabled');
            $this->redirectWithNotifications($this->context->link->getPageLink('authentication', true));
        }
        $module->startSession($customer);
        CookieJar::setLast($provider, $module->isHttps());
        EventLogger::log($created ? 'created' : 'login', $provider, '', $idCustomer);
        Tools::redirect($module->absoluteBack($back));
    }
}
