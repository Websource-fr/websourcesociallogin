<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Settings;

/**
 * Validation stricte des couleurs saisies en BO : #rgb, #rgba, #rrggbb, #rrggbbaa, rgb(), rgba() (alpha 0 à 1),
 * « transparent » et var(--nom) du thème. Tout autre texte est refusé (aucune injection CSS possible).
 */
final class ColorValidator
{
    /** @return string|null couleur normalisée, '' si vide, null si invalide */
    public static function normalize(string $value): ?string
    {
        $v = trim($value);
        if ($v === '') {
            return '';
        }
        if (strlen($v) > 40) {
            return null;
        }
        if (preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $v)) {
            return strtolower($v);
        }
        if (strtolower($v) === 'transparent') {
            return 'transparent';
        }
        if (preg_match('/^var\(--[a-z0-9-]{1,40}\)$/', $v)) {
            return $v;
        }
        if (preg_match('/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*(?:,\s*(0|1|0?\.\d{1,3}|1\.0{1,3})\s*)?\)$/i', $v, $m)) {
            if ((int) $m[1] > 255 || (int) $m[2] > 255 || (int) $m[3] > 255) {
                return null;
            }
            $alpha = $m[4] ?? null;
            if ($alpha !== null && (float) $alpha > 1) {
                return null;
            }

            return $alpha === null ? sprintf('rgb(%d, %d, %d)', $m[1], $m[2], $m[3]) : sprintf('rgba(%d, %d, %d, %s)', $m[1], $m[2], $m[3], $alpha);
        }

        return null;
    }
}
