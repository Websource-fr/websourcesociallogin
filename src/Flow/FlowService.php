<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Flow;

use Websource\SocialLogin\Http\HttpClientInterface;
use Websource\SocialLogin\Linking\LinkingService;
use Websource\SocialLogin\Linking\Result;
use Websource\SocialLogin\Provider\Profile;
use Websource\SocialLogin\Provider\ProviderException;
use Websource\SocialLogin\Provider\ProviderRegistry;
use Websource\SocialLogin\Security\BackUrlValidator;
use Websource\SocialLogin\Security\Pkce;
use Websource\SocialLogin\Security\Token;
use Websource\SocialLogin\Settings\Settings;

/**
 * Orchestration du flux Authorization Code : démarrage (state, nonce, PKCE liés à la session par un cookie de liaison),
 * retour (state à usage unique, échange de code, validation, rattachement) et étapes intermédiaires.
 */
final class FlowService
{
    const MAX_STARTS_PER_10_MIN = 30;
    const MAX_CONFIRM_ATTEMPTS = 5;

    /** @var ProviderRegistry */
    private $registry;
    /** @var Settings */
    private $settings;
    /** @var FlowStoreInterface */
    private $store;
    /** @var HttpClientInterface */
    private $http;
    /** @var LinkingService */
    private $linking;
    /** @var BackUrlValidator */
    private $backValidator;
    /** @var string */
    private $ipHash;
    /** @var callable fn(): string URL de callback exacte */
    private $redirectUri;

    public function __construct(ProviderRegistry $registry, Settings $settings, FlowStoreInterface $store, HttpClientInterface $http, LinkingService $linking, BackUrlValidator $back, string $ipHash, callable $redirectUri)
    {
        $this->registry = $registry;
        $this->settings = $settings;
        $this->store = $store;
        $this->http = $http;
        $this->linking = $linking;
        $this->backValidator = $back;
        $this->ipHash = $ipHash;
        $this->redirectUri = $redirectUri;
    }

    /** Empreinte de la session navigateur à partir du cookie de liaison. */
    public static function bindHash(string $bindCookie): string
    {
        return $bindCookie === '' ? '' : Token::hash('bind|' . $bindCookie);
    }

    /**
     * @return string adresse d'autorisation vers laquelle rediriger
     *
     * @throws ProviderException not_configured | rate_limited | unknown_provider
     */
    public function start(string $providerId, string $back, ?int $currentCustomerId, string $bindCookie): string
    {
        if (!$this->registry->has($providerId)) {
            throw new ProviderException('unknown_provider');
        }
        if (!$this->settings->isActive($providerId) || !Token::looksValid($bindCookie)) {
            throw new ProviderException('not_configured');
        }
        if ($this->store->countRecent($this->ipHash, 600) >= self::MAX_STARTS_PER_10_MIN) {
            throw new ProviderException('rate_limited');
        }
        $provider = $this->registry->get($providerId);
        $nonce = Token::random(24);
        $verifier = $provider->usesPkce() ? Pkce::verifier() : '';
        $state = $this->store->create(FlowStoreInterface::AUTH, [
            'provider' => $providerId,
            'nonce' => $nonce,
            'verifier' => $verifier,
            'back' => $this->backValidator->sanitize($back),
            'customer' => $currentCustomerId ?: 0,
        ], 600, self::bindHash($bindCookie), $this->ipHash);

        return $provider->buildAuthorizationUrl(
            $this->settings->credentials($providerId),
            ($this->redirectUri)(),
            $state,
            $nonce,
            $verifier !== '' ? Pkce::challenge($verifier) : null,
            $this->settings->scopes($providerId)
        );
    }

    /**
     * @param array<string,mixed> $extra champs POST additionnels (Apple : « user »)
     *
     * @throws ProviderException state_invalid | rate_limited | provider errors
     */
    public function callback(string $state, string $code, string $bindCookie, array $extra, ?int $currentCustomerId, int $idShop): Outcome
    {
        if (!Token::looksValid($state) || $code === '' || strlen($code) > 2048) {
            throw new ProviderException('state_invalid');
        }
        if ($this->store->countRecent($this->ipHash, 600) >= self::MAX_STARTS_PER_10_MIN * 2) {
            throw new ProviderException('rate_limited');
        }
        // usage unique + liaison à la session navigateur : un state rejoué ou volé échoue
        $flow = $this->store->consume(FlowStoreInterface::AUTH, $state, self::bindHash($bindCookie));
        if ($flow === null || !$this->registry->has((string) $flow['provider']) || !$this->settings->isActive((string) $flow['provider'])) {
            throw new ProviderException('state_invalid');
        }
        $providerId = (string) $flow['provider'];
        $intendedCustomer = (int) $flow['customer'];
        if ($intendedCustomer !== (int) $currentCustomerId) {
            throw new ProviderException('session_changed');
        }
        $provider = $this->registry->get($providerId);
        $verifier = (string) $flow['verifier'];
        $profile = $provider->fetchProfile(
            $this->http,
            $this->settings->credentials($providerId),
            $code,
            ($this->redirectUri)(),
            $verifier !== '' ? $verifier : null,
            (string) $flow['nonce'],
            $extra
        );

        return $this->finish($providerId, $profile, $intendedCustomer ?: null, $idShop, (string) $flow['back'], $bindCookie);
    }

    private function finish(string $providerId, Profile $profile, ?int $customer, int $idShop, string $back, string $bindCookie): Outcome
    {
        $result = $this->linking->handle($providerId, $profile, $customer, $idShop);
        $out = new Outcome($result);
        $out->provider = $providerId;
        $out->back = $back;
        $out->intent = $customer ? 'link' : 'login';
        $bind = self::bindHash($bindCookie);
        $payload = ['provider' => $providerId, 'profile' => $profile->toArray(), 'back' => $back, 'customer' => $result->customerId, 'attempts' => 0];
        if ($result->action === Result::CONFIRM_PASSWORD) {
            $out->pendingToken = $this->store->create(FlowStoreInterface::CONFIRM, $payload, 900, $bind, $this->ipHash);
        } elseif ($result->action === Result::NEED_EMAIL || $result->action === Result::VERIFY_EMAIL) {
            $out->pendingToken = $this->store->create(FlowStoreInterface::ASKMAIL, $payload, 900, $bind, $this->ipHash);
        }

        return $out;
    }

    /** @return array<string,mixed>|null */
    public function pending(string $type, string $token, string $bindCookie): ?array
    {
        return $this->store->find($type, $token, self::bindHash($bindCookie));
    }

    public function confirmPassword(string $token, string $bindCookie, string $password, int $idShop): Outcome
    {
        $bind = self::bindHash($bindCookie);
        $p = $this->store->find(FlowStoreInterface::CONFIRM, $token, $bind);
        if ($p === null) {
            throw new ProviderException('state_invalid');
        }
        $attempts = (int) $p['attempts'] + 1;
        if ($attempts > self::MAX_CONFIRM_ATTEMPTS) {
            $this->store->delete(FlowStoreInterface::CONFIRM, $token);
            throw new ProviderException('too_many_attempts');
        }
        $p['attempts'] = $attempts;
        $this->store->update(FlowStoreInterface::CONFIRM, $token, $p);
        $profile = Profile::fromArray((array) $p['profile']);
        $result = $this->linking->confirmWithPassword((string) $p['provider'], $profile, (int) $p['customer'], $password, $idShop);
        $out = new Outcome($result);
        $out->provider = (string) $p['provider'];
        $out->back = (string) $p['back'];
        $out->pendingToken = $token;
        if ($result->action === Result::LOGIN) {
            $this->store->delete(FlowStoreInterface::CONFIRM, $token);
        }

        return $out;
    }

    /**
     * Étape « e-mail » : crée un lien de vérification à usage unique (1 h) lié à ce navigateur.
     *
     * @return array{token:string,email:string,provider:string}|null null si l'adresse est invalide
     */
    public function requestEmailVerification(string $token, string $bindCookie, string $email): ?array
    {
        $bind = self::bindHash($bindCookie);
        $p = $this->store->find(FlowStoreInterface::ASKMAIL, $token, $bind);
        if ($p === null) {
            throw new ProviderException('state_invalid');
        }
        $email = strtolower(trim($email));
        if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        if ($this->store->countRecent($this->ipHash, 600) >= self::MAX_STARTS_PER_10_MIN) {
            throw new ProviderException('rate_limited');
        }
        $p['email'] = $email;
        $verifyToken = $this->store->create(FlowStoreInterface::VERIFY, $p, 3600, $bind, $this->ipHash);
        $this->store->delete(FlowStoreInterface::ASKMAIL, $token);

        return ['token' => $verifyToken, 'email' => $email, 'provider' => (string) $p['provider']];
    }

    /** Clic sur le lien reçu par e-mail, dans le même navigateur. */
    public function completeVerification(string $token, string $bindCookie, int $idShop): Outcome
    {
        $p = $this->store->consume(FlowStoreInterface::VERIFY, $token, self::bindHash($bindCookie));
        if ($p === null) {
            throw new ProviderException('verify_invalid');
        }
        $orig = Profile::fromArray((array) $p['profile']);
        $verified = new Profile($orig->id, (string) $p['email'], true, $orig->firstname, $orig->lastname);

        return $this->finish((string) $p['provider'], $verified, null, $idShop, (string) $p['back'], $bindCookie);
    }
}
