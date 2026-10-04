<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Linking;

final class Result
{
    const LOGIN = 'login';                    // compte déjà lié : connexion
    const CREATED = 'created';                // nouveau client créé et lié : connexion
    const LINKED = 'linked';                  // réseau lié au client déjà connecté
    const CONFIRM_PASSWORD = 'confirm_password'; // client existant : mot de passe requis avant liaison
    const NEED_EMAIL = 'need_email';          // le réseau n'a pas fourni d'e-mail
    const VERIFY_EMAIL = 'verify_email';      // e-mail fourni mais non vérifié par le réseau
    const ERROR = 'error';

    /** @var string */
    public $action;
    /** @var int */
    public $customerId;
    /** @var string */
    public $error;

    public function __construct(string $action, int $customerId = 0, string $error = '')
    {
        $this->action = $action;
        $this->customerId = $customerId;
        $this->error = $error;
    }
}
