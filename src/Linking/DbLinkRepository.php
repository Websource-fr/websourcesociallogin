<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Linking;

/** Table websourcesociallogin_link (multiboutique : unique par fournisseur + utilisateur + boutique). */
final class DbLinkRepository implements LinkRepositoryInterface
{
    const TABLE = 'websourcesociallogin_link';

    private function t(): string
    {
        return _DB_PREFIX_ . self::TABLE;
    }

    public function findCustomerId(string $provider, string $providerUserId, int $idShop): ?int
    {
        $id = \Db::getInstance()->getValue('SELECT id_customer FROM `' . $this->t() . '` WHERE provider = \'' . pSQL($provider) . '\' AND provider_user_id = \'' . pSQL($providerUserId) . '\' AND id_shop = ' . $idShop);

        return $id ? (int) $id : null;
    }

    public function add(int $idCustomer, string $provider, string $providerUserId, string $email, int $idShop): void
    {
        $now = date('Y-m-d H:i:s');
        \Db::getInstance()->insert(self::TABLE, [
            'id_customer' => $idCustomer,
            'id_shop' => $idShop,
            'provider' => pSQL($provider),
            'provider_user_id' => pSQL($providerUserId),
            'email' => pSQL(substr($email, 0, 255)),
            'consent_date' => $now,
            'date_add' => $now,
            'date_last_login' => $now,
        ]);
    }

    public function remove(int $idCustomer, string $provider): bool
    {
        return (bool) \Db::getInstance()->execute('DELETE FROM `' . $this->t() . '` WHERE id_customer = ' . $idCustomer . ' AND provider = \'' . pSQL($provider) . '\'');
    }

    public function hasLink(int $idCustomer, string $provider): bool
    {
        return (bool) \Db::getInstance()->getValue('SELECT 1 FROM `' . $this->t() . '` WHERE id_customer = ' . $idCustomer . ' AND provider = \'' . pSQL($provider) . '\'');
    }

    public function touch(string $provider, string $providerUserId, int $idShop): void
    {
        \Db::getInstance()->update(self::TABLE, ['date_last_login' => date('Y-m-d H:i:s')], 'provider = \'' . pSQL($provider) . '\' AND provider_user_id = \'' . pSQL($providerUserId) . '\' AND id_shop = ' . $idShop);
    }

    public function listForCustomer(int $idCustomer): array
    {
        $rows = \Db::getInstance()->executeS('SELECT provider, email, consent_date, date_add, date_last_login FROM `' . $this->t() . '` WHERE id_customer = ' . $idCustomer . ' ORDER BY date_add');

        return is_array($rows) ? $rows : [];
    }

    public function deleteForCustomer(int $idCustomer): void
    {
        \Db::getInstance()->execute('DELETE FROM `' . $this->t() . '` WHERE id_customer = ' . $idCustomer);
    }
}
