# Soumission App Store — COLObanes (iOS)

## Rejet Guideline 5.1.1(ii) — chaînes d'objectif (Purpose Strings)

Apple exige que chaque `NS*UsageDescription` dans `Info.plist` explique **comment** et **pourquoi** l'app utilise la ressource, avec un **exemple concret**. Les formulations du type « l'application a besoin d'accéder à… » sont refusées.

Les textes ont été mis à jour dans `ios/Runner/Info.plist`. Un **dialogue in-app** (`lib/services/native_permission_service.dart`) s'affiche avant la boîte système pour la caméra et la localisation, avec les mêmes finalités que les chaînes Info.plist.

Référence : [Human Interface Guidelines — Privacy](https://developer.apple.com/design/human-interface-guidelines/privacy#Requesting-permission)

## Identifiants

| Plateforme | Identifiant |
|------------|-------------|
| iOS (App Store Connect) | `com.colobanes.app` |
| Android | `com.colobanes.app` |
| Firebase `GoogleService-Info.plist` | `BUNDLE_ID` = `com.colobanes.app` |

> Si dans la console Firebase vous avez créé une app iOS sous `com.ariaedu.app`, **ajoutez une seconde app iOS** avec le bundle `com.colobanes.app`, téléchargez le nouveau `GoogleService-Info.plist` et placez-le dans `ios/Runner/`.

## Build sur Mac (Xcode)

### Prérequis

- macOS avec Xcode 15+
- Flutter SDK stable (`flutter doctor`)
- Compte Apple Developer + certificats de distribution
- Fichier `ios/Runner/GoogleService-Info.plist` présent (emplacement officiel Firebase / Flutter)

### Étapes

```bash
cd colobane
flutter pub get
cd ios
pod install   # génère Podfile si absent après premier flutter build ios
cd ..
flutter build ipa --release
```

Ou ouvrir **`ios/Runner.xcworkspace`** dans Xcode :

1. Sélectionner la cible **Runner** → **Signing & Capabilities** : équipe + bundle `com.colobanes.app`
2. Vérifier que **GoogleService-Info.plist** apparaît dans le groupe Runner (coche « Target Membership » Runner)
3. **Product → Archive** → distribuer vers App Store Connect

### Firebase (notifications)

- `firebase_core` et `firebase_messaging` sont initialisés dans `lib/main.dart`
- Demande de permission : `FCMService.requestNotificationPermission()` (dialogue iOS / Android 13+)
- `Info.plist` : `UIBackgroundModes` → `remote-notification`
- `Runner.entitlements` : `aps-environment` (passer à **`production`** avant l’archive App Store si besoin)
- Dans Xcode : cible **Runner** → **Signing & Capabilities** → ajouter **Push Notifications**
- Console Firebase : clé **APNs** (fichier .p8) uploadée pour l’app iOS `com.colobanes.app`
- Pas d'Analytics IDFA : pas de module `FirebaseAnalytics` avec suivi publicitaire
- `FirebaseAppDelegateProxyEnabled` = `false` dans `Info.plist` (gestion via plugins Flutter)

> Sur iOS, il n’existe pas de clé `NS*UsageDescription` pour les notifications push : le système affiche sa propre boîte de dialogue lors de `requestPermission()`.

### App Store Connect — confidentialité

Dans **App Privacy**, déclarer notamment :

- Données de localisation (coarse/precise) — livraison, inscription, boutiques proches, emplacement boutique vendeur ; **sur action utilisateur** (« Localiser ») ; **When In Use** uniquement, pas de suivi arrière-plan
- Photos — contenu fourni par l'utilisateur
- Identifiants (jeton push FCM/APNs)
- Données d'utilisation / diagnostics si collectés par Firebase (selon configuration console)

URL politique de confidentialité : `https://colobanes.com/politique-confidentialite.php`
URL CGU : `https://colobanes.com/conditions-utilisation.php`

## Après correction

1. Incrémenter le **build number** si besoin (version actuelle : `1.2.0+2` dans `pubspec.yaml`)
2. Soumettre une nouvelle build
3. Dans la résolution du rejet, indiquer que les chaînes caméra / photos / localisation ont été réécrites avec exemples et finalités détaillées, qu'un dialogue explicatif in-app précède la demande système, que le micro et la localisation arrière-plan ne sont pas utilisés, et que la politique de confidentialité (section 9) et les CGU (section 4 bis) ont été mises à jour
