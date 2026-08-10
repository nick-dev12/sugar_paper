# Android App Links — sugar-paper.com

Erreur Play Console : **Échec des vérifications du domaine** pour `sugar-paper.com` et `www.sugar-paper.com`.

## Cause

Google doit récupérer `https://sugar-paper.com/.well-known/assetlinks.json` avec le type **`application/json`**, contenant l’empreinte **SHA-256** du certificat de signature Play Store.

## Configuration (une fois)

1. **Google Play Console** → application **sugar paper** → **Release** → **Intégrité de l'application** → **Signature d'application**
2. Copier le **SHA-256** du certificat **« Clé de signature de l'application »** (Play App Signing)
3. Sur le VPS :
   ```bash
   cp config/assetlinks.example.php config/assetlinks.php
   nano config/assetlinks.php
   ```
4. Coller l’empreinte dans `sha256_cert_fingerprints` (format `AA:BB:CC:…`)
5. Déployer : `bash scripts/deploy.sh`
6. Vérifier :
   ```bash
   curl -sI https://sugar-paper.com/.well-known/assetlinks.json | grep -i content-type
   curl -s https://sugar-paper.com/.well-known/assetlinks.json
   ```
7. Play Console → **Liens profonds** → **Recontrôler la vérification**

## URLs à valider

- https://sugar-paper.com/.well-known/assetlinks.json
- https://www.sugar-paper.com/.well-known/assetlinks.json

## Package Android

`com.sugarpaper.app` — activité `MainActivity` (`android:autoVerify="true"` dans `AndroidManifest.xml`).
