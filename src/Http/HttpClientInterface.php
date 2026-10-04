<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Http;

interface HttpClientInterface
{
    /**
     * @param string               $method  GET|POST
     * @param string[]             $headers lignes « Nom: valeur »
     * @param array<string,string> $form    corps application/x-www-form-urlencoded (POST)
     */
    public function request(string $method, string $url, array $headers = [], array $form = []): HttpResponse;
}
