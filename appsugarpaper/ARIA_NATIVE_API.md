# API ColobanesNative — Guide d'utilisation

L'application Flutter COLObanes (`colobane/`) embarque le site **https://colobanes.com/** dans une WebView et expose une API JavaScript **`ColobanesNative`** (alias rétrocompatible `AriaNative`).

Le site PHP utilise **`GeoNativeBridge`** (`js/geo-native-bridge.js`) pour prioriser le GPS natif Flutter, avec repli sur `navigator.geolocation` dans le navigateur.

## Localisation GPS

### Côté site web (PHP/JS)

```javascript
// Capture position (native Flutter si app, sinon navigateur)
const pos = await window.GeoNativeBridge.getCurrentPosition({
  enableHighAccuracy: true,
  maximumAge: 60000,
  nativeWaitMs: 6000  // attente injection ColobanesNative dans l'app
});

// Géocodage inverse (format adresse serveur)
const adresse = await window.GeoNativeBridge.reverseGeocode(
  pos.coords.latitude,
  pos.coords.longitude
);
// → POST vers set-location.php, commande.php, etc.
```

API serveur : `GET /api/geo-reverse.php?lat=…&lng=…`

### Côté app Flutter (direct)

```javascript
const location = await window.ColobanesNative.requestLocation();
if (location.success) {
  console.log(location.latitude, location.longitude, location.accuracy);
}
```

Réponse succès : `{ success: true, latitude, longitude, accuracy, altitude, speed, timestamp }`  
Erreurs en français : services désactivés, permission refusée, etc.

### Permissions Flutter (`lib/main.dart`)

- `geolocationEnabled: true` + `onGeolocationPermissionsShowPrompt`
- `onPermissionRequest` accorde caméra **et** géolocalisation
- Handler `requestLocation` → package `geolocator`
- Flag `window.__COLOBANES_NATIVE_APP = true` injecté au démarrage

### Android / iOS

- Android : `ACCESS_FINE_LOCATION`, `ACCESS_COARSE_LOCATION` dans `AndroidManifest.xml`
- iOS : `NSLocationWhenInUseUsageDescription` dans `Info.plist`

## Caméra

```javascript
const result = await window.ColobanesNative.requestCamera();
if (result.success) {
  // result.image = data:image/jpeg;base64,...
}
```

## Stockage local

```javascript
await window.ColobanesNative.saveData({ cle: 'valeur' });
const valeur = await window.ColobanesNative.getData('cle');
```

## Connexion sociale native

```javascript
await window.ColobanesNative.signInWithGoogle();
await window.ColobanesNative.signInWithApple();
```

## Déploiement

Les scripts PHP/JS de localisation doivent être déployés sur **colobanes.com** pour que l'app mobile en production en bénéficie. L'app charge l'URL de production (`kMarketplaceBaseUrl`).
