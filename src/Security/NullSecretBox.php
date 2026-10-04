<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Security;

/** Sans chiffrement (tests). */
final class NullSecretBox implements SecretBoxInterface
{
    public function encrypt(string $plain): string
    {
        return $plain;
    }

    public function decrypt(string $stored): string
    {
        return $stored;
    }
}
