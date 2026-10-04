<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Provider;

use Websource\SocialLogin\Http\HttpClientInterface;

/**
 * Un réseau social. Pour en ajouter un : implémenter cette interface (ou étendre AbstractProvider / OidcProvider),
 * écrire son guide dans src/Guide/data_*.php, puis l'enregistrer dans ProviderRegistry::default().
 */
interface ProviderInterface
{
    public function id(): string;

    public function label(): string;

    /**
     * Champs de configuration : [clé => ['type' => text|secret|textarea, 'required' => bool, 'default' => string]].
     * « client_id » et « client_secret » sont toujours présents (sauf Apple : voir AppleProvider).
     *
     * @return array<string,array<string,mixed>>
     */
    public function fields(): array;

    public function defaultScopes(): string;

    public function usesPkce(): bool;

    /** true si le réseau renvoie la réponse en POST (response_mode=form_post) : cookie SameSite=None requis. */
    public function usesFormPost(): bool;

    /**
     * @param array<string,string> $cred
     */
    public function buildAuthorizationUrl(array $cred, string $redirectUri, string $state, string $nonce, ?string $codeChallenge, string $scopes): string;

    /**
     * Échange le code, valide les jetons et renvoie le profil.
     *
     * @param array<string,string> $cred
     * @param array<string,mixed>  $extra données de retour additionnelles (ex. « user » d'Apple)
     *
     * @throws ProviderException
     */
    public function fetchProfile(HttpClientInterface $http, array $cred, string $code, string $redirectUri, ?string $codeVerifier, string $nonce, array $extra = []): Profile;
}
