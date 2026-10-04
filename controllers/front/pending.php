<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

/**
 * Étapes intermédiaires : confirmation par mot de passe (client existant), saisie de l'e-mail, vérification par lien.
 * Chaque formulaire porte le jeton d'étape (à usage limité, lié au navigateur) ET un jeton CSRF dérivé.
 */

use Websource\SocialLogin\Flow\FlowStoreInterface;
use Websource\SocialLogin\Linking\Result;
use Websource\SocialLogin\Provider\ProviderException;
use Websource\SocialLogin\Support\CookieJar;
use Websource\SocialLogin\Support\EventLogger;

class WebsourceSocialLoginPendingModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $auth = false;

    /** @var string */
    private $type = '';
    /** @var string */
    private $token = '';
    /** @var string */
    private $bind = '';
    /** @var array<string,mixed> */
    private $view = [];

    public function init()
    {
        parent::init();
        $this->type = (string) Tools::getValue('type');
        $this->token = (string) Tools::getValue('t');
        $this->bind = CookieJar::bindValue($_COOKIE);
    }

    private function csrf(): string
    {
        return hash_hmac('sha256', 'wssl|' . $this->token . '|' . $this->bind, _COOKIE_KEY_);
    }

    private function csrfOk(): bool
    {
        return hash_equals($this->csrf(), (string) Tools::getValue('wssl_csrf'));
    }

    private function expired(): void
    {
        $this->errors[] = $this->module->tr('err_session');
        $this->redirectWithNotifications($this->context->link->getPageLink('authentication', true));
    }

    public function postProcess()
    {
        /** @var WebsourceSocialLogin $module */
        $module = $this->module;
        $flow = $module->flow();
        $idShop = (int) $this->context->shop->id;

        // lien reçu par e-mail
        if ($this->type === 'verify') {
            try {
                $outcome = $flow->completeVerification($this->token, $this->bind, $idShop);
            } catch (ProviderException $e) {
                $this->expired();
            }
            $this->route($outcome);
        }

        if ($this->type === 'confirm' && Tools::isSubmit('wssl_confirm')) {
            if (!$this->csrfOk()) {
                $this->expired();
            }
            try {
                $outcome = $flow->confirmPassword($this->token, $this->bind, (string) Tools::getValue('password'), $idShop);
            } catch (ProviderException $e) {
                EventLogger::log('confirm_failed', '', $e->getMessage());
                $this->errors[] = $module->tr($e->getMessage() === 'too_many_attempts' ? 'err_attempts' : 'err_session');
                $this->redirectWithNotifications($this->context->link->getPageLink('authentication', true));
            }
            if ($outcome->result->action === Result::ERROR) {
                EventLogger::log('confirm_failed', $outcome->provider, $outcome->result->error);
                $this->errors[] = $module->tr('err_bad_password');
            } else {
                $this->route($outcome);
            }
        }

        if ($this->type === 'email' && Tools::isSubmit('wssl_email')) {
            if (!$this->csrfOk()) {
                $this->expired();
            }
            try {
                $res = $flow->requestEmailVerification($this->token, $this->bind, (string) Tools::getValue('email'));
            } catch (ProviderException $e) {
                $this->errors[] = $module->tr($e->getMessage() === 'rate_limited' ? 'err_rate' : 'err_session');
                $this->redirectWithNotifications($this->context->link->getPageLink('authentication', true));
            }
            if ($res === null) {
                $this->errors[] = $module->tr('err_email_invalid');
            } else {
                $this->sendVerificationMail($res['email'], $res['token']);
                $this->view['sent'] = true;
            }
        }
    }

    /** @param \Websource\SocialLogin\Flow\Outcome $outcome */
    private function route($outcome): void
    {
        /** @var WebsourceSocialLogin $module */
        $module = $this->module;
        $r = $outcome->result;
        if ($r->action === Result::LOGIN || $r->action === Result::CREATED) {
            $customer = new Customer($r->customerId);
            if (Validate::isLoadedObject($customer) && $customer->active && !$customer->deleted) {
                $module->startSession($customer);
                CookieJar::setLast($outcome->provider, $module->isHttps());
                EventLogger::log($r->action === Result::CREATED ? 'created' : 'login', $outcome->provider, '', $r->customerId);
                Tools::redirect($module->absoluteBack($outcome->back));
            }
        }
        if ($r->action === Result::CONFIRM_PASSWORD) {
            Tools::redirect($this->context->link->getModuleLink($module->name, 'pending', ['type' => 'confirm', 't' => $outcome->pendingToken], true));
        }
        $this->errors[] = $module->tr('err_provider');
        $this->redirectWithNotifications($this->context->link->getPageLink('authentication', true));
    }

    private function sendVerificationMail(string $email, string $verifyToken): void
    {
        /** @var WebsourceSocialLogin $module */
        $module = $this->module;
        $link = $this->context->link->getModuleLink($module->name, 'pending', ['type' => 'verify', 't' => $verifyToken], true);
        $iso = (string) $this->context->language->iso_code;
        $idLang = (int) $this->context->language->id;
        if (!is_dir(_PS_MODULE_DIR_ . $module->name . '/mails/' . $iso)) {
            $idLang = (int) (Language::getIdByIso('en') ?: $idLang);
        }
        Mail::Send(
            $idLang,
            'wssl_verify',
            $module->tr('mail_subject', Language::getIsoById($idLang) ?: 'en'),
            ['{link}' => $link, '{shop_name}' => (string) Configuration::get('PS_SHOP_NAME')],
            $email,
            null,
            null,
            null,
            null,
            null,
            _PS_MODULE_DIR_ . $module->name . '/mails/',
            false,
            (int) $this->context->shop->id
        );
        EventLogger::log('mail_sent');
    }

    public function initContent()
    {
        parent::initContent();
        /** @var WebsourceSocialLogin $module */
        $module = $this->module;
        $flow = $module->flow();
        $stored = null;
        if ($this->type === 'confirm' || $this->type === 'email') {
            $stored = $flow->pending($this->type === 'confirm' ? FlowStoreInterface::CONFIRM : FlowStoreInterface::ASKMAIL, $this->token, $this->bind);
        }
        $sent = !empty($this->view['sent']);
        if ($stored === null && !$sent) {
            $this->expired();
        }
        $profile = (array) ($stored['profile'] ?? []);
        $this->context->smarty->assign([
            'wssl_type' => $this->type,
            'wssl_token' => $this->token,
            'wssl_csrf' => $this->csrf(),
            'wssl_sent' => $sent,
            'wssl_email' => (string) ($profile['email'] ?? ''),
            'wssl_provider' => (string) ($stored['provider'] ?? ''),
            'wssl_provider_label' => $stored && $module->registry()->has((string) $stored['provider']) ? $module->registry()->get((string) $stored['provider'])->label() : '',
            'wssl_action' => $this->context->link->getModuleLink($module->name, 'pending', ['type' => $this->type, 't' => $this->token], true),
            'wssl_tx' => $module->translator()->all((string) $this->context->language->iso_code),
        ]);
        $this->setTemplate('module:websourcesociallogin/views/templates/front/pending.tpl');
    }
}
