<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Http;

use Websource\SocialLogin\Provider\ProviderException;

/**
 * Client HTTP minimal en cURL : HTTPS obligatoire, vérification TLS, délais courts, aucune redirection suivie.
 */
final class CurlHttpClient implements HttpClientInterface
{
    public function request(string $method, string $url, array $headers = [], array $form = []): HttpResponse
    {
        if (stripos($url, 'https://') !== 0) {
            throw new ProviderException('http_insecure');
        }
        if (!function_exists('curl_init')) {
            throw new ProviderException('curl_missing');
        }
        $ch = curl_init($url);
        $headers[] = 'Accept: application/json';
        $headers[] = 'User-Agent: WebsourceSocialLogin/1.0';
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ];
        if (strtoupper($method) === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = http_build_query($form, '', '&');
        }
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $failed = $body === false;
        curl_close($ch);
        if ($failed) {
            throw new ProviderException('http_unreachable');
        }

        return new HttpResponse($status, (string) $body);
    }
}
