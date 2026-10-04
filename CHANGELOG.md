# Changelog

## 1.0.0
- Première version publique : connexion et inscription en un clic avec Google, Facebook, X (Twitter), LinkedIn, Microsoft, Apple et GitHub. / First public release.
- OAuth 2.0 Authorization Code avec `state` à usage unique lié au navigateur, `nonce` et validation complète de l'id_token (signature JWKS RS256, iss, aud, exp) pour les fournisseurs OpenID Connect, PKCE S256 là où le réseau le gère.
- Anti-prise de compte : jamais de fusion automatique ; e-mail vérifié correspondant à un client existant = mot de passe exigé ; e-mail non vérifié ou absent = confirmation par lien (même navigateur).
- Table `websourcesociallogin_link` (multiboutique), bloc « Connexions sociales » dans Mon compte (lier / délier), RGPD (export, suppression), limitation du taux d'essais, journal sobre.
- Boutons « Continuer avec … » : connexion, inscription, tunnel de commande, menu « Mon compte » de l'en-tête, fenêtre de la liste de souhaits ; 3 styles, grille d'icônes, séparateur « ou », dernier réseau utilisé.
- Écran de configuration par réseau avec guides pas à pas (fr/en), URL de rappel avec bouton copier, test de configuration.
- Documentation utilisateur (fr/en, HTML et PDF), licence AFL-3.0 avec en-têtes de copyright, THIRD_PARTY.md.
