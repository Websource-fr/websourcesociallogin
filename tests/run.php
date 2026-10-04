<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

/**
 * Tests autonomes (sans PrestaShop ni réseau) : PKCE, state, validation d'id_token (jetons signés localement),
 * fournisseurs avec client HTTP factice, anti-prise de compte, redirections, styles, guides, traductions.
 * Lancement : php tests/run.php
 */

if (PHP_SAPI !== 'cli') {
    exit;
}

require __DIR__ . '/../vendor/autoload.php';

use Websource\SocialLogin\Flow\FlowService;
use Websource\SocialLogin\Flow\FlowStoreInterface;
use Websource\SocialLogin\Flow\MemoryFlowStore;
use Websource\SocialLogin\Guide\GuideCatalog;
use Websource\SocialLogin\Http\HttpClientInterface;
use Websource\SocialLogin\Http\HttpResponse;
use Websource\SocialLogin\Linking\CustomerGatewayInterface;
use Websource\SocialLogin\Linking\LinkingService;
use Websource\SocialLogin\Linking\MemoryLinkRepository;
use Websource\SocialLogin\Linking\Result;
use Websource\SocialLogin\Provider\AppleProvider;
use Websource\SocialLogin\Provider\FacebookProvider;
use Websource\SocialLogin\Provider\GithubProvider;
use Websource\SocialLogin\Provider\GoogleProvider;
use Websource\SocialLogin\Provider\MicrosoftProvider;
use Websource\SocialLogin\Provider\Profile;
use Websource\SocialLogin\Provider\ProviderException;
use Websource\SocialLogin\Provider\ProviderInterface;
use Websource\SocialLogin\Provider\ProviderRegistry;
use Websource\SocialLogin\Provider\XProvider;
use Websource\SocialLogin\Render\ButtonViewBuilder;
use Websource\SocialLogin\Security\Base64Url;
use Websource\SocialLogin\Security\BackUrlValidator;
use Websource\SocialLogin\Security\IdTokenValidator;
use Websource\SocialLogin\Security\Jwt;
use Websource\SocialLogin\Security\JwksProviderInterface;
use Websource\SocialLogin\Security\NullSecretBox;
use Websource\SocialLogin\Security\Pkce;
use Websource\SocialLogin\Security\SecretBoxInterface;
use Websource\SocialLogin\Security\Token;
use Websource\SocialLogin\Settings\ArrayConfigStore;
use Websource\SocialLogin\Settings\ColorValidator;
use Websource\SocialLogin\Settings\Settings;
use Websource\SocialLogin\Settings\StyleSanitizer;
use Websource\SocialLogin\Support\EventLogger;
use Websource\SocialLogin\Support\Translator;

$passed = 0;
$failed = [];
function check(string $label, bool $ok): void
{
    global $passed, $failed;
    $ok ? ++$passed : $failed[] = $label;
}
function throwsCode(callable $fn, string $code): bool
{
    try {
        $fn();
    } catch (ProviderException $e) {
        return $e->getMessage() === $code;
    }

    return false;
}
function ids(array $providers): array
{
    return array_map(function ($p) {
        return $p->id();
    }, $providers);
}

// ---------------------------------------------------------------- helpers : clés et jetons signés localement
function rsaKey()
{
    return openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
}
function jwkOf($key, string $kid): array
{
    $d = openssl_pkey_get_details($key);

    return ['kty' => 'RSA', 'use' => 'sig', 'alg' => 'RS256', 'kid' => $kid, 'n' => Base64Url::encode($d['rsa']['n']), 'e' => Base64Url::encode($d['rsa']['e'])];
}
function signJwt(array $claims, $key, string $kid = 'k1', string $alg = 'RS256'): string
{
    $h = Base64Url::encode((string) json_encode(['alg' => $alg, 'kid' => $kid, 'typ' => 'JWT']));
    $p = Base64Url::encode((string) json_encode($claims));
    openssl_sign($h . '.' . $p, $sig, $key, OPENSSL_ALGO_SHA256);

    return $h . '.' . $p . '.' . Base64Url::encode($sig);
}

final class FakeJwks implements JwksProviderInterface
{
    public $keys = [];
    public $refreshed = 0;
    public $afterRefresh = null;

    public function keys(string $jwksUrl, bool $forceRefresh = false): array
    {
        if ($forceRefresh) {
            ++$this->refreshed;
            if ($this->afterRefresh !== null) {
                $this->keys = $this->afterRefresh;
            }
        }

        return $this->keys;
    }
}

final class FakeHttp implements HttpClientInterface
{
    public $routes = [];
    public $calls = [];

    public function route(string $needle, int $status, $body): void
    {
        $this->routes[] = [$needle, new HttpResponse($status, is_string($body) ? $body : (string) json_encode($body))];
    }

    public function request(string $method, string $url, array $headers = [], array $form = []): HttpResponse
    {
        $this->calls[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'form' => $form];
        foreach ($this->routes as $r) {
            if (strpos($url, $r[0]) !== false) {
                return $r[1];
            }
        }

        return new HttpResponse(404, '{}');
    }
}

$now = 1800000000;
$key = rsaKey();
$jwk = jwkOf($key, 'k1');
$validator = new IdTokenValidator(60);
$base = ['iss' => 'https://issuer.test', 'aud' => 'client-1', 'sub' => 'u-42', 'exp' => $now + 600, 'iat' => $now - 10, 'nonce' => 'nonce-abc'];
$ISS = 'https://issuer.test';

// ---------------------------------------------------------------- PKCE
check('pkce vecteur RFC 7636', Pkce::challenge('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk') === 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM');
$v = Pkce::verifier();
check('pkce verifier valide', Pkce::isValidVerifier($v) && strlen($v) >= 43 && strlen($v) <= 128);
check('pkce verifier aléatoire', $v !== Pkce::verifier());
check('pkce verifier invalide refusé', !Pkce::isValidVerifier('court') && !Pkce::isValidVerifier(str_repeat('a', 129)) && !Pkce::isValidVerifier(str_repeat('é', 50)));

// ---------------------------------------------------------------- id_token
$tok = signJwt($base, $key);
check('id_token valide accepté', $validator->validate($tok, [$jwk], $ISS, 'client-1', 'nonce-abc', $now)['sub'] === 'u-42');
check('id_token iss en fonction', $validator->validate($tok, [$jwk], function ($c) { return $c['iss'] === 'https://issuer.test'; }, 'client-1', 'nonce-abc', $now)['sub'] === 'u-42');
check('id_token mauvais iss', throwsCode(function () use ($validator, $tok, $jwk, $now) { $validator->validate($tok, [$jwk], 'https://evil.test', 'client-1', 'nonce-abc', $now); }, 'jwt_iss'));
check('id_token mauvais aud', throwsCode(function () use ($validator, $tok, $jwk, $now, $ISS) { $validator->validate($tok, [$jwk], $ISS, 'autre-client', 'nonce-abc', $now); }, 'jwt_aud'));
check('id_token mauvais nonce', throwsCode(function () use ($validator, $tok, $jwk, $now, $ISS) { $validator->validate($tok, [$jwk], $ISS, 'client-1', 'rejoue', $now); }, 'jwt_nonce'));
check('id_token nonce vide refusé', throwsCode(function () use ($validator, $tok, $jwk, $now, $ISS) { $validator->validate($tok, [$jwk], $ISS, 'client-1', '', $now); }, 'jwt_nonce'));
check('id_token expiré', throwsCode(function () use ($validator, $tok, $jwk, $now, $ISS) { $validator->validate($tok, [$jwk], $ISS, 'client-1', 'nonce-abc', $now + 601 + 61); }, 'jwt_expired'));
check('id_token expiré dans la tolérance accepté', $validator->validate($tok, [$jwk], $ISS, 'client-1', 'nonce-abc', $now + 601 + 30)['sub'] === 'u-42');
check('id_token émis dans le futur refusé', throwsCode(function () use ($validator, $key, $jwk, $base, $now, $ISS) { $validator->validate(signJwt(['iat' => $now + 1000] + $base, $key), [$jwk], $ISS, 'client-1', 'nonce-abc', $now); }, 'jwt_iat'));
$parts = explode('.', $tok);
$forgedPayload = Base64Url::encode((string) json_encode(['sub' => 'admin'] + $base));
check('id_token charge utile modifiée => signature invalide', throwsCode(function () use ($validator, $parts, $forgedPayload, $jwk, $now, $ISS) { $validator->validate($parts[0] . '.' . $forgedPayload . '.' . $parts[2], [$jwk], $ISS, 'client-1', 'nonce-abc', $now); }, 'jwt_signature'));
$otherKey = rsaKey();
check('id_token signé par une autre clé refusé', throwsCode(function () use ($validator, $otherKey, $jwk, $base, $now, $ISS) { $validator->validate(signJwt($base, $otherKey), [$jwk], $ISS, 'client-1', 'nonce-abc', $now); }, 'jwt_signature'));
$noneHeader = Base64Url::encode((string) json_encode(['alg' => 'none', 'kid' => 'k1']));
check('alg=none refusé', throwsCode(function () use ($validator, $noneHeader, $parts, $jwk, $now, $ISS) { $validator->validate($noneHeader . '.' . $parts[1] . '.' . $parts[2], [$jwk], $ISS, 'client-1', 'nonce-abc', $now); }, 'jwt_alg'));
$hsHeader = Base64Url::encode((string) json_encode(['alg' => 'HS256', 'kid' => 'k1']));
check('alg=HS256 (confusion de clé) refusé', throwsCode(function () use ($validator, $hsHeader, $parts, $jwk, $now, $ISS) { $validator->validate($hsHeader . '.' . $parts[1] . '.' . $parts[2], [$jwk], $ISS, 'client-1', 'nonce-abc', $now); }, 'jwt_alg'));
check('kid inconnu refusé', throwsCode(function () use ($validator, $key, $jwk, $base, $now, $ISS) { $validator->validate(signJwt($base, $key, 'k-inconnu'), [$jwk], $ISS, 'client-1', 'nonce-abc', $now); }, 'jwt_unknown_key'));
check('aud multiple sans azp refusé', throwsCode(function () use ($validator, $key, $jwk, $base, $now, $ISS) { $validator->validate(signJwt(['aud' => ['client-1', 'x']] + $base, $key), [$jwk], $ISS, 'client-1', 'nonce-abc', $now); }, 'jwt_azp'));
check('aud multiple avec azp accepté', $validator->validate(signJwt(['aud' => ['client-1', 'x'], 'azp' => 'client-1'] + $base, $key), [$jwk], $ISS, 'client-1', 'nonce-abc', $now)['sub'] === 'u-42');
check('jeton mal formé refusé', throwsCode(function () use ($validator, $jwk, $now) { $validator->validate('a.b', [$jwk], 'x', 'c', 'n', $now); }, 'jwt_malformed'));
check('sub absent refusé', throwsCode(function () use ($validator, $key, $jwk, $base, $now, $ISS) { $c = $base; unset($c['sub']); $validator->validate(signJwt($c, $key), [$jwk], $ISS, 'client-1', 'nonce-abc', $now); }, 'jwt_sub'));

// ES256 (client secret Apple)
$ec = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
openssl_pkey_export($ec, $ecPem);
$secret = AppleProvider::buildClientSecret('TEAM123456', 'fr.shop.signin', 'KEY1234567', $ecPem, $now);
list($h, $pl, $sig, $signed) = Jwt::parse($secret);
check('apple client secret : en-tête ES256 + kid', $h['alg'] === 'ES256' && $h['kid'] === 'KEY1234567');
check('apple client secret : claims', $pl['iss'] === 'TEAM123456' && $pl['sub'] === 'fr.shop.signin' && $pl['aud'] === 'https://appleid.apple.com' && $pl['exp'] === $now + 300);
check('apple client secret : signature brute 64 octets', strlen($sig) === 64);
$toDer = function (string $raw): string {
    $r = ltrim(substr($raw, 0, 32), "\x00");
    $s = ltrim(substr($raw, 32), "\x00");
    $r = (ord($r[0]) & 0x80 ? "\x00" : '') . $r;
    $s = (ord($s[0]) & 0x80 ? "\x00" : '') . $s;
    $body = "\x02" . chr(strlen($r)) . $r . "\x02" . chr(strlen($s)) . $s;

    return "\x30" . chr(strlen($body)) . $body;
};
check('apple client secret : signature vérifiable avec la clé publique', openssl_verify($signed, $toDer($sig), openssl_pkey_get_details($ec)['key'], OPENSSL_ALGO_SHA256) === 1);
check('apple clé invalide refusée', throwsCode(function () { AppleProvider::buildClientSecret('T', 'C', 'K', 'pas une clé'); }, 'key_invalid'));

// ---------------------------------------------------------------- fournisseurs OIDC avec HTTP factice
$jw = new FakeJwks();
$jw->keys = [$jwk];
$google = new GoogleProvider();
$google->setJwksProvider($jw);
$cred = ['client_id' => 'client-1', 'client_secret' => 's3cret'];
$gUrl = $google->buildAuthorizationUrl($cred, 'https://shop.test/module/websourcesociallogin/callback', 'STATE', 'NONCE', 'CHALLENGE', 'openid email profile');
parse_str((string) parse_url($gUrl, PHP_URL_QUERY), $q);
check('google URL : hôte et chemin', strpos($gUrl, 'https://accounts.google.com/o/oauth2/v2/auth?') === 0);
check('google URL : state/nonce/PKCE S256', $q['state'] === 'STATE' && $q['nonce'] === 'NONCE' && $q['code_challenge'] === 'CHALLENGE' && $q['code_challenge_method'] === 'S256' && $q['response_type'] === 'code' && $q['scope'] === 'openid email profile' && $q['redirect_uri'] === 'https://shop.test/module/websourcesociallogin/callback');
$gClaims = ['iss' => 'https://accounts.google.com', 'aud' => 'client-1', 'sub' => 'g-1', 'exp' => time() + 600, 'iat' => time(), 'nonce' => 'N1', 'email' => 'Jean@Example.com', 'email_verified' => true, 'given_name' => 'Jean', 'family_name' => 'Dupont'];
$http = new FakeHttp();
$http->route('oauth2.googleapis.com/token', 200, ['id_token' => signJwt($gClaims, $key), 'access_token' => 'AT']);
$prof = $google->fetchProfile($http, $cred, 'CODE', 'https://shop.test/cb', Pkce::verifier(), 'N1');
check('google profil : id/e-mail normalisé/vérifié/noms', $prof->id === 'g-1' && $prof->email === 'jean@example.com' && $prof->emailVerified && $prof->firstname === 'Jean' && $prof->lastname === 'Dupont');
check('google échange : secret et code_verifier envoyés en POST', $http->calls[0]['method'] === 'POST' && $http->calls[0]['form']['client_secret'] === 's3cret' && isset($http->calls[0]['form']['code_verifier']) && $http->calls[0]['form']['code'] === 'CODE');
check('google : mauvais nonce refusé', throwsCode(function () use ($google, $cred, $http) { $google->fetchProfile($http, $cred, 'C', 'https://x/cb', null, 'AUTRE'); }, 'jwt_nonce'));
$gBad = new FakeHttp();
$gBad->route('oauth2.googleapis.com/token', 200, ['id_token' => signJwt(['iss' => 'https://evil.test'] + $gClaims, $key)]);
check('google : émetteur incorrect refusé', throwsCode(function () use ($google, $cred, $gBad) { $google->fetchProfile($gBad, $cred, 'C', 'https://x/cb', null, 'N1'); }, 'jwt_iss'));
$gErr = new FakeHttp();
$gErr->route('oauth2.googleapis.com/token', 400, ['error' => 'invalid_grant']);
check('google : échec d\'échange => erreur générique sans détail', throwsCode(function () use ($google, $cred, $gErr) { $google->fetchProfile($gErr, $cred, 'C', 'https://x/cb', null, 'N1'); }, 'token_exchange_failed'));
$gNoId = new FakeHttp();
$gNoId->route('oauth2.googleapis.com/token', 200, ['access_token' => 'AT']);
check('google : id_token absent refusé', throwsCode(function () use ($google, $cred, $gNoId) { $google->fetchProfile($gNoId, $cred, 'C', 'https://x/cb', null, 'N1'); }, 'id_token_missing'));
$rot = new FakeJwks();
$rot->keys = [jwkOf(rsaKey(), 'ancienne')];
$rot->afterRefresh = [$jwk];
$g2 = new GoogleProvider();
$g2->setJwksProvider($rot);
check('google : rotation de clés => un seul rafraîchissement du JWKS', $g2->fetchProfile($http, $cred, 'C', 'https://x/cb', null, 'N1')->id === 'g-1' && $rot->refreshed === 1);
$unv = new FakeHttp();
$unv->route('oauth2.googleapis.com/token', 200, ['id_token' => signJwt(['email_verified' => false] + $gClaims, $key)]);
check('google : e-mail non vérifié signalé', !$google->fetchProfile($unv, $cred, 'C', 'https://x/cb', null, 'N1')->emailVerified);

// Microsoft
$ms = new MicrosoftProvider();
$ms->setJwksProvider($jw);
$tid = '11111111-2222-3333-4444-555555555555';
$mClaims = ['iss' => 'https://login.microsoftonline.com/' . $tid . '/v2.0', 'tid' => $tid, 'aud' => 'client-1', 'sub' => 'pairwise', 'oid' => 'oid-9', 'exp' => time() + 600, 'iat' => time(), 'nonce' => 'N1', 'email' => 'x@corp.test', 'name' => 'Ada Lovelace'];
$mCred = ['client_id' => 'client-1', 'client_secret' => 's', 'tenant' => 'common'];
$mh = new FakeHttp();
$mh->route('/oauth2/v2.0/token', 200, ['id_token' => signJwt($mClaims, $key)]);
$mp = $ms->fetchProfile($mh, $mCred, 'C', 'https://x/cb', null, 'N1');
check('microsoft : e-mail NON vérifié par défaut (nOAuth)', !$mp->emailVerified && $mp->email === 'x@corp.test');
check('microsoft : identifiant = tid.oid, nom scindé', $mp->id === $tid . '.oid-9' && $mp->firstname === 'Ada' && $mp->lastname === 'Lovelace');
$mh2 = new FakeHttp();
$mh2->route('/oauth2/v2.0/token', 200, ['id_token' => signJwt(['xms_edov' => true] + $mClaims, $key)]);
check('microsoft : xms_edov => vérifié', $ms->fetchProfile($mh2, $mCred, 'C', 'https://x/cb', null, 'N1')->emailVerified);
$mh3 = new FakeHttp();
$mh3->route('/oauth2/v2.0/token', 200, ['id_token' => signJwt(['iss' => 'https://login.microsoftonline.com/99999999-2222-3333-4444-555555555555/v2.0'] + $mClaims, $key)]);
check('microsoft : iss différent du tid refusé', throwsCode(function () use ($ms, $mh3, $mCred) { $ms->fetchProfile($mh3, $mCred, 'C', 'https://x/cb', null, 'N1'); }, 'jwt_iss'));
check('microsoft : tenant imposé != tid refusé', throwsCode(function () use ($ms, $mh) { $ms->fetchProfile($mh, ['client_id' => 'client-1', 'client_secret' => 's', 'tenant' => '99999999-2222-3333-4444-555555555555'], 'C', 'https://x/cb', null, 'N1'); }, 'jwt_iss'));
check('microsoft : tenant invalide => common (pas d\'injection d\'URL)', strpos($ms->buildAuthorizationUrl(['client_id' => 'c', 'tenant' => 'evil.com/../x'], 'https://x/cb', 's', 'n', 'ch', 'openid'), 'login.microsoftonline.com/common/oauth2/v2.0/authorize') !== false);

// Apple
$ap = new AppleProvider();
$ap->setJwksProvider($jw);
$aClaims = ['iss' => 'https://appleid.apple.com', 'aud' => 'fr.shop.signin', 'sub' => 'a-1', 'exp' => time() + 600, 'iat' => time(), 'nonce' => 'N1', 'email' => 'relay@privaterelay.appleid.com', 'email_verified' => 'true'];
$ah = new FakeHttp();
$ah->route('appleid.apple.com/auth/token', 200, ['id_token' => signJwt($aClaims, $key)]);
$aCred = ['client_id' => 'fr.shop.signin', 'team_id' => 'TEAM123456', 'key_id' => 'KEY1234567', 'private_key' => $ecPem];
$apProf = $ap->fetchProfile($ah, $aCred, 'C', 'https://x/cb', null, 'N1', ['user' => json_encode(['name' => ['firstName' => 'Ana', 'lastName' => 'Bell'], 'email' => 'forge@evil.test'])]);
check('apple : e-mail de l\'id_token, "true" textuel accepté, nom du POST', $apProf->email === 'relay@privaterelay.appleid.com' && $apProf->emailVerified && $apProf->firstname === 'Ana');
check('apple : client_secret JWT généré dans l\'échange', count(explode('.', $ah->calls[0]['form']['client_secret'])) === 3);
check('apple : response_mode=form_post + usesFormPost', $ap->usesFormPost() && strpos($ap->buildAuthorizationUrl($aCred, 'https://x/cb', 's', 'n', null, 'name email'), 'response_mode=form_post') !== false);
check('apple : pas de PKCE dans l\'URL', strpos($ap->buildAuthorizationUrl($aCred, 'https://x/cb', 's', 'n', 'CH', 'name email'), 'code_challenge') === false);

// Facebook
$fb = new FacebookProvider();
$fh = new FakeHttp();
$fh->route('/oauth/access_token', 200, ['access_token' => 'AT-FB']);
$fh->route('/me?', 200, ['id' => '1234567890', 'first_name' => 'Léa', 'last_name' => 'Martin', 'email' => 'Lea@Example.com']);
$fp = $fb->fetchProfile($fh, $cred, 'C', 'https://x/cb', null, 'N');
check('facebook : profil', $fp->id === '1234567890' && $fp->email === 'lea@example.com' && $fp->emailVerified);
check('facebook : jeton dans l\'en-tête, pas dans l\'URL; appsecret_proof', strpos($fh->calls[1]['url'], 'AT-FB') === false && $fh->calls[1]['headers'][0] === 'Authorization: Bearer AT-FB' && strpos($fh->calls[1]['url'], 'appsecret_proof=') !== false);
$fh2 = new FakeHttp();
$fh2->route('/oauth/access_token', 200, ['access_token' => 'AT']);
$fh2->route('/me?', 200, ['id' => 'pas-numerique']);
check('facebook : id non numérique refusé', throwsCode(function () use ($fb, $cred, $fh2) { $fb->fetchProfile($fh2, $cred, 'C', 'https://x/cb', null, 'N'); }, 'profile_invalid'));
$fh3 = new FakeHttp();
$fh3->route('/oauth/access_token', 200, ['access_token' => 'AT']);
$fh3->route('/me?', 200, ['id' => '5', 'first_name' => 'Sans']);
check('facebook : sans e-mail => e-mail vide', $fb->fetchProfile($fh3, $cred, 'C', 'https://x/cb', null, 'N')->email === '');

// X
$x = new XProvider();
$xu = $x->buildAuthorizationUrl($cred, 'https://x/cb', 'S', 'N', 'CH', 'users.read tweet.read users.email');
check('x : PKCE S256 + endpoint', strpos($xu, 'https://x.com/i/oauth2/authorize?') === 0 && strpos($xu, 'code_challenge_method=S256') !== false && strpos($xu, 'code_challenge=CH') !== false);
$xh = new FakeHttp();
$xh->route('oauth2/token', 200, ['access_token' => 'AT-X']);
$xh->route('confirmed_email', 200, ['data' => ['id' => '777', 'name' => 'Max Payne', 'confirmed_email' => 'max@example.com']]);
$xp = $x->fetchProfile($xh, $cred, 'C', 'https://x/cb', Pkce::verifier(), '');
check('x : profil avec e-mail confirmé', $xp->id === '777' && $xp->email === 'max@example.com' && $xp->emailVerified && $xp->lastname === 'Payne');
check('x : Basic + code_verifier à l\'échange', strpos(implode('|', $xh->calls[0]['headers']), 'Authorization: Basic ') !== false && !empty($xh->calls[0]['form']['code_verifier']));
$xh2 = new FakeHttp();
$xh2->route('oauth2/token', 200, ['access_token' => 'AT-X']);
$xh2->route('confirmed_email', 403, ['title' => 'Forbidden']);
$xh2->route('/2/users/me', 200, ['data' => ['id' => '888', 'name' => 'Solo']]);
$xp2 = $x->fetchProfile($xh2, $cred, 'C', 'https://x/cb', Pkce::verifier(), '');
check('x : repli sans e-mail si l\'accès e-mail est refusé', $xp2->id === '888' && $xp2->email === '' && !$xp2->emailVerified);

// GitHub
$gh = new GithubProvider();
$ghh = new FakeHttp();
$ghh->route('login/oauth/access_token', 200, ['access_token' => 'AT-GH']);
$ghh->route('/user/emails', 200, [['email' => 'no@x.test', 'verified' => false, 'primary' => true], ['email' => 'second@x.test', 'verified' => true, 'primary' => false], ['email' => 'main@x.test', 'verified' => true, 'primary' => true]]);
$ghh->route('api.github.com/user', 200, ['id' => 99, 'login' => 'octo', 'name' => 'Octo Cat', 'email' => 'public@x.test']);
$ghp = $gh->fetchProfile($ghh, $cred, 'C', 'https://x/cb', Pkce::verifier(), '');
check('github : e-mail primaire vérifié (jamais le profil public)', $ghp->email === 'main@x.test' && $ghp->emailVerified && $ghp->id === '99');
$ghh2 = new FakeHttp();
$ghh2->route('login/oauth/access_token', 200, ['access_token' => 'AT']);
$ghh2->route('/user/emails', 200, [['email' => 'u@x.test', 'verified' => false, 'primary' => true]]);
$ghh2->route('api.github.com/user', 200, ['id' => 5, 'login' => 'l', 'email' => 'public@x.test']);
check('github : aucun e-mail vérifié => vide', $gh->fetchProfile($ghh2, $cred, 'C', 'https://x/cb', null, '')->email === '');

// ---------------------------------------------------------------- registre
$reg = ProviderRegistry::default();
check('registre : 7 réseaux', $reg->ids() === ['google', 'facebook', 'x', 'linkedin', 'microsoft', 'apple', 'github']);
check('registre : réseau inconnu', throwsCode(function () use ($reg) { $reg->get('myspace'); }, 'unknown_provider'));
$badProvider = new class() implements ProviderInterface {
    public function id(): string { return 'Bad Id!'; }
    public function label(): string { return ''; }
    public function fields(): array { return []; }
    public function defaultScopes(): string { return ''; }
    public function usesPkce(): bool { return false; }
    public function usesFormPost(): bool { return false; }
    public function buildAuthorizationUrl(array $c, string $r, string $s, string $n, ?string $ch, string $sc): string { return ''; }
    public function fetchProfile(HttpClientInterface $h, array $c, string $co, string $r, ?string $v, string $n, array $e = []): Profile { return new Profile('1'); }
};
$refused = false;
try {
    $reg->register($badProvider);
} catch (\InvalidArgumentException $e) {
    $refused = true;
}
check('registre : identifiant invalide refusé', $refused);
check('PKCE : Google/X/Microsoft/GitHub oui, Facebook/Apple/LinkedIn non', $reg->get('google')->usesPkce() && $reg->get('x')->usesPkce() && $reg->get('microsoft')->usesPkce() && $reg->get('github')->usesPkce() && !$reg->get('facebook')->usesPkce() && !$reg->get('apple')->usesPkce() && !$reg->get('linkedin')->usesPkce());

// ---------------------------------------------------------------- redirections
$bv = new BackUrlValidator(['shop.test', 'www.shop.test']);
check('back : chemin relatif accepté', $bv->sanitize('/panier?action=show') === '/panier?action=show');
check('back : URL du domaine accepté', $bv->sanitize('https://shop.test/fr/commande') === 'https://shop.test/fr/commande');
check('back : sous-domaine déclaré accepté', $bv->sanitize('https://www.shop.test/') === 'https://www.shop.test/');
foreach (['https://evil.test/', '//evil.test/', 'javascript:alert(1)', 'data:text/html,x', 'https://shop.test.evil.test/', 'https://evil.test\\@shop.test/', '/\\evil.test', "/ok\r\nLocation: x", 'https://user:pass@shop.test/', 'ftp://shop.test/', '', 'https://shop.test@evil.test/', 'HTTPS://EVIL.TEST', '///evil.test', "/\t/evil.test"] as $bad) {
    check('back refusé : ' . json_encode($bad), $bv->sanitize($bad) === '');
}
check('back : trop long refusé', $bv->sanitize('/' . str_repeat('a', 3000)) === '');

// ---------------------------------------------------------------- couleurs et styles
foreach (['#fff', '#ffff', '#ffffff', '#FFFFFF80', 'rgb(0,0,0)', 'rgba(10, 20, 30, .5)', 'rgba(1,2,3,0.25)', 'rgba(1,2,3,1)', 'transparent', 'var(--color-brand-500)', ''] as $okc) {
    check('couleur valide : ' . $okc, ColorValidator::normalize($okc) !== null);
}
foreach (['red', '#ff', '#fffff', '#gggggg', 'rgb(256,0,0)', 'rgba(0,0,0,1.5)', 'rgba(0,0,0,2)', 'url(x)', 'expression(alert(1))', '#fff;background:url(x)', 'red;}', 'var(--x);color:red', 'rgb(0,0,0))', "#f\nff", 'rgba(0,0,0,-1)', 'calc(1px)', '</style>', str_repeat('a', 50)] as $badc) {
    check('couleur refusée : ' . $badc, ColorValidator::normalize($badc) === null);
}
check('couleur normalisée', ColorValidator::normalize(' #ABCDEF ') === '#abcdef');
$st = StyleSanitizer::sanitize(['preset' => 'evil', 'layout' => 'grid', 'shape' => 'pill', 'radius' => '99', 'border' => '9', 'bg' => 'red;x', 'color' => '#123456', 'border_color' => 'rgba(0,0,0,.3)', 'icon_only' => '1']);
check('style : choix invalide => défaut', $st['style']['preset'] === 'outline' && $st['style']['layout'] === 'grid' && $st['style']['shape'] === 'pill');
check('style : bornes et couleurs invalides signalées', $st['style']['radius'] === '' && $st['style']['border'] === 1 && $st['style']['bg'] === '' && in_array('radius', $st['errors'], true) && in_array('border', $st['errors'], true) && in_array('bg', $st['errors'], true));
check('style : valeurs valides conservées', $st['style']['color'] === '#123456' && $st['style']['border_color'] === 'rgba(0, 0, 0, .3)' && $st['style']['icon_only'] === true);
$css = ButtonViewBuilder::cssVars(['preset' => 'brand', 'bg' => '#ff0000', 'color' => 'x;y', 'shape' => 'pill', 'radius' => '', 'border' => 2]);
check('cssVars : seules des valeurs sûres', $css === '--wssl-radius:999px;--wssl-border-w:2px;--wssl-bg:#ff0000');
check('cssVars : rayon personnalisé', strpos(ButtonViewBuilder::cssVars(['radius' => '12']), '--wssl-radius:12px') === 0);

// ---------------------------------------------------------------- réglages, secrets, visibilité
final class RotBox implements SecretBoxInterface
{
    public function encrypt(string $plain): string { return $plain === '' ? '' : 'enc1:' . strrev($plain); }
    public function decrypt(string $stored): string { return strpos($stored, 'enc1:') === 0 ? strrev(substr($stored, 5)) : $stored; }
}
$store = new ArrayConfigStore();
$settings = new Settings($store, new RotBox(), $reg, 1);
check('réglages : rien d\'actif au départ', $settings->activeProviders() === []);
$settings->setEnabled('google', true);
check('réglages : activé mais incomplet => invisible', $settings->activeProviders() === [] && !$settings->isConfigured('google') && $settings->missing('google') === ['client_id', 'client_secret']);
$settings->saveCredentials('google', ['client_id' => 'cid.apps.googleusercontent.com', 'client_secret' => 'TOPSECRET']);
check('réglages : configuré+activé => visible', ids($settings->activeProviders()) === ['google']);
check('réglages : secret chiffré au repos', $store->data[1]['WSSL_GOOGLE_CLIENT_SECRET'] === 'enc1:TERCESPOT' && strpos(json_encode($store->data), 'TOPSECRET') === false);
check('réglages : filled() n\'expose pas la valeur', $settings->filled('google') === ['client_id' => true, 'client_secret' => true]);
$settings->saveCredentials('google', ['client_id' => 'cid2', 'client_secret' => '']);
check('réglages : secret vide = conservé', $settings->credentials('google')['client_secret'] === 'TOPSECRET' && $settings->credentials('google')['client_id'] === 'cid2');
$settings->saveCredentials('google', [], ['client_secret']);
check('réglages : effacement explicite du secret', !$settings->isConfigured('google') && $settings->activeProviders() === []);
$settings->saveCredentials('google', ['client_id' => 'cid3', 'client_secret' => 's']);
$settings->saveCredentials('google', ['client_id' => "bad id\n", 'client_secret' => '']);
check('réglages : identifiant avec espace/saut de ligne ignoré', $settings->credentials('google')['client_id'] === 'cid3');
$settings->saveCredentials('github', ['client_id' => 'gh', 'client_secret' => 'gs']);
$settings->setEnabled('github', true);
$settings->saveCredentials('google', ['client_id' => 'g', 'client_secret' => 'gs']);
check('réglages : ordre par défaut', ids($settings->activeProviders()) === ['google', 'github']);
$settings->saveOrder(['github', 'google']);
check('réglages : ordre personnalisé', ids($settings->activeProviders()) === ['github', 'google']);
check('réglages : dernier réseau utilisé en premier', $settings->activeProviders('google')[0]->id() === 'google');
check('réglages : dernier réseau inconnu ignoré', $settings->activeProviders('inconnu')[0]->id() === 'github');
$settings->saveFlags(true, false);
check('réglages : option « dernier réseau » désactivable', $settings->activeProviders('google')[0]->id() === 'github');
$settings->setEnabled('apple', true);
$settings->saveCredentials('apple', ['client_id' => 'a', 'team_id' => 't', 'key_id' => 'k']);
check('réglages : Apple sans clé privée => incomplet', !$settings->isActive('apple') && $settings->missing('apple') === ['private_key']);
check('scopes : nettoyés', Settings::sanitizeScopes('openid, email profile <script> a;b') === 'openid email profile');
check('scopes : par défaut', $settings->scopes('x') === 'users.read tweet.read users.email');
$settings->savePlacements(['login', 'evil', 'checkout']);
check('emplacements : liste blanche', $settings->placements() === ['login', 'checkout']);
$settings->savePlacements([]);
check('emplacements : tous décochés respecté', $settings->placements() === []);
$other = new Settings($store, new RotBox(), $reg, 2);
check('multiboutique : réglages isolés par boutique', $other->activeProviders() === []);
$settings->saveTexts([1 => '<b>Se connecter avec {network}</b>'], [1 => str_repeat('x', 500)]);
check('textes : balises retirées, longueur bornée', $settings->buttonLabel(1) === 'Se connecter avec {network}' && strlen($settings->gdprText(1)) === 400);

// ---------------------------------------------------------------- vue des boutons
$tr = new Translator(__DIR__ . '/../translations');
$builder = new ButtonViewBuilder($tr);
$settings->savePlacements(['login']);
$settings->saveFlags(true, true);
$none = $builder->build($settings, [], 'login', 'fr', '', '', function () { return '#'; }, null);
check('boutons : aucun réseau => rien', $none === null);
$view = $builder->build($settings, [$reg->get('google'), $reg->get('x')], 'login', 'fr', '', 'RGPD', function ($id) { return '/start?provider=' . $id; }, 'x');
check('boutons : libellé par défaut fr + X court', $view['buttons'][0]['label'] === 'Continuer avec Google' && $view['buttons'][1]['label'] === 'Continuer avec X');
check('boutons : dernier réseau marqué', $view['buttons'][1]['last'] && !$view['buttons'][0]['last'] && strpos($view['buttons'][1]['aria'], 'Dernière connexion') !== false);
$vr = $builder->build($settings, [$reg->get('google')], 'register', 'en', '', '', function () { return '#'; }, null);
check('boutons : séparateur avant sur login, après sur register', $view['separator'] && $view['separator_pos'] === 'before' && $vr['separator_pos'] === 'after');
$vh = $builder->build($settings, [$reg->get('google')], 'header', 'fr', '', 'RGPD', function () { return '#'; }, null);
check('boutons : en-tête compact, icônes, sans séparateur ni mention', strpos($vh['class'], 'wssl--compact') !== false && strpos($vh['class'], 'wssl--icononly') !== false && !$vh['separator'] && $vh['gdpr'] === '');
check('boutons : libellé personnalisé multilingue', $builder->build($settings, [$reg->get('github')], 'login', 'en', 'Sign in with {network}', '', function () { return '#'; }, null)['buttons'][0]['label'] === 'Sign in with GitHub');
$iconsOk = true;
foreach ($reg->ids() as $id) {
    $svg = \Websource\SocialLogin\Render\IconLibrary::svg($id);
    $iconsOk = $iconsOk && strpos($svg, '<svg') === 0 && substr($svg, -6) === '</svg>' && strpos($svg, 'aria-hidden="true"') !== false;
}
check('icônes : SVG pour chaque réseau', $iconsOk);

// ---------------------------------------------------------------- liaison / anti-prise de compte
final class FakeGateway implements CustomerGatewayInterface
{
    public $customers = [];
    public $inactive = [];
    public $passwords = [];
    public $created = [];
    public $next = 100;

    public function findIdByEmail(string $email, int $idShop): ?int { return $this->customers[$email] ?? null; }
    public function canLogin(int $idCustomer): bool { return !in_array($idCustomer, $this->inactive, true); }
    public function createCustomer(Profile $profile, int $idShop): int { $id = $this->next++; $this->customers[$profile->email] = $id; $this->created[] = $profile; return $id; }
    public function checkPassword(int $idCustomer, string $password): bool { return isset($this->passwords[$idCustomer]) && hash_equals($this->passwords[$idCustomer], $password); }
}
$links = new MemoryLinkRepository();
$gw = new FakeGateway();
$gw->customers['alice@example.com'] = 7;
$gw->passwords[7] = 'bon-mot-de-passe';
$svc = new LinkingService($links, $gw);

$r = $svc->handle('google', new Profile('g-100', 'bob@example.com', true, 'Bob', 'Roe'), null, 1);
check('liaison : nouveau client créé + lié + connecté', $r->action === Result::CREATED && $r->customerId === 100 && $links->findCustomerId('google', 'g-100', 1) === 100);
$r = $svc->handle('google', new Profile('g-100', 'bob@example.com', true), null, 1);
check('liaison : compte déjà lié => connexion directe', $r->action === Result::LOGIN && $r->customerId === 100 && count($gw->created) === 1);
$r = $svc->handle('google', new Profile('g-100', 'autre@example.com', false), null, 1);
check('liaison : lien existant prioritaire sur l\'e-mail renvoyé', $r->action === Result::LOGIN && $r->customerId === 100);

$before = count($links->rows);
$r = $svc->handle('facebook', new Profile('f-1', 'alice@example.com', true), null, 1);
check('anti-prise de compte : e-mail vérifié d\'un client existant => mot de passe exigé, aucun lien', $r->action === Result::CONFIRM_PASSWORD && $r->customerId === 7 && count($links->rows) === $before);
$r = $svc->handle('microsoft', new Profile('m-1', 'alice@example.com', false), null, 1);
check('anti-prise de compte : e-mail NON vérifié correspondant => jamais de fusion ni de connexion', $r->action === Result::VERIFY_EMAIL && $r->customerId === 0 && count($links->rows) === $before);
$r = $svc->handle('microsoft', new Profile('m-2', 'nouveau@example.com', false), null, 1);
check('e-mail non vérifié inconnu => pas de création avant vérification', $r->action === Result::VERIFY_EMAIL && count($gw->created) === 1);
$r = $svc->handle('x', new Profile('x-1', '', false), null, 1);
check('sans e-mail => demande d\'e-mail', $r->action === Result::NEED_EMAIL && count($gw->created) === 1);
$r = $svc->handle('x', new Profile('x-2', 'pas-un-email', true), null, 1);
check('e-mail mal formé => demande d\'e-mail', $r->action === Result::NEED_EMAIL);
$r = $svc->confirmWithPassword('facebook', new Profile('f-1', 'alice@example.com', true), 7, 'mauvais', 1);
check('mot de passe erroné => aucun lien', $r->action === Result::ERROR && $r->error === 'bad_password' && $links->findCustomerId('facebook', 'f-1', 1) === null);
$r = $svc->confirmWithPassword('facebook', new Profile('f-1', 'alice@example.com', true), 7, 'bon-mot-de-passe', 1);
check('mot de passe correct => lié + connexion', $r->action === Result::LOGIN && $links->findCustomerId('facebook', 'f-1', 1) === 7);
$r = $svc->confirmWithPassword('facebook', new Profile('f-9', 'alice@example.com', true), 7, 'bon-mot-de-passe', 1);
check('un client ne peut pas lier deux comptes du même réseau', $r->action === Result::ERROR && $r->error === 'already_linked');
$gw->inactive[] = 7;
$r = $svc->handle('facebook', new Profile('f-1', 'alice@example.com', true), null, 1);
check('compte désactivé : connexion refusée', $r->action === Result::ERROR && $r->error === 'account_disabled');
$r = $svc->handle('github', new Profile('gh-5', 'alice@example.com', true), null, 1);
check('compte désactivé : pas de création en double', $r->action === Result::ERROR && $r->error === 'account_disabled' && count($gw->created) === 1);
$gw->inactive = [];
$r = $svc->handle('github', new Profile('gh-7', 'peu-importe@x.test', false), 7, 1);
check('Mon compte : lie au client connecté sans e-mail vérifié', $r->action === Result::LINKED && $links->findCustomerId('github', 'gh-7', 1) === 7);
$r = $svc->handle('github', new Profile('gh-7', '', false), 100, 1);
check('Mon compte : identité déjà liée à un autre client refusée', $r->action === Result::ERROR && $r->error === 'linked_to_other');
$r = $svc->handle('github', new Profile('gh-8', '', false), 7, 1);
check('Mon compte : deuxième compte du même réseau refusé', $r->action === Result::ERROR && $r->error === 'already_has_provider');
check('multiboutique : lien propre à la boutique', $links->findCustomerId('google', 'g-100', 2) === null);
$links->deleteForCustomer(7);
check('suppression RGPD des liens', !$links->hasLink(7, 'facebook') && !$links->hasLink(7, 'github'));
check('déliaison', $links->remove(100, 'google') && !$links->hasLink(100, 'google'));
check('identifiant de profil vide refusé', $svc->handle('google', new Profile('', 'a@b.fr', true), null, 1)->error === 'profile_invalid');

// ---------------------------------------------------------------- flux complet
final class ScriptedProvider implements ProviderInterface
{
    public $profile;
    public $lastNonce;
    public $lastVerifier;

    public function id(): string { return 'fake'; }
    public function label(): string { return 'Fake'; }
    public function fields(): array { return ['client_id' => ['type' => 'text', 'required' => true, 'default' => ''], 'client_secret' => ['type' => 'secret', 'required' => true, 'default' => '']]; }
    public function defaultScopes(): string { return 'openid email'; }
    public function usesPkce(): bool { return true; }
    public function usesFormPost(): bool { return false; }
    public function buildAuthorizationUrl(array $cred, string $redirectUri, string $state, string $nonce, ?string $codeChallenge, string $scopes): string
    {
        return 'https://idp.test/auth?' . http_build_query(['state' => $state, 'nonce' => $nonce, 'cc' => (string) $codeChallenge, 'ru' => $redirectUri, 'cid' => $cred['client_id']]);
    }
    public function fetchProfile(HttpClientInterface $http, array $cred, string $code, string $redirectUri, ?string $codeVerifier, string $nonce, array $extra = []): Profile
    {
        if ($code === 'BAD') {
            throw new ProviderException('token_exchange_failed');
        }
        $this->lastNonce = $nonce;
        $this->lastVerifier = $codeVerifier;

        return $this->profile;
    }
}
function makeFlow(&$parts, ?int $now = null): FlowService
{
    $fake = new ScriptedProvider();
    $r = new ProviderRegistry();
    $r->register($fake);
    $settings = new Settings(new ArrayConfigStore(), new NullSecretBox(), $r, 1);
    $settings->setEnabled('fake', true);
    $settings->saveCredentials('fake', ['client_id' => 'cid', 'client_secret' => 'sec']);
    $flowStore = new MemoryFlowStore($now);
    $lk = new MemoryLinkRepository();
    $g = new FakeGateway();
    $parts = ['fake' => $fake, 'store' => $flowStore, 'links' => $lk, 'gw' => $g, 'settings' => $settings];

    return new FlowService($r, $settings, $flowStore, new FakeHttp(), new LinkingService($lk, $g), new BackUrlValidator(['shop.test']), 'iphash', function () { return 'https://shop.test/module/websourcesociallogin/callback'; });
}
$bind = Token::random(32);
$otherBind = Token::random(32);
$flow = makeFlow($p);
$url = $flow->start('fake', 'https://shop.test/panier', null, $bind);
parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
check('start : state/nonce aléatoires et challenge S256 de 43 caractères', strlen($q['state']) >= 40 && strlen($q['nonce']) >= 30 && strlen($q['cc']) === 43 && $q['ru'] === 'https://shop.test/module/websourcesociallogin/callback');
check('start : seul le haché du state est stocké', !isset($p['store']->rows[$q['state']]) && count($p['store']->rows) === 1);
check('start : réseau inconnu', throwsCode(function () use ($flow, $bind) { $flow->start('inconnu', '', null, $bind); }, 'unknown_provider'));
check('start : cookie de liaison absent refusé', throwsCode(function () use ($flow) { $flow->start('fake', '', null, ''); }, 'not_configured'));
$p['settings']->setEnabled('fake', false);
check('start : réseau désactivé refusé', throwsCode(function () use ($flow, $bind) { $flow->start('fake', '', null, $bind); }, 'not_configured'));
$p['settings']->setEnabled('fake', true);

$p['fake']->profile = new Profile('u-1', 'new@example.com', true, 'N', 'U');
$out = $flow->callback($q['state'], 'CODE', $bind, [], null, 1);
check('callback : création + nonce et verifier transmis au fournisseur', $out->result->action === Result::CREATED && $p['fake']->lastNonce === $q['nonce'] && Pkce::challenge((string) $p['fake']->lastVerifier) === $q['cc']);
check('callback : adresse de retour validée conservée', $out->back === 'https://shop.test/panier');
check('callback : state à usage unique (rejeu refusé)', throwsCode(function () use ($flow, $q, $bind) { $flow->callback($q['state'], 'CODE', $bind, [], null, 1); }, 'state_invalid'));

$flow = makeFlow($p);
$url = $flow->start('fake', 'https://evil.test/', null, $bind);
parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
$p['fake']->profile = new Profile('u-2', 'x@example.com', true);
check('callback : state volé (autre navigateur) refusé', throwsCode(function () use ($flow, $q, $otherBind) { $flow->callback($q['state'], 'CODE', $otherBind, [], null, 1); }, 'state_invalid'));
check('callback : state sans cookie refusé', throwsCode(function () use ($flow, $q) { $flow->callback($q['state'], 'CODE', '', [], null, 1); }, 'state_invalid'));
check('callback : state inventé refusé', throwsCode(function () use ($flow, $bind) { $flow->callback(str_repeat('a', 43), 'CODE', $bind, [], null, 1); }, 'state_invalid'));
check('callback : state mal formé / code vide refusés', throwsCode(function () use ($flow, $bind) { $flow->callback('<script>', 'CODE', $bind, [], null, 1); }, 'state_invalid') && throwsCode(function () use ($flow, $q, $bind) { $flow->callback($q['state'], '', $bind, [], null, 1); }, 'state_invalid'));
$out = $flow->callback($q['state'], 'CODE', $bind, [], null, 1);
check('callback : redirection vers un autre domaine neutralisée dès l\'entrée', $out->back === '');

$flow = makeFlow($p, 1000000);
$url = $flow->start('fake', '', null, $bind);
parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
$p['store']->now += 601;
check('callback : state expiré (10 min) refusé', throwsCode(function () use ($flow, $q, $bind) { $flow->callback($q['state'], 'CODE', $bind, [], null, 1); }, 'state_invalid'));

$flow = makeFlow($p);
$p['gw']->customers['alice@example.com'] = 7;
$p['gw']->passwords[7] = 'pw';
$p['fake']->profile = new Profile('u-3', 'alice@example.com', true, 'A', 'L');
parse_str((string) parse_url($flow->start('fake', '', null, $bind), PHP_URL_QUERY), $q);
$out = $flow->callback($q['state'], 'CODE', $bind, [], null, 1);
check('flux : client existant => étape mot de passe, rien de lié', $out->result->action === Result::CONFIRM_PASSWORD && $out->pendingToken !== '' && $p['links']->rows === []);
check('flux : étape mot de passe liée au navigateur', $flow->pending(FlowStoreInterface::CONFIRM, $out->pendingToken, $bind) !== null && $flow->pending(FlowStoreInterface::CONFIRM, $out->pendingToken, $otherBind) === null);
$badr = $flow->confirmPassword($out->pendingToken, $bind, 'faux', 1);
check('flux : mauvais mot de passe => pas de lien', $badr->result->action === Result::ERROR && $p['links']->rows === []);
for ($i = 0; $i < 4; ++$i) {
    $flow->confirmPassword($out->pendingToken, $bind, 'faux', 1);
}
check('flux : 5 essais maximum puis étape détruite', throwsCode(function () use ($flow, $out, $bind) { $flow->confirmPassword($out->pendingToken, $bind, 'pw', 1); }, 'too_many_attempts') && throwsCode(function () use ($flow, $out, $bind) { $flow->confirmPassword($out->pendingToken, $bind, 'pw', 1); }, 'state_invalid'));

$flow = makeFlow($p);
$p['gw']->customers['alice@example.com'] = 7;
$p['gw']->passwords[7] = 'pw';
$p['fake']->profile = new Profile('u-3', 'alice@example.com', true, 'A', 'L');
parse_str((string) parse_url($flow->start('fake', '', null, $bind), PHP_URL_QUERY), $q);
$out = $flow->callback($q['state'], 'CODE', $bind, [], null, 1);
$okr = $flow->confirmPassword($out->pendingToken, $bind, 'pw', 1);
check('flux : bon mot de passe => lié', $okr->result->action === Result::LOGIN && $p['links']->findCustomerId('fake', 'u-3', 1) === 7);

$flow = makeFlow($p);
$p['fake']->profile = new Profile('u-4', '', false, 'Sans', 'Mail');
parse_str((string) parse_url($flow->start('fake', '', null, $bind), PHP_URL_QUERY), $q);
$out = $flow->callback($q['state'], 'CODE', $bind, [], null, 1);
check('flux : sans e-mail => étape de saisie', $out->result->action === Result::NEED_EMAIL && $out->pendingToken !== '');
check('flux : e-mail invalide refusé', $flow->requestEmailVerification($out->pendingToken, $bind, 'pas un mail') === null);
$req = $flow->requestEmailVerification($out->pendingToken, $bind, 'Victime@Example.com');
check('flux : lien de vérification émis, saisie consommée', $req !== null && $req['email'] === 'victime@example.com' && $flow->pending(FlowStoreInterface::ASKMAIL, $out->pendingToken, $bind) === null);
check('flux : lien ouvert dans un autre navigateur => refusé (anti pré-détournement)', throwsCode(function () use ($flow, $req, $otherBind) { $flow->completeVerification($req['token'], $otherBind, 1); }, 'verify_invalid'));
$done = $flow->completeVerification($req['token'], $bind, 1);
check('flux : lien valide, même navigateur => client créé avec l\'e-mail vérifié', $done->result->action === Result::CREATED && $p['gw']->created[0]->email === 'victime@example.com' && $p['gw']->created[0]->emailVerified);
check('flux : lien de vérification à usage unique', throwsCode(function () use ($flow, $req, $bind) { $flow->completeVerification($req['token'], $bind, 1); }, 'verify_invalid'));

$flow = makeFlow($p);
$p['gw']->customers['alice@example.com'] = 7;
$p['fake']->profile = new Profile('u-5', '', false);
parse_str((string) parse_url($flow->start('fake', '', null, $bind), PHP_URL_QUERY), $q);
$out = $flow->callback($q['state'], 'CODE', $bind, [], null, 1);
$req = $flow->requestEmailVerification($out->pendingToken, $bind, 'alice@example.com');
$done = $flow->completeVerification($req['token'], $bind, 1);
check('flux : e-mail confirmé d\'un client existant => mot de passe exigé quand même', $done->result->action === Result::CONFIRM_PASSWORD && $p['links']->rows === []);

$flow = makeFlow($p);
$p['fake']->profile = new Profile('u-6', 'a@b.fr', true);
parse_str((string) parse_url($flow->start('fake', '', 12, $bind), PHP_URL_QUERY), $q);
check('liaison : client connecté différent au retour refusé', throwsCode(function () use ($flow, $q, $bind) { $flow->callback($q['state'], 'CODE', $bind, [], 99, 1); }, 'session_changed'));
parse_str((string) parse_url($flow->start('fake', '', 12, $bind), PHP_URL_QUERY), $q);
$out = $flow->callback($q['state'], 'CODE', $bind, [], 12, 1);
check('liaison : client connecté identique => lié', $out->result->action === Result::LINKED && $p['links']->findCustomerId('fake', 'u-6', 1) === 12);

$flow = makeFlow($p);
$p['fake']->profile = new Profile('u-7', 'a@b.fr', true);
parse_str((string) parse_url($flow->start('fake', '', null, $bind), PHP_URL_QUERY), $q);
check('callback : erreur du fournisseur propagée en code court', throwsCode(function () use ($flow, $q, $bind) { $flow->callback($q['state'], 'BAD', $bind, [], null, 1); }, 'token_exchange_failed'));
check('callback : state consommé même en cas d\'échec', throwsCode(function () use ($flow, $q, $bind) { $flow->callback($q['state'], 'CODE', $bind, [], null, 1); }, 'state_invalid'));
$flow = makeFlow($p);
for ($i = 0; $i < FlowService::MAX_STARTS_PER_10_MIN; ++$i) {
    $flow->start('fake', '', null, $bind);
}
check('limitation : au-delà de 30 démarrages / 10 min => refus', throwsCode(function () use ($flow, $bind) { $flow->start('fake', '', null, $bind); }, 'rate_limited'));
$p['store']->now += 601;
check('limitation : fenêtre glissante', is_string($flow->start('fake', '', null, $bind)));

// ---------------------------------------------------------------- guides, traductions, journal
$guides = new GuideCatalog();
foreach (['fr', 'en'] as $iso) {
    foreach ($reg->ids() as $id) {
        $g = $guides->guide($id, $iso);
        $hasCb = false;
        $okSteps = true;
        foreach ($g['steps'] as $s) {
            $hasCb = $hasCb || strpos(json_encode($s), '{callback}') !== false;
            $okSteps = $okSteps && !empty($s['title']) && !empty($s['path']) && !empty($s['body']);
        }
        check("guide $iso/$id : intro, console https, >=5 étapes, >=3 erreurs, liens, URL de rappel", $g !== null && $g['intro'] !== '' && strpos($g['console'][1], 'https://') === 0 && count($g['steps']) >= 5 && count($g['errors']) >= 3 && count($g['links']) >= 1 && $hasCb);
        check("guide $iso/$id : chaque étape a titre, chemin et texte", $okSteps);
    }
    check("guide $iso : problèmes communs", count($guides->common($iso)['errors']) >= 3);
}
$fr = $guides->data('fr');
$en = $guides->data('en');
$same = array_keys($fr) === array_keys($en);
foreach ($fr as $k => $vv) {
    $same = $same && isset($en[$k]) && (!isset($vv['steps']) || count($vv['steps']) === count($en[$k]['steps'])) && (!isset($vv['errors']) || count($vv['errors']) === count($en[$k]['errors']));
}
check('guides : fr et en parallèles', $same);
check('guides : l\'anglais est bien traduit', $en['google']['intro'] !== $fr['google']['intro'] && $en['github']['steps'][0]['title'] !== $fr['github']['steps'][0]['title']);
$html = GuideCatalog::fill('Collez {callback} sur {domain}', ['callback' => 'https://a.test/x?"><script>', 'origin' => '', 'domain' => '<b>d</b>', 'scopes' => ''], true);
check('guide : valeurs dynamiques échappées', strpos($html, '<script>') === false && strpos($html, '&lt;script&gt;') !== false && strpos($html, '<b>') === false);
check('guide : texte brut échappé', strpos(GuideCatalog::fill('<img src=x onerror=1> {domain}', ['domain' => 'd.test'], false), '<img') === false);
$cf = include __DIR__ . '/../translations/catalog_fr.php';
$ce = include __DIR__ . '/../translations/catalog_en.php';
check('traductions : catalogues fr et en parallèles', array_keys($cf) === array_keys($ce) && count($cf) > 100);
check('traductions : repli et paramètres', $tr->t('inexistante', 'fr') === 'inexistante' && $tr->t('btn_continue_with', 'de', ['network' => 'X']) === 'Continue with X' && $tr->t('btn_continue_with', 'fr', ['network' => 'X']) === 'Continuer avec X');
$msg = EventLogger::log('login', 'goo gle<>', 'bad code!', 5);
check('journal : format sobre, entrées nettoyées', $msg === '[websourcesociallogin] login provider=-' && strpos($msg, '@') === false);
check('journal : événement inconnu => error', strpos(EventLogger::log('hack', 'google', 'token_exchange_failed'), '] error provider=google code=token_exchange_failed') !== false);

// ---------------------------------------------------------------- fichiers du module
$root = dirname(__DIR__);
$missing = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $f) {
    if ($f->isDir() && strpos($f->getPathname(), '/vendor/') === false && !is_file($f->getPathname() . '/index.php')) {
        $missing[] = $f->getPathname();
    }
}
check('index.php dans chaque dossier : ' . implode(',', $missing), $missing === []);
$secretsInJs = false;
foreach (glob($root . '/views/js/*.js') as $js) {
    $secretsInJs = $secretsInJs || (bool) preg_match('/client_secret|private_key|SECRET/i', (string) file_get_contents($js));
}
check('aucun secret dans le JavaScript', !$secretsInJs);
$screen = (string) file_get_contents($root . '/src/Admin/ConfigScreen.php');
check('écran BO : champs secrets rendus sans valeur', strpos($screen, "type=\"password\" id=\"wssl-' . \$e(\$id . '-' . \$name) . '\" name=\"' . \$e(\$nm) . '\" value=\"\"") !== false);

echo "OK : $passed vérifications\n";
if ($failed) {
    echo 'ÉCHECS (' . count($failed) . ") :\n - " . implode("\n - ", $failed) . "\n";
    exit(1);
}
