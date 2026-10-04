<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Support;

use Websource\SocialLogin\Security\Token;

/**
 * Deux cookies : « wssl_bind » (HttpOnly, aléatoire, lie le state à ce navigateur) et « wssl_last » (dernier réseau utilisé :
 * simple identifiant de réseau, aucune donnée personnelle).
 */
final class CookieJar
{
    const BIND = 'wssl_bind';
    const LAST = 'wssl_last';

    /** @param array<string,mixed> $cookies $_COOKIE */
    public static function bindValue(array $cookies): string
    {
        $v = isset($cookies[self::BIND]) && is_string($cookies[self::BIND]) ? $cookies[self::BIND] : '';

        return Token::looksValid($v) ? $v : '';
    }

    /**
     * Crée / renouvelle le cookie de liaison. SameSite=Lax, sauf « None » (HTTPS obligatoire) pour les réseaux qui répondent en POST (Apple).
     *
     * @param array<string,mixed> $cookies
     */
    public static function ensureBind(array $cookies, bool $https, bool $crossSitePost): string
    {
        $value = self::bindValue($cookies);
        if ($value === '') {
            $value = Token::random(32);
        }
        self::set(self::BIND, $value, time() + 3600, true, $https, $crossSitePost && $https ? 'None' : 'Lax');

        return $value;
    }

    public static function setLast(string $provider, bool $https): void
    {
        if (preg_match('/^[a-z0-9]{1,20}$/', $provider)) {
            self::set(self::LAST, $provider, time() + 86400 * 180, false, $https, 'Lax');
        }
    }

    /** @param array<string,mixed> $cookies */
    public static function last(array $cookies): ?string
    {
        $v = $cookies[self::LAST] ?? null;

        return is_string($v) && preg_match('/^[a-z0-9]{1,20}$/', $v) ? $v : null;
    }

    private static function set(string $name, string $value, int $expires, bool $httpOnly, bool $secure, string $sameSite): void
    {
        if (headers_sent()) {
            return;
        }
        if (PHP_VERSION_ID >= 70300) {
            setcookie($name, $value, ['expires' => $expires, 'path' => '/', 'secure' => $secure, 'httponly' => $httpOnly, 'samesite' => $sameSite]);
        } else {
            setcookie($name, $value, $expires, '/; samesite=' . $sameSite, '', $secure, $httpOnly);
        }
        $_COOKIE[$name] = $value;
    }
}
