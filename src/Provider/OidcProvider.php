<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Provider;

use Websource\SocialLogin\Http\HttpClientInterface;
use Websource\SocialLogin\Security\HttpJwksProvider;
use Websource\SocialLogin\Security\IdTokenValidator;
use Websource\SocialLogin\Security\JwksProviderInterface;

/**
 * Socle OpenID Connect : flux Authorization Code (+ PKCE), puis validation complète de l'id_token
 * (signature JWKS RS256, iss, aud, exp, nonce). L'identité vient UNIQUEMENT de l'id_token validé.
 */
abstract class OidcProvider extends AbstractProvider
{
    /** @var JwksProviderInterface|null */
    private $jwks;
    /** @var IdTokenValidator|null */
    private $validator;

    public function setJwksProvider(JwksProviderInterface $jwks): void
    {
        $this->jwks = $jwks;
    }

    public function setValidator(IdTokenValidator $validator): void
    {
        $this->validator = $validator;
    }

    /** @param array<string,string> $cred */
    abstract protected function authorizeEndpoint(array $cred): string;

    /** @param array<string,string> $cred */
    abstract protected function tokenEndpoint(array $cred): string;

    /** @param array<string,string> $cred */
    abstract protected function jwksUrl(array $cred): string;

    /**
     * @param array<string,string> $cred
     *
     * @return string|callable
     */
    abstract protected function issuer(array $cred);

    /**
     * @param array<string,mixed> $claims
     * @param array<string,mixed> $extra
     */
    abstract protected function profileFromClaims(array $claims, array $extra): Profile;

    /** @param array<string,string> $cred */
    protected function clientSecret(array $cred): string
    {
        return (string) ($cred['client_secret'] ?? '');
    }

    /** @return array<string,string> paramètres d'autorisation additionnels */
    protected function authorizeExtra(): array
    {
        return [];
    }

    public function buildAuthorizationUrl(array $cred, string $redirectUri, string $state, string $nonce, ?string $codeChallenge, string $scopes): string
    {
        $params = [
            'response_type' => 'code',
            'client_id' => (string) ($cred['client_id'] ?? ''),
            'redirect_uri' => $redirectUri,
            'scope' => $this->scopeList($scopes),
            'state' => $state,
            'nonce' => $nonce,
        ] + $this->authorizeExtra();
        if ($codeChallenge !== null && $this->usesPkce()) {
            $params['code_challenge'] = $codeChallenge;
            $params['code_challenge_method'] = 'S256';
        }

        return $this->url($this->authorizeEndpoint($cred), $params);
    }

    public function fetchProfile(HttpClientInterface $http, array $cred, string $code, string $redirectUri, ?string $codeVerifier, string $nonce, array $extra = []): Profile
    {
        $form = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => (string) ($cred['client_id'] ?? ''),
            'client_secret' => $this->clientSecret($cred),
        ];
        if ($codeVerifier !== null && $this->usesPkce()) {
            $form['code_verifier'] = $codeVerifier;
        }
        $token = $this->postForm($http, $this->tokenEndpoint($cred), $form);
        if (empty($token['id_token']) || !is_string($token['id_token'])) {
            throw new ProviderException('id_token_missing');
        }

        $jwks = $this->jwks ?? new HttpJwksProvider($http);
        $validator = $this->validator ?? new IdTokenValidator();
        $url = $this->jwksUrl($cred);
        try {
            $claims = $validator->validate($token['id_token'], $jwks->keys($url), $this->issuer($cred), (string) $cred['client_id'], $nonce);
        } catch (ProviderException $e) {
            if ($e->getMessage() !== 'jwt_unknown_key') {
                throw $e;
            }
            // rotation de clés : un seul nouvel essai avec un JWKS frais
            $claims = $validator->validate($token['id_token'], $jwks->keys($url, true), $this->issuer($cred), (string) $cred['client_id'], $nonce);
        }

        return $this->profileFromClaims($claims, $extra);
    }
}
