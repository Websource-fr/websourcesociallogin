<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Provider;

/** Erreur de fournisseur : le message est un code court (jamais un jeton ni un secret). */
final class ProviderException extends \RuntimeException
{
}
