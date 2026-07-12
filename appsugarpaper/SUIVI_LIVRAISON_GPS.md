# Suivi GPS livreur — app mobile Sugar Paper

## Fonctionnement

L'application Flutter (`appsugarpaper`) charge l'admin Sugar Paper en WebView. Quand un livreur démarre une livraison sur `admin/livreurs/suivi.php`, le site appelle le pont natif :

- `SugarPaperNative.startDeliveryTracking(config)` → GPS natif + notification Android + socket/API
- `SugarPaperNative.stopDeliveryTracking()` → arrêt complet

Le suivi **continue en arrière-plan** (app minimisée ou autre écran) jusqu'à :

- appui sur **Terminer la livraison**, ou
- démarrage d'une **autre livraison**.

## Fichiers

| Fichier | Rôle |
|---------|------|
| `lib/services/livreur_tracking_service.dart` | Service GPS arrière-plan, HTTP, Socket.io |
| `lib/services/native_permission_service.dart` | Permissions « toujours » livreur |
| `lib/main.dart` | Handlers WebView + API JS |
| `js/livreur-native-tracking-bridge.js` | Pont côté site |
| `js/admin-livreur-suivi.js` | Démarrage/arrêt natif si app détectée |

## Permissions

### Android

- `ACCESS_FINE_LOCATION`, `ACCESS_BACKGROUND_LOCATION`
- `FOREGROUND_SERVICE`, `FOREGROUND_SERVICE_LOCATION`
- Notification persistante pendant la course (obligatoire Android 8+)

### iOS

- `NSLocationWhenInUseUsageDescription`
- `NSLocationAlwaysAndWhenInUseUsageDescription`
- `UIBackgroundModes` → `location`

## Test

1. Compiler l'app : `flutter run` ou installer l'APK/AAB.
2. Se connecter en livreur dans l'app.
3. Ouvrir une livraison avec `autostart=1`.
4. Autoriser la localisation **Toujours** quand demandé.
5. Mettre l'app en arrière-plan ou naviguer ailleurs dans l'app.
6. Vérifier `regarder=1` et le lien public partagé.

## Build production

```bash
cd appsugarpaper
flutter pub get
flutter build appbundle --release   # Google Play
flutter build ipa --release         # App Store (macOS)
```

## App Store / Play Console

Déclarer l'usage **localisation en arrière-plan** uniquement pour les comptes livreurs pendant une livraison active. Mettre à jour la politique de confidentialité si nécessaire.
