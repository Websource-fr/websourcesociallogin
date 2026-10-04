<?php

declare(strict_types=1);

/**
 * Websource Social Login : connexion et inscription en un clic avec Google, Facebook, X, LinkedIn, Microsoft, Apple et GitHub
 * (OAuth 2.0 / OpenID Connect, sans dépendance externe), avec guides de configuration intégrés.
 *
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use Websource\SocialLogin\Admin\ConfigHandler;
use Websource\SocialLogin\Admin\ConfigScreen;
use Websource\SocialLogin\Flow\DbFlowStore;
use Websource\SocialLogin\Flow\FlowService;
use Websource\SocialLogin\Guide\GuideCatalog;
use Websource\SocialLogin\Http\CurlHttpClient;
use Websource\SocialLogin\Linking\DbLinkRepository;
use Websource\SocialLogin\Linking\LinkingService;
use Websource\SocialLogin\Linking\PsCustomerGateway;
use Websource\SocialLogin\Provider\OidcProvider;
use Websource\SocialLogin\Provider\ProviderRegistry;
use Websource\SocialLogin\Render\ButtonViewBuilder;
use Websource\SocialLogin\Security\BackUrlValidator;
use Websource\SocialLogin\Security\PsSecretBox;
use Websource\SocialLogin\Settings\Placements;
use Websource\SocialLogin\Settings\PsConfigStore;
use Websource\SocialLogin\Settings\Settings;
use Websource\SocialLogin\Support\CookieJar;
use Websource\SocialLogin\Support\Translator;

class WebsourceSocialLogin extends Module
{
    const HOOKS = [
        'displayCustomerLoginFormAfter',
        'displayWebsourceSocialLogin',
        'displayCustomerAccount',
        'actionFrontControllerSetMedia',
        'actionDeleteGDPRCustomer',
        'actionExportGDPRData',
        'actionObjectCustomerDeleteAfter',
    ];

    /** @var ProviderRegistry|null */
    private $registry;
    /** @var Translator|null */
    private $translator;

    public function __construct()
    {
        $this->name = 'websourcesociallogin';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Websource';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Websource Social Login');
        $this->description = $this->l('Connexion et inscription en un clic avec Google, Facebook, X, LinkedIn, Microsoft, Apple et GitHub, avec guides de configuration pas à pas.');
        $this->ps_versions_compliancy = ['min' => '1.7.7.0', 'max' => _PS_VERSION_];
    }

    // ------------------------------------------------------------------ cycle de vie

    public function install(): bool
    {
        if (!parent::install() || !$this->installTables()) {
            return false;
        }
        foreach (self::HOOKS as $hook) {
            if (!$this->registerHook($hook)) {
                return false;
            }
        }

        return true;
    }

    public function uninstall(): bool
    {
        $db = Db::getInstance();
        foreach (['link', 'flow'] as $t) {
            $db->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'websourcesociallogin_' . $t . '`');
        }
        $db->execute('DELETE FROM `' . _DB_PREFIX_ . 'configuration_lang` WHERE id_configuration IN (SELECT id_configuration FROM `' . _DB_PREFIX_ . 'configuration` WHERE name LIKE \'WSSL\\_%\')');
        $db->execute('DELETE FROM `' . _DB_PREFIX_ . 'configuration` WHERE name LIKE \'WSSL\\_%\'');

        return parent::uninstall();
    }

    public function installTables(): bool
    {
        $p = _DB_PREFIX_;
        $engine = _MYSQL_ENGINE_;
        $sql = [
            'CREATE TABLE IF NOT EXISTS `' . $p . 'websourcesociallogin_link` (
                `id_link` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_customer` INT UNSIGNED NOT NULL,
                `id_shop` INT UNSIGNED NOT NULL DEFAULT 1,
                `provider` VARCHAR(20) NOT NULL,
                `provider_user_id` VARCHAR(191) NOT NULL,
                `email` VARCHAR(255) NOT NULL DEFAULT \'\',
                `consent_date` DATETIME NULL,
                `date_add` DATETIME NOT NULL,
                `date_last_login` DATETIME NULL,
                PRIMARY KEY (`id_link`),
                UNIQUE KEY `uniq_provider_user` (`provider`, `provider_user_id`, `id_shop`),
                UNIQUE KEY `uniq_customer_provider` (`id_customer`, `provider`),
                KEY `idx_customer` (`id_customer`)
            ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4;',
            'CREATE TABLE IF NOT EXISTS `' . $p . 'websourcesociallogin_flow` (
                `id_flow` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `token_hash` CHAR(64) NOT NULL,
                `type` VARCHAR(12) NOT NULL,
                `payload` TEXT NOT NULL,
                `bind_hash` CHAR(64) NOT NULL,
                `ip_hash` CHAR(64) NOT NULL,
                `id_shop` INT UNSIGNED NOT NULL DEFAULT 1,
                `expires` INT UNSIGNED NOT NULL,
                `created` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`id_flow`),
                UNIQUE KEY `uniq_token` (`token_hash`),
                KEY `idx_ip_created` (`ip_hash`, `created`),
                KEY `idx_expires` (`expires`)
            ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4;',
        ];
        foreach ($sql as $q) {
            if (!Db::getInstance()->execute($q)) {
                return false;
            }
        }

        return true;
    }

    // ------------------------------------------------------------------ services

    public function registry(): ProviderRegistry
    {
        if ($this->registry === null) {
            $this->registry = ProviderRegistry::default();
        }

        return $this->registry;
    }

    public function translator(): Translator
    {
        if ($this->translator === null) {
            $this->translator = new Translator(__DIR__ . '/translations');
        }

        return $this->translator;
    }

    public function tr(string $key, ?string $iso = null, array $params = []): string
    {
        return $this->translator()->t($key, $iso ?? (string) $this->context->language->iso_code, $params);
    }

    public function settings(?int $idShop = null): Settings
    {
        return new Settings(new PsConfigStore(), new PsSecretBox(), $this->registry(), $idShop ?? (int) $this->context->shop->id);
    }

    /** Ouverture de session client, comme AuthController (cookie, panier, hooks). */
    public function startSession(Customer $customer): void
    {
        $context = $this->context;
        Hook::exec('actionBeforeAuthentication');
        $context->updateCustomer($customer);
        Hook::exec('actionAuthentication', ['customer' => $context->customer]);
        CartRule::autoRemoveFromCart($context);
        CartRule::autoAddToCart($context);
    }

    public function ipHash(): string
    {
        return hash('sha256', (string) Tools::getRemoteAddr() . '|' . _COOKIE_KEY_);
    }

    public function links(): DbLinkRepository
    {
        return new DbLinkRepository();
    }

    public function backValidator(): BackUrlValidator
    {
        $hosts = [Tools::getShopDomain(false), Tools::getShopDomainSsl(false)];
        $sql = 'SELECT domain, domain_ssl FROM `' . _DB_PREFIX_ . 'shop_url` WHERE active = 1 AND id_shop = ' . (int) $this->context->shop->id;
        foreach ((array) Db::getInstance()->executeS($sql) as $row) {
            $hosts[] = $row['domain'];
            $hosts[] = $row['domain_ssl'];
        }

        return new BackUrlValidator($hosts);
    }

    /** URL de rappel exacte à enregistrer chez le réseau (https, langue par défaut de la boutique). */
    public function redirectUri(?int $idShop = null): string
    {
        $idShop = $idShop ?? (int) $this->context->shop->id;
        $lang = (int) Configuration::get('PS_LANG_DEFAULT', null, null, $idShop);
        $url = $this->context->link->getModuleLink($this->name, 'callback', [], true, $lang, $idShop);

        return (string) preg_replace('#^http://#i', 'https://', $url);
    }

    public function flow(): FlowService
    {
        $idShop = (int) $this->context->shop->id;
        $http = new CurlHttpClient();
        $self = $this;

        return new FlowService(
            $this->registry(),
            $this->settings(),
            new DbFlowStore($idShop),
            $http,
            new LinkingService($this->links(), new PsCustomerGateway()),
            $this->backValidator(),
            $this->ipHash(),
            function () use ($self): string {
                return $self->redirectUri();
            }
        );
    }

    public function isHttps(): bool
    {
        return (bool) Tools::usingSecureMode();
    }

    /** Valide une adresse de retour : nom de page PrestaShop, chemin du site ou URL d'un domaine de la boutique. */
    public function resolveBack(string $raw): string
    {
        $raw = trim(urldecode($raw));
        if (preg_match('/^[a-z][a-z0-9\-]{1,29}$/', $raw) && in_array($raw, ['my-account', 'order', 'index', 'history', 'identity', 'addresses', 'cart'], true)) {
            return $this->context->link->getPageLink($raw, true);
        }

        return $this->backValidator()->sanitize($raw);
    }

    /** Adresse absolue vers laquelle rediriger (jamais hors de la boutique). */
    public function absoluteBack(string $back): string
    {
        $safe = $this->backValidator()->sanitize($back);
        if ($safe === '') {
            return $this->context->link->getPageLink('my-account', true);
        }
        if ($safe[0] === '/') {
            return rtrim(Tools::getShopDomainSsl(true), '/') . $safe;
        }

        return $safe;
    }

    /** Jeton CSRF des formulaires de liaison / déliaison (lié au client connecté). */
    public function csrfToken(): string
    {
        return Tools::getToken(false);
    }

    public function csrfValid(string $posted): bool
    {
        return $posted !== '' && hash_equals($this->csrfToken(), $posted);
    }

    // ------------------------------------------------------------------ front : boutons

    public function hookActionFrontControllerSetMedia(): void
    {
        $logged = $this->isLogged();
        $controller = $this->context->controller;
        $own = $controller instanceof ModuleFrontController && $controller->module === $this;
        if (!$own && ($logged || $this->settings()->activeProviders() === [])) {
            return;
        }
        $controller->registerStylesheet('websourcesociallogin-front', 'modules/' . $this->name . '/views/css/front.css', ['media' => 'all', 'priority' => 160]);
        $controller->registerJavascript('websourcesociallogin-front', 'modules/' . $this->name . '/views/js/front.js', ['position' => 'bottom', 'priority' => 160, 'attribute' => 'defer']);
    }

    private function isLogged(): bool
    {
        return (bool) ($this->context->customer && $this->context->customer->isLogged());
    }

    public function hookDisplayCustomerLoginFormAfter(array $params = []): string
    {
        return $this->renderButtons(Placements::LOGIN);
    }

    public function hookDisplayWebsourceSocialLogin(array $params = []): string
    {
        $place = isset($params['place']) ? (string) $params['place'] : '';

        return in_array($place, Placements::all(), true) ? $this->renderButtons($place) : '';
    }

    /** Bloc de boutons pour un emplacement (visiteur uniquement, réseaux actifs et configurés uniquement). */
    public function renderButtons(string $place, bool $preview = false): string
    {
        if (!$preview && $this->isLogged()) {
            return '';
        }
        $settings = $this->settings();
        if (!$preview && !in_array($place, $settings->placements(), true)) {
            return '';
        }
        $providers = $preview ? array_values($this->registry()->all()) : $settings->activeProviders(CookieJar::last($_COOKIE));
        $iso = (string) $this->context->language->iso_code;
        $idLang = (int) $this->context->language->id;
        $back = $this->defaultBack($place);
        $token = $this->csrfToken();
        $link = $this->context->link;
        $urlFor = function (string $id) use ($preview, $link, $back, $token): string {
            return $preview ? '#' : $link->getModuleLink($this->name, 'start', ['provider' => $id, 'back' => $back, 'token' => $token], true);
        };
        $view = (new ButtonViewBuilder($this->translator()))->build(
            $settings,
            $providers,
            $place,
            $iso,
            $settings->buttonLabel($idLang),
            $settings->gdprText($idLang) !== '' ? $settings->gdprText($idLang) : $this->tr('gdpr_default'),
            $urlFor,
            CookieJar::last($_COOKIE)
        );
        if ($view === null) {
            return '';
        }
        $this->context->smarty->assign('wssl', $view);

        return $this->context->smarty->fetch($this->getLocalPath() . 'views/templates/hook/buttons.tpl');
    }

    /** Adresse de retour d'un emplacement : la page courante, ou la page indiquée par ?back=. */
    private function defaultBack(string $place): string
    {
        $requested = (string) Tools::getValue('back');
        if ($requested !== '') {
            $ok = $this->resolveBack($requested);
            if ($ok !== '') {
                return $ok;
            }
        }
        if ($place === Placements::CHECKOUT) {
            return $this->context->link->getPageLink('order', true);
        }
        if (in_array($place, [Placements::LOGIN, Placements::REGISTER], true)) {
            return '';
        }
        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';

        return $this->backValidator()->sanitize($uri);
    }

    // ------------------------------------------------------------------ front : Mon compte, RGPD

    public function hookDisplayCustomerAccount(array $params = []): string
    {
        if (!$this->isLogged()) {
            return '';
        }
        $has = $this->settings()->activeProviders() !== [] || $this->links()->listForCustomer((int) $this->context->customer->id) !== [];
        if (!$has) {
            return '';
        }
        $this->context->smarty->assign([
            'wssl_account_url' => $this->context->link->getModuleLink($this->name, 'account', [], true),
            'wssl_account_label' => $this->tr('account_tile'),
        ]);

        return $this->context->smarty->fetch($this->getLocalPath() . 'views/templates/hook/account-tile.tpl');
    }

    /** RGPD : suppression à la demande d'un client (module psgdpr). */
    public function hookActionDeleteGDPRCustomer($customer)
    {
        $email = is_array($customer) ? (string) ($customer['email'] ?? '') : '';
        if ($email === '' || !Validate::isEmail($email)) {
            return json_encode(false);
        }
        $db = Db::getInstance();
        $ids = $db->executeS('SELECT id_customer FROM `' . _DB_PREFIX_ . 'customer` WHERE email = \'' . pSQL($email) . '\'');
        foreach ((array) $ids as $row) {
            $this->links()->deleteForCustomer((int) $row['id_customer']);
        }

        return json_encode(true);
    }

    /** RGPD : export des données de liaison (module psgdpr). */
    public function hookActionExportGDPRData($customer)
    {
        $email = is_array($customer) ? (string) ($customer['email'] ?? '') : '';
        if ($email === '' || !Validate::isEmail($email)) {
            return json_encode(false);
        }
        $out = [];
        $ids = Db::getInstance()->executeS('SELECT id_customer FROM `' . _DB_PREFIX_ . 'customer` WHERE email = \'' . pSQL($email) . '\'');
        foreach ((array) $ids as $row) {
            foreach ($this->links()->listForCustomer((int) $row['id_customer']) as $l) {
                $out[] = [
                    $this->l('Réseau') => (string) $l['provider'],
                    $this->l('E-mail du réseau') => (string) $l['email'],
                    $this->l('Consentement') => (string) $l['consent_date'],
                    $this->l('Lié le') => (string) $l['date_add'],
                    $this->l('Dernière connexion') => (string) $l['date_last_login'],
                ];
            }
        }

        return $out === [] ? json_encode(false) : json_encode($out);
    }

    /** Toute suppression d'un client (BO, API…) supprime ses liens. */
    public function hookActionObjectCustomerDeleteAfter(array $params): void
    {
        if (isset($params['object']->id) && (int) $params['object']->id > 0) {
            $this->links()->deleteForCustomer((int) $params['object']->id);
        }
    }

    // ------------------------------------------------------------------ back-office

    public function getContent(): string
    {
        $ctx = $this->context;
        $singleShop = !Shop::isFeatureActive() || Shop::getContext() === Shop::CONTEXT_SHOP;
        $idShop = $singleShop ? (int) $ctx->shop->id : (int) Configuration::get('PS_SHOP_DEFAULT');
        $settings = $this->settings($idShop);
        $languages = Language::getLanguages(false);
        $langIds = array_map('intval', array_column($languages, 'id_lang'));
        $handler = new ConfigHandler($this->registry());
        $messages = [];
        $errors = [];
        $testId = null;

        if ($singleShop && Tools::isSubmit('wssl_save') || Tools::isSubmit('wssl_test')) {
            $expected = Tools::getAdminTokenLite('AdminModules');
            if (!$singleShop || !hash_equals($expected, (string) Tools::getValue('wssl_token'))) {
                $errors[] = $this->tr('bo_invalid_token');
            } else {
                $res = $handler->save($_POST, $settings, $langIds);
                foreach ($res['errors'] as $field) {
                    $errors[] = $this->tr('bo_invalid_field') . ' : ' . $this->tr('bo_field_' . $field);
                }
                $messages[] = $this->tr('bo_saved');
                if (Tools::isSubmit('wssl_test')) {
                    $cand = (string) Tools::getValue('wssl_test');
                    $testId = $this->registry()->has($cand) ? $cand : null;
                }
            }
        }

        $ctx->controller->addCSS($this->_path . 'views/css/admin.css');
        $ctx->controller->addCSS($this->_path . 'views/css/front.css');
        $ctx->controller->addJS($this->_path . 'views/js/admin.js');

        return ConfigScreen::render($this->buildScreenModel($settings, $idShop, $singleShop, $languages, $handler, $testId, $messages, $errors));
    }

    /**
     * @param array<int,array<string,mixed>> $languages
     * @param string[]                       $messages
     * @param string[]                       $errors
     *
     * @return array<string,mixed>
     */
    private function buildScreenModel(Settings $settings, int $idShop, bool $singleShop, array $languages, ConfigHandler $handler, ?string $testId, array $messages, array $errors): array
    {
        $bo = (string) $this->context->language->iso_code;
        $self = $this;
        $t = function (string $key, ?string $iso = null) use ($self, $bo): string {
            return $self->tr($key, $iso ?? $bo);
        };
        $guides = new GuideCatalog();
        $callback = $this->redirectUri($idShop);
        $parts = parse_url($callback) ?: [];
        $domain = (string) ($parts['host'] ?? '');
        $origin = 'https://' . $domain;
        $providers = [];
        $pos = 0;
        foreach ($settings->order() as $id) {
            $provider = $this->registry()->get($id);
            ++$pos;
            $filled = $settings->filled($id);
            $cred = $settings->credentials($id);
            $fields = [];
            foreach ($provider->fields() as $name => $def) {
                $secret = in_array($def['type'], ['secret', 'secret_textarea'], true);
                $fields[$name] = [
                    'type' => (string) $def['type'],
                    'required' => !empty($def['required']),
                    'filled' => !empty($filled[$name]),
                    // jamais la valeur d'un secret : uniquement les champs publics (Client ID, Team ID, Key ID, tenant)
                    'value' => $secret ? '' : (string) $cred[$name],
                ];
            }
            $missing = $settings->missing($id);
            $anyFilled = false; // seuls les champs obligatoires comptent (le tenant Microsoft a une valeur par défaut)
            foreach ($provider->fields() as $fname => $fdef) {
                $anyFilled = $anyFilled || (!empty($fdef['required']) && !empty($filled[$fname]));
            }
            $status = $settings->isActive($id) ? 'active' : ($missing !== [] && ($settings->isEnabled($id) || $anyFilled) ? 'incomplete' : ($missing === [] ? 'configured' : 'empty'));
            $vars = ['callback' => $callback, 'origin' => $origin, 'domain' => $domain, 'scopes' => $settings->scopes($id)];
            $providers[] = [
                'id' => $id,
                'label' => $provider->label(),
                'enabled' => $settings->isEnabled($id),
                'status' => $status,
                'missing' => $missing,
                'fields' => $fields,
                'scopes' => $settings->scopes($id),
                'default_scopes' => $provider->defaultScopes(),
                'callback' => $callback,
                'pkce' => $provider->usesPkce(),
                'oidc' => $provider instanceof OidcProvider,
                'position' => $pos,
                'guide' => $guides->guide($id, $bo),
                'vars' => $vars,
                'test' => $testId === $id ? $handler->test($id, $settings, $callback) : null,
            ];
        }
        $labels = [];
        $gdpr = [];
        $langs = [];
        foreach ($languages as $l) {
            $langs[] = ['id' => (int) $l['id_lang'], 'iso' => (string) $l['iso_code'], 'name' => (string) $l['name']];
            $labels[(int) $l['id_lang']] = $settings->buttonLabel((int) $l['id_lang']);
            $gdpr[(int) $l['id_lang']] = $settings->gdprText((int) $l['id_lang']);
        }
        $common = $guides->common($bo);

        return [
            't' => function (string $key, ?string $iso = null) use ($t): string {
                return $t($key, $iso);
            },
            'messages' => $messages,
            'errors' => $errors,
            'single_shop' => $singleShop,
            'shop_name' => (string) (new Shop($idShop))->name,
            'ssl_ok' => (bool) Configuration::get('PS_SSL_ENABLED', null, null, $idShop),
            'action' => $this->context->link->getAdminLink('AdminModules', true, [], ['configure' => $this->name, 'module_name' => $this->name]),
            'token' => Tools::getAdminTokenLite('AdminModules'),
            'providers' => $providers,
            'style' => $settings->style(),
            'places' => $settings->placements(),
            'separator' => $settings->separator(),
            'remember' => $settings->rememberLast(),
            'langs' => $langs,
            'labels' => $labels,
            'gdpr' => $gdpr,
            'preview' => $this->renderButtons(Placements::LOGIN, true),
            'common' => $common,
            'vars_common' => ['callback' => $callback, 'origin' => $origin, 'domain' => $domain, 'scopes' => ''],
        ];
    }
}
