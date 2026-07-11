# Justifications des permissions — COLObanes

Application marketplace (WebView + pont natif). Usage réel documenté dans `lib/main.dart`, `lib/services/native_permission_service.dart` et `ios/Runner/Info.plist`.

## Apple App Store (chaînes Info.plist)

Voir `ios/Runner/Info.plist` — chaque clé inclut finalité + exemple concret (exigence 5.1.1).

**Localisation** : uniquement `NSLocationWhenInUseUsageDescription` (pas d'accès « Toujours » / arrière-plan).

Avant la boîte système iOS, l'app affiche un **dialogue explicatif** (`NativePermissionService`) reprenant les mêmes finalités.

## Google Play Console

### CAMERA

```
COLObanes permet de prendre une photo lorsque l'utilisateur appuie sur « Prendre une photo » pour son profil, une fiche produit vendeur ou une pièce jointe. Exemple : photographier un article mis en vente sur la marketplace.
```

### ACCESS_FINE_LOCATION / ACCESS_COARSE_LOCATION

```
COLObanes utilise la localisation uniquement lorsque l'utilisateur appuie sur « Localiser » ou « Mettre à jour ma position » pour : confirmer une adresse de livraison lors d'une commande, enregistrer son adresse à l'inscription, afficher les boutiques à proximité, ou localiser sa boutique vendeur. Exemple : positionner le point de livraison à Dakar sur la carte. Pas de suivi en arrière-plan ; accès « pendant l'utilisation » uniquement.
```

**Formulaire Sécurité des données (Play Console)** — localisation approximative et précise :
- Collectées : Oui (sur action utilisateur)
- Partagées : Non (sauf exécution livraison / affichage boutique sur la plateforme)
- Obligatoire : Non
- Finalité : Fonctionnalité de l'app (livraison, carte, boutiques proches)

Chaînes Android : `android/app/src/main/res/values/strings.xml`  
Dialogue in-app avant autorisation système : `NativePermissionService.requestLocationWithRationale()`

### POST_NOTIFICATIONS (Android 13+)

```
Notifications de statut de commande et messages liés au compte (ex. : commande expédiée). L'utilisateur peut refuser dans les paramètres système.
```

### READ/WRITE_EXTERNAL_STORAGE (selon version Android)

```
Accès aux images uniquement lorsque l'utilisateur choisit d'importer une photo depuis la galerie ou d'enregistrer une image téléchargée depuis la plateforme.
```

## Permissions non utilisées

- **Microphone** : non demandé (retiré des autorisations WebView et absent d'Info.plist iOS).
- **Localisation en arrière-plan** : non demandée (`ACCESS_BACKGROUND_LOCATION` absent du manifeste).

## Pages légales (URLs store)

- Politique de confidentialité : https://colobanes.com/politique-confidentialite.php (section 9 — app mobile et GPS)
- CGU : https://colobanes.com/conditions-utilisation.php (section 4 bis — autorisations)
