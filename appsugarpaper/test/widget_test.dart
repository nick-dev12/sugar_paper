// Tests sans montage de la WebView (incompatible avec l'environnement de test).
import 'package:flutter_test/flutter_test.dart';

import 'package:sugar_paper_app/main.dart' as app;

void main() {
  test('resolveRelativeMarketUrl gère URL absolue', () {
    expect(
      app.resolveRelativeMarketUrl('https://sugar-paper.com/panier/'),
      'https://sugar-paper.com/panier/',
    );
  });

  test('resolveRelativeMarketUrl gère chemin relatif', () {
    expect(
      app.resolveRelativeMarketUrl('/produits/'),
      'https://sugar-paper.com/produits/',
    );
  });

  test('normalizeMarketplaceUrl conserve les chemins du site', () {
    expect(
      app.normalizeMarketplaceUrl(
        'https://sugar-paper.com/produits.php',
      ),
      'https://sugar-paper.com/produits.php',
    );
  });

  test('normalizeMarketplaceUrl retire www', () {
    expect(
      app.normalizeMarketplaceUrl('https://www.sugar-paper.com/produits.php'),
      'https://sugar-paper.com/produits.php',
    );
  });
}
