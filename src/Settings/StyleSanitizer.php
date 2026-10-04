<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Settings;

/** Réglages de style des boutons : liste blanche pour les choix, bornes pour les nombres, ColorValidator pour les couleurs. */
final class StyleSanitizer
{
    const PRESETS = ['outline', 'brand', 'neutral'];
    const LAYOUTS = ['stack', 'grid'];
    const SHAPES = ['square', 'rounded', 'pill'];

    /** @return array<string,mixed> */
    public static function defaults(): array
    {
        return ['preset' => 'outline', 'layout' => 'stack', 'shape' => 'rounded', 'radius' => '', 'border' => 1, 'bg' => '', 'color' => '', 'border_color' => '', 'icon_only' => false];
    }

    /**
     * @param array<string,mixed> $in
     *
     * @return array{style:array<string,mixed>,errors:string[]} champs invalides remplacés par la valeur par défaut
     */
    public static function sanitize(array $in): array
    {
        $d = self::defaults();
        $errors = [];
        $out = [];
        $out['preset'] = in_array($in['preset'] ?? '', self::PRESETS, true) ? $in['preset'] : $d['preset'];
        $out['layout'] = in_array($in['layout'] ?? '', self::LAYOUTS, true) ? $in['layout'] : $d['layout'];
        $out['shape'] = in_array($in['shape'] ?? '', self::SHAPES, true) ? $in['shape'] : $d['shape'];
        $out['icon_only'] = !empty($in['icon_only']) && $in['icon_only'] !== '0';

        $radius = trim((string) ($in['radius'] ?? ''));
        if ($radius === '') {
            $out['radius'] = '';
        } elseif (preg_match('/^\d{1,2}$/', $radius) && (int) $radius <= 32) {
            $out['radius'] = (int) $radius;
        } else {
            $out['radius'] = '';
            $errors[] = 'radius';
        }
        $border = trim((string) ($in['border'] ?? '1'));
        if (preg_match('/^[0-4]$/', $border)) {
            $out['border'] = (int) $border;
        } else {
            $out['border'] = 1;
            $errors[] = 'border';
        }
        foreach (['bg', 'color', 'border_color'] as $k) {
            $c = ColorValidator::normalize((string) ($in[$k] ?? ''));
            if ($c === null) {
                $errors[] = $k;
                $c = '';
            }
            $out[$k] = $c;
        }

        return ['style' => $out, 'errors' => $errors];
    }
}
