import 'dart:convert';
import 'dart:io' show Platform;

import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:http/http.dart' as http;
import 'package:package_info_plus/package_info_plus.dart';

const String kAppVersionApiUrl = 'https://colobanes.com/api/app_version.php';

/// Résultat du contrôle de version côté serveur.
class AppVersionCheckResult {
  const AppVersionCheckResult({
    required this.updateRequired,
    required this.title,
    required this.message,
    required this.storeUrl,
    required this.installedBuild,
    required this.minBuild,
  });

  final bool updateRequired;
  final String title;
  final String message;
  final String storeUrl;
  final int installedBuild;
  final int minBuild;

  factory AppVersionCheckResult.fromJson(
    Map<String, dynamic> json, {
    required int installedBuild,
  }) {
    return AppVersionCheckResult(
      updateRequired: json['update_required'] == true,
      title: (json['title'] ?? 'Mise à jour requise').toString(),
      message: (json['message'] ?? '').toString(),
      storeUrl: (json['store_url'] ?? '').toString(),
      installedBuild: installedBuild,
      minBuild: int.tryParse('${json['min_build'] ?? 0}') ?? 0,
    );
  }
}

String appVersionPlatformName() {
  if (kIsWeb) {
    return 'web';
  }
  return Platform.isIOS ? 'ios' : 'android';
}

/// Vérifie si une mise à jour obligatoire est requise.
/// Retourne `null` en cas d'erreur réseau (l'app reste utilisable).
Future<AppVersionCheckResult?> fetchAppVersionCheck() async {
  if (kIsWeb) {
    return null;
  }

  final packageInfo = await PackageInfo.fromPlatform();
  final installedBuild = int.tryParse(packageInfo.buildNumber) ?? 0;
  final platform = appVersionPlatformName();
  if (platform == 'web') {
    return null;
  }

  final uri = Uri.parse(kAppVersionApiUrl).replace(
    queryParameters: {
      'platform': platform,
      'build': installedBuild.toString(),
    },
  );

  try {
    final response = await http
        .get(uri)
        .timeout(const Duration(seconds: 10));
    if (response.statusCode != 200) {
      return null;
    }
    final decoded = jsonDecode(response.body);
    if (decoded is! Map<String, dynamic>) {
      return null;
    }
    return AppVersionCheckResult.fromJson(
      decoded,
      installedBuild: installedBuild,
    );
  } catch (_) {
    return null;
  }
}
