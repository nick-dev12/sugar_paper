// Configuration Firebase — projet sugar-paper (aligné config/firebase_config.php).
// Si android/app/google-services.json est présent, Firebase.initializeApp()
// sans options utilise aussi les ressources Gradle générées.

import 'package:firebase_core/firebase_core.dart' show FirebaseOptions;
import 'package:flutter/foundation.dart'
    show defaultTargetPlatform, kIsWeb, TargetPlatform;

class DefaultFirebaseOptions {
  static const String projectId = 'sugar-paper';
  static const String messagingSenderId = '409713248489';

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
    apiKey: 'AIzaSyAOGTcYf7i-Jj6jj5KuTOJboFVagkbdBW4',
    appId: '1:409713248489:web:6bff9f5584e52c05a04878',
    messagingSenderId: messagingSenderId,
    projectId: projectId,
    authDomain: 'sugar-paper.firebaseapp.com',
    storageBucket: 'sugar-paper.firebasestorage.app',
    measurementId: 'G-M2RMN55G2E',
  );

  /// Secours si google-services.json absent (aligné sur com.sugarpaper.app).
  static const FirebaseOptions android = FirebaseOptions(
    apiKey: 'AIzaSyD2icrm7NOHYZWg7_ypmc_ue9P6h0O0Kjs',
    appId: '1:409713248489:android:ed55bf5bbfbd0778a04878',
    messagingSenderId: messagingSenderId,
    projectId: projectId,
    authDomain: 'sugar-paper.firebaseapp.com',
    storageBucket: 'sugar-paper.firebasestorage.app',
  );

  /// App iOS sugar-paper — com.sugarpaper.app
  static const FirebaseOptions ios = FirebaseOptions(
    apiKey: 'AIzaSyDUZK9mKCkwfMnjUofnlCMlPJLpYnps_2w',
    appId: '1:409713248489:ios:9cdae54c43721760a04878',
    messagingSenderId: messagingSenderId,
    projectId: projectId,
    authDomain: 'sugar-paper.firebaseapp.com',
    storageBucket: 'sugar-paper.firebasestorage.app',
    iosBundleId: 'com.sugarpaper.app',
  );
}
