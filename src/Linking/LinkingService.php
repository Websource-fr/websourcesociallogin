<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Linking;

use Websource\SocialLogin\Provider\Profile;

/**
 * Logique de rattachement d'un compte social à un client, indépendante de PrestaShop.
 *
 * Règles de sécurité :
 *  1. un compte social déjà lié se connecte directement ;
 *  2. jamais de fusion automatique : un client existant n'est lié qu'après saisie de SON mot de passe ;
 *  3. un e-mail non vérifié par le réseau n'est jamais utilisé tel quel (ni pour fusionner, ni pour créer) :
 *     il doit d'abord être confirmé par un lien envoyé à cette adresse.
 */
final class LinkingService
{
    /** @var LinkRepositoryInterface */
    private $links;
    /** @var CustomerGatewayInterface */
    private $customers;

    public function __construct(LinkRepositoryInterface $links, CustomerGatewayInterface $customers)
    {
        $this->links = $links;
        $this->customers = $customers;
    }

    public function handle(string $provider, Profile $profile, ?int $currentCustomerId, int $idShop): Result
    {
        if ($profile->id === '' || strlen($profile->id) > 190) {
            return new Result(Result::ERROR, 0, 'profile_invalid');
        }

        $linked = $this->links->findCustomerId($provider, $profile->id, $idShop);
        if ($linked !== null) {
            if ($currentCustomerId !== null && $currentCustomerId !== $linked) {
                return new Result(Result::ERROR, 0, 'linked_to_other');
            }
            if (!$this->customers->canLogin($linked)) {
                return new Result(Result::ERROR, 0, 'account_disabled');
            }
            $this->links->touch($provider, $profile->id, $idShop);

            return new Result($currentCustomerId !== null ? Result::LINKED : Result::LOGIN, $linked);
        }

        // Client déjà connecté qui lie un réseau depuis « Mon compte » : son authentification fait foi.
        if ($currentCustomerId !== null) {
            if ($this->links->hasLink($currentCustomerId, $provider)) {
                return new Result(Result::ERROR, 0, 'already_has_provider');
            }
            $this->links->add($currentCustomerId, $provider, $profile->id, $profile->email, $idShop);

            return new Result(Result::LINKED, $currentCustomerId);
        }

        if ($profile->email === '' || !filter_var($profile->email, FILTER_VALIDATE_EMAIL)) {
            return new Result(Result::NEED_EMAIL);
        }
        if (!$profile->emailVerified) {
            return new Result(Result::VERIFY_EMAIL);
        }

        $existing = $this->customers->findIdByEmail($profile->email, $idShop);
        if ($existing !== null) {
            if (!$this->customers->canLogin($existing)) {
                return new Result(Result::ERROR, 0, 'account_disabled');
            }
            if ($this->links->hasLink($existing, $provider)) {
                // ce client a déjà un autre compte du même réseau : pas de second lien silencieux
                return new Result(Result::ERROR, 0, 'already_has_provider');
            }

            return new Result(Result::CONFIRM_PASSWORD, $existing);
        }

        $id = $this->customers->createCustomer($profile, $idShop);
        if ($id <= 0) {
            return new Result(Result::ERROR, 0, 'create_failed');
        }
        $this->links->add($id, $provider, $profile->id, $profile->email, $idShop);

        return new Result(Result::CREATED, $id);
    }

    /** Confirmation par mot de passe : lie puis autorise la connexion. */
    public function confirmWithPassword(string $provider, Profile $profile, int $idCustomer, string $password, int $idShop): Result
    {
        if (!$this->customers->canLogin($idCustomer) || !$this->customers->checkPassword($idCustomer, $password)) {
            return new Result(Result::ERROR, 0, 'bad_password');
        }
        if ($this->links->findCustomerId($provider, $profile->id, $idShop) !== null || $this->links->hasLink($idCustomer, $provider)) {
            return new Result(Result::ERROR, 0, 'already_linked');
        }
        $this->links->add($idCustomer, $provider, $profile->id, $profile->email, $idShop);

        return new Result(Result::LOGIN, $idCustomer);
    }
}
