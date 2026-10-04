<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Admin;

use Websource\SocialLogin\Guide\GuideCatalog;
use Websource\SocialLogin\Render\IconLibrary;
use Websource\SocialLogin\Settings\Placements;
use Websource\SocialLogin\Settings\StyleSanitizer;

/**
 * Écran de configuration (HTML généré ici : toutes les valeurs dynamiques sont échappées ; aucun secret n'est rendu).
 */
final class ConfigScreen
{
    private static function e($v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }

    /**
     * @param array<string,mixed> $m modèle de vue (voir WebsourceSocialLogin::buildScreenModel)
     */
    public static function render(array $m): string
    {
        /** @var callable $t */
        $t = $m['t'];
        $e = [self::class, 'e'];
        $h = '<div class="wssl-bo" data-wssl-bo>';
        $h .= '<header class="wssl-bo__head"><div><h1>' . $e($t('bo_title')) . '</h1><p>' . $e($t('bo_subtitle')) . '</p></div>';
        $h .= '<div class="wssl-bo__shop"><span>' . $e($t('bo_shop')) . '</span> <strong>' . $e($m['shop_name']) . '</strong></div></header>';

        foreach ((array) $m['messages'] as $msg) {
            $h .= '<div class="wssl-bo__notice wssl-bo__notice--ok" role="status">' . $e($msg) . '</div>';
        }
        foreach ((array) $m['errors'] as $msg) {
            $h .= '<div class="wssl-bo__notice wssl-bo__notice--err" role="alert">' . $e($msg) . '</div>';
        }
        if (!$m['single_shop']) {
            $h .= '<div class="wssl-bo__notice wssl-bo__notice--warn">' . $e($t('bo_choose_shop')) . '</div></div>';

            return $h;
        }
        if (!$m['ssl_ok']) {
            $h .= '<div class="wssl-bo__notice wssl-bo__notice--warn">' . $e($t('bo_ssl_warning')) . '</div>';
        }

        $h .= '<nav class="wssl-bo__nav" aria-label="' . $e($t('bo_title')) . '"><a href="#wssl-networks">' . $e($t('bo_nav_networks')) . '</a><a href="#wssl-appearance">' . $e($t('bo_nav_appearance')) . '</a><a href="#wssl-texts">' . $e($t('bo_nav_texts')) . '</a><a href="#wssl-help">' . $e($t('bo_nav_help')) . '</a></nav>';
        $h .= '<form method="post" action="' . $e($m['action']) . '" autocomplete="off" class="wssl-bo__form">';
        $h .= '<input type="hidden" name="wssl_token" value="' . $e($m['token']) . '">';

        // --- Réseaux
        $h .= '<section id="wssl-networks" class="wssl-bo__section"><h2>' . $e($t('bo_networks')) . '</h2><p class="wssl-bo__lead">' . $e($t('bo_networks_lead')) . '</p>';
        foreach ($m['providers'] as $p) {
            $h .= self::providerCard($p, $t);
        }
        $h .= '</section>';

        // --- Apparence & emplacements
        $s = $m['style'];
        $h .= '<section id="wssl-appearance" class="wssl-bo__section"><h2>' . $e($t('bo_appearance')) . '</h2><div class="wssl-bo__cols"><div>';
        $h .= self::select('style[preset]', $t('bo_preset'), $s['preset'], ['outline' => $t('preset_outline'), 'brand' => $t('preset_brand'), 'neutral' => $t('preset_neutral')], 'preset');
        $h .= self::select('style[layout]', $t('bo_layout'), $s['layout'], ['stack' => $t('layout_stack'), 'grid' => $t('layout_grid')], 'layout');
        $h .= self::select('style[shape]', $t('bo_shape'), $s['shape'], ['square' => $t('shape_square'), 'rounded' => $t('shape_rounded'), 'pill' => $t('shape_pill')], 'shape');
        $h .= '<label class="wssl-bo__check"><input type="checkbox" name="style[icon_only]" value="1" data-wssl-style="icon_only"' . (!empty($s['icon_only']) ? ' checked' : '') . '> ' . $e($t('bo_icon_only')) . '</label>';
        $h .= '<div class="wssl-bo__row">' . self::input('style[radius]', $t('bo_radius'), (string) $s['radius'], 'number', 'radius', ' min="0" max="32" step="1" placeholder="auto"') . self::input('style[border]', $t('bo_border'), (string) $s['border'], 'number', 'border', ' min="0" max="4" step="1"') . '</div>';
        $h .= '<p class="wssl-bo__hint">' . $e($t('bo_colors_hint')) . '</p><div class="wssl-bo__row">';
        $h .= self::input('style[bg]', $t('bo_color_bg'), (string) $s['bg'], 'text', 'bg', ' placeholder="#ffffff" maxlength="40"');
        $h .= self::input('style[color]', $t('bo_color_fg'), (string) $s['color'], 'text', 'color', ' placeholder="#1a1a1a" maxlength="40"');
        $h .= self::input('style[border_color]', $t('bo_color_border'), (string) $s['border_color'], 'text', 'border_color', ' placeholder="rgba(0,0,0,.2)" maxlength="40"');
        $h .= '</div>';
        $h .= '<label class="wssl-bo__check"><input type="checkbox" name="separator" value="1"' . ($m['separator'] ? ' checked' : '') . '> ' . $e($t('bo_separator')) . '</label>';
        $h .= '<label class="wssl-bo__check"><input type="checkbox" name="remember" value="1"' . ($m['remember'] ? ' checked' : '') . '> ' . $e($t('bo_remember')) . '</label>';
        $h .= '</div><div><h3>' . $e($t('bo_preview')) . '</h3><div class="wssl-bo__preview" data-wssl-preview>' . $m['preview'] . '</div><p class="wssl-bo__hint">' . $e($t('bo_preview_hint')) . '</p>';
        $h .= '<h3>' . $e($t('bo_places')) . '</h3><p class="wssl-bo__hint">' . $e($t('bo_places_hint')) . '</p>';
        foreach (Placements::all() as $pl) {
            $h .= '<label class="wssl-bo__check"><input type="checkbox" name="places[]" value="' . $e($pl) . '"' . (in_array($pl, $m['places'], true) ? ' checked' : '') . '> ' . $e($t('place_' . $pl)) . '</label>';
        }
        $h .= '</div></div></section>';

        // --- Textes
        $h .= '<section id="wssl-texts" class="wssl-bo__section"><h2>' . $e($t('bo_texts')) . '</h2><div class="wssl-bo__cols"><div><h3>' . $e($t('bo_label')) . '</h3><p class="wssl-bo__hint">' . $e($t('bo_label_hint')) . '</p>';
        foreach ($m['langs'] as $l) {
            $h .= '<label class="wssl-bo__field"><span class="wssl-bo__lang">' . $e($l['iso']) . '</span><input type="text" maxlength="60" name="label[' . (int) $l['id'] . ']" value="' . $e($m['labels'][$l['id']] ?? '') . '" placeholder="' . $e($t('btn_continue_with', $l['iso'])) . '"></label>';
        }
        $h .= '</div><div><h3>' . $e($t('bo_gdpr')) . '</h3><p class="wssl-bo__hint">' . $e($t('bo_gdpr_hint')) . '</p>';
        foreach ($m['langs'] as $l) {
            $h .= '<label class="wssl-bo__field"><span class="wssl-bo__lang">' . $e($l['iso']) . '</span><textarea rows="3" maxlength="400" name="gdpr[' . (int) $l['id'] . ']" placeholder="' . $e($t('gdpr_default', $l['iso'])) . '">' . $e($m['gdpr'][$l['id']] ?? '') . '</textarea></label>';
        }
        $h .= '</div></div></section>';

        $h .= '<div class="wssl-bo__savebar"><button type="submit" name="wssl_save" value="1" class="wssl-bo__btn wssl-bo__btn--primary">' . $e($t('bo_save')) . '</button><span>' . $e($t('bo_save_hint')) . '</span></div></form>';

        // --- Aide commune
        $h .= '<section id="wssl-help" class="wssl-bo__section"><h2>' . $e($m['common']['title']) . '</h2>' . self::errorsList($m['common']['errors'], $m['vars_common'], $t) . '</section>';
        $h .= '<section class="wssl-bo__section wssl-bo__privacy"><h2>' . $e($t('bo_privacy_title')) . '</h2><p>' . $e($t('bo_privacy_text')) . '</p></section>';

        return $h . '</div>';
    }

    private static function select(string $name, string $label, string $value, array $opts, string $key): string
    {
        $h = '<label class="wssl-bo__field"><span>' . self::e($label) . '</span><select name="' . self::e($name) . '" data-wssl-style="' . self::e($key) . '">';
        foreach ($opts as $k => $v) {
            $h .= '<option value="' . self::e($k) . '"' . ($k === $value ? ' selected' : '') . '>' . self::e($v) . '</option>';
        }

        return $h . '</select></label>';
    }

    private static function input(string $name, string $label, string $value, string $type, string $key, string $extra = ''): string
    {
        return '<label class="wssl-bo__field"><span>' . self::e($label) . '</span><input type="' . self::e($type) . '" name="' . self::e($name) . '" value="' . self::e($value) . '" data-wssl-style="' . self::e($key) . '"' . $extra . '></label>';
    }

    /** @param array<int,array<int,string>> $errors */
    private static function errorsList(array $errors, array $vars, callable $t): string
    {
        $h = '<dl class="wssl-guide__errors">';
        foreach ($errors as $err) {
            $h .= '<dt>' . GuideCatalog::fill($err[0], $vars, false, $t('bo_copy')) . '</dt><dd>' . GuideCatalog::fill($err[1], $vars, true, $t('bo_copy')) . '</dd>';
        }

        return $h . '</dl>';
    }

    /** @param array<string,mixed> $p */
    private static function providerCard(array $p, callable $t): string
    {
        $e = [self::class, 'e'];
        $id = $p['id'];
        $status = $p['status'];
        $h = '<article class="wssl-card wssl-card--' . $e($status) . '" id="wssl-p-' . $e($id) . '">';
        $h .= '<header class="wssl-card__head"><span class="wssl-card__logo wssl-card__logo--' . $e($id) . '">' . IconLibrary::svg($id, 'wssl-card__icon') . '</span><div class="wssl-card__title"><h3>' . $e($p['label']) . '</h3>';
        $h .= '<span class="wssl-badge wssl-badge--' . $e($status) . '">' . $e($t('status_' . $status)) . '</span>';
        if ($status === 'incomplete' && $p['missing']) {
            $h .= '<span class="wssl-card__missing">' . $e($t('bo_missing')) . ' ' . $e(implode(', ', array_map(function ($f) use ($t, $id) {
                return $t('field_' . $id . '_' . $f) !== 'field_' . $id . '_' . $f ? $t('field_' . $id . '_' . $f) : $t('field_' . $f);
            }, $p['missing']))) . '</span>';
        }
        $h .= '</div><label class="wssl-switch"><input type="checkbox" name="p[' . $e($id) . '][enabled]" value="1"' . ($p['enabled'] ? ' checked' : '') . '><span class="wssl-switch__track" aria-hidden="true"></span><span class="wssl-switch__text">' . $e($t('bo_enable')) . '</span></label></header>';

        $h .= '<div class="wssl-card__body"><div class="wssl-card__fields">';
        foreach ($p['fields'] as $name => $f) {
            $lk = 'field_' . $id . '_' . $name;
            $label = $t($lk) !== $lk ? $t($lk) : $t('field_' . $name);
            $nm = 'p[' . $id . '][' . $name . ']';
            $h .= '<div class="wssl-bo__field"><label for="wssl-' . $e($id . '-' . $name) . '"><span>' . $e($label) . ($f['required'] ? ' *' : '') . '</span></label>';
            if ($f['type'] === 'secret') {
                $h .= '<input type="password" id="wssl-' . $e($id . '-' . $name) . '" name="' . $e($nm) . '" value="" autocomplete="new-password" spellcheck="false" placeholder="' . $e($f['filled'] ? $t('bo_secret_saved') : $t('bo_secret_paste')) . '">';
            } elseif ($f['type'] === 'secret_textarea') {
                $h .= '<textarea id="wssl-' . $e($id . '-' . $name) . '" name="' . $e($nm) . '" rows="4" spellcheck="false" autocomplete="off" placeholder="' . $e($f['filled'] ? $t('bo_secret_saved') : $t('bo_key_paste')) . '"></textarea>';
            } else {
                $h .= '<input type="text" id="wssl-' . $e($id . '-' . $name) . '" name="' . $e($nm) . '" value="' . $e($f['value']) . '" autocomplete="off" spellcheck="false" maxlength="255">';
            }
            if (($f['type'] === 'secret' || $f['type'] === 'secret_textarea') && $f['filled']) {
                $h .= '<label class="wssl-bo__check wssl-bo__check--small"><input type="checkbox" name="p[' . $e($id) . '][clear][' . $e($name) . ']" value="1"> ' . $e($t('bo_secret_clear')) . '</label>';
            }
            $h .= '</div>';
        }
        $h .= '<div class="wssl-bo__field"><label for="wssl-' . $e($id) . '-scopes"><span>' . $e($t('bo_scopes')) . '</span></label><input type="text" id="wssl-' . $e($id) . '-scopes" name="p[' . $e($id) . '][scopes]" value="' . $e($p['scopes']) . '" maxlength="300" spellcheck="false"><small>' . $e($t('bo_scopes_hint')) . ' <code>' . $e($p['default_scopes']) . '</code></small></div>';
        $h .= '<div class="wssl-bo__field wssl-bo__field--pos"><label for="wssl-' . $e($id) . '-pos"><span>' . $e($t('bo_position')) . '</span></label><input type="number" id="wssl-' . $e($id) . '-pos" name="p[' . $e($id) . '][position]" value="' . (int) $p['position'] . '" min="1" max="99"></div>';
        $h .= '</div><aside class="wssl-card__side"><div class="wssl-bo__callback"><span class="wssl-bo__label">' . $e($t('bo_callback')) . '</span>' . GuideCatalog::fill('{callback}', ['callback' => $p['callback']], true, $t('bo_copy')) . '<small>' . $e($t('bo_callback_hint')) . '</small></div>';
        $h .= '<div class="wssl-card__meta"><span>' . $e($p['pkce'] ? $t('bo_pkce_on') : $t('bo_pkce_off')) . '</span><span>' . $e($t($p['oidc'] ? 'bo_oidc' : 'bo_oauth2')) . '</span></div>';
        $h .= '<button type="submit" name="wssl_test" value="' . $e($id) . '" class="wssl-bo__btn">' . $e($t('bo_test')) . '</button>';
        if ($p['test'] !== null) {
            $h .= '<ul class="wssl-test" role="status">';
            foreach ($p['test'] as $c) {
                $label = $c['key'] === 'field' ? $t('test_field') . ' ' . ($t('field_' . $id . '_' . $c['detail']) !== 'field_' . $id . '_' . $c['detail'] ? $t('field_' . $id . '_' . $c['detail']) : $t('field_' . $c['detail'])) : $t('test_' . $c['key']);
                $h .= '<li class="' . ($c['ok'] ? 'is-ok' : 'is-ko') . '"><span aria-hidden="true">' . ($c['ok'] ? '✔' : '✖') . '</span> ' . $e($label) . '</li>';
            }
            $h .= '</ul>';
        }
        $h .= '</aside></div>';

        // guide
        $g = $p['guide'];
        if ($g) {
            $vars = $p['vars'];
            $h .= '<details class="wssl-guide"><summary>' . $e($t('bo_guide')) . ' ' . $e($p['label']) . '</summary><div class="wssl-guide__body">';
            $h .= '<p class="wssl-guide__intro">' . GuideCatalog::fill($g['intro'], $vars, true, $t('bo_copy')) . '</p>';
            $h .= '<p><a class="wssl-bo__btn wssl-bo__btn--link" href="' . $e($g['console'][1]) . '" target="_blank" rel="noopener noreferrer">' . $e($t('bo_open_console')) . ' : ' . $e($g['console'][0]) . ' ↗</a></p><ol class="wssl-steps">';
            foreach ($g['steps'] as $i => $step) {
                $h .= '<li><details class="wssl-step"' . ($i === 0 ? ' open' : '') . '><summary><span class="wssl-step__n">' . ($i + 1) . '</span><span class="wssl-step__t">' . $e($step['title']) . '</span></summary><div class="wssl-step__body">';
                $h .= '<p class="wssl-step__path" aria-label="' . $e($t('bo_where')) . '"><span aria-hidden="true">▸</span> ' . $e($step['path']) . '</p>';
                $h .= '<p>' . GuideCatalog::fill($step['body'], $vars, true, $t('bo_copy')) . '</p>';
                if (!empty($step['fields'])) {
                    $h .= '<table class="wssl-step__fields"><tbody>';
                    foreach ($step['fields'] as $f) {
                        $h .= '<tr><th scope="row">' . $e($f[0]) . '</th><td>' . GuideCatalog::fill($f[1], $vars, false, $t('bo_copy')) . '</td></tr>';
                    }
                    $h .= '</tbody></table>';
                }
                $h .= '</div></details></li>';
            }
            $h .= '</ol><p class="wssl-guide__scopes">' . GuideCatalog::fill($g['scopes'], $vars, true, $t('bo_copy')) . '</p>';
            $h .= '<h4>' . $e($t('bo_errors_title')) . '</h4>' . self::errorsList($g['errors'], $vars, $t);
            $h .= '<p class="wssl-guide__links"><strong>' . $e($t('bo_links')) . ' :</strong> ';
            foreach ($g['links'] as $i => $l) {
                $h .= ($i ? ' · ' : '') . '<a href="' . $e($l[1]) . '" target="_blank" rel="noopener noreferrer">' . $e($l[0]) . '</a>';
            }
            $h .= '</p></div></details>';
        }

        return $h . '</article>';
    }
}
