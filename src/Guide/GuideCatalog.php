<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Guide;

/**
 * Guides pas à pas intégrés (fr / en). Le contenu est écrit par nous (HTML de confiance restreint) ;
 * les valeurs dynamiques (URL de rappel, domaine…) sont toujours échappées avant insertion.
 */
final class GuideCatalog
{
    /** @var string */
    private $dir;
    /** @var array<string,array<string,mixed>> */
    private $cache = [];

    public function __construct(?string $dir = null)
    {
        $this->dir = $dir ?? __DIR__;
    }

    /** @return array<string,mixed> */
    public function data(string $iso): array
    {
        $iso = $iso === 'en' ? 'en' : 'fr';
        if (!isset($this->cache[$iso])) {
            $file = $this->dir . '/data_' . $iso . '.php';
            $this->cache[$iso] = is_file($file) ? (array) include $file : [];
            if ($this->cache[$iso] === [] && $iso !== 'fr') {
                $this->cache[$iso] = (array) include $this->dir . '/data_fr.php';
            }
        }

        return $this->cache[$iso];
    }

    /** @return array<string,mixed>|null */
    public function guide(string $providerId, string $iso): ?array
    {
        $d = $this->data($iso);

        return isset($d[$providerId]) && $providerId[0] !== '_' ? $d[$providerId] : null;
    }

    /** @return array<string,mixed> */
    public function common(string $iso): array
    {
        return $this->data($iso)['_common'] ?? ['title' => '', 'errors' => []];
    }

    /**
     * Remplace les variables. $html = true : texte HTML de confiance ; false : texte brut échappé ici.
     *
     * @param array<string,string> $vars callback, origin, domain, scopes (valeurs brutes)
     */
    public static function fill(string $text, array $vars, bool $html, string $copyLabel = 'Copier'): string
    {
        if (!$html) {
            $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }
        $esc = function (string $s): string {
            return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        };
        $repl = [];
        $cb = $vars['callback'] ?? '';
        $repl['{callback}'] = '<span class="wssl-copy"><code>' . $esc($cb) . '</code><button type="button" class="wssl-copy__btn" data-wssl-copy="' . $esc($cb) . '">' . $esc($copyLabel) . '</button></span>';
        foreach (['origin', 'domain', 'scopes'] as $k) {
            $v = $esc($vars[$k] ?? '');
            $repl['{' . $k . '}'] = $html ? '<code>' . $v . '</code>' : $v;
        }

        return strtr($text, $repl);
    }
}
