import 'dart:io' show Platform;

import 'package:flutter/foundation.dart' show kIsWeb;

/// PROVISOIRE — tests livreur / suivi GPS sur le VPS samapiece.com (Android).
/// Repasser à `false` avant publication Play Store production.
const bool kAndroidUseSamapieceForTesting = true;

/// Désactive le blocage « mise à jour obligatoire » pendant les tests samapiece
/// (l'API `/api/app_version.php` peut être absente sur le VPS de test).
bool get kSkipAppVersionCheckForTesting {
  return !kIsWeb && Platform.isAndroid && kAndroidUseSamapieceForTesting;
}

const String kSiteUrlProduction = 'https://sugar-paper.com/';
const String kSiteUrlSamapieceTest = 'https://samapiece.com/';

/// URL chargée dans la WebView au démarrage.
String get kMarketplaceBaseUrl {
  if (!kIsWeb && Platform.isAndroid && kAndroidUseSamapieceForTesting) {
    return kSiteUrlSamapieceTest;
  }
  return kSiteUrlProduction;
}

/// API version app (même domaine que la WebView).
String get kAppVersionApiUrl {
  final base = kMarketplaceBaseUrl.replaceAll(RegExp(r'/+$'), '');
  return '$base/api/app_version.php';
}

bool isMarketplaceHost(String host) {
  final h = host.toLowerCase();
  if (h == 'sugar-paper.com' || h == 'www.sugar-paper.com') {
    return true;
  }
  if (kAndroidUseSamapieceForTesting &&
      (h == 'samapiece.com' || h == 'www.samapiece.com')) {
    return true;
  }
  return false;
}

String normalizeMarketplaceHost(String host) {
  var h = host.toLowerCase();
  if (h == 'www.sugar-paper.com') {
    return 'sugar-paper.com';
  }
  if (kAndroidUseSamapieceForTesting && h == 'www.samapiece.com') {
    return 'samapiece.com';
  }
  return h;
}
