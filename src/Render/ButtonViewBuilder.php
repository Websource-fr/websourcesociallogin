<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Render;

use Websource\SocialLogin\Provider\ProviderInterface;
use Websource\SocialLogin\Settings\Placements;
use Websource\SocialLogin\Settings\Settings;
use Websource\SocialLogin\Settings\StyleSanitizer;
use Websource\SocialLogin\Support\Translator;

/** Construit les données (déjà prêtes à échapper) du bloc de boutons « Continuer avec … ». */
final class ButtonViewBuilder
{
    /** @var Translator */
    private $tr;

    public function __construct(Translator $tr)
    {
        $this->tr = $tr;
    }

    /**
     * @param ProviderInterface[] $providers fournisseurs actifs dans l'ordre d'affichage
     * @param callable           $urlFor     fn(string $providerId): string
     *
     * @return array<string,mixed>|null null si rien à afficher
     */
    public function build(Settings $settings, array $providers, string $place, string $iso, string $customLabel, string $gdpr, callable $urlFor, ?string $last): ?array
    {
        if ($providers === []) {
            return null;
        }
        $style = $settings->style();
        $compact = Placements::isCompact($place);
        $layout = $compact ? 'grid' : $style['layout'];
        $iconOnly = $compact || $layout === 'grid' || !empty($style['icon_only']);
        $template = $customLabel !== '' ? $customLabel : $this->tr->t('btn_continue_with', $iso);

        $buttons = [];
        foreach ($providers as $p) {
            $name = $p->id() === 'x' ? 'X' : $p->label();
            $label = str_replace('{network}', $name, $template);
            if (strpos($template, '{network}') === false) {
                $label = trim($template . ' ' . $name);
            }
            $isLast = $last !== null && $last === $p->id();
            $buttons[] = [
                'id' => $p->id(),
                'label' => $label,
                'aria' => $label . ($isLast ? ' (' . $this->tr->t('last_used', $iso) . ')' : ''),
                'url' => $urlFor($p->id()),
                'icon' => IconLibrary::svg($p->id()),
                'multicolor' => IconLibrary::isMulticolor($p->id()),
                'last' => $isLast,
            ];
        }
        $classes = ['wssl', 'wssl--' . $style['preset'], 'wssl--' . $layout, 'wssl--shape-' . $style['shape'], 'wssl--place-' . $place];
        if ($iconOnly) {
            $classes[] = 'wssl--icononly';
        }
        if ($compact) {
            $classes[] = 'wssl--compact';
        }

        return [
            'place' => $place,
            'class' => implode(' ', $classes),
            'style' => self::cssVars($style),
            'buttons' => $buttons,
            'separator' => $settings->separator() && in_array($place, [Placements::LOGIN, Placements::REGISTER, Placements::CHECKOUT], true),
            'separator_pos' => $place === Placements::LOGIN ? 'before' : 'after',
            'separator_text' => $this->tr->t('separator_or', $iso),
            'gdpr' => $compact ? '' : $gdpr,
            'last_label' => $this->tr->t('last_used', $iso),
            'title' => $this->tr->t('social_title', $iso),
        ];
    }

    /** @param array<string,mixed> $style style déjà validé : seules des valeurs sûres sont émises */
    public static function cssVars(array $style): string
    {
        $style = StyleSanitizer::sanitize($style)['style'];
        $radius = $style['radius'] !== '' ? (int) $style['radius'] . 'px' : ['square' => '2px', 'rounded' => 'var(--radius-xl, .75rem)', 'pill' => '999px'][$style['shape']];
        $v = ['--wssl-radius:' . $radius, '--wssl-border-w:' . (int) $style['border'] . 'px'];
        foreach (['bg' => '--wssl-bg', 'color' => '--wssl-fg', 'border_color' => '--wssl-bc'] as $k => $var) {
            if ($style[$k] !== '') {
                $v[] = $var . ':' . $style[$k];
            }
        }

        return implode(';', $v);
    }
}
