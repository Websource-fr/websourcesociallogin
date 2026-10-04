<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Security;

use Websource\SocialLogin\Provider\ProviderException;

/** Lecture / vérification RS256 et signature ES256 (client secret d'Apple). Algorithmes autres que RS256 refusés en lecture. */
final class Jwt
{
    /**
     * @return array{0:array<string,mixed>,1:array<string,mixed>,2:string,3:string} en-tête, charge utile, signature binaire, données signées
     */
    public static function parse(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3 || strlen($jwt) > 16384) {
            throw new ProviderException('jwt_malformed');
        }
        $header = json_decode(Base64Url::decode($parts[0]), true);
        $payload = json_decode(Base64Url::decode($parts[1]), true);
        $sig = Base64Url::decode($parts[2]);
        if (!is_array($header) || !is_array($payload) || $sig === '') {
            throw new ProviderException('jwt_malformed');
        }

        return [$header, $payload, $sig, $parts[0] . '.' . $parts[1]];
    }

    /** @param array<string,mixed> $jwk */
    public static function jwkToPem(array $jwk): string
    {
        if (($jwk['kty'] ?? '') !== 'RSA' || empty($jwk['n']) || empty($jwk['e'])) {
            throw new ProviderException('jwk_unsupported');
        }
        $n = Base64Url::decode((string) $jwk['n']);
        $e = Base64Url::decode((string) $jwk['e']);
        if ($n === '' || $e === '') {
            throw new ProviderException('jwk_unsupported');
        }
        $rsaKey = self::seq(self::int($n) . self::int($e));
        // AlgorithmIdentifier rsaEncryption + BIT STRING
        $algo = hex2bin('300d06092a864886f70d0101010500');
        $spki = self::seq($algo . "\x03" . self::len(strlen($rsaKey) + 1) . "\x00" . $rsaKey);

        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    public static function verifyRs256(string $signedData, string $signature, string $pem): bool
    {
        $key = openssl_pkey_get_public($pem);
        if ($key === false) {
            return false;
        }

        return openssl_verify($signedData, $signature, $key, OPENSSL_ALGO_SHA256) === 1;
    }

    /** @param array<string,mixed> $header @param array<string,mixed> $claims */
    public static function signEs256(array $header, array $claims, string $privateKeyPem): string
    {
        $data = Base64Url::encode((string) json_encode($header)) . '.' . Base64Url::encode((string) json_encode($claims));
        $key = openssl_pkey_get_private($privateKeyPem);
        if ($key === false || !openssl_sign($data, $der, $key, OPENSSL_ALGO_SHA256)) {
            throw new ProviderException('key_invalid');
        }

        return $data . '.' . Base64Url::encode(self::derToRaw($der, 32));
    }

    /** Signature ECDSA DER -> concaténation r||s (format JWS). */
    public static function derToRaw(string $der, int $size): string
    {
        $o = 2;
        if (ord($der[1]) & 0x80) {
            $o += ord($der[1]) & 0x7f;
        }
        $parts = [];
        for ($i = 0; $i < 2; ++$i) {
            $len = ord($der[$o + 1]);
            $int = substr($der, $o + 2, $len);
            $o += 2 + $len;
            $int = ltrim($int, "\x00");
            $parts[] = str_pad($int, $size, "\x00", STR_PAD_LEFT);
        }

        return $parts[0] . $parts[1];
    }

    private static function len(int $l): string
    {
        if ($l < 128) {
            return chr($l);
        }
        $b = ltrim(pack('N', $l), "\x00");

        return chr(0x80 | strlen($b)) . $b;
    }

    private static function seq(string $c): string
    {
        return "\x30" . self::len(strlen($c)) . $c;
    }

    private static function int(string $v): string
    {
        $v = ltrim($v, "\x00");
        if ($v === '' || (ord($v[0]) & 0x80)) {
            $v = "\x00" . $v;
        }

        return "\x02" . self::len(strlen($v)) . $v;
    }
}
