// Copiez ce fichier en firebase_options.dart après :
//   dart pub global activate flutterfire_cli
//   flutterfire configure
//
// Ou recopiez les valeurs depuis Firebase Console → Paramètres du projet → Vos applications.

import 'package:firebase_core/firebase_core.dart' show FirebaseOptions;
import 'package:flutter/foundation.dart'
    show defaultTargetPlatform, kIsWeb, TargetPlatform;

class DefaultFirebaseOptions {
  static const String projectId = 'VOTRE_PROJECT_ID';
  static const String messagingSenderId = 'VOTRE_SENDER_ID';

  static FirebaseOptions get currentPlatform {
    if (kIsWeb) {
      return web;
    }
    switch (defaultTargetPlatform) {
      case TargetPlatform.android:
        return android;
      case TargetPlatform.iOS:
        return ios;
      default:
        return web;
    }
  }

  static const FirebaseOptions web = FirebaseOptions(
    apiKey: 'VOTRE_API_KEY_WEB',
    appId: 'VOTRE_APP_ID_WEB',
    messagingSenderId: messagingSenderId,
    projectId: projectId,
    authDomain: 'VOTRE_PROJECT_ID.firebaseapp.com',
    storageBucket: 'VOTRE_PROJECT_ID.firebasestorage.app',
  );

  static const FirebaseOptions android = FirebaseOptions(
    apiKey: 'VOTRE_API_KEY_ANDROID',
    appId: 'VOTRE_APP_ID_ANDROID',
    messagingSenderId: messagingSenderId,
    projectId: projectId,
    storageBucket: 'VOTRE_PROJECT_ID.firebasestorage.app',
  );

  static const FirebaseOptions ios = FirebaseOptions(
    apiKey: 'VOTRE_API_KEY_IOS',
    appId: 'VOTRE_APP_ID_IOS',
    messagingSenderId: messagingSenderId,
    projectId: projectId,
    storageBucket: 'VOTRE_PROJECT_ID.firebasestorage.app',
    iosBundleId: 'com.colobanes.app',
    iosClientId: 'VOTRE_IOS_CLIENT_ID.apps.googleusercontent.com',
  );
}
