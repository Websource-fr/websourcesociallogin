<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Provider;

/**
 * Microsoft Entra ID (comptes professionnels, scolaires et personnels Outlook/Microsoft).
 * L'e-mail Microsoft n'est jamais considéré comme vérifié, sauf si la revendication optionnelle « xms_edov » le certifie :
 * c'est la protection contre l'attaque « nOAuth ». Sinon, le module demande une confirmation par e-mail.
 */
final class MicrosoftProvider extends OidcProvider
{
    public function id(): string
    {
        return 'microsoft';
    }

    public function label(): string
    {
        return 'Microsoft';
    }

    public function fields(): array
    {
        return parent::fields() + ['tenant' => ['type' => 'text', 'required' => false, 'default' => 'common']];
    }

    public function defaultScopes(): string
    {
        return 'openid profile email';
    }

    public function usesPkce(): bool
    {
        return true;
    }

    /** @param array<string,string> $cred */
    private function tenant(array $cred): string
    {
        $t = trim((string) ($cred['tenant'] ?? ''));

        return preg_match('/^(common|organizations|consumers|[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})$/', $t) ? strtolower($t) : 'common';
    }

    protected function authorizeEndpoint(array $cred): string
    {
        return 'https://login.microsoftonline.com/' . $this->tenant($cred) . '/oauth2/v2.0/authorize';
    }

    protected function tokenEndpoint(array $cred): string
    {
        return 'https://login.microsoftonline.com/' . $this->tenant($cred) . '/oauth2/v2.0/token';
    }

    protected function jwksUrl(array $cred): string
    {
        return 'https://login.microsoftonline.com/' . $this->tenant($cred) . '/discovery/v2.0/keys';
    }

    protected function issuer(array $cred)
    {
        $tenant = $this->tenant($cred);

        return function (array $claims) use ($tenant): bool {
            $tid = strtolower((string) ($claims['tid'] ?? ''));
            if (!preg_match('/^[0-9a-f\-]{36}$/', $tid)) {
                return false;
            }
            if (preg_match('/^[0-9a-f\-]{36}$/', $tenant) && $tenant !== $tid) {
                return false;
            }

            return hash_equals('https://login.microsoftonline.com/' . $tid . '/v2.0', (string) ($claims['iss'] ?? ''));
        };
    }

    protected function authorizeExtra(): array
    {
        return ['prompt' => 'select_account', 'response_mode' => 'query'];
    }

    protected function profileFromClaims(array $claims, array $extra): Profile
    {
        $email = (string) ($claims['email'] ?? '');
        if ($email === '' && filter_var($claims['preferred_username'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $email = (string) $claims['preferred_username'];
        }
        $verified = ($claims['xms_edov'] ?? false) === true || ($claims['xms_edov'] ?? '') === '1' || ($claims['xms_edov'] ?? '') === 'true' || ($claims['xms_edov'] ?? null) === 1;
        $first = (string) ($claims['given_name'] ?? '');
        $last = (string) ($claims['family_name'] ?? '');
        if ($first === '' && $last === '') {
            [$first, $last] = $this->splitName((string) ($claims['name'] ?? ''));
        }
        // identifiant stable : tenant + objet, pour ne jamais confondre deux annuaires
        $id = (string) ($claims['tid'] ?? '') . '.' . (string) ($claims['oid'] ?? $claims['sub']);

        return new Profile($id, $email, $verified, $first, $last);
    }
}
