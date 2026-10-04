<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

/**
 * Démarre une connexion : contrôle du jeton, limitation du taux, cookie de liaison, puis redirection vers le réseau.
 * Un client connecté (liaison depuis « Mon compte ») doit passer par un formulaire POST avec jeton CSRF.
 */

use Websource\SocialLogin\Provider\ProviderException;
use Websource\SocialLogin\Support\CookieJar;
use Websource\SocialLogin\Support\EventLogger;

class WebsourceSocialLoginStartModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $auth = false;

    public function postProcess()
    {
        /** @var WebsourceSocialLogin $module */
        $module = $this->module;
        $logged = (bool) $this->context->customer->isLogged();
        $provider = (string) Tools::getValue('provider');
        $token = (string) Tools::getValue('token');

        // client connecté : seule la liaison depuis « Mon compte » (POST + jeton CSRF) est possible
        if ($logged && (!Tools::isSubmit('wssl_link') || $_SERVER['REQUEST_METHOD'] !== 'POST')) {
            Tools::redirect($this->context->link->getModuleLink($module->name, 'account', [], true));
        }
        if (!$module->csrfValid($token) || !preg_match('/^[a-z0-9]{1,20}$/', $provider)) {
            $this->fail($logged, 'err_csrf');
        }

        $reg = $module->registry();
        $needsPost = $reg->has($provider) && $reg->get($provider)->usesFormPost();
        $bind = CookieJar::ensureBind($_COOKIE, $module->isHttps(), $needsPost);
        try {
            $back = $module->resolveBack((string) Tools::getValue('back'));
            $url = $module->flow()->start($provider, $back, $logged ? (int) $this->context->customer->id : null, $bind);
        } catch (ProviderException $e) {
            EventLogger::log($e->getMessage() === 'rate_limited' ? 'rate_limited' : 'error', $provider, $e->getMessage());
            $this->fail($logged, $e->getMessage() === 'rate_limited' ? 'err_rate' : 'err_unavailable');
        }
        EventLogger::log('start', $provider);
        // redirection vers le réseau : l'adresse est construite par le fournisseur (domaine fixe), jamais depuis une entrée utilisateur
        header('Cache-Control: no-store');
        header('Location: ' . $url, true, 302);
        exit;
    }

    private function fail(bool $logged, string $key): void
    {
        $this->errors[] = $this->module->tr($key);
        $target = $logged ? $this->context->link->getModuleLink($this->module->name, 'account', [], true) : $this->context->link->getPageLink('authentication', true);
        $this->redirectWithNotifications($target);
    }
}
