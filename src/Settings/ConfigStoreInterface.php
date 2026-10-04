<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Settings;

interface ConfigStoreInterface
{
    public function get(string $key, int $idShop): string;

    public function set(string $key, string $value, int $idShop): void;

    /** @return string valeur dans la langue donnée (repli : défaut) */
    public function getLang(string $key, int $idLang, int $idShop): string;

    /** @param array<int,string> $values [id_lang => texte] */
    public function setLang(string $key, array $values, int $idShop): void;
}
