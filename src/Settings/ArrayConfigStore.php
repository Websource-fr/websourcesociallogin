<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Settings;

/** Stockage en mémoire (tests). */
final class ArrayConfigStore implements ConfigStoreInterface
{
    /** @var array<string,mixed> */
    public $data = [];

    public function get(string $key, int $idShop): string
    {
        return (string) ($this->data[$idShop][$key] ?? '');
    }

    public function set(string $key, string $value, int $idShop): void
    {
        $this->data[$idShop][$key] = $value;
    }

    public function getLang(string $key, int $idLang, int $idShop): string
    {
        return (string) ($this->data[$idShop][$key][$idLang] ?? '');
    }

    public function setLang(string $key, array $values, int $idShop): void
    {
        $this->data[$idShop][$key] = $values;
    }
}
