<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Provider;

use Websource\SocialLogin\Http\HttpClientInterface;

/** X (Twitter) : OAuth 2.0 Authorization Code avec PKCE, client confidentiel (Basic). E-mail via le scope users.email si accordé. */
final class XProvider extends AbstractProvider
{
    public function id(): string
    {
        return 'x';
    }

    public function label(): string
    {
        return 'X (Twitter)';
    }

    public function defaultScopes(): string
    {
        return 'users.read tweet.read users.email';
    }

    public function usesPkce(): bool
    {
        return true;
    }

    public function buildAuthorizationUrl(array $cred, string $redirectUri, string $state, string $nonce, ?string $codeChallenge, string $scopes): string
    {
        return $this->url('https://x.com/i/oauth2/authorize', [
            'response_type' => 'code',
            'client_id' => (string) $cred['client_id'],
            'redirect_uri' => $redirectUri,
            'scope' => $this->scopeList($scopes),
            'state' => $state,
            'code_challenge' => (string) $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);
    }

    public function fetchProfile(HttpClientInterface $http, array $cred, string $code, string $redirectUri, ?string $codeVerifier, string $nonce, array $extra = []): Profile
    {
        $basic = 'Authorization: Basic ' . base64_encode((string) $cred['client_id'] . ':' . (string) $cred['client_secret']);
        $token = $this->postForm($http, 'https://api.x.com/2/oauth2/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => (string) $cred['client_id'],
            'code_verifier' => (string) $codeVerifier,
        ], [$basic]);
        $access = (string) $token['access_token'];

        try {
            $me = $this->getJson($http, 'https://api.x.com/2/users/me?user.fields=confirmed_email', $access);
        } catch (ProviderException $e) {
            // l'application n'a pas l'accès « e-mail » : on retente sans ce champ
            $me = $this->getJson($http, 'https://api.x.com/2/users/me', $access);
        }
        $data = isset($me['data']) && is_array($me['data']) ? $me['data'] : [];
        if (empty($data['id']) || !preg_match('/^\d{1,30}$/', (string) $data['id'])) {
            throw new ProviderException('profile_invalid');
        }
        [$first, $last] = $this->splitName((string) ($data['name'] ?? ''));
        $email = (string) ($data['confirmed_email'] ?? '');

        return new Profile((string) $data['id'], $email, $email !== '', $first, $last);
    }
}
