<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Linking;

use Websource\SocialLogin\Provider\Profile;

interface CustomerGatewayInterface
{
    /** client enregistré (hors invité) portant cet e-mail dans cette boutique */
    public function findIdByEmail(string $email, int $idShop): ?int;

    /** compte actif et non supprimé */
    public function canLogin(int $idCustomer): bool;

    /** Crée un client à partir du profil (e-mail vérifié) : mot de passe aléatoire, groupe par défaut, offres décochées. */
    public function createCustomer(Profile $profile, int $idShop): int;

    /** Vérifie le mot de passe du client ; true si correct. */
    public function checkPassword(int $idCustomer, string $password): bool;
}
