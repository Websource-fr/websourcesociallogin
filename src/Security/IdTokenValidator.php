<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Security;

use Websource\SocialLogin\Provider\ProviderException;

/**
 * Validation d'un id_token OpenID Connect : algorithme RS256 uniquement, signature via JWKS,
 * iss, aud (et azp), exp, iat/nbf avec tolérance, nonce.
 */
final class IdTokenValidator
{
    /** @var int */
    private $leeway;

    public function __construct(int $leeway = 120)
    {
        $this->leeway = $leeway;
    }

    /**
     * @param array<int,array<string,mixed>> $jwks       clés (JWK) du fournisseur
     * @param string|callable                $issuer     émetteur attendu, ou fonction (array $claims): bool
     * @return array<string,mixed> claims validés
     *
     * @throws ProviderException
     */
    public function validate(string $idToken, array $jwks, $issuer, string $clientId, string $nonce, ?int $now = null): array
    {
        $now = $now ?? time();
        [$header, $claims, $sig, $signed] = Jwt::parse($idToken);

        if (($header['alg'] ?? '') !== 'RS256') {
            throw new ProviderException('jwt_alg');
        }
        $key = $this->selectKey($jwks, isset($header['kid']) ? (string) $header['kid'] : null);
        if ($key === null) {
            throw new ProviderException('jwt_unknown_key');
        }
        if (!Jwt::verifyRs256($signed, $sig, Jwt::jwkToPem($key))) {
            throw new ProviderException('jwt_signature');
        }

        $iss = (string) ($claims['iss'] ?? '');
        $issuerOk = is_callable($issuer) ? (bool) $issuer($claims) : ($iss !== '' && hash_equals((string) $issuer, $iss));
        if (!$issuerOk) {
            throw new ProviderException('jwt_iss');
        }
        $aud = $claims['aud'] ?? null;
        $audList = is_array($aud) ? $aud : [$aud];
        if ($clientId === '' || !in_array($clientId, array_map('strval', $audList), true)) {
            throw new ProviderException('jwt_aud');
        }
        if (count($audList) > 1 && (string) ($claims['azp'] ?? '') !== $clientId) {
            throw new ProviderException('jwt_azp');
        }
        if (!isset($claims['exp']) || !is_numeric($claims['exp']) || (int) $claims['exp'] + $this->leeway < $now) {
            throw new ProviderException('jwt_expired');
        }
        if (isset($claims['iat']) && is_numeric($claims['iat']) && (int) $claims['iat'] - $this->leeway > $now) {
            throw new ProviderException('jwt_iat');
        }
        if (isset($claims['nbf']) && is_numeric($claims['nbf']) && (int) $claims['nbf'] - $this->leeway > $now) {
            throw new ProviderException('jwt_nbf');
        }
        $tokenNonce = (string) ($claims['nonce'] ?? '');
        if ($nonce === '' || $tokenNonce === '' || !hash_equals($nonce, $tokenNonce)) {
            throw new ProviderException('jwt_nonce');
        }
        if (!isset($claims['sub']) || (string) $claims['sub'] === '') {
            throw new ProviderException('jwt_sub');
        }

        return $claims;
    }

    /** @param array<int,array<string,mixed>> $jwks @return array<string,mixed>|null */
    private function selectKey(array $jwks, ?string $kid): ?array
    {
        $rsa = array_values(array_filter($jwks, function ($k) {
            return is_array($k) && ($k['kty'] ?? '') === 'RSA' && (($k['use'] ?? 'sig') === 'sig');
        }));
        if ($kid !== null) {
            foreach ($rsa as $k) {
                if (($k['kid'] ?? null) === $kid) {
                    return $k;
                }
            }

            return null;
        }

        return count($rsa) === 1 ? $rsa[0] : null;
    }
}
