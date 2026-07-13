# API SugarPaperNative — Guide d'utilisation

L'application Flutter Sugar Paper embarque le site **https://sugar-paper.com/** dans une WebView et expose une API JavaScript **`SugarPaperNative`**.

## Détection app native

```javascript
if (window.__SUGARPAPER_NATIVE_APP === true) {
  // Code spécifique à l'app
}
```

## Méthodes disponibles

### Géolocalisation

```javascript
const location = await window.SugarPaperNative.requestLocation();
```

### Caméra

```javascript
const result = await window.SugarPaperNative.requestCamera();
```

### Connexion sociale

```javascript
await window.SugarPaperNative.signInWithGoogle();
await window.SugarPaperNative.signInWithApple();
```

### Partage natif

```javascript
await window.SugarPaperNative.shareContent({ title: '...', url: '...' });
```

### Import contacts (carnet clients)

```javascript
// iOS + Android (app native uniquement)
const result = await window.SugarPaperNative.pickContacts();
// result.contacts = [{ nom, prenom, telephone, email }, ...]
```

Disponible si `window.SugarPaperNative.supportsPickContacts()` est vrai.
Sur le web navigateur : Contact Picker (Android Chrome) ou fichier `.vcf` / `.csv`.

### Suivi livreur (GPS natif)

```javascript
await window.SugarPaperNative.startDeliveryTracking(config);
await window.SugarPaperNative.stopDeliveryTracking();
```

## Événement de disponibilité

```javascript
window.addEventListener('sugarPaperNativeReady', function () {
  // API prête
});
```

Les scripts PHP/JS du site doivent être déployés sur **https://sugar-paper.com/** pour que l'app mobile en production en bénéficie.
