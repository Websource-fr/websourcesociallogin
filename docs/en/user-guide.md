# Websource Social Login: user guide

Version 1.0.0: free PrestaShop module, AFL-3.0 licence, by [Websource](https://websource.fr).

## 1. Overview

**Websource Social Login** lets your customers sign in and create their account in one click with **Google, Facebook, X (Twitter), LinkedIn, Microsoft, Apple and GitHub**.

- Sign-in **and automatic registration**: the customer account is created from the network profile (ID, first name, last name, email).
- No external library: cURL and native PHP (OAuth 2.0 / OpenID Connect, PKCE, id_token validation).
- **Account-takeover protection**: accounts are never merged automatically; an email the network has not verified is confirmed by link.
- One settings card per network with a built-in **step-by-step guide** (English and French), the callback URL to copy and a configuration test.
- Customisable buttons (3 styles, icon grid, colours, shapes) shown where you want: login, registration, checkout, header, wishlist.
- GDPR: minimal data, exported and erased with the customer account.

![Settings screen: networks and callback URL](../img/bo-en-overview.png)

### How a sign-in works

1. The customer clicks "Continue with Google" (or another network).
2. When returning from the network:
    - social account already linked: the customer is **signed in**;
    - **verified** email and existing customer: the customer **enters their password** to link both accounts (never merged automatically);
    - verified and unknown email: the **customer is created** (strong random password, default group, newsletter and partner offers unchecked) and signed in;
    - **unverified** email (e.g. Microsoft) or **missing** email (e.g. X): the customer enters an email and receives a **confirmation link** (valid for 1 hour, to open in the same browser).
3. In "My account > Social connections" the customer links or unlinks a network.

## 2. Requirements

| Item | Requirement |
|---|---|
| PrestaShop | 8.x (compatible from 1.7.7) |
| PHP | 7.3 or higher (`curl`, `json`, `openssl` extensions) |
| HTTPS | **mandatory**: networks refuse an `http://` callback URL (Shop Parameters > Traffic & SEO > Enable SSL on all pages) |
| Developer accounts | a free account with each network you enable |
| Firewall | your server must be able to reach the networks over outbound HTTPS (cURL) |

You do not have to enable all seven networks: enable those your customers use.

## 3. Installation

### From the zip (recommended)

1. Download `websourcesociallogin-1.0.0.zip` (module page on [websource.fr](https://websource.fr) or the repository's "Releases" page). To check it, compare with the published SHA-256: `sha256sum websourcesociallogin-1.0.0.zip`.
2. Back office > **Modules > Module Manager > Upload a module**, pick the zip.
3. Click **Configure** when the installation is done.

### From GitHub

```bash
cd modules/
git clone https://github.com/Websource-fr/websourcesociallogin.git
cd ..
php bin/console prestashop:module install websourcesociallogin
```

The repository also contains `docs/` and `tests/` (not needed in production). The `vendor/` folder is included: no Composer command is required.

### Updating

Replace the module folder (or upload the new zip): PrestaShop runs the upgrade scripts. Your settings are kept.

## 4. General configuration

Back office > Modules > Websource Social Login > **Configure**. The screen has four parts: **Networks**, **Appearance and locations**, **Texts and GDPR**, **Help**. Settings are **per shop** (multistore: choose the shop context).

For each network (see section 5):

| Field | Purpose |
|---|---|
| Enable | network switch |
| Client ID / Client Secret | given by the network; the secret is **encrypted** in the database and never sent back to the page ("•••• saved"); left empty, it is kept |
| Requested scopes | permissions requested; the default fits almost every case |
| Position | display order (1 = first) |
| Callback URL | exact address to paste at the network, with a **Copy** button |
| Test the configuration | checks the filled fields, the https callback URL, the scopes and the activation |
| Status | *Active*, *Configured*, *Incomplete*, *Not configured* |

**A network only shows on the storefront when it is enabled AND complete.**

![Network card and Google step-by-step guide](../img/bo-en-google-guide.png)

## 5. Network guides

Common principle: (1) create an application at the network, (2) register the **callback URL** shown by the module there (it is specific to your shop; in the examples below: `https://your-shop.example/en/module/websourcesociallogin/callback`), (3) copy the ID and secret into the module, (4) enable, save, test, (5) **publish the application** at the network: while it is in test or development mode, only your test accounts can sign in.

These guides are the ones from the settings screen (Networks tab, "Step-by-step guide"), which show your own callback URL with a Copy button. Network interfaces change: if something differs, follow the step titles and the official links.


### 1. Google

Google uses OpenID Connect: the module validates the id_token signature and only accepts the email if it is verified by Google. Allow about 10 minutes. No cost.

- **Console** : [Google Cloud Console](https://console.cloud.google.com/apis/credentials)
- **Callback URL to register** : `https://your-shop.example/en/module/websourcesociallogin/callback`

#### Step 1. Create (or select) a Google Cloud project

*Where : console.cloud.google.com > project picker (top bar) > New project*

Sign in with your company's Google account. Click the project picker, then **New project**. Give it your store's name and confirm.

#### Step 2. Configure the OAuth consent screen

*Where : Menu ☰ > APIs & Services > OAuth consent screen (or "Google Auth Platform > Branding")*

This is the window your customers see when signing in. Choose the **External** user type.

| Fields to fill in | |
|---|---|
| App name | your store's name |
| User support email | an address you actually read |
| Authorized domains | your-shop.example |
| Application privacy policy link | https://your-shop.example/ (your "privacy policy" page) |
| Developer contact information | your email |

#### Step 3. Declare the permissions (scopes)

*Where : Consent screen > Data Access (Scopes) > Add or remove scopes*

Add only the three "non-sensitive" scopes: `openid`, `.../auth/userinfo.email` and `.../auth/userinfo.profile`. They do not trigger an in-depth review by Google.

#### Step 4. Create the OAuth client ID

*Where : Menu ☰ > APIs & Services > Credentials > + Create credentials > OAuth client ID*

Application type: **Web application**. In **Authorized redirect URIs**, paste the address below exactly (https, no spaces, no extra trailing "/").

| Fields to fill in | |
|---|---|
| Application type | Web application |
| Name | Store login |
| Authorized JavaScript origins | leave empty (not needed) |
| Authorized redirect URIs | `https://your-shop.example/en/module/websourcesociallogin/callback` |

#### Step 5. Copy the client ID and client secret into the module

*Where : "OAuth client created" dialog > Client ID + Client secret*

Copy the **Client ID** (ends with `.apps.googleusercontent.com`) and the **Client secret**, paste them into this module's fields, enable Google, then save. The secret is never displayed in clear text afterwards.

#### Step 6. Move to production

*Where : Consent screen > Publishing status > Publish app*

While the app is in **Testing**, only the "test users" you list (100 maximum) can sign in, and their authorization expires after 7 days. Click **Publish app** (status "In production"). With only the non-sensitive scopes above, no verification by Google is required.

**Permissions (scopes)** : Requested scopes: `. The module receives: Google identifier (sub), email, email verification status, first name, last name. Nothing else.

**Common errors**

- **Error 400: redirect_uri_mismatch** : The redirect URI entered in Google is not strictly identical to `https://your-shop.example/en/module/websourcesociallogin/callback`. Check https, the domain name (with or without www) and any extra trailing "/". Changes may take a few minutes to take effect.
- **Error 403: access_denied / "App has not completed the Google verification process"** : The app is still in Testing mode and your account is not among the test users: add it, or publish the app (step 6).
- **Error 401: invalid_client** : The client ID or client secret is incorrect or has been regenerated. Paste them again from the Credentials page.
- **"Google hasn't verified this app"** : Expected if you request sensitive scopes. Stick to the three scopes from step 3.
- **Error 400: invalid_request ("Google policies prohibit...")** : A redirect URI is not https, or the domain is not declared in the consent screen.

**Official links** : [Google credentials console](https://console.cloud.google.com/apis/credentials) · [Google OpenID Connect documentation](https://developers.google.com/identity/openid-connect/openid-connect)


### 2. Facebook

Facebook Login (Meta). The `public_profile` and `email` permissions are granted by default, without app review. Allow about 15 minutes. Free.

- **Console** : [Meta for Developers](https://developers.facebook.com/apps/)
- **Callback URL to register** : `https://your-shop.example/en/module/websourcesociallogin/callback`

#### Step 1. Create a Meta app

*Where : developers.facebook.com > My Apps > Create App*

Sign in with a Facebook account (which becomes a "developer account"). Click **Create App**, enter your store's name and your email. For the use case, choose **Authenticate and request data from users with Facebook Login**. App type: "Consumer" or "Other", depending on the wizard.

#### Step 2. Fill in the basic information

*Where : App settings > Basic*

Fill in what is required to go Live, then copy the **App ID** and the **App secret** ("Show" button, your Facebook password is requested).

| Fields to fill in | |
|---|---|
| App domains | your-shop.example |
| Privacy Policy URL | your privacy policy page (required) |
| Terms of Service URL | your terms and conditions page (recommended) |
| App icon (1024 x 1024) | your logo |
| Category | Shopping / Commerce |

#### Step 3. Configure Facebook Login

*Where : Dashboard > Use cases > Facebook Login > Customize > Settings (or Products > Facebook Login > Settings)*

Enable **Client OAuth login** and **Web OAuth login**, **Enforce HTTPS** and **Strict Mode** for redirect URIs, then paste the address below.

| Fields to fill in | |
|---|---|
| Valid OAuth Redirect URIs | `https://your-shop.example/en/module/websourcesociallogin/callback` |

#### Step 4. Check the permissions

*Where : Use cases > Facebook Login > Permissions and features*

Make sure `public_profile` and `email` are set to "Standard access" (they are by default). No app review is needed for these two.

#### Step 5. Paste the ID and secret into the module

*Where : This module > Facebook*

App ID = "Client ID", App secret = "Client Secret". Enable Facebook and save.

#### Step 6. Switch the app to Live mode

*Where : Banner at the top of the dashboard > "App Mode": Development → Live*

In **Development** mode, only the app's administrators, developers and testers can sign in. Switch to **Live** once the privacy policy and category are filled in. "Business verification" is only required for advanced access, not for public_profile + email.

**Permissions (scopes)** : Requested permissions: `. The module receives: Facebook identifier (id), first name, last name and email if there is one. Facebook only returns confirmed emails.

**Common errors**

- **"URL Blocked: This redirect failed" / redirect_uri is not whitelisted** : The URI entered in "Valid OAuth Redirect URIs" differs from `https://your-shop.example/en/module/websourcesociallogin/callback`. Copy it with the button, enable Strict Mode and try again.
- **"Feature Unavailable" / "App not active"** : The app is in Development mode and your account has no role on it. Add it under App roles, or switch to Live (step 6).
- **"Can't load URL: The domain of this URL isn't included in the app's domains"** : Add your-shop.example under Settings > Basic > App domains.
- **No email received** : The customer has no confirmed email on Facebook, or they unchecked the permission. The module then asks them to enter their email, which they confirm via a link.
- **Invalid client_secret / Error validating client secret** : The app secret was regenerated or copied incorrectly: paste it again.

**Official links** : [My Meta apps](https://developers.facebook.com/apps/) · [Facebook Login documentation](https://developers.facebook.com/docs/facebook-login/web)


### 3. X (Twitter)

X (formerly Twitter): OAuth 2.0 with PKCE, "confidential client" application type. The email is only provided if your app is allowed to access it (scope `users.email`); otherwise the module will ask the customer for it. X API plans and quotas change over time: check them on the portal.

- **Console** : [X Developer Portal](https://developer.x.com/en/portal/dashboard)
- **Callback URL to register** : `https://your-shop.example/en/module/websourcesociallogin/callback`

#### Step 1. Open an X developer account

*Where : developer.x.com > Developer Portal*

Sign in with your company's X account and accept the developer agreement (describe the use: "signing in my store's customers with their X account").

#### Step 2. Create a Project and an App

*Where : Developer Portal > Projects & Apps > + Add Project (then Add App)*

The app must be attached to a **Project** (otherwise the API responds "client-not-enrolled").

#### Step 3. Enable user authentication (OAuth 2.0)

*Where : Your App > Settings > User authentication settings > Set up*

This is where sign-in is configured.

| Fields to fill in | |
|---|---|
| App permissions | Read (read-only is enough) |
| Request email from users | tick if available (requires the two URLs below) |
| Type of App | Web App, Automated App or Bot (= Confidential client) |
| Callback URI / Redirect URL | `https://your-shop.example/en/module/websourcesociallogin/callback` |
| Website URL | https://your-shop.example |
| Terms of service / Privacy policy | URLs of your terms and conditions and your privacy policy |

#### Step 4. Retrieve the Client ID and Client Secret

*Where : Your App > Keys and tokens > OAuth 2.0 Client ID and Client Secret*

Click **Generate** (or Regenerate). The **Client Secret is only displayed once**: copy it immediately. Do not use "API Key / API Secret" or the "Bearer Token": those are other mechanisms (OAuth 1.0a).

#### Step 5. Choose the scopes

*Where : This module > X > Scopes*

Default: `users.read tweet.read users.email`. If X responds "invalid_scope", remove `users.email`: sign-in will work, and the module will ask the customer for their email.

#### Step 6. Paste the credentials into the module

*Where : This module > X*

Client ID, Client Secret, enable X, then save.

#### Step 7. Going live

*Where : Developer Portal > your App > Settings*

X has no "development / live" mode: the app is usable as soon as it is created. Do check the access level (Free, Basic...) and its request quotas, though: a high-traffic store may exceed the limits of a free tier.

**Permissions (scopes)** : Requested scopes: `. The module receives: X identifier (id), display name and, if allowed, the confirmed email (confirmed_email).

**Common errors**

- **"Something went wrong. You weren't able to give access to the App"** : The callback URL registered in X differs from `https://your-shop.example/en/module/websourcesociallogin/callback` (https, domain, trailing "/"). Fix it in User authentication settings.
- **invalid_scope** : The `users.email` scope is not allowed for your app: remove it from the module's scopes.
- **client-not-enrolled / 403** : The App is not in a Project, or your access level does not include the /2/users/me endpoint.
- **429 Too Many Requests** : X plan quota exceeded: wait or change plan.
- **No email received** : Expected if the email is not allowed or is not confirmed on X: the customer enters their email and confirms it via a link.

**Official links** : [X Developer Portal](https://developer.x.com/en/portal/dashboard) · [OAuth 2.0 documentation (X)](https://docs.x.com/resources/fundamentals/authentication/oauth-2-0/authorization-code)


### 4. LinkedIn

LinkedIn via the "Sign In with LinkedIn using OpenID Connect" product (standard OpenID Connect). A LinkedIn company Page is required to create the app. Allow about 10 minutes. Free.

- **Console** : [LinkedIn Developer Portal](https://www.linkedin.com/developers/apps)
- **Callback URL to register** : `https://your-shop.example/en/module/websourcesociallogin/callback`

#### Step 1. Create the app

*Where : linkedin.com/developers/apps > Create app*

Fill in the fields; the LinkedIn Page must belong to your company (you must be an administrator of it).

| Fields to fill in | |
|---|---|
| App name | your store's name |
| LinkedIn Page | your company's LinkedIn Page |
| Privacy policy URL | your privacy policy |
| App logo | your logo (minimum 100×100 px) |

#### Step 2. Add the "Sign In with LinkedIn using OpenID Connect" product

*Where : Your app > Products tab*

Click **Request access** next to "Sign In with LinkedIn using OpenID Connect" (access is granted immediately). Without this product, LinkedIn responds "unauthorized_scope_error".

#### Step 3. Declare the redirect URL

*Where : Your app > Auth tab > OAuth 2.0 settings > Authorized redirect URLs for your app*

Click the pencil, **Add redirect URL**, paste the address below, then Update.

| Fields to fill in | |
|---|---|
| Authorized redirect URLs | `https://your-shop.example/en/module/websourcesociallogin/callback` |

#### Step 4. Copy the Client ID and Client Secret

*Where : Your app > Auth tab > Application credentials*

Copy the **Client ID** and the **Primary Client Secret**, paste them into this module, enable LinkedIn, then save.

#### Step 5. Verify the app

*Where : Your app > Settings tab > Verify*

Click **Verify**: LinkedIn generates a link that the Page administrator must approve. An unverified app may display a warning to customers. There is no separate "live" mode: the app works as soon as the product is added.

**Permissions (scopes)** : Requested scopes: ` (openid, profile, email). The module receives: LinkedIn identifier (sub), first name, last name, email and its verification status.

**Common errors**

- **redirect_uri doesn't match / Bummer, something went wrong** : The URL registered in the Auth tab differs from `https://your-shop.example/en/module/websourcesociallogin/callback`. It must be identical character for character.
- **unauthorized_scope_error / scope not authorized** : The "Sign In with LinkedIn using OpenID Connect" product has not been added (Products tab): add it.
- **invalid_client_id / invalid_client_secret** : Copy the Client ID and the Primary Client Secret again from the Auth tab (not the Page identifier).
- **No email (or unverified email)** : The member has no confirmed email: the module asks for their email and confirms it via a link.

**Official links** : [My LinkedIn apps](https://www.linkedin.com/developers/apps) · [Sign In with LinkedIn documentation (OpenID Connect)](https://learn.microsoft.com/linkedin/consumer/integrations/self-serve/sign-in-with-linkedin-v2)


### 5. Microsoft

Microsoft Entra ID (work or school accounts, and personal Outlook, Hotmail and Xbox accounts). Important: Microsoft does not guarantee that the email of a work account belongs to its holder; the module therefore only treats it as verified if Microsoft attests it (`xms_edov` claim) and otherwise asks for an email confirmation.

- **Console** : [Microsoft Entra admin center](https://entra.microsoft.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade/quickStartType~/null/sourceType/Microsoft_AAD_IAM)
- **Callback URL to register** : `https://your-shop.example/en/module/websourcesociallogin/callback`

#### Step 1. Register an application

*Where : entra.microsoft.com > Identity > Applications > App registrations > + New registration*

You need an Azure/Entra account (a free Microsoft account is enough to create the tenant).

| Fields to fill in | |
|---|---|
| Name | your store's name |
| Supported account types | Accounts in any organizational directory (Any Microsoft Entra ID tenant - Multitenant) and personal Microsoft accounts (e.g. Skype, Xbox) |
| Redirect URI - platform | Web |
| Redirect URI | `https://your-shop.example/en/module/websourcesociallogin/callback` |

#### Step 2. Note the Application (client) ID

*Where : Your registration > Overview*

Copy the **Application (client) ID**: this is the Client ID. The module's "Tenant" field stays on `common` (work and personal accounts). Use `consumers` for personal accounts only, `organizations` for work accounts only, or the Directory (tenant) ID for a single directory.

#### Step 3. Create a client secret

*Where : Certificates & secrets > Client secrets > + New client secret*

Choose a duration (24 months maximum), then immediately copy the **Value** column (not the "Secret ID"). It will not be displayed again. **Note the expiration date**: when it expires, sign-in will stop working until you create a new secret.

#### Step 4. Check the permissions

*Where : API permissions*

Microsoft Graph, delegated permissions: `openid`, `profile`, `email` (and User.Read by default). No admin consent is required for these permissions.

#### Step 5. Add optional claims (recommended)

*Where : Token configuration > + Add optional claim > ID*

Add `email`, `given_name`, `family_name` and, if offered, `xms_edov` (email domain verified). Agree to turn on the Graph "email" permission if Entra offers it.

#### Step 6. Branding and going live

*Where : Branding & properties*

Fill in the logo, the terms of service and privacy statement URLs, and if possible **publisher verification** (removes the "unverified" label on the consent screen). There is no "test" mode to leave: the app is usable as soon as it is registered. In some organizations, an administrator must approve the app for their users.

**Permissions (scopes)** : Requested scopes: `. The module receives: identifier (tenant + object), name, email (preferred: email claim).

**Common errors**

- **AADSTS50011: The reply URL does not match** : The registration's redirect URI ("Web" platform) differs from `https://your-shop.example/en/module/websourcesociallogin/callback`. Fix it under Authentication.
- **AADSTS7000215: Invalid client secret** : You copied the "Secret ID" instead of the **Value**, or the secret has expired: create a new one.
- **AADSTS700016 / AADSTS50020: Application not found in the tenant** : The registration's account type or the module's "Tenant" field do not match (e.g. personal account with the "organizations" tenant).
- **AADSTS65001: Consent required** : An administrator of the customer's organization must approve the app, or the customer must accept the request.
- **An email is requested again from the customer** : Expected: Microsoft does not attest ownership of the email (protection against the nOAuth attack). Adding the `xms_edov` claim (step 5) avoids this step when Microsoft provides it.

**Official links** : [Entra app registrations](https://entra.microsoft.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade/quickStartType~/null/sourceType/Microsoft_AAD_IAM) · [Authorization code flow documentation](https://learn.microsoft.com/entra/identity-platform/v2-oauth2-auth-code-flow)


### 6. Apple

Sign in with Apple: the most technical of the networks. It requires the Apple Developer Program (99 USD/year), a Services ID, a `.p8` private key and an HTTPS address. The module generates the "client secret" (an ES256-signed JWT) itself on each sign-in from your key: you do not have to renew it every 6 months. Apple may hide the customer's email (relay address @privaterelay.appleid.com).

- **Console** : [Apple Developer](https://developer.apple.com/account/resources/identifiers/list/serviceId)
- **Callback URL to register** : `https://your-shop.example/en/module/websourcesociallogin/callback`

#### Step 1. Join the Apple Developer Program

*Where : developer.apple.com/programs > Enroll*

Membership is paid (99 USD/year) and can take 1 to 2 days for a company (D-U-N-S number).

#### Step 2. Create an App ID with "Sign in with Apple"

*Where : Certificates, Identifiers & Profiles > Identifiers > + > App IDs > App*

Description and Bundle ID (e.g. `com.yourstore.web`). Under Capabilities, tick **Sign in with Apple**, then Continue > Register. This App ID serves as the "Primary App ID".

#### Step 3. Create a Services ID (= Client ID)

*Where : Identifiers > + > Services IDs*

Description and Identifier (e.g. `com.yourstore.signin`): this **Identifier is the Client ID**. Tick **Sign in with Apple** then click **Configure**.

| Fields to fill in | |
|---|---|
| Primary App ID | the App ID created in step 2 |
| Domains and Subdomains | your-shop.example (without https://) |
| Return URLs | `https://your-shop.example/en/module/websourcesociallogin/callback` |

#### Step 4. Create a private key

*Where : Keys > + > Key Name > Sign in with Apple > Configure*

Choose the Primary App ID, confirm, then **download the `AuthKey_XXXXXXXXXX.p8` file**: Apple only offers it once, keep it somewhere safe. Note the **Key ID** (10 characters).

#### Step 5. Find the Team ID

*Where : developer.apple.com/account > Membership details > Team ID*

10-character identifier.

#### Step 6. Fill in the module

*Where : This module > Apple*

Services ID, Team ID, Key ID, then paste the **entire contents** of the .p8 file (from `-----BEGIN PRIVATE KEY-----` to `-----END PRIVATE KEY-----`) into the "Private key" field. It is encrypted in the database and never sent back to the browser. Enable Apple and save.

#### Step 7. Going live and relay emails

*Where : Certificates, Identifiers & Profiles > More > Configure Sign in with Apple for Email Communication*

Apple has no "test" mode: everything works as soon as it is configured. If a customer chooses "Hide My Email", your transactional emails go through an Apple relay: register your sending domain and sender addresses there (valid SPF and DKIM), otherwise they will not be delivered. Apple may also ask you to verify the domain by placing a file in `/.well-known/`.

**Permissions (scopes)** : Requested scopes: `. The module receives: Apple identifier (sub), email (real or relay), verification status, and the name only on the very first authorization.

**Common errors**

- **invalid_client** : The Services ID, Team ID, Key ID or .p8 key do not match, or the key is not associated with the right Primary App ID. Recreate the key if needed.
- **invalid_request: Invalid web redirect url / redirect_uri mismatch** : The Services ID's "Return URLs" differ from `https://your-shop.example/en/module/websourcesociallogin/callback`, or the domain is not listed in "Domains and Subdomains".
- **Back on the site with "session expired" after Apple** : Apple responds with a POST from another domain: the site must be on HTTPS (SameSite=None; Secure cookie). Check the certificate and that the binding cookie is not blocked.
- **First name and last name are empty** : Apple only sends the name on the first authorization. The customer can revoke access at appleid.apple.com > Sign-In and Security > Sign in with Apple, then try again.
- **Emails not delivered to @privaterelay.appleid.com** : Register your sending domain in "Configure Sign in with Apple for Email Communication" (step 7).

**Official links** : [Apple Developer Services IDs](https://developer.apple.com/account/resources/identifiers/list/serviceId) · [Sign in with Apple documentation (web)](https://developer.apple.com/documentation/signinwithapple/configuring-your-webpage-for-sign-in-with-apple)


### 7. GitHub

GitHub (OAuth App). Ideal for a developer audience. The module only uses emails marked "verified" by GitHub. Allow 5 minutes. Free.

- **Console** : [GitHub Developer settings](https://github.com/settings/developers)
- **Callback URL to register** : `https://your-shop.example/en/module/websourcesociallogin/callback`

#### Step 1. Open the developer settings

*Where : github.com > your avatar > Settings > Developer settings > OAuth Apps > New OAuth App*

For an app owned by an organization: Organization > Settings > Developer settings > OAuth Apps.

#### Step 2. Fill in the form

*Where : Register a new OAuth application*

GitHub accepts **only one** callback URL per OAuth App: create one app per store domain.

| Fields to fill in | |
|---|---|
| Application name | your store's name |
| Homepage URL | https://your-shop.example |
| Authorization callback URL | `https://your-shop.example/en/module/websourcesociallogin/callback` |
| Enable Device Flow | leave unchecked |

#### Step 3. Retrieve the Client ID and generate the secret

*Where : After "Register application" > Client ID, then "Generate a new client secret"*

Copy the **Client ID** then click **Generate a new client secret**: it is only displayed once, copy it into this module immediately.

#### Step 4. Scopes

*Where : This module > GitHub > Scopes*

Default `read:user user:email`: `user:email` allows reading the account's emails, including the verified primary one.

#### Step 5. Going live

*Where : Application > Advanced / logo*

OAuth Apps have no review or test mode: they are immediately usable by all GitHub accounts. Add a logo (Upload new logo). If a customer's organization restricts access to third-party applications, they will see "Request": their organization's administrator has to approve it.

**Permissions (scopes)** : Requested scopes: `. The module receives: GitHub identifier (id), name, verified primary email.

**Common errors**

- **redirect_uri_mismatch** : The OAuth App's callback URL differs from `https://your-shop.example/en/module/websourcesociallogin/callback` (only one URL per app is possible, and its path must start with the registered one).
- **incorrect_client_credentials** : Wrong Client ID or secret: regenerate a secret.
- **bad_verification_code** : The code has already been used or has expired (10 minutes): start the sign-in again.
- **No email received** : The account has no verified address (or the user:email scope was removed): the customer enters their email and confirms it via a link.

**Official links** : [GitHub OAuth Apps](https://github.com/settings/developers) · [GitHub OAuth documentation](https://docs.github.com/apps/oauth-apps/building-oauth-apps/authorizing-oauth-apps)


### Common issues (all networks)

- **No button appears** : A network is only visible if it is **enabled AND complete** (all required fields filled in) and the location is ticked in "Where to display the buttons". Incomplete networks stay invisible to your customers.
- **"Session expired" after returning from the network** : The binding cookie was blocked (browser in strict private browsing, different domain between www and non-www) or the request took more than 10 minutes. Try again; always use the same address (the callback URL above is the store's).
- **The network rejects an "http://" URL** : Switch the store to HTTPS (Shop Parameters > Traffic & SEO > Enable SSL on all pages). The callback URL is displayed in https.
- **Multilingual store or several domains** : The callback URL is the same for all languages of a domain (it uses the default language). With several domains/stores, register each store's URL with the network (displayed in each store).

## 6. Customising the buttons

**Appearance and locations** tab: the preview updates live.

| Setting | Options |
|---|---|
| Style | *Outline* (white background, theme border), *Filled* (network brand colour), *Neutral* (theme colours) |
| Layout | full-width column, or icon grid |
| Shape | square, rounded (theme), pill |
| Icon only | hides the text (kept for screen readers) |
| Radius, border | in pixels (0 to 32 and 0 to 4) |
| Colours | background, text, border: `#rgb`, `#rrggbb`, `rgb()`, `rgba()`, `transparent` or `var(--theme-variable)`; any other value is rejected |
| "or" separator | shown or not |
| Last used network | moved first (local cookie, no personal data) |

Google and Microsoft logos keep their official colours, as their brand guidelines require.

| Outline | Filled | Neutral |
|---|---|---|
| ![](../img/buttons-en-outline.png) | ![](../img/buttons-en-brand.png) | ![](../img/buttons-en-neutral.png) |

### Locations

Tick where to show the buttons: **login page**, **registration page**, **checkout** (personal information), **header** ("My account" menu, visitors), **wishlist sign-in window**. Buttons are never shown to a signed-in customer.

![Appearance and locations](../img/bo-en-appearance.png)

### Texts and GDPR

**Texts and GDPR** tab: button text (per language), separator and information notice shown under the buttons (adapt it to your privacy policy).

![Texts and GDPR](../img/bo-en-texts-gdpr.png)

## 7. Theme compatibility

The module relies on two hooks:

- `displayCustomerLoginFormAfter`: native hook of the login form (Classic, Hummingbird and most third-party themes). Nothing to do: buttons show on the login page.
- `displayWebsourceSocialLogin`: the module's own hook, to add in your theme for the other locations, with the `place` parameter: `register`, `checkout`, `header`, `wishlist`.

If a location does not show, add the call to the theme template (copy it into your child theme if needed), then clear the Smarty cache:

```smarty
{* Registration page: themes/<theme>/templates/customer/registration.tpl, after the form *}
{hook h='displayWebsourceSocialLogin' place='register'}

{* Checkout: templates/checkout/_partials/steps/personal-information.tpl *}
{hook h='displayWebsourceSocialLogin' place='checkout'}

{* Header "My account" menu (visitor), in your account block template *}
{hook h='displayWebsourceSocialLogin' place='header'}

{* Wishlist window (modals/login.tpl of the blockwishlist module, theme override) *}
{hook h='displayWebsourceSocialLogin' place='wishlist'}

{* Login page if your theme does not call the native hook: templates/customer/authentication.tpl *}
{hook h='displayCustomerLoginFormAfter'}
```

The "My account" page gets a **Social connections** tile through the native `displayCustomerAccount` hook.

**Styling**: `views/css/front.css` uses the theme's CSS variables (`--color-brand-*`, `--color-line`, `--radius-xl`...) with fallbacks. For a third-party theme, adjust style, colours and radius in the Appearance tab, or override `.wssl`, `.wssl-btn`, `.wssl__sep` in your theme CSS.

After a template change: Advanced Parameters > Performance > Clear cache.

## 8. GDPR and security

### Stored data

Table `ps_websourcesociallogin_link` (one row per linked network): customer, network, the customer's ID at the network, email received, consent date, creation date, last sign-in date. Table `ps_websourcesociallogin_flow`: temporary sign-in states (hashed, deleted at expiry, 10 minutes to 1 hour). **No access token is stored or logged.** The PrestaShop log only holds the network, the event and a short code (no email, token or secret); the IP address is only kept as a hash for rate limiting.

Tell your customers in your privacy policy: the module receives the ID, first name, last name and email from the network (nothing is posted to the network). A configurable notice is shown under the buttons.

### Export and erasure

Links are included in the customer's GDPR export (`actionExportGDPRData`) and deleted with the account (`actionDeleteGDPRCustomer`, customer deletion). Customers can unlink a network themselves in My account > Social connections.

### Protections

- **Account-takeover protection**: never merged automatically. Verified email matching an existing customer: password required (5 attempts max). Unverified or missing email: confirmation link, same browser. Microsoft: email treated as unverified unless the `xms_edov` claim is present (nOAuth protection).
- Random 256-bit `state`, single use, 10 minutes, bound to the browser by an `HttpOnly` cookie; OpenID Connect `nonce`; PKCE S256 where the network supports it.
- id_token: RS256 only (`none` and HS256 rejected), signature checked through JWKS, `iss`, `aud`, `exp`, `iat`, `nbf` verified.
- Redirects restricted to an allow-list (no open redirect).
- Secrets encrypted at rest (PrestaShop encryption), never in HTML or JavaScript.
- Rate limiting: 30 starts per 10 minutes per IP.
- Link and unlink use POST with a CSRF token.

## 9. Troubleshooting and FAQ

**No button shows.** The network must be enabled and complete, and the location ticked. Check the theme hook (section 7), clear the cache. A signed-in customer never sees the buttons.

**"redirect_uri_mismatch" or similar.** The URL registered at the network differs from the module's: copy it with the Copy button (https, same domain with or without www, no trailing "/"). A change may take a few minutes to apply.

**Only my own accounts can sign in.** The application is in test/development mode at the network: publish it (see the end of each guide).

**"Session expired" on return.** Blocked cookie, different domain (www or not), or more than 10 minutes elapsed: try again, always using the same address.

**The customer has no email.** They enter one and confirm it by link (X, Microsoft, Apple relay...).

**Where are the errors?** Advanced Parameters > Information > Logs: filter on `[websourcesociallogin]`.

**Multilingual or multistore shop?** The callback URL is the same for all languages of a domain. With several domains or shops, register each shop's URL at the network (shown in each context).

**My theme is third-party and a location is missing.** See section 7.

**Is there a cost?** The module is free; the listed networks do not charge for sign-in.

## 10. Uninstalling

Modules > Websource Social Login > Uninstall: removes the two module tables (links and temporary states) and all its settings. **Network-customer links are then lost**: customers created through a network keep their account but have no known password and will have to use "Forgot your password?". Export what you need first.

## 11. Developer FAQ: adding a network

Networks are `Websource\SocialLogin\Provider\ProviderInterface` classes (`src/Provider/` folder). For an OpenID Connect provider extend `OidcProvider`; otherwise `AbstractProvider`.

1. Create `src/Provider/MyNetworkProvider.php` implementing: `id()`, `label()`, `fields()` (settings fields: `client_id`, `client_secret`...), `defaultScopes()`, `usesPkce()`, `usesFormPost()`, `buildAuthorizationUrl(...)` and `fetchProfile(...)` returning a `Profile` object (ID, email, email verified, first name, last name).
2. Register it in `ProviderRegistry::default()`.
3. Add its guide to `src/Guide/data_fr.php` and `data_en.php` (keys `intro`, `console`, `steps`, `scopes`, `errors`, `links`) and its logo to `src/Render/IconLibrary.php`.
4. Run the tests: `php tests/run.php` (no PrestaShop or network needed; providers are tested with a fake HTTP client).

Account-takeover protection and the linking flow live in the core (`Linking\LinkingService`, `Flow\FlowService`): a provider only has to return a trustworthy `Profile`, stating honestly whether the email is verified.

## 12. Changelog

See [CHANGELOG.md](../../CHANGELOG.md). Version 1.0.0: first public release.

## 13. Licence and support

Academic Free License 3.0 (AFL-3.0), see `LICENSE.md`. Third-party libraries: see `THIRD_PARTY.md`. Report an issue on [GitHub](https://github.com/Websource-fr/websourcesociallogin/issues) or via [websource.fr](https://websource.fr).
