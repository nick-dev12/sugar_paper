# Justifications des permissions — Sugar Paper

Application e-commerce (WebView + pont natif). Bundle / package : **`com.sugarpaper.app`**.

Usage réel : `lib/main.dart`, `lib/services/native_permission_service.dart`, `ios/Runner/Info.plist`, `android/app/src/main/AndroidManifest.xml`.

Pages légales (stores) :
- Politique : https://sugar-paper.com/politique-confidentialite.php (alias : https://sugar-paper.com/privacy-policy.php)
- CGU : https://sugar-paper.com/conditions-utilisation.php (section 4 bis — autorisations)

---

## Apple App Store — `Info.plist` (Guideline 5.1.1)

Chaque clé `NS*UsageDescription` décrit **comment**, **pourquoi** et un **exemple concret**. Fichier : `ios/Runner/Info.plist`.

| Clé | Finalité |
|-----|----------|
| `NSCameraUsageDescription` | Photo profil / commande personnalisée (« Prendre une photo », ex. gâteau) |
| `NSPhotoLibraryUsageDescription` | Import galerie si l'utilisateur choisit « Importer depuis la galerie » |
| `NSPhotoLibraryAddUsageDescription` | Enregistrement d'une image téléchargée depuis la plateforme (action explicite) |
| `NSLocationWhenInUseUsageDescription` | Adresse de livraison (« Localiser ») ; position livreur pendant une course |
| `NSLocationAlwaysAndWhenInUseUsageDescription` | Suivi livreur en arrière-plan pendant une livraison active uniquement |
| `NSLocationAlwaysUsageDescription` | Même finalité (compatibilité iOS) |
| `NSContactsUsageDescription` | Import clients depuis le répertoire (espace commercial) — sélection explicite |

**Arrière-plan iOS** : `UIBackgroundModes` → `location`, `remote-notification`.

**Dialogue in-app** (avant la boîte système) : `NativePermissionService` + `ProminentDisclosureDialog` — écran plein page pour la localisation arrière-plan (livreurs), dialogues avec lien politique de confidentialité pour caméra, localisation client, contacts.

**Notifications push** : pas de clé `NS*UsageDescription` (dialogue système iOS via `FCMService.requestNotificationPermission()`).

**Non utilisé** : microphone (absent d'Info.plist et refusé dans la WebView).

### App Store Connect — App Privacy (à déclarer)

- **Localisation précise** : Oui — adresse commande (action utilisateur) ; suivi livraison (livreurs, course active, arrêt en fin de course)
- **Photos** : Oui — contenu fourni par l'utilisateur
- **Contacts** : Oui — import manuel de clients (espace commercial), sélection utilisateur
- **Identifiants** : jeton push FCM/APNs
- **Données d'utilisation** : selon Firebase Analytics (si activé en console)

Indiquer que la localisation **arrière-plan** concerne **uniquement les livreurs** pendant une livraison en cours.

---

## Google Play Console

### Textes pour le formulaire « Sécurité des données » / permissions sensibles

**CAMERA**
```
Sugar Paper utilise la caméra lorsque l'utilisateur appuie sur « Prendre une photo » pour son profil ou une commande personnalisée. Exemple : photographier un gâteau pour une commande sur mesure.
```

**ACCESS_FINE_LOCATION / ACCESS_COARSE_LOCATION**
```
Sugar Paper utilise la position lorsque l'utilisateur appuie sur « Localiser » pour confirmer une adresse de livraison ou s'inscrire. Les livreurs peuvent partager leur position en direct pendant une livraison active jusqu'à la fin de la course.
```

**ACCESS_BACKGROUND_LOCATION** (Android — non utilisé)
```
Sugar Paper n'utilise pas ACCESS_BACKGROUND_LOCATION sur Android. Le suivi livreur repose sur un service de premier plan (notification « Livraison en cours ») et la permission « Pendant l'utilisation de l'app ». Déclarez uniquement la localisation approximative/précise « pendant l'utilisation » dans la Play Console.
```

**POST_NOTIFICATIONS (Android 13+)**
```
Alertes de statut de commande et messages liés au compte (ex. : commande expédiée, livraison). Refusable dans les paramètres système.
```

**READ/WRITE_EXTERNAL_STORAGE** (Android ≤ 12, si applicable)
```
Accès aux images uniquement lorsque l'utilisateur importe une photo depuis la galerie ou enregistre une image depuis la plateforme.
```

**READ_CONTACTS**
```
Sugar Paper accède au répertoire uniquement lorsque l'utilisateur (espace commercial) importe des clients. L'utilisateur sélectionne explicitement les contacts ; seuls nom, téléphone et e-mail sont enregistrés dans le carnet clients. Aucune lecture automatique en arrière-plan.
```

Chaînes Android (référence Play + cohérence) : `android/app/src/main/res/values/strings.xml`  
Dialogues in-app : `lib/services/native_permission_service.dart` (source principale des textes affichés).

### Déclarations Play Console obligatoires

1. **Localisation en arrière-plan** : formulaire dédié + vidéo de démonstration si demandée (livreur démarre course → notification persistante → client voit le suivi).
2. **Foreground service (location)** : service `GeolocatorLocationService` pendant livraison active.
3. **Photos et vidéos** : accès caméra + galerie (sur action utilisateur).

---

## Matrice technique

| Permission | Android manifest | iOS Info.plist | Dialogue in-app | Contexte |
|------------|------------------|----------------|----------------|----------|
| Caméra | ✅ | ✅ | ✅ | Profil / commande |
| Galerie / photos | ✅ storage* | ✅ | ⚠️ système / WebView | Import utilisateur |
| Contacts | ✅ READ_CONTACTS | ✅ NSContactsUsageDescription | ✅ | Import clients admin |
| Localisation (usage) | ✅ | ✅ | ✅ | Adresse, carte |
| Localisation arrière-plan | ✅ | ✅ + UIBackgroundModes | ✅ livreur | Course active |
| Notifications | ✅ POST_NOTIFICATIONS | UIBackgroundModes | ⚠️ au démarrage FCM | Commandes |
| Micro | ❌ | ❌ | — | Non utilisé |

\* Envisager `READ_MEDIA_IMAGES` pour Android 13+ si la galerie native est sollicitée hors WebView.

---

## Checklist avant soumission

- [ ] URLs légales prod : `https://sugar-paper.com/politique-confidentialite.php` (ou alias `https://sugar-paper.com/privacy-policy.php`) et `conditions-utilisation.php`
- [ ] Play Console > Contenu de l'appli > Politique de confidentialité : URL HTTPS active (tester en navigation privée)
- [ ] App Store Connect : déclarer localisation arrière-plan (livreurs)
- [ ] Play Console : formulaire localisation arrière-plan + foreground service location
- [ ] Xcode : capability **Background Modes** → Location updates + Push Notifications
- [ ] `Runner.entitlements` : `aps-environment` = `production` pour l'archive App Store
- [ ] Vidéo test livreur (Apple/Google peuvent la demander pour GPS arrière-plan)
