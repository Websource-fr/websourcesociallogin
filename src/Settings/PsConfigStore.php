<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Settings;

/** Stockage dans Configuration de PrestaShop, boutique par boutique. */
final class PsConfigStore implements ConfigStoreInterface
{
    public function get(string $key, int $idShop): string
    {
        $v = \Configuration::get($key, null, null, $idShop);

        return $v === false ? '' : (string) $v;
    }

    public function set(string $key, string $value, int $idShop): void
    {
        \Configuration::updateValue($key, $value, false, null, $idShop);
    }

    public function getLang(string $key, int $idLang, int $idShop): string
    {
        $v = \Configuration::get($key, $idLang, null, $idShop);

        return $v === false ? '' : (string) $v;
    }

    public function setLang(string $key, array $values, int $idShop): void
    {
        \Configuration::updateValue($key, $values, false, null, $idShop);
    }
}
