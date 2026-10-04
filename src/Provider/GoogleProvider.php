<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Provider;

final class GoogleProvider extends OidcProvider
{
    public function id(): string
    {
        return 'google';
    }

    public function label(): string
    {
        return 'Google';
    }

    public function defaultScopes(): string
    {
        return 'openid email profile';
    }

    public function usesPkce(): bool
    {
        return true;
    }

    protected function authorizeEndpoint(array $cred): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth';
    }

    protected function tokenEndpoint(array $cred): string
    {
        return 'https://oauth2.googleapis.com/token';
    }

    protected function jwksUrl(array $cred): string
    {
        return 'https://www.googleapis.com/oauth2/v3/certs';
    }

    protected function issuer(array $cred)
    {
        return function (array $claims): bool {
            return in_array((string) ($claims['iss'] ?? ''), ['https://accounts.google.com', 'accounts.google.com'], true);
        };
    }

    protected function authorizeExtra(): array
    {
        return ['prompt' => 'select_account'];
    }

    protected function profileFromClaims(array $claims, array $extra): Profile
    {
        $verified = ($claims['email_verified'] ?? false) === true || ($claims['email_verified'] ?? '') === 'true';

        return new Profile((string) $claims['sub'], (string) ($claims['email'] ?? ''), $verified, (string) ($claims['given_name'] ?? ''), (string) ($claims['family_name'] ?? ''));
    }
}
