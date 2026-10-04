<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Security;

/**
 * Chiffre les secrets au repos avec le chiffrement de PrestaShop (clé _NEW_COOKIE_KEY_).
 * Valeur stockée : « enc1:... ». Une ancienne valeur en clair reste lisible (puis rechiffrée à la prochaine sauvegarde).
 */
final class PsSecretBox implements SecretBoxInterface
{
    const PREFIX = 'enc1:';

    /** @var object|null */
    private $engine;

    public function __construct()
    {
        if (class_exists('\PhpEncryption') && defined('_NEW_COOKIE_KEY_')) {
            $this->engine = new \PhpEncryption(_NEW_COOKIE_KEY_);
        }
    }

    public function encrypt(string $plain): string
    {
        if ($plain === '' || $this->engine === null) {
            return $plain;
        }

        return self::PREFIX . $this->engine->encrypt($plain);
    }

    public function decrypt(string $stored): string
    {
        if (strpos($stored, self::PREFIX) !== 0) {
            return $stored;
        }
        if ($this->engine === null) {
            return '';
        }
        $plain = $this->engine->decrypt(substr($stored, strlen(self::PREFIX)));

        return is_string($plain) ? $plain : '';
    }
}
