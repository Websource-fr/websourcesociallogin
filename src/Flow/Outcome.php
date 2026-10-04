<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Flow;

use Websource\SocialLogin\Linking\Result;

/** Résultat de la fin d'un flux, à traduire en redirection par le contrôleur. */
final class Outcome
{
    /** @var Result */
    public $result;
    /** @var string */
    public $provider = '';
    /** @var string adresse de retour déjà validée ('' = Mon compte) */
    public $back = '';
    /** @var string jeton de l'étape suivante (confirmation, e-mail) */
    public $pendingToken = '';
    /** @var string */
    public $intent = 'login';

    public function __construct(Result $result)
    {
        $this->result = $result;
    }
}
