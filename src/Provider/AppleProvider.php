<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Provider;

use Websource\SocialLogin\Security\Jwt;

/**
 * Sign in with Apple. Le « client secret » est un JWT ES256 de courte durée généré à chaque échange
 * à partir de la clé privée .p8 (Team ID, Key ID, Services ID). Réponse en POST (form_post).
 */
final class AppleProvider extends OidcProvider
{
    public function id(): string
    {
        return 'apple';
    }

    public function label(): string
    {
        return 'Apple';
    }

    public function fields(): array
    {
        return [
            'client_id' => ['type' => 'text', 'required' => true, 'default' => ''],
            'team_id' => ['type' => 'text', 'required' => true, 'default' => ''],
            'key_id' => ['type' => 'text', 'required' => true, 'default' => ''],
            'private_key' => ['type' => 'secret_textarea', 'required' => true, 'default' => ''],
        ];
    }

    public function defaultScopes(): string
    {
        return 'name email';
    }

    public function usesPkce(): bool
    {
        return false;
    }

    public function usesFormPost(): bool
    {
        return true;
    }

    protected function authorizeEndpoint(array $cred): string
    {
        return 'https://appleid.apple.com/auth/authorize';
    }

    protected function tokenEndpoint(array $cred): string
    {
        return 'https://appleid.apple.com/auth/token';
    }

    protected function jwksUrl(array $cred): string
    {
        return 'https://appleid.apple.com/auth/keys';
    }

    protected function issuer(array $cred)
    {
        return 'https://appleid.apple.com';
    }

    protected function authorizeExtra(): array
    {
        return ['response_mode' => 'form_post'];
    }

    /** @param array<string,string> $cred */
    protected function clientSecret(array $cred): string
    {
        return self::buildClientSecret((string) $cred['team_id'], (string) $cred['client_id'], (string) $cred['key_id'], (string) $cred['private_key']);
    }

    public static function buildClientSecret(string $teamId, string $clientId, string $keyId, string $privateKeyPem, ?int $now = null): string
    {
        $now = $now ?? time();

        return Jwt::signEs256(
            ['alg' => 'ES256', 'kid' => $keyId, 'typ' => 'JWT'],
            ['iss' => $teamId, 'iat' => $now, 'exp' => $now + 300, 'aud' => 'https://appleid.apple.com', 'sub' => $clientId],
            $privateKeyPem
        );
    }

    protected function profileFromClaims(array $claims, array $extra): Profile
    {
        $verified = ($claims['email_verified'] ?? false) === true || ($claims['email_verified'] ?? '') === 'true';
        $first = '';
        $last = '';
        // Apple n'envoie le nom qu'à la toute première autorisation, dans le champ POST « user » (non signé : sert uniquement à l'affichage).
        if (!empty($extra['user']) && is_string($extra['user']) && strlen($extra['user']) < 2048) {
            $u = json_decode($extra['user'], true);
            if (is_array($u) && isset($u['name']) && is_array($u['name'])) {
                $first = (string) ($u['name']['firstName'] ?? '');
                $last = (string) ($u['name']['lastName'] ?? '');
            }
        }

        return new Profile((string) $claims['sub'], (string) ($claims['email'] ?? ''), $verified, $first, $last);
    }
}
