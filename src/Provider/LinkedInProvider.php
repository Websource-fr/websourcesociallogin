<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Provider;

/** « Sign In with LinkedIn using OpenID Connect ». */
final class LinkedInProvider extends OidcProvider
{
    public function id(): string
    {
        return 'linkedin';
    }

    public function label(): string
    {
        return 'LinkedIn';
    }

    public function defaultScopes(): string
    {
        return 'openid profile email';
    }

    public function usesPkce(): bool
    {
        // PKCE n'est proposé par LinkedIn que pour certains types d'applications : le secret client protège ce flux.
        return false;
    }

    protected function authorizeEndpoint(array $cred): string
    {
        return 'https://www.linkedin.com/oauth/v2/authorization';
    }

    protected function tokenEndpoint(array $cred): string
    {
        return 'https://www.linkedin.com/oauth/v2/accessToken';
    }

    protected function jwksUrl(array $cred): string
    {
        return 'https://www.linkedin.com/oauth/openid/jwks';
    }

    protected function issuer(array $cred)
    {
        return 'https://www.linkedin.com';
    }

    protected function profileFromClaims(array $claims, array $extra): Profile
    {
        $verified = ($claims['email_verified'] ?? false) === true || ($claims['email_verified'] ?? '') === 'true';

        return new Profile((string) $claims['sub'], (string) ($claims['email'] ?? ''), $verified, (string) ($claims['given_name'] ?? ''), (string) ($claims['family_name'] ?? ''));
    }
}
