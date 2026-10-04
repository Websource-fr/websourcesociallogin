<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Settings;

/** Emplacements où les boutons « Continuer avec … » peuvent s'afficher. */
final class Placements
{
    const LOGIN = 'login';
    const REGISTER = 'register';
    const CHECKOUT = 'checkout';
    const HEADER = 'header';
    const WISHLIST = 'wishlist';

    /** @return string[] */
    public static function all(): array
    {
        return [self::LOGIN, self::REGISTER, self::CHECKOUT, self::HEADER, self::WISHLIST];
    }

    /** @return string[] */
    public static function defaults(): array
    {
        return [self::LOGIN, self::REGISTER, self::CHECKOUT, self::HEADER, self::WISHLIST];
    }

    /** @param mixed $raw @return string[] */
    public static function sanitize($raw): array
    {
        $list = is_array($raw) ? $raw : explode(',', (string) $raw);

        return array_values(array_intersect(self::all(), array_map('strval', $list)));
    }

    /** Présentation par emplacement : l'en-tête et les modales sont compacts. */
    public static function isCompact(string $place): bool
    {
        return $place === self::HEADER;
    }
}
