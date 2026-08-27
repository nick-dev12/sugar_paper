# Soumission App Store — Sugar Paper (iOS)

## Guideline 5.1.1 — chaînes d'objectif (`Info.plist`)

Apple exige que chaque `NS*UsageDescription` explique **comment** et **pourquoi** l'app utilise la ressource, avec un **exemple concret**.

✅ Configuré dans `ios/Runner/Info.plist` (caméra, photothèque, localisation usage + arrière-plan livreur).

Un **écran de divulgation in-app** (`ProminentDisclosureDialog` + `NativePermissionService`) précède la boîte système : écran plein page pour le suivi livraison (localisation arrière-plan), dialogues avec lien vers la politique de confidentialité pour caméra, localisation client et contacts.

Référence : [Human Interface Guidelines — Privacy](https://developer.apple.com/design/human-interface-guidelines/privacy#Requesting-permission)

## Identifiants

| Plateforme | Identifiant |
|------------|-------------|
| iOS (App Store Connect) | `com.goobridge.sugarpaper` |
| Android | `com.sugarpaper.app` |
| Firebase `GoogleService-Info.plist` | `BUNDLE_ID` = `com.goobridge.sugarpaper` |

## Firebase — aligner l’app iOS (obligatoire)

Sur votre capture Firebase, l’app iOS enregistrée est encore **`com.sugarpaper.app`**.  
L’App Store et Xcode utilisent **`com.goobridge.sugarpaper`**.

Firebase **ne permet pas** de changer le Bundle ID d’une app existante → il faut **ajouter une nouvelle app iOS**.

### Étapes (Firebase Console → sugar-paper → Paramètres du projet)

1. Section **Vos applications** → **Ajouter une application** → **Apple**
2. Bundle ID : **`com.goobridge.sugarpaper`** (exactement comme App Store Connect)
3. Surnom (optionnel) : `Sugar Paper iOS`
4. **Enregistrer l’application**
5. Télécharger le nouveau **`GoogleService-Info.plist`**
6. Remplacer le fichier :
   ```
   appsugarpaper/ios/Runner/GoogleService-Info.plist
   ```
7. Dans Firebase, sur cette **nouvelle** app iOS, renseigner :
   - **Team ID** : `XA8994VJC6` (Paramètres du projet → app iOS → « Add a Team ID »)
   - **App Store ID** : l’ID numérique App Store Connect (optionnel)
8. **Paramètres du projet → Cloud Messaging** (pas Authentication) → section **Apple** :
   - Uploader la clé APNs `.p8` (Key ID `GDH9F8THP9`, Team `XA8994VJC6`)
   - ⚠️ La clé Apple dans **Authentication → Apple** sert à la connexion, **pas** aux push
9. Mettre à jour `lib/firebase_options.dart` avec le `GOOGLE_APP_ID` du plist :
   ```dart
   appId: '1:409713248489:ios:XXXXXXXX',  // valeur GOOGLE_APP_ID du nouveau plist
   iosBundleId: 'com.goobridge.sugarpaper',
   ```

### Ancienne app `com.sugarpaper.app` dans Firebase

- Vous pouvez la **laisser** (Android utilise le même package) ou l’ignorer côté iOS.
- Ne pas la supprimer si vous l’utilisez encore pour des tests Android / anciens builds.

### Vérification rapide

| Élément | Valeur attendue |
|--------|------------------|
| App Store Connect | `com.goobridge.sugarpaper` |
| Xcode `PRODUCT_BUNDLE_IDENTIFIER` | `com.goobridge.sugarpaper` |
| Firebase app iOS | `com.goobridge.sugarpaper` (nouvelle entrée) |
| `GoogleService-Info.plist` → `BUNDLE_ID` | `com.goobridge.sugarpaper` |
| `GoogleService-Info.plist` → `GOOGLE_APP_ID` | **nouveau** (pas celui de `com.sugarpaper.app`) |

Tant que Firebase n’a que `com.sugarpaper.app`, les **notifications push** et parfois **Firebase Auth** peuvent échouer sur l’app publiée.

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

1. Cible **Runner** → **Signing & Capabilities** : équipe + bundle `com.goobridge.sugarpaper`
2. Ajouter **Push Notifications** et **Background Modes** → cocher **Location updates** et **Remote notifications**
3. Vérifier **GoogleService-Info.plist** (Target Membership Runner)
4. **Product → Archive** → App Store Connect

### Firebase (notifications)

- `firebase_core` / `firebase_messaging` dans `lib/main.dart`
- Permission : `FCMService.requestNotificationPermission()` (dialogue système iOS)
- `Info.plist` : `UIBackgroundModes` → `remote-notification`, `location`
- `Runner.entitlements` : `aps-environment` → **`production`** avant archive store
- Console Firebase : clé APNs (.p8) pour `com.goobridge.sugarpaper`

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
