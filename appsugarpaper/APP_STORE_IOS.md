# Soumission App Store — Sugar Paper (iOS)

## Guideline 5.1.1 — chaînes d'objectif (`Info.plist`)

Apple exige que chaque `NS*UsageDescription` explique **comment** et **pourquoi** l'app utilise la ressource, avec un **exemple concret**.

✅ Configuré dans `ios/Runner/Info.plist` (caméra, photothèque, localisation usage + arrière-plan livreur).

Un **dialogue in-app** (`lib/services/native_permission_service.dart`) précède la boîte système pour la caméra, la localisation client et le suivi livraison livreur.

Référence : [Human Interface Guidelines — Privacy](https://developer.apple.com/design/human-interface-guidelines/privacy#Requesting-permission)

## Identifiants

| Plateforme | Identifiant |
|------------|-------------|
| iOS (App Store Connect) | `com.sugarpaper.app` |
| Android | `com.sugarpaper.app` |
| Firebase `GoogleService-Info.plist` | `BUNDLE_ID` = `com.sugarpaper.app` |

## Build sur Mac (Xcode)

### Prérequis

- macOS avec Xcode 15+
- Flutter SDK stable (`flutter doctor`)
- Compte Apple Developer + certificats de distribution
- `ios/Runner/GoogleService-Info.plist` (projet Firebase **sugar-paper**)

### Étapes

```bash
cd appsugarpaper
flutter pub get
cd ios
pod install
cd ..
flutter build ipa --release
```

Ou ouvrir **`ios/Runner.xcworkspace`** dans Xcode :

1. Cible **Runner** → **Signing & Capabilities** : équipe + bundle `com.sugarpaper.app`
2. Ajouter **Push Notifications** et **Background Modes** → cocher **Location updates** et **Remote notifications**
3. Vérifier **GoogleService-Info.plist** (Target Membership Runner)
4. **Product → Archive** → App Store Connect

### Firebase (notifications)

- `firebase_core` / `firebase_messaging` dans `lib/main.dart`
- Permission : `FCMService.requestNotificationPermission()` (dialogue système iOS)
- `Info.plist` : `UIBackgroundModes` → `remote-notification`, `location`
- `Runner.entitlements` : `aps-environment` → **`production`** avant archive store
- Console Firebase : clé APNs (.p8) pour `com.sugarpaper.app`

## App Store Connect — confidentialité et review

### App Privacy

- **Localisation précise** : adresse livraison (action « Localiser ») ; suivi livreur en course active (**arrière-plan limité aux livreurs**)
- **Photos** : contenu utilisateur (profil, commande)
- **Identifiants** : jeton push
- Ne pas déclarer le micro (non utilisé)

### Localisation arrière-plan (livreurs)

Apple peut demander une **vidéo** montrant :
1. Livreur connecté → démarrage livraison sur `admin/livreurs/suivi.php`
2. Dialogue explicatif in-app puis autorisation « Toujours »
3. App en arrière-plan → client voit la position sur le suivi

Texte de résolution de rejet type :
> Les chaînes Info.plist décrivent l'usage caméra, photos et localisation avec exemples. Un dialogue in-app précède chaque demande. La localisation arrière-plan est réservée aux livreurs pendant une livraison active et s'arrête en fin de course. Politique de confidentialité et CGU mises à jour (sections app mobile et suivi GPS).

### URLs légales

- Politique : `https://sugar-paper.com/politique-confidentialite.php`
- CGU : `https://sugar-paper.com/conditions-utilisation.php`

Voir aussi : `JUSTIFICATIONS_PERMISSIONS.md`
