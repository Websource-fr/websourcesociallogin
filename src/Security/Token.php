<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Security;

/** Jetons aléatoires (state, nonce, jetons de formulaire). */
final class Token
{
    public static function random(int $bytes = 32): string
    {
        return Base64Url::encode(random_bytes($bytes));
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function looksValid(string $token): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_\-]{20,128}$/', $token);
    }
}
