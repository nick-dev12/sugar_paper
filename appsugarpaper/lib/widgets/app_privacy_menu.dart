import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../config/legal_urls.dart';
import '../theme/app_colors.dart';

/// Menu confidentialité accessible depuis l'app (exigence Google Play / App Store).
class AppPrivacyMenu extends StatelessWidget {
  const AppPrivacyMenu({super.key});

  Future<void> _openUrl(String url) async {
    final uri = Uri.parse(url);
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  @override
  Widget build(BuildContext context) {
    return PopupMenuButton<String>(
      icon: const Icon(Icons.privacy_tip_outlined, color: kRosePrincipal),
      tooltip: 'Confidentialité et données',
      onSelected: (value) {
        switch (value) {
          case 'privacy':
            _openUrl(LegalUrls.privacyPolicy);
          case 'terms':
            _openUrl(LegalUrls.termsOfUse);
          case 'delete':
            _openUrl(LegalUrls.accountDeletion);
          case 'permissions':
            _openUrl(LegalUrls.privacyPolicyPermissions);
        }
      },
      itemBuilder: (context) => const [
        PopupMenuItem(
          value: 'privacy',
          child: ListTile(
            leading: Icon(Icons.shield_outlined),
            title: Text('Politique de confidentialité'),
            contentPadding: EdgeInsets.zero,
            dense: true,
          ),
        ),
        PopupMenuItem(
          value: 'terms',
          child: ListTile(
            leading: Icon(Icons.description_outlined),
            title: Text('Conditions d\'utilisation'),
            contentPadding: EdgeInsets.zero,
            dense: true,
          ),
        ),
        PopupMenuItem(
          value: 'permissions',
          child: ListTile(
            leading: Icon(Icons.location_on_outlined),
            title: Text('Autorisations de l\'app'),
            contentPadding: EdgeInsets.zero,
            dense: true,
          ),
        ),
        PopupMenuItem(
          value: 'delete',
          child: ListTile(
            leading: Icon(Icons.person_remove_outlined),
            title: Text('Supprimer mon compte'),
            contentPadding: EdgeInsets.zero,
            dense: true,
          ),
        ),
      ],
    );
  }
}
