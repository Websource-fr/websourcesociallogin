<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Linking;

/** Implémentation en mémoire (tests). */
final class MemoryLinkRepository implements LinkRepositoryInterface
{
    /** @var array<int,array<string,mixed>> */
    public $rows = [];

    public function findCustomerId(string $provider, string $providerUserId, int $idShop): ?int
    {
        foreach ($this->rows as $r) {
            if ($r['provider'] === $provider && $r['uid'] === $providerUserId && $r['shop'] === $idShop) {
                return $r['customer'];
            }
        }

        return null;
    }

    public function add(int $idCustomer, string $provider, string $providerUserId, string $email, int $idShop): void
    {
        $this->rows[] = ['customer' => $idCustomer, 'provider' => $provider, 'uid' => $providerUserId, 'email' => $email, 'shop' => $idShop];
    }

    public function remove(int $idCustomer, string $provider): bool
    {
        $n = count($this->rows);
        $this->rows = array_values(array_filter($this->rows, function ($r) use ($idCustomer, $provider) {
            return !($r['customer'] === $idCustomer && $r['provider'] === $provider);
        }));

        return count($this->rows) < $n;
    }

    public function hasLink(int $idCustomer, string $provider): bool
    {
        foreach ($this->rows as $r) {
            if ($r['customer'] === $idCustomer && $r['provider'] === $provider) {
                return true;
            }
        }

        return false;
    }

    public function touch(string $provider, string $providerUserId, int $idShop): void
    {
    }

    public function listForCustomer(int $idCustomer): array
    {
        return [];
    }

    public function deleteForCustomer(int $idCustomer): void
    {
        $this->rows = array_values(array_filter($this->rows, function ($r) use ($idCustomer) {
            return $r['customer'] !== $idCustomer;
        }));
    }
}
