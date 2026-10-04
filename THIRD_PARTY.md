# Composants tiers / Third-party components

Ce module n'embarque **aucune bibliothèque tierce** hors de l'autoloader généré par Composer. Les appels réseau utilisent cURL et PHP natifs.
This module bundles **no third-party library** other than the Composer-generated autoloader. Network calls use native cURL and PHP.

| Composant | Version | Licence | Emplacement | Usage |
|---|---|---|---|---|
| composer/composer (ClassLoader et fichiers `autoload_*.php` générés) | 2.x | MIT (copyright Nils Adermann, Jordi Boggiano) | `vendor/composer/`, `vendor/autoload.php` | chargement automatique des classes `Websource\SocialLogin\` (PSR-4) |

La licence MIT de Composer est reproduite dans `vendor/composer/LICENSE`. MIT est compatible avec la licence du module (AFL-3.0).
The Composer MIT licence is reproduced in `vendor/composer/LICENSE`. MIT is compatible with this module's licence (AFL-3.0).

## Dépendances de développement / Development dependencies

Aucune. Les tests (`tests/run.php`) sont autonomes : PHP seul, sans PHPUnit.
None. The tests (`tests/run.php`) are standalone: plain PHP, no PHPUnit.

## Logos des réseaux / Network logos

Les logos Google, Facebook, X, LinkedIn, Microsoft, Apple et GitHub sont des **marques de leurs propriétaires**. Ils sont reproduits sous forme de tracés SVG intégrés au code (`src/Render/IconLibrary.php`) uniquement pour identifier le service de connexion, conformément aux chartes d'utilisation des boutons de connexion de chaque réseau. Aucune police, image ni script tiers n'est chargé depuis un serveur externe par le module.
The Google, Facebook, X, LinkedIn, Microsoft, Apple and GitHub logos are **trademarks of their respective owners**. They are reproduced as inline SVG paths (`src/Render/IconLibrary.php`) only to identify the sign-in service, in line with each network's sign-in button guidelines. The module loads no third-party font, image or script from an external server.

## Licence du module / Module licence

Academic Free License 3.0 : voir `LICENSE.md` / see `LICENSE.md`.
