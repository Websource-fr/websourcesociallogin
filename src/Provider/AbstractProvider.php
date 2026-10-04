<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Provider;

use Websource\SocialLogin\Http\HttpClientInterface;

abstract class AbstractProvider implements ProviderInterface
{
    public function fields(): array
    {
        return [
            'client_id' => ['type' => 'text', 'required' => true, 'default' => ''],
            'client_secret' => ['type' => 'secret', 'required' => true, 'default' => ''],
        ];
    }

    public function usesFormPost(): bool
    {
        return false;
    }

    /** @param array<string,string> $params */
    protected function url(string $endpoint, array $params): string
    {
        return $endpoint . (strpos($endpoint, '?') === false ? '?' : '&') . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @param string[]             $headers
     * @param array<string,string> $form
     *
     * @return array<string,mixed>
     */
    protected function postForm(HttpClientInterface $http, string $url, array $form, array $headers = []): array
    {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $res = $http->request('POST', $url, $headers, $form);
        $data = $res->json();
        if (!$res->ok() || isset($data['error']) || empty($data['access_token'] ?? $data['id_token'] ?? null)) {
            throw new ProviderException('token_exchange_failed');
        }

        return $data;
    }

    /** @return array<string,mixed> */
    protected function getJson(HttpClientInterface $http, string $url, string $accessToken, array $more = []): array
    {
        $res = $http->request('GET', $url, array_merge(['Authorization: Bearer ' . $accessToken], $more));
        if (!$res->ok()) {
            throw new ProviderException('profile_unavailable');
        }
        $data = $res->json();
        if ($data === [] && trim($res->body()) !== '[]') {
            throw new ProviderException('profile_unavailable');
        }

        return $data;
    }

    /** @return array{0:string,1:string} */
    protected function splitName(string $full): array
    {
        $full = trim(preg_replace('/\s+/u', ' ', $full) ?? '');
        if ($full === '') {
            return ['', ''];
        }
        $parts = explode(' ', $full, 2);

        return [$parts[0], $parts[1] ?? ''];
    }

    protected function scopeList(string $scopes, string $sep = ' '): string
    {
        $list = preg_split('/[\s,]+/', trim($scopes), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode($sep, array_unique($list));
    }
}
