/// URL du site Sugar Paper chargée dans la WebView.
const String kMarketplaceBaseUrl = 'https://sugar-paper.com/';

/// API version app (même domaine que la WebView).
String get kAppVersionApiUrl {
  return 'https://sugar-paper.com/api/app_version.php';
}

bool isMarketplaceHost(String host) {
  final h = host.toLowerCase();
  return h == 'sugar-paper.com' || h == 'www.sugar-paper.com';
}

String normalizeMarketplaceHost(String host) {
  var h = host.toLowerCase();
  if (h == 'www.sugar-paper.com') {
    return 'sugar-paper.com';
  }
  return h;
}
