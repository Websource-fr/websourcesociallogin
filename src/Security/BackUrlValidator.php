<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Security;

/**
 * Liste blanche de redirection après connexion : chemin relatif du site ou URL absolue d'un domaine de la boutique.
 * Tout le reste (autre domaine, //hote, schémas javascript:/data:, antislash, caractères de contrôle) est refusé.
 */
final class BackUrlValidator
{
    /** @var string[] */
    private $hosts;

    /** @param string[] $allowedHosts */
    public function __construct(array $allowedHosts)
    {
        $this->hosts = array_values(array_filter(array_map('strtolower', $allowedHosts)));
    }

    /** Renvoie l'adresse nettoyée, ou '' si refusée. */
    public function sanitize(string $url): string
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x1f\x7f\\\\]/', $url)) {
            return '';
        }
        if (preg_match('#^/(?!/)#', $url)) {
            return $url;
        }
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return '';
        }
        if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return '';
        }
        if (!in_array(strtolower($parts['host']), $this->hosts, true)) {
            return '';
        }

        return $url;
    }
}
