<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Support;

/**
 * Traductions du module (fr/en) : catalogues translations/catalog_<iso>.php (clé => texte).
 * Langue absente : repli anglais puis français.
 */
final class Translator
{
    /** @var string */
    private $dir;
    /** @var array<string,array<string,string>> */
    private $cache = [];

    public function __construct(string $translationsDir)
    {
        $this->dir = rtrim($translationsDir, '/');
    }

    /** @return array<string,string> */
    private function load(string $iso): array
    {
        $iso = preg_match('/^[a-z]{2}$/', $iso) ? $iso : 'fr';
        if (!isset($this->cache[$iso])) {
            $file = $this->dir . '/catalog_' . $iso . '.php';
            $this->cache[$iso] = is_file($file) ? (array) include $file : [];
        }

        return $this->cache[$iso];
    }

    /** @return array<string,string> catalogue complet pour une langue (avec replis) */
    public function all(string $iso): array
    {
        return $this->load($iso) + $this->load('en') + $this->load('fr');
    }

    /** @param array<string,string> $params */
    public function t(string $key, string $iso, array $params = []): string
    {
        $text = $this->load($iso)[$key] ?? $this->load('en')[$key] ?? $this->load('fr')[$key] ?? $key;
        foreach ($params as $k => $v) {
            $text = str_replace('{' . $k . '}', $v, $text);
        }

        return $text;
    }
}
