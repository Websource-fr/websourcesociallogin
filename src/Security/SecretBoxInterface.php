<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Security;

interface SecretBoxInterface
{
    public function encrypt(string $plain): string;

    public function decrypt(string $stored): string;
}
