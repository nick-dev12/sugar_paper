// Tests sans montage de la WebView (incompatible avec l'environnement de test).
import 'package:flutter_test/flutter_test.dart';

import 'package:colobanes_marketplace/main.dart' as app;

void main() {
  test('resolveRelativeMarketUrl gère URL absolue', () {
    expect(
      app.resolveRelativeMarketUrl('https://colobanes.com/panier/'),
      'https://colobanes.com/panier/',
    );
  });

  test('resolveRelativeMarketUrl gère chemin relatif', () {
    expect(
      app.resolveRelativeMarketUrl('/produits/'),
      'https://colobanes.com/produits/',
    );
  });

  test('normalizeMarketplaceUrl conserve les liens boutique partagés', () {
    expect(
      app.normalizeMarketplaceUrl(
        'https://colobanes.com/boutique/index.php?boutique=sugar-paper',
      ),
      'https://colobanes.com/boutique/index.php?boutique=sugar-paper',
    );
  });

  test('normalizeMarketplaceUrl retire www', () {
    expect(
      app.normalizeMarketplaceUrl('https://www.colobanes.com/produits/'),
      'https://colobanes.com/produits/',
    );
  });
}
