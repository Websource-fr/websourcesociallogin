<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Linking;

interface LinkRepositoryInterface
{
    public function findCustomerId(string $provider, string $providerUserId, int $idShop): ?int;

    public function add(int $idCustomer, string $provider, string $providerUserId, string $email, int $idShop): void;

    public function remove(int $idCustomer, string $provider): bool;

    public function hasLink(int $idCustomer, string $provider): bool;

    public function touch(string $provider, string $providerUserId, int $idShop): void;

    /** @return array<int,array<string,string>> */
    public function listForCustomer(int $idCustomer): array;

    public function deleteForCustomer(int $idCustomer): void;
}
