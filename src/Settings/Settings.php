<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Settings;

use Websource\SocialLogin\Provider\ProviderInterface;
use Websource\SocialLogin\Provider\ProviderRegistry;
use Websource\SocialLogin\Security\SecretBoxInterface;

/**
 * Lecture / écriture des réglages d'une boutique. Les secrets sont chiffrés au repos et ne sont jamais renvoyés au HTML.
 */
final class Settings
{
    const PREFIX = 'WSSL_';

    /** @var ConfigStoreInterface */
    private $store;
    /** @var SecretBoxInterface */
    private $box;
    /** @var ProviderRegistry */
    private $registry;
    /** @var int */
    private $shop;

    public function __construct(ConfigStoreInterface $store, SecretBoxInterface $box, ProviderRegistry $registry, int $idShop)
    {
        $this->store = $store;
        $this->box = $box;
        $this->registry = $registry;
        $this->shop = $idShop;
    }

    public function shopId(): int
    {
        return $this->shop;
    }

    private function key(string $provider, string $field): string
    {
        return self::PREFIX . strtoupper($provider) . '_' . strtoupper($field);
    }

    public function isEnabled(string $provider): bool
    {
        return $this->store->get($this->key($provider, 'enabled'), $this->shop) === '1';
    }

    public function setEnabled(string $provider, bool $on): void
    {
        $this->store->set($this->key($provider, 'enabled'), $on ? '1' : '0', $this->shop);
    }

    private function isSecretType(string $type): bool
    {
        return $type === 'secret' || $type === 'secret_textarea';
    }

    /** @return array<string,string> identifiants en clair (usage serveur uniquement) */
    public function credentials(string $provider): array
    {
        $out = [];
        foreach ($this->registry->get($provider)->fields() as $name => $def) {
            $raw = $this->store->get($this->key($provider, $name), $this->shop);
            if ($this->isSecretType((string) $def['type'])) {
                $raw = $this->box->decrypt($raw);
            }
            if ($raw === '' && !empty($def['default'])) {
                $raw = (string) $def['default'];
            }
            $out[$name] = $raw;
        }

        return $out;
    }

    /** @return array<string,bool> champ => renseigné ? (sans exposer la valeur) */
    public function filled(string $provider): array
    {
        $out = [];
        foreach ($this->credentials($provider) as $k => $v) {
            $out[$k] = $v !== '';
        }

        return $out;
    }

    /** @return string[] noms des champs obligatoires manquants */
    public function missing(string $provider): array
    {
        $missing = [];
        $cred = $this->credentials($provider);
        foreach ($this->registry->get($provider)->fields() as $name => $def) {
            if (!empty($def['required']) && trim((string) $cred[$name]) === '') {
                $missing[] = $name;
            }
        }

        return $missing;
    }

    public function isConfigured(string $provider): bool
    {
        return $this->missing($provider) === [];
    }

    public function isActive(string $provider): bool
    {
        return $this->registry->has($provider) && $this->isEnabled($provider) && $this->isConfigured($provider);
    }

    /** @param array<string,string> $values champ => nouvelle valeur ; un secret vide conserve l'existant */
    public function saveCredentials(string $provider, array $values, array $clearSecrets = []): void
    {
        foreach ($this->registry->get($provider)->fields() as $name => $def) {
            $secret = $this->isSecretType((string) $def['type']);
            $new = isset($values[$name]) ? trim((string) $values[$name]) : '';
            if ($secret) {
                if (in_array($name, $clearSecrets, true)) {
                    $this->store->set($this->key($provider, $name), '', $this->shop);
                } elseif ($new !== '') {
                    $this->store->set($this->key($provider, $name), $this->box->encrypt($new), $this->shop);
                }
                continue;
            }
            if ($name === 'tenant' && $new === '') {
                $new = 'common';
            }
            if (!preg_match('/^[\x21-\x7e]{0,255}$/', $new)) {
                continue; // identifiants : ASCII imprimable sans espace
            }
            $this->store->set($this->key($provider, $name), $new, $this->shop);
        }
    }

    public function scopes(string $provider): string
    {
        $s = $this->store->get($this->key($provider, 'scopes'), $this->shop);

        return $s !== '' ? $s : $this->registry->get($provider)->defaultScopes();
    }

    public function saveScopes(string $provider, string $raw): void
    {
        $this->store->set($this->key($provider, 'scopes'), self::sanitizeScopes($raw), $this->shop);
    }

    public static function sanitizeScopes(string $raw): string
    {
        $list = preg_split('/[\s,]+/', trim($raw), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $list = array_filter($list, function ($s) {
            return (bool) preg_match('#^[A-Za-z0-9:._\-/]{1,80}$#', $s);
        });

        return implode(' ', array_slice(array_unique($list), 0, 15));
    }

    /** @return string[] identifiants dans l'ordre d'affichage */
    public function order(): array
    {
        $saved = array_filter(explode(',', $this->store->get(self::PREFIX . 'ORDER', $this->shop)));
        $ids = array_values(array_unique(array_merge(array_filter($saved, [$this->registry, 'has']), $this->registry->ids())));

        return $ids;
    }

    /** @param string[] $ids */
    public function saveOrder(array $ids): void
    {
        $ids = array_values(array_filter(array_unique($ids), [$this->registry, 'has']));
        $this->store->set(self::PREFIX . 'ORDER', implode(',', $ids), $this->shop);
    }

    /** @return ProviderInterface[] fournisseurs actifs (activés ET configurés), dans l'ordre */
    public function activeProviders(?string $lastUsed = null): array
    {
        $out = [];
        foreach ($this->order() as $id) {
            if ($this->isActive($id)) {
                $out[] = $this->registry->get($id);
            }
        }
        if ($lastUsed !== null && $this->rememberLast()) {
            usort($out, function (ProviderInterface $a, ProviderInterface $b) use ($lastUsed) {
                return (int) ($b->id() === $lastUsed) <=> (int) ($a->id() === $lastUsed);
            });
        }

        return $out;
    }

    /** @return array<string,mixed> */
    public function style(): array
    {
        $raw = json_decode($this->store->get(self::PREFIX . 'STYLE', $this->shop), true);

        return StyleSanitizer::sanitize(is_array($raw) ? $raw : [])['style'];
    }

    /** @param array<string,mixed> $style déjà nettoyé */
    public function saveStyle(array $style): void
    {
        $this->store->set(self::PREFIX . 'STYLE', (string) json_encode($style), $this->shop);
    }

    /** @return string[] */
    public function placements(): array
    {
        $raw = $this->store->get(self::PREFIX . 'PLACES', $this->shop);

        return $raw === '' ? Placements::defaults() : Placements::sanitize($raw);
    }

    /** @param string[] $places */
    public function savePlacements(array $places): void
    {
        $this->store->set(self::PREFIX . 'PLACES', $places === [] ? '-' : implode(',', Placements::sanitize($places)), $this->shop);
    }

    public function separator(): bool
    {
        return $this->store->get(self::PREFIX . 'SEPARATOR', $this->shop) !== '0';
    }

    public function rememberLast(): bool
    {
        return $this->store->get(self::PREFIX . 'REMEMBER', $this->shop) !== '0';
    }

    public function saveFlags(bool $separator, bool $remember): void
    {
        $this->store->set(self::PREFIX . 'SEPARATOR', $separator ? '1' : '0', $this->shop);
        $this->store->set(self::PREFIX . 'REMEMBER', $remember ? '1' : '0', $this->shop);
    }

    public function buttonLabel(int $idLang): string
    {
        return $this->store->getLang(self::PREFIX . 'LABEL', $idLang, $this->shop);
    }

    public function gdprText(int $idLang): string
    {
        return $this->store->getLang(self::PREFIX . 'GDPR', $idLang, $this->shop);
    }

    /** @param array<int,string> $labels @param array<int,string> $gdpr */
    public function saveTexts(array $labels, array $gdpr): void
    {
        $clean = function (array $a, int $max): array {
            $o = [];
            foreach ($a as $id => $t) {
                $t = trim(strip_tags((string) $t));
                $o[(int) $id] = function_exists('mb_substr') ? mb_substr($t, 0, $max) : substr($t, 0, $max);
            }

            return $o;
        };
        $this->store->setLang(self::PREFIX . 'LABEL', $clean($labels, 60), $this->shop);
        $this->store->setLang(self::PREFIX . 'GDPR', $clean($gdpr, 400), $this->shop);
    }
}
