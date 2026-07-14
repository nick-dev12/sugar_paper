# Authentification Google / Apple — app Sugar Paper (Flutter)

## Identifiants Apple (juillet 2026)

| Élément | Valeur |
|--------|--------|
| Team ID | `XA8994VJC6` |
| Key ID | `GDH9F8THP9` (fichier `AuthKey_GDH9F8THP9.p8` — **Firebase Console uniquement**, jamais committer) |
| App ID (Primary) | `com.goobridge.sugarpaper` |
| Services ID | `com.goobridge.sugarpaper.signin` |
| Bundle iOS (App Store) | `com.goobridge.sugarpaper` |
| Package Android (Play Store) | `com.sugarpaper.app` |
| Domaine | `sugar-paper.com` |
| Return URL site web | `https://sugar-paper.firebaseapp.com/__/auth/handler` |
| Return URL app Android | `https://sugar-paper.com/auth/apple-callback` |

> La clé `.p8` se colle dans **Firebase → Authentication → Sign-in method → Apple**.  
> Elle n’est **pas** utilisée par le PHP ni par Flutter.

## Firebase Console (déjà aligné)

- **Enable** Apple : ON  
- **Services ID** : `com.goobridge.sugarpaper.signin`  
- **Team ID** : `XA8994VJC6`  
- **Key ID** : `GDH9F8THP9`  
- **Private Key** : contenu de `AuthKey_GDH9F8THP9.p8`

## Apple Developer — checklist obligatoire

### 1. App ID `com.goobridge.sugarpaper`
- Capability **Sign In with Apple** activée
- **Identique** au Bundle ID Xcode et App Store Connect

### 2. Services ID `com.goobridge.sugarpaper.signin`
1. Identifiers → **Services IDs** → `com.goobridge.sugarpaper.signin`
2. **Sign In with Apple** → **Configure**
3. **Primary App ID** : `sugar paper (…com.goobridge.sugarpaper)`
4. **Domains** : `sugar-paper.com` (vérifié)
5. **Return URLs** — **les deux** lignes exactes :
   - `https://sugar-paper.firebaseapp.com/__/auth/handler` (site web / Firebase)
   - `https://sugar-paper.com/auth/apple-callback` (app Android)

Sans la 2ᵉ URL → erreur `invalid_client` / `Invalid web redirect url` sur Android.

## Apple Sign-In dans l’app

- Capability iOS : `ios/Runner/Runner.entitlements`
- Config Flutter (générée) : `lib/config/firebase_auth_config.dart`
- Regénérer après changement PHP :
  ```bash
  php scripts/sync_sugarpaper_auth_config.php
  ```
- Android : `webAuthenticationOptions` avec Services ID + `kAppleAndroidRedirectUri`
- Callback serveur : `auth/apple-callback.php` → intent `signinwithapple` / package `com.sugarpaper.app`

## Site web

- Boutons : `includes/google_auth_button.php`
- JS Firebase : `js/firebase-social-auth.js` → `OAuthProvider('apple.com')`
- Config publique : `config/firebase_config.php` (section `auth`)
- Le site s’appuie sur la config **Apple dans Firebase** (Services ID + clé `.p8`)

## Après modification

1. Vérifier les 2 Return URLs dans Apple Developer  
2. Sauver Firebase Authentication → Apple  
3. `php scripts/sync_sugarpaper_auth_config.php`  
4. **Republier** l’app (Android + iOS)  
5. Tester : web Safari, app iOS, app Android
