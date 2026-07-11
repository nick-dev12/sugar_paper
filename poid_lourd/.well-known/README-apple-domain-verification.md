# Vérification du domaine Apple (Sign In with Apple — app Android)

Erreur Apple **`invalid_request` / `Invalid web redirect url`** sur l’app Android :
le domaine `colobanes.com` doit être **vérifié** chez Apple et l’URL de retour enregistrée.

## Étapes (Apple Developer)

1. [developer.apple.com](https://developer.apple.com) → **Certificates, Identifiers & Profiles** → **Identifiers**
2. Ouvrir le **Services ID** : `com.colobanes.web`
3. **Sign In with Apple** → **Configure**
4. **Domains and Subdomains** : `colobanes.com`
5. Cliquer **Verify** (ou **Download**) → Apple fournit un fichier
6. Enregistrer ce fichier **tel quel** (sans modification) sous :
   ```
   .well-known/apple-developer-domain-association.txt
   ```
7. Déployer sur le VPS (https://colobanes.com)
8. Vérifier que l’URL répond **200** :
   ```
   https://colobanes.com/.well-known/apple-developer-domain-association.txt
   ```
9. Dans Apple Developer, cliquer **Verify** jusqu’à validation du domaine
10. **Return URLs** — les **deux** lignes exactes :
    - `https://gestion-scolaire-6945a.firebaseapp.com/__/auth/handler` (site web)
    - `https://colobanes.com/auth/apple-callback` (app Android)

## Vérification rapide

- Callback Android : https://colobanes.com/auth/apple-callback (doit afficher « Retour connexion Apple… »)
- Fichier domaine : https://colobanes.com/.well-known/apple-developer-domain-association.txt (doit **pas** être 404)

Ne commitez pas le fichier téléchargé depuis Apple s’il contient des identifiants sensibles — déployez-le uniquement sur le serveur.
