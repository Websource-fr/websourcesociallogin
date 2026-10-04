<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Linking;

use PrestaShop\PrestaShop\Core\Crypto\Hashing;
use Websource\SocialLogin\Provider\Profile;

/** Accès aux clients PrestaShop. */
final class PsCustomerGateway implements CustomerGatewayInterface
{
    public function findIdByEmail(string $email, int $idShop): ?int
    {
        if (!\Validate::isEmail($email)) {
            return null;
        }
        $id = \Customer::customerExists($email, true, true);

        return $id ? (int) $id : null;
    }

    public function canLogin(int $idCustomer): bool
    {
        $c = new \Customer($idCustomer);

        return \Validate::isLoadedObject($c) && (bool) $c->active && !$c->deleted;
    }

    public function createCustomer(Profile $profile, int $idShop): int
    {
        $ctx = \Context::getContext();
        $hashing = new Hashing();
        $c = new \Customer();
        $c->email = $profile->email;
        $c->firstname = self::cleanName($profile->firstname, $profile->email);
        $c->lastname = self::cleanName($profile->lastname, '-');
        $c->passwd = $hashing->hash(\Tools::passwdGen(40, 'RANDOM') . bin2hex(random_bytes(16)), _COOKIE_KEY_);
        $c->id_gender = 0;       // civilité non renseignée
        $c->newsletter = 0;      // offres et newsletter : jamais cochées d'office
        $c->optin = 0;           // offres des partenaires : idem
        $c->active = 1;
        $c->is_guest = 0;
        $c->id_lang = (int) $ctx->language->id;
        $c->id_shop = $idShop;
        $c->id_default_group = (int) \Configuration::get('PS_CUSTOMER_GROUP', null, null, $idShop);
        if (!$c->add()) {
            return 0;
        }
        if (!$c->id) {
            return 0;
        }
        \Hook::exec('actionCustomerAccountAdd', ['_POST' => [], 'newCustomer' => $c]);

        return (int) $c->id;
    }

    public function checkPassword(int $idCustomer, string $password): bool
    {
        $c = new \Customer($idCustomer);
        if (!\Validate::isLoadedObject($c) || $password === '') {
            return false;
        }

        return (new Hashing())->checkHash($password, (string) $c->passwd, _COOKIE_KEY_);
    }

    /** Nom accepté par PrestaShop (Validate::isName), repli sur la partie locale de l'e-mail puis « - ». */
    public static function cleanName(string $name, string $fallback): string
    {
        foreach ([$name, strstr($fallback, '@', true) ?: $fallback] as $candidate) {
            $n = trim((string) preg_replace('/[0-9!<>,;?=+()@#"°{}_$%:¤|\\\\\/*^`~\[\]]+/u', ' ', $candidate));
            $n = trim((string) preg_replace('/\s+/u', ' ', $n));
            $n = function_exists('mb_substr') ? mb_substr($n, 0, 60) : substr($n, 0, 60);
            if ($n !== '' && \Validate::isName($n)) {
                return $n;
            }
        }

        return '-';
    }
}
