<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Provider;

use Websource\SocialLogin\Http\HttpClientInterface;

/** GitHub OAuth App : e-mail principal VÉRIFIÉ via /user/emails (scope user:email). */
final class GithubProvider extends AbstractProvider
{
    public function id(): string
    {
        return 'github';
    }

    public function label(): string
    {
        return 'GitHub';
    }

    public function defaultScopes(): string
    {
        return 'read:user user:email';
    }

    public function usesPkce(): bool
    {
        return true;
    }

    public function buildAuthorizationUrl(array $cred, string $redirectUri, string $state, string $nonce, ?string $codeChallenge, string $scopes): string
    {
        $p = [
            'client_id' => (string) $cred['client_id'],
            'redirect_uri' => $redirectUri,
            'scope' => $this->scopeList($scopes),
            'state' => $state,
            'allow_signup' => 'true',
        ];
        if ($codeChallenge !== null) {
            $p['code_challenge'] = $codeChallenge;
            $p['code_challenge_method'] = 'S256';
        }

        return $this->url('https://github.com/login/oauth/authorize', $p);
    }

    public function fetchProfile(HttpClientInterface $http, array $cred, string $code, string $redirectUri, ?string $codeVerifier, string $nonce, array $extra = []): Profile
    {
        $form = [
            'client_id' => (string) $cred['client_id'],
            'client_secret' => (string) $cred['client_secret'],
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ];
        if ($codeVerifier !== null) {
            $form['code_verifier'] = $codeVerifier;
        }
        $token = $this->postForm($http, 'https://github.com/login/oauth/access_token', $form);
        $access = (string) $token['access_token'];
        $h = ['X-GitHub-Api-Version: 2022-11-28'];
        $user = $this->getJson($http, 'https://api.github.com/user', $access, $h);
        if (empty($user['id']) || !preg_match('/^\d{1,30}$/', (string) $user['id'])) {
            throw new ProviderException('profile_invalid');
        }
        $email = '';
        try {
            $emails = $this->getJson($http, 'https://api.github.com/user/emails', $access, $h);
        } catch (ProviderException $e) {
            $emails = [];
        }
        $best = '';
        foreach ($emails as $e) {
            if (is_array($e) && !empty($e['verified']) && !empty($e['email'])) {
                if (!empty($e['primary'])) {
                    $best = (string) $e['email'];
                    break;
                }
                $best = $best === '' ? (string) $e['email'] : $best;
            }
        }
        $email = $best;
        [$first, $last] = $this->splitName((string) ($user['name'] ?? ''));
        if ($first === '') {
            $first = (string) ($user['login'] ?? '');
        }

        // l'e-mail du profil public GitHub n'est jamais utilisé : seuls les e-mails marqués « verified » comptent
        return new Profile((string) $user['id'], $email, $email !== '', $first, $last);
    }
}
