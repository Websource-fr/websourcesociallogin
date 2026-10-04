<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Security;

use Websource\SocialLogin\Http\HttpClientInterface;
use Websource\SocialLogin\Provider\ProviderException;

/** Récupère les clés publiques d'un fournisseur (JWKS) avec un cache fichier de 6 h. */
final class HttpJwksProvider implements JwksProviderInterface
{
    const TTL = 21600;

    /** @var HttpClientInterface */
    private $http;
    /** @var string */
    private $dir;

    public function __construct(HttpClientInterface $http, ?string $cacheDir = null)
    {
        $this->http = $http;
        $base = $cacheDir ?? (defined('_PS_CACHE_DIR_') ? rtrim((string) _PS_CACHE_DIR_, '/') : sys_get_temp_dir());
        $this->dir = $base . '/websourcesociallogin';
    }

    public function keys(string $jwksUrl, bool $forceRefresh = false): array
    {
        $file = $this->dir . '/jwks_' . hash('sha256', $jwksUrl) . '.json';
        if (!$forceRefresh && is_file($file) && filemtime($file) > time() - self::TTL) {
            $cached = json_decode((string) file_get_contents($file), true);
            if (isset($cached['keys']) && is_array($cached['keys'])) {
                return $cached['keys'];
            }
        }
        $res = $this->http->request('GET', $jwksUrl);
        $data = $res->json();
        if (!$res->ok() || !isset($data['keys']) || !is_array($data['keys'])) {
            throw new ProviderException('jwks_unavailable');
        }
        if (is_dir($this->dir) || @mkdir($this->dir, 0755, true)) {
            @file_put_contents($file, (string) json_encode(['keys' => $data['keys']]), LOCK_EX);
        }

        return $data['keys'];
    }
}
