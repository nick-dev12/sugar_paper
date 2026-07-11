import 'dart:io';

import 'package:firebase_auth/firebase_auth.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:google_sign_in/google_sign_in.dart';
import 'package:sign_in_with_apple/sign_in_with_apple.dart';

import '../config/firebase_auth_config.dart';

/// Connexion Google / Apple native (hors WebView) → token Firebase pour le site PHP.
class SocialAuthService {
  static GoogleSignIn get _googleSignIn => GoogleSignIn(
    scopes: ['email', 'profile'],
    clientId: Platform.isIOS && !kFirebaseIosClientId.contains('REMPLACEZ')
        ? kFirebaseIosClientId
        : null,
    serverClientId: kFirebaseWebClientId.contains('REMPLACEZ')
        ? null
        : kFirebaseWebClientId,
  );

  static String _friendlyGoogleError(Object e) {
    final raw = e.toString();
    if (raw.contains('ApiException: 10') ||
        raw.contains('DEVELOPER_ERROR') ||
        (raw.contains('sign_in_failed') && raw.contains(': 10'))) {
      return 'Connexion Google impossible (erreur 10). '
          'Si l’app vient du Play Store, ajoutez dans Firebase Console '
          'le SHA-1 « Certificat de signature de l’application » '
          '(Play Console → Intégrité de l’app), retéléchargez google-services.json, '
          'rebuild et republiez.';
    }
    if (raw.contains('ApiException: 12500') ||
        raw.contains('SIGN_IN_CANCELLED')) {
      return 'Connexion Google annulée.';
    }
    if (Platform.isIOS &&
        (raw.contains('handleURL') ||
            raw.contains('url') && raw.contains('scheme'))) {
      return 'Connexion Google impossible sur iOS. Mettez à jour l’application '
          '(retour OAuth Google) puis réessayez.';
    }
    if (e is PlatformException) {
      return e.message ?? e.code;
    }
    if (e is FirebaseAuthException) {
      return e.message ?? e.code;
    }
    return raw;
  }

  static Future<Map<String, dynamic>> signInWithGoogle() async {
    if (kFirebaseWebClientId.contains('REMPLACEZ')) {
      return {
        'success': false,
        'error':
            'Web Client ID Firebase non configuré dans firebase_auth_config.dart',
      };
    }

    try {
      await _googleSignIn.signOut();

      final GoogleSignInAccount? account = await _googleSignIn.signIn();
      if (account == null) {
        return {'success': false, 'error': 'Connexion Google annulée.'};
      }

      final GoogleSignInAuthentication auth = await account.authentication;
      final String? idToken = auth.idToken;
      final String? accessToken = auth.accessToken;

      if (idToken == null || idToken.isEmpty) {
        return {
          'success': false,
          'error':
              'Token Google introuvable. Vérifiez le Web Client ID et les SHA-1 Firebase (dont Play App Signing).',
        };
      }

      final OAuthCredential credential = GoogleAuthProvider.credential(
        accessToken: accessToken,
        idToken: idToken,
      );

      final UserCredential userCredential = await FirebaseAuth.instance
          .signInWithCredential(credential);
      final User? user = userCredential.user;

      if (user == null) {
        return {'success': false, 'error': 'Compte Firebase introuvable.'};
      }

      final String firebaseIdToken = await user.getIdToken() ?? '';
      if (firebaseIdToken.isEmpty) {
        return {'success': false, 'error': 'Token Firebase introuvable.'};
      }

      return {
        'success': true,
        'idToken': firebaseIdToken,
        'email': user.email ?? '',
        'displayName': user.displayName ?? '',
      };
    } on FirebaseAuthException catch (e) {
      return {
        'success': false,
        'error': 'Firebase Auth : ${e.message ?? e.code}',
      };
    } catch (e) {
      return {'success': false, 'error': _friendlyGoogleError(e)};
    }
  }

  static Future<Map<String, dynamic>> signInWithApple() async {
    if (!Platform.isIOS && !Platform.isAndroid && !kIsWeb) {
      return {
        'success': false,
        'error': 'Connexion Apple non disponible sur cette plateforme.',
      };
    }

    try {
      final bool available = await SignInWithApple.isAvailable();
      if (!available) {
        return {
          'success': false,
          'error':
              'Connexion Apple indisponible sur cet appareil. Mettez à jour le système.',
        };
      }

      AuthorizationCredentialAppleID appleCredential;

      if (Platform.isAndroid) {
        if (kAppleServicesClientId.contains('REMPLACEZ') ||
            kAppleServicesClientId.isEmpty) {
          return {
            'success': false,
            'error':
                'Services ID Apple non configuré (firebase_auth_config.dart).',
          };
        }

        appleCredential = await SignInWithApple.getAppleIDCredential(
          scopes: [
            AppleIDAuthorizationScopes.email,
            AppleIDAuthorizationScopes.fullName,
          ],
          webAuthenticationOptions: WebAuthenticationOptions(
            clientId: kAppleServicesClientId,
            redirectUri: Uri.parse(kAppleAndroidRedirectUri),
          ),
        );
      } else {
        appleCredential = await SignInWithApple.getAppleIDCredential(
          scopes: [
            AppleIDAuthorizationScopes.email,
            AppleIDAuthorizationScopes.fullName,
          ],
        );
      }

      final String? identityToken = appleCredential.identityToken;
      if (identityToken == null || identityToken.isEmpty) {
        return {
          'success': false,
          'error': 'Token Apple introuvable. Réessayez.',
        };
      }

      final OAuthCredential oauthCredential = OAuthProvider('apple.com')
          .credential(
            idToken: identityToken,
            accessToken: appleCredential.authorizationCode,
          );

      final UserCredential userCredential = await FirebaseAuth.instance
          .signInWithCredential(oauthCredential);
      final User? user = userCredential.user;

      if (user == null) {
        return {'success': false, 'error': 'Compte Firebase introuvable.'};
      }

      final String firebaseIdToken = await user.getIdToken() ?? '';
      if (firebaseIdToken.isEmpty) {
        return {'success': false, 'error': 'Token Firebase introuvable.'};
      }

      return {
        'success': true,
        'idToken': firebaseIdToken,
        'email': user.email ?? appleCredential.email ?? '',
        'displayName':
            user.displayName ?? _appleDisplayName(appleCredential) ?? '',
      };
    } on SignInWithAppleAuthorizationException catch (e) {
      if (e.code == AuthorizationErrorCode.canceled) {
        return {'success': false, 'error': 'Connexion Apple annulée.'};
      }
      return {'success': false, 'error': 'Apple : ${e.message}'};
    } on FirebaseAuthException catch (e) {
      return {
        'success': false,
        'error': 'Firebase Auth : ${e.message ?? e.code}',
      };
    } catch (e) {
      final raw = e.toString();
      if (raw.contains('invalid_client')) {
        return {
          'success': false,
          'error':
              'Apple invalid_client : vérifiez le Services ID ($kAppleServicesClientId) '
              'et l’URL de retour ($kAppleAndroidRedirectUri) dans Apple Developer '
              '(identique à Firebase → Authentication → Apple).',
        };
      }
      return {'success': false, 'error': raw};
    }
  }

  static String? _appleDisplayName(AuthorizationCredentialAppleID cred) {
    final parts = <String>[
      if (cred.givenName != null && cred.givenName!.isNotEmpty) cred.givenName!,
      if (cred.familyName != null && cred.familyName!.isNotEmpty)
        cred.familyName!,
    ];
    if (parts.isEmpty) {
      return null;
    }
    return parts.join(' ');
  }
}
