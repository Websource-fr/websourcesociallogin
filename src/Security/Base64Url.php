<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Security;

final class Base64Url
{
    public static function encode(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    public static function decode(string $text): string
    {
        if ($text === '' || !preg_match('/^[A-Za-z0-9_\-]+$/', $text)) {
            return '';
        }
        $pad = strlen($text) % 4;
        if ($pad) {
            $text .= str_repeat('=', 4 - $pad);
        }
        $out = base64_decode(strtr($text, '-_', '+/'), true);

        return $out === false ? '' : $out;
    }
}
