# Authentification Google / Apple — app COLObanes (Flutter)

## Google : debug OK, Play Store KO (erreur 10)

L’erreur **`ApiException: 10` / `DEVELOPER_ERROR`** en production vient presque toujours du **certificat de signature Google Play**, pas de votre keystore local.

| Mode | Certificat utilisé | SHA déjà dans Firebase ? |
|------|-------------------|--------------------------|
| `flutter run` (debug) | `~/.android/debug.keystore` | Oui (`F6:64:49:32:…`) |
| APK/AAB signé localement (release) | `aria-release-key.jks` | Oui (`40:D6:A0:F3:…`) |
| **Install depuis Google Play** | **Play App Signing** (clé Google) | **Non — à ajouter** |

### Correction (obligatoire pour le Play Store)

1. **Google Play Console** → votre app → **Intégrité de l’app** (App integrity)
2. Onglet **Signature de l’application** → section **Certificat de signature de l’application** (App signing key certificate)
3. Copiez le **SHA-1** et le **SHA-256** (pas la clé « upload », mais bien **App signing**)
4. **Firebase Console** → projet `gestion-scolaire-6945a` → app Android **`com.colobanes.app`**
5. **Ajouter une empreinte** → collez le SHA-1 Play App Signing (et idéalement SHA-256)
6. **Retéléchargez `google-services.json`** → remplacez :
   ```
   colobane/android/app/google-services.json
   ```
7. Vérifiez qu’un **3ᵉ client OAuth Android** apparaît dans le JSON (`certificate_hash` = SHA-1 Play sans les `:`)
8. Rebuild et publiez une **nouvelle version** sur le Play Store :
   ```bash
   cd colobane
   flutter clean
   flutter pub get
   flutter build appbundle --release
   ```

### Empreintes locales (keystore aria / debug)

```bash
cd colobane/android
./gradlew signingReport
```

- Debug SHA-1 : `F6:64:49:32:B4:05:0F:54:E3:0F:CB:B4:E6:60:0C:41:80:10:A3:D8`
- Release SHA-1 : `40:D6:A0:F3:E1:00:7E:70:BE:8C:04:F9:C1:2B:F8:5F:14:6A:DC:DA`

### Identifiants OAuth (source unique : `config/firebase_config.php` → section `auth`)

| Clé | Valeur |
|-----|--------|
| Web Client ID | `983006440407-goai5vsnrtaur5fpk8vq8m6gdnv1eh90.apps.googleusercontent.com` |
| iOS Client ID | `983006440407-0lj1ljivl26pt6emhu4tlvgl2p047rgp.apps.googleusercontent.com` |

Synchroniser l’app Flutter après toute modification :

```bash
php scripts/sync_colobane_auth_config.php
```

### App Links (assetlinks.json)

Ajoutez aussi le **SHA-256 Play App Signing** dans `.well-known/assetlinks.json` sur `https://colobanes.com` (en plus debug + release), puis redéployez le site.

---

## Apple Sign-In

### iOS — natif (app iPhone / iPad)

- Capability **Sign in with Apple** : `ios/Runner/Runner.entitlements`
- Bundle ID : `com.colobanes.app`
- **Firebase Console** → Authentication → Apple : clé `.p8`, Key ID, Team ID (`XA8994VJC6`)
- Si Google **et** Apple échouent dans l’app iOS : republiez une build avec `AppDelegate.swift` (retour URL Google) et `clientId` iOS dans `social_auth_service.dart`

### Android — flux web obligatoire

Sur Android, Apple exige `webAuthenticationOptions` (Services ID + URL de retour HTTPS).

**Configuration code** (générée depuis `config/firebase_config.php`) :

- `kAppleServicesClientId` : `com.colobanes.web`
- `kAppleAndroidRedirectUri` : `https://colobanes.com/auth/apple-callback` (**app Android uniquement**)
- `kAppleWebOAuthRedirectUri` : `https://gestion-scolaire-6945a.firebaseapp.com/__/auth/handler` (**site web uniquement**)

**Apple Developer** (Services ID `com.colobanes.web`) — **2 Return URLs** obligatoires :

| Usage | Return URL |
|-------|------------|
| Site web (Firebase JS) | `https://gestion-scolaire-6945a.firebaseapp.com/__/auth/handler` |
| App Android | `https://colobanes.com/auth/apple-callback` |

1. Identifiers → **Services IDs** → `com.colobanes.web`
2. **Domains** : `colobanes.com`
3. Ajoutez **les deux** Return URLs ci-dessus
4. Firebase → Authentication → Apple : Services ID + Team ID `XA8994VJC6` + clé `.p8`

> **Ne pas** utiliser l’URL Firebase handler sur Android : erreur « absence d’état initial » (SessionStorage du navigateur Custom Tab).

Erreur **`invalid_client`** = Return URL absente dans Apple Developer.

### Android — bloqué sur « Retour connexion Apple… »

Apple envoie un **POST** (`form_post`) vers `auth/apple-callback.php`. Cette page doit **rediriger vers l’app** via :

`intent://callback?code=…#Intent;package=com.colobanes.app;scheme=signinwithapple;end`

Prérequis côté app :

- `auth/apple-callback.php` déployé sur le VPS (redirection intent)
- Activité `SignInWithAppleCallback` dans `AndroidManifest.xml`
- **Nouvelle build** Android obligatoire après ajout de l’activité

### Android — `invalid_request` / `Invalid web redirect url`

L’app envoie `https://colobanes.com/auth/apple-callback`. Apple refuse si :

1. Cette URL n’est **pas** dans les Return URLs du Services ID `com.colobanes.web`
2. Le domaine `colobanes.com` n’est **pas vérifié** (fichier manquant sur le serveur)

**Vérification domaine** (obligatoire) :

1. Apple Developer → Services ID → domaine `colobanes.com` → **Verify** → télécharger le fichier
2. Déployer sur le VPS : `.well-known/apple-developer-domain-association.txt`
3. Tester : `https://colobanes.com/.well-known/apple-developer-domain-association.txt` → doit répondre **200** (pas 404)
4. Ajouter Return URL : `https://colobanes.com/auth/apple-callback`

Voir aussi : `.well-known/README-apple-domain-verification.md`

---

## Fonctionnement dans l’app

- La WebView appelle le code **natif Flutter** (`signInWithGoogle` / `signInWithApple`)
- Le token Firebase est renvoyé au site PHP (`/auth-firebase-callback.php`)

Après toute modification Firebase ou Play Console, **republiez** une nouvelle version sur le Play Store.
