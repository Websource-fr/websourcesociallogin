<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Security;

/** PKCE (RFC 7636), méthode S256. */
final class Pkce
{
    public static function verifier(): string
    {
        // 64 octets aléatoires => 86 caractères base64url (plage autorisée : 43 à 128)
        return Base64Url::encode(random_bytes(64));
    }

    public static function challenge(string $verifier): string
    {
        return Base64Url::encode(hash('sha256', $verifier, true));
    }

    public static function isValidVerifier(string $verifier): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9\-._~]{43,128}$/', $verifier);
    }
}
