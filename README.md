# Websource Social Login

**Français** | [English](#english)

Module PrestaShop **gratuit** : connexion et inscription en un clic avec **Google, Facebook, X (Twitter), LinkedIn, Microsoft, Apple et GitHub**. OAuth 2.0 / OpenID Connect sans bibliothèque externe, anti-prise de compte, guides de configuration intégrés (fr/en), conforme RGPD.

![Écran de configuration](docs/img/bo-fr-overview.png)

## Points forts

- Connexion **et création automatique du compte** client ; liaison sécurisée à un compte existant (mot de passe exigé, jamais de fusion automatique).
- 7 réseaux, chacun avec son **guide pas à pas** dans le back-office (création de l'application, URL de rappel à copier, scopes, mise en production, erreurs fréquentes) et un test de configuration.
- Boutons personnalisables : 3 styles, grille d'icônes, formes, couleurs ; emplacements : connexion, inscription, tunnel de commande, en-tête, liste de souhaits.
- Sécurité : `state` à usage unique lié au navigateur, `nonce`, PKCE S256, validation de l'id_token (JWKS), secrets chiffrés, limitation du taux, redirections en liste blanche.
- RGPD : données minimales, export et suppression avec le compte, aucun jeton d'accès conservé.
- Multiboutique, multilingue (fr/en), compatible thèmes tiers (hooks `displayCustomerLoginFormAfter` et `displayWebsourceSocialLogin`).

## Prérequis

PrestaShop 8.x (compatible à partir de 1.7.7), PHP 7.3+ (`curl`, `json`, `openssl`), **HTTPS** sur la boutique, un compte développeur gratuit chez chaque réseau activé.

## Installation

1. Téléchargez `websourcesociallogin-1.0.0.zip` depuis la page [Releases](https://github.com/Websource-fr/websourcesociallogin/releases) (empreinte SHA-256 indiquée dans la release).
2. Back-office > Modules > Gestionnaire de modules > **Installer un module** > choisissez le zip > **Configurer**.

Ou depuis GitHub : `git clone https://github.com/Websource-fr/websourcesociallogin.git modules/websourcesociallogin` puis `php bin/console prestashop:module install websourcesociallogin` (le dossier `vendor/` est fourni).

## Documentation

- Guide utilisateur complet (réseaux, boutons, thème, RGPD, FAQ, développeurs) : [docs/fr/guide-utilisateur.md](docs/fr/guide-utilisateur.md) ([HTML](docs/fr/guide-utilisateur.html), [PDF](docs/websourcesociallogin-guide-fr.pdf)).
- Guides par réseau : section 5 du guide ; l'écran de configuration les affiche aussi avec votre URL de rappel.
- Compatibilité thème (snippets Smarty prêts à coller) : section 7.
- Ajouter un réseau (`ProviderInterface`) : section 11.

## Tests

`php tests/run.php` : PKCE (vecteur RFC 7636), `state`, id_token signés localement, fournisseurs avec client HTTP factice, anti-prise de compte, redirections, styles, guides, traductions (sans PrestaShop ni réseau). L'authentification réelle auprès des réseaux nécessite vos identifiants développeur et n'est pas couverte par les tests automatiques.

## Licence

[Academic Free License 3.0 (AFL-3.0)](LICENSE.md). Composants tiers : [THIRD_PARTY.md](THIRD_PARTY.md). Historique : [CHANGELOG.md](CHANGELOG.md). Par [Websource](https://websource.fr).

---

## English

**Free** PrestaShop module: one-click sign-in and sign-up with **Google, Facebook, X (Twitter), LinkedIn, Microsoft, Apple and GitHub**. OAuth 2.0 / OpenID Connect with no external library, account-takeover protection, built-in setup guides (fr/en), GDPR-friendly.

![Settings screen](docs/img/bo-en-overview.png)

### Highlights

- Sign-in **and automatic customer creation**; safe linking to an existing account (password required, never merged automatically).
- 7 networks, each with its **step-by-step guide** in the back office (creating the app, callback URL to copy, scopes, going live, common errors) and a configuration test.
- Customisable buttons: 3 styles, icon grid, shapes, colours; locations: login, registration, checkout, header, wishlist.
- Security: single-use browser-bound `state`, `nonce`, PKCE S256, id_token validation (JWKS), encrypted secrets, rate limiting, allow-listed redirects.
- GDPR: minimal data, exported and erased with the account, no access token kept.
- Multistore, multilingual (fr/en), works with third-party themes (`displayCustomerLoginFormAfter` and `displayWebsourceSocialLogin` hooks).

### Requirements

PrestaShop 8.x (compatible from 1.7.7), PHP 7.3+ (`curl`, `json`, `openssl`), **HTTPS** on the shop, a free developer account with each network you enable.

### Installation

1. Download `websourcesociallogin-1.0.0.zip` from the [Releases](https://github.com/Websource-fr/websourcesociallogin/releases) page (SHA-256 given in the release notes).
2. Back office > Modules > Module Manager > **Upload a module** > pick the zip > **Configure**.

Or from GitHub: `git clone https://github.com/Websource-fr/websourcesociallogin.git modules/websourcesociallogin` then `php bin/console prestashop:module install websourcesociallogin` (`vendor/` is included).

### Documentation

- Full user guide (networks, buttons, theme, GDPR, FAQ, developers): [docs/en/user-guide.md](docs/en/user-guide.md) ([HTML](docs/en/user-guide.html), [PDF](docs/websourcesociallogin-guide-en.pdf)).
- Per-network guides: section 5; the settings screen also shows them with your callback URL.
- Theme compatibility (ready-to-paste Smarty snippets): section 7.
- Adding a network (`ProviderInterface`): section 11.

### Tests

`php tests/run.php`: PKCE (RFC 7636 vector), `state`, locally signed id_tokens, providers with a fake HTTP client, account-takeover protection, redirects, styles, guides, translations (no PrestaShop or network needed). Real authentication against the networks needs your own developer credentials and is not covered by the automated tests.

### Licence

[Academic Free License 3.0 (AFL-3.0)](LICENSE.md). Third-party components: [THIRD_PARTY.md](THIRD_PARTY.md). History: [CHANGELOG.md](CHANGELOG.md). By [Websource](https://websource.fr).
