# Authentification Google / Apple — app Sugar Paper (Flutter)

Configuration alignée sur le projet Firebase **sugar-paper** et le domaine **sugar-paper.com**.

## Identifiants Apple (configurés)

| Élément | Valeur |
|---------|--------|
| Team ID | `XA8994VJC6` |
| Key ID | `DL3564HLZ4` |
| Services ID | `com.sugarpaper.app` |
| Bundle iOS / package Android | `com.sugarpaper.app` |
| Return URL site web | `https://sugar-paper.firebaseapp.com/__/auth/handler` |
| Return URL app Android | `https://sugar-paper.com/auth/apple-callback` |

Synchroniser l'app Flutter après toute modification de `config/firebase_config.php` :

```bash
php scripts/sync_sugarpaper_auth_config.php
```

---

## Apple Sign-In

### iOS — natif (app iPhone / iPad)

- Capability **Sign in with Apple** : `ios/Runner/Runner.entitlements`
- Bundle ID attendu : `com.sugarpaper.app`
- **Firebase Console** → Authentication → Apple : clé `.p8`, Key ID `DL3564HLZ4`, Team ID `XA8994VJC6`

> **Important** : l'App ID Apple doit correspondre au bundle Flutter (`com.sugarpaper.app`).
> Si vous avez créé `com.sugar-paper.app` (avec tiret), créez aussi un App ID `com.sugarpaper.app`
> avec Sign in with Apple activé, ou alignez le bundle Xcode sur l'App ID existant.

### Android — flux web obligatoire

Sur Android, Apple exige `webAuthenticationOptions` (Services ID + URL de retour HTTPS).

**Configuration code** (générée depuis `config/firebase_config.php`) :

- `kAppleServicesClientId` : `com.sugarpaper.app`
- `kAppleAndroidRedirectUri` : `https://sugar-paper.com/auth/apple-callback` (**app Android uniquement**)
- `kAppleWebOAuthRedirectUri` : `https://sugar-paper.firebaseapp.com/__/auth/handler` (**site web uniquement**)

**Apple Developer** (Services ID `com.sugarpaper.app`) — **2 Return URLs** obligatoires :

| Usage | Return URL |
|-------|------------|
| Site web (Firebase JS) | `https://sugar-paper.firebaseapp.com/__/auth/handler` |
| App Android | `https://sugar-paper.com/auth/apple-callback` |

1. Identifiers → **Services IDs** → `com.sugarpaper.app`
2. **Domains** : `sugar-paper.com`
3. Ajoutez **les deux** Return URLs ci-dessus
4. Firebase → Authentication → Apple : Services ID + Team ID + clé `.p8`

> **Ne pas** utiliser l'URL Firebase handler sur Android : erreur « absence d'état initial ».

Erreur **`invalid_client`** = Return URL absente dans Apple Developer.

### Vérification domaine (obligatoire pour Android)

1. Apple Developer → Services ID → domaine `sugar-paper.com` → **Verify** → télécharger le fichier
2. Déployer sur le VPS : `.well-known/apple-developer-domain-association.txt`
3. Tester : `https://sugar-paper.com/.well-known/apple-developer-domain-association.txt` → doit répondre **200**
4. Voir : `.well-known/README-apple-domain-verification.md`

### Android — callback serveur

Apple envoie un **POST** vers `auth/apple-callback.php`. Cette page redirige vers l'app via :

`intent://callback?code=…#Intent;package=com.sugarpaper.app;scheme=signinwithapple;end`

Prérequis :

- `auth/apple-callback.php` déployé sur le VPS
- Activité `SignInWithAppleCallback` dans `AndroidManifest.xml`
- Règle `.htaccess` : `RewriteRule ^auth/apple-callback$ auth/apple-callback.php [L]`

---

## Site web (connexion / inscription)

- Boutons Google + Apple : `includes/google_auth_button.php`
- Scripts Firebase Auth : `includes/google_auth_scripts.php`
- Logique JS : `js/firebase-social-auth.js`
- Endpoint PHP : `auth-firebase-callback.php`

---

## Fonctionnement dans l'app

- La WebView appelle le code **natif Flutter** (`signInWithGoogle` / `signInWithApple`)
- Le token Firebase est renvoyé au site PHP (`/auth-firebase-callback.php`)

Après toute modification Firebase ou Apple Developer, **republiez** une nouvelle version de l'app.
