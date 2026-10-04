<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Provider;

use Websource\SocialLogin\Http\HttpClientInterface;

/** Facebook Login (OAuth 2.0 + Graph API). Facebook ne renvoie que des e-mails confirmés. */
final class FacebookProvider extends AbstractProvider
{
    const GRAPH = 'https://graph.facebook.com/v21.0';

    public function id(): string
    {
        return 'facebook';
    }

    public function label(): string
    {
        return 'Facebook';
    }

    public function defaultScopes(): string
    {
        return 'public_profile email';
    }

    public function usesPkce(): bool
    {
        return false;
    }

    public function buildAuthorizationUrl(array $cred, string $redirectUri, string $state, string $nonce, ?string $codeChallenge, string $scopes): string
    {
        return $this->url('https://www.facebook.com/v21.0/dialog/oauth', [
            'client_id' => (string) $cred['client_id'],
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'response_type' => 'code',
            'scope' => $this->scopeList($scopes, ','),
        ]);
    }

    public function fetchProfile(HttpClientInterface $http, array $cred, string $code, string $redirectUri, ?string $codeVerifier, string $nonce, array $extra = []): Profile
    {
        $token = $this->postForm($http, self::GRAPH . '/oauth/access_token', [
            'client_id' => (string) $cred['client_id'],
            'client_secret' => (string) $cred['client_secret'],
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]);
        $access = (string) ($token['access_token'] ?? '');
        $proof = hash_hmac('sha256', $access, (string) $cred['client_secret']);
        $me = $this->getJson($http, self::GRAPH . '/me?' . http_build_query(['fields' => 'id,first_name,last_name,email', 'appsecret_proof' => $proof]), $access);
        if (empty($me['id']) || !preg_match('/^\d{1,30}$/', (string) $me['id'])) {
            throw new ProviderException('profile_invalid');
        }
        $email = (string) ($me['email'] ?? '');

        return new Profile((string) $me['id'], $email, $email !== '', (string) ($me['first_name'] ?? ''), (string) ($me['last_name'] ?? ''));
    }
}
