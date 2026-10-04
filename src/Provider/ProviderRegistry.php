<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Provider;

/** Registre des fournisseurs. Pour en ajouter un : $registry->register(new MonProvider()). */
final class ProviderRegistry
{
    /** @var array<string,ProviderInterface> */
    private $providers = [];

    public static function default(): self
    {
        $r = new self();
        foreach ([new GoogleProvider(), new FacebookProvider(), new XProvider(), new LinkedInProvider(), new MicrosoftProvider(), new AppleProvider(), new GithubProvider()] as $p) {
            $r->register($p);
        }

        return $r;
    }

    public function register(ProviderInterface $provider): void
    {
        if (!preg_match('/^[a-z][a-z0-9]{0,19}$/', $provider->id())) {
            throw new \InvalidArgumentException('Identifiant de fournisseur invalide.');
        }
        $this->providers[$provider->id()] = $provider;
    }

    public function has(string $id): bool
    {
        return isset($this->providers[$id]);
    }

    public function get(string $id): ProviderInterface
    {
        if (!isset($this->providers[$id])) {
            throw new ProviderException('unknown_provider');
        }

        return $this->providers[$id];
    }

    /** @return array<string,ProviderInterface> */
    public function all(): array
    {
        return $this->providers;
    }

    /** @return string[] */
    public function ids(): array
    {
        return array_keys($this->providers);
    }
}
