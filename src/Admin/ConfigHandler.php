<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Admin;

use Websource\SocialLogin\Provider\ProviderRegistry;
use Websource\SocialLogin\Settings\Placements;
use Websource\SocialLogin\Settings\Settings;
use Websource\SocialLogin\Settings\StyleSanitizer;

/** Traitement du formulaire de configuration (après contrôle du jeton CSRF par le module). */
final class ConfigHandler
{
    /** @var ProviderRegistry */
    private $registry;

    public function __construct(ProviderRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * @param array<string,mixed> $post
     * @param int[]               $langIds
     *
     * @return array{errors:string[],saved:bool} codes d'erreur de champs (couleurs, rayon…)
     */
    public function save(array $post, Settings $settings, array $langIds): array
    {
        $errors = [];
        foreach ($this->registry->all() as $id => $provider) {
            $p = isset($post['p'][$id]) && is_array($post['p'][$id]) ? $post['p'][$id] : [];
            $settings->setEnabled($id, !empty($p['enabled']));
            $values = [];
            foreach (array_keys($provider->fields()) as $f) {
                $values[$f] = isset($p[$f]) && is_string($p[$f]) ? $p[$f] : '';
            }
            $clear = [];
            foreach ((array) ($p['clear'] ?? []) as $f => $on) {
                if ($on) {
                    $clear[] = (string) $f;
                }
            }
            $settings->saveCredentials($id, $values, $clear);
            $settings->saveScopes($id, isset($p['scopes']) && is_string($p['scopes']) ? $p['scopes'] : '');
        }

        // ordre : nombre saisi par réseau (1 = premier)
        $pos = [];
        foreach ($this->registry->ids() as $i => $id) {
            $n = isset($post['p'][$id]['position']) ? (int) $post['p'][$id]['position'] : ($i + 1);
            $pos[$id] = [$n > 0 && $n < 100 ? $n : $i + 1, $i];
        }
        uasort($pos, function ($a, $b) {
            return $a <=> $b;
        });
        $settings->saveOrder(array_keys($pos));

        $sanitized = StyleSanitizer::sanitize(isset($post['style']) && is_array($post['style']) ? $post['style'] : []);
        $settings->saveStyle($sanitized['style']);
        $errors = array_merge($errors, $sanitized['errors']);

        $settings->savePlacements(Placements::sanitize($post['places'] ?? []));
        $settings->saveFlags(!empty($post['separator']), !empty($post['remember']));

        $labels = [];
        $gdpr = [];
        foreach ($langIds as $l) {
            $labels[$l] = isset($post['label'][$l]) && is_string($post['label'][$l]) ? $post['label'][$l] : '';
            $gdpr[$l] = isset($post['gdpr'][$l]) && is_string($post['gdpr'][$l]) ? $post['gdpr'][$l] : '';
        }
        $settings->saveTexts($labels, $gdpr);

        return ['errors' => $errors, 'saved' => true];
    }

    /**
     * Test de configuration d'un réseau (sans appel réseau) : champs, https, scopes.
     *
     * @return array<int,array{ok:bool,key:string,detail:string}>
     */
    public function test(string $providerId, Settings $settings, string $callbackUrl): array
    {
        $checks = [];
        if (!$this->registry->has($providerId)) {
            return $checks;
        }
        $filled = $settings->filled($providerId);
        foreach ($this->registry->get($providerId)->fields() as $name => $def) {
            if (!empty($def['required'])) {
                $checks[] = ['ok' => !empty($filled[$name]), 'key' => 'field', 'detail' => $name];
            }
        }
        $checks[] = ['ok' => stripos($callbackUrl, 'https://') === 0, 'key' => 'https', 'detail' => ''];
        $checks[] = ['ok' => $settings->scopes($providerId) !== '', 'key' => 'scopes', 'detail' => ''];
        $checks[] = ['ok' => $settings->isEnabled($providerId), 'key' => 'enabled', 'detail' => ''];

        return $checks;
    }
}
