<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Support;

/**
 * Journalisation sobre (PrestaShopLogger) : fournisseur, événement et code d'erreur court uniquement.
 * Jamais de jeton, de code d'autorisation, de secret, d'e-mail ni de mot de passe.
 */
final class EventLogger
{
    const EVENTS = ['start', 'login', 'created', 'linked', 'unlinked', 'confirm_failed', 'error', 'rate_limited', 'mail_sent'];

    public static function log(string $event, string $provider = '', string $code = '', int $idCustomer = 0): string
    {
        $event = in_array($event, self::EVENTS, true) ? $event : 'error';
        $provider = preg_match('/^[a-z0-9]{1,20}$/', $provider) ? $provider : '-';
        $code = preg_match('/^[a-z0-9_]{1,40}$/', $code) ? $code : '';
        $msg = '[websourcesociallogin] ' . $event . ' provider=' . $provider . ($code !== '' ? ' code=' . $code : '');
        if (class_exists('\PrestaShopLogger')) {
            \PrestaShopLogger::addLog($msg, $event === 'error' || $event === 'confirm_failed' || $event === 'rate_limited' ? 2 : 1, null, 'Customer', $idCustomer ?: null, true);
        }

        return $msg;
    }
}
