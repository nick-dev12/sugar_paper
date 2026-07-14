# Vérification du domaine Apple (Sign In with Apple — app Android)

Erreur Apple **`invalid_request` / `Invalid web redirect url`** sur l'app Android :
le domaine `sugar-paper.com` doit être **vérifié** chez Apple et l'URL de retour enregistrée.

## Étapes (Apple Developer)

1. [developer.apple.com](https://developer.apple.com) → **Certificates, Identifiers & Profiles** → **Identifiers**
2. Ouvrir le **Services ID** : `com.goobridge.sugarpaper.signin`
3. **Sign In with Apple** → **Configure**
4. **Primary App ID** : `com.goobridge.sugarpaper`
5. **Domains and Subdomains** : `sugar-paper.com`
6. Cliquer **Verify** (ou **Download**) → Apple fournit un fichier
7. Enregistrer ce fichier **tel quel** (sans modification) sous :
   ```
   .well-known/apple-developer-domain-association.txt
   ```
8. Déployer sur le VPS (https://sugar-paper.com)
9. Vérifier que l'URL répond **200** :
   ```
   https://sugar-paper.com/.well-known/apple-developer-domain-association.txt
   ```
10. Dans Apple Developer, cliquer **Verify** jusqu'à validation du domaine
11. **Return URLs** — les **deux** lignes exactes :
    - `https://sugar-paper.firebaseapp.com/__/auth/handler` (site web)
    - `https://sugar-paper.com/auth/apple-callback` (app Android)

## Vérification rapide

- Callback Android : https://sugar-paper.com/auth/apple-callback (doit afficher « Retour connexion Apple… »)
- Fichier domaine : https://sugar-paper.com/.well-known/apple-developer-domain-association.txt (doit **pas** être 404)

Ne commitez pas le fichier téléchargé depuis Apple s'il contient des identifiants sensibles — déployez-le uniquement sur le serveur.
