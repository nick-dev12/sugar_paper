# Sugar Paper — Application mobile Flutter

Application **Flutter** qui charge **https://sugar-paper.com/** dans une WebView, avec accès aux fonctionnalités natives (caméra, géolocalisation, notifications Firebase, suivi livreur, etc.).

## URL du site

Configuration unique dans `lib/config/webview_site_config.dart` :

```dart
const String kMarketplaceBaseUrl = 'https://sugar-paper.com/';
```

## Démarrage

```bash
cd appsugarpaper
flutter pub get
flutter run
```

## Build production

```bash
flutter build appbundle --release   # Android (Play Store)
flutter build ios --release         # iOS (App Store)
```

## Identifiants

| Élément | Valeur |
|---------|--------|
| Package Android / Bundle iOS | `com.sugarpaper.app` |
| Projet Firebase | `sugar-paper` |
| Site web | `https://sugar-paper.com/` |

## Documentation

- `AUTHENTIFICATION_SOCIALE.md` — Google / Apple Sign-In
- `APP_STORE_IOS.md` — publication iOS
- `SUGARPAPER_NATIVE_API.md` — API JavaScript `SugarPaperNative`
- `SUIVI_LIVRAISON_GPS.md` — suivi livreur natif

Synchroniser la config auth Flutter après modification de `config/firebase_config.php` :

```bash
php scripts/sync_sugarpaper_auth_config.php
```
