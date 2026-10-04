<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Security;

interface JwksProviderInterface
{
    /**
     * @return array<int,array<string,mixed>> clés JWK
     *
     * @throws \Websource\SocialLogin\Provider\ProviderException
     */
    public function keys(string $jwksUrl, bool $forceRefresh = false): array;
}
