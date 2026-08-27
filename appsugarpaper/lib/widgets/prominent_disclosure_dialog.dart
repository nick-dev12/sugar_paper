import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../config/legal_urls.dart';
import '../theme/app_colors.dart';

/// Divulgation bien visible exigée par Google Play avant [ACCESS_BACKGROUND_LOCATION].
/// Réf. : https://support.google.com/googleplay/android-developer/answer/9888170
class ProminentDisclosureDialog {
  /// Écran plein page, non dismissible, consentement explicite avant toute demande
  /// de localisation en arrière-plan.
  static Future<bool> showBackgroundLocation(
    BuildContext context,
  ) async {
    final result = await Navigator.of(context).push<bool>(
      MaterialPageRoute<bool>(
        fullscreenDialog: true,
        builder: (ctx) => const _BackgroundLocationDisclosurePage(),
      ),
    );
    return result == true;
  }

  /// Dialogue explicatif avant les autres autorisations sensibles (caméra, contacts, GPS usage).
  static Future<bool> showPermissionRationale(
    BuildContext context, {
    required String title,
    required String body,
    required IconData icon,
    String? privacySectionUrl,
  }) async {
    final result = await showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => _PermissionRationaleDialog(
        title: title,
        body: body,
        icon: icon,
        privacySectionUrl: privacySectionUrl,
      ),
    );
    return result == true;
  }
}

class _BackgroundLocationDisclosurePage extends StatelessWidget {
  const _BackgroundLocationDisclosurePage();

  Future<void> _openPrivacyPolicy() async {
    final uri = Uri.parse(LegalUrls.privacyPolicyGpsAnchor);
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return PopScope(
      canPop: false,
      child: Scaffold(
        backgroundColor: kSurfaceSoft,
        appBar: AppBar(
          automaticallyImplyLeading: false,
          title: const Text('Autorisation requise'),
          backgroundColor: kRosePrincipal,
          foregroundColor: Colors.white,
          centerTitle: true,
        ),
        body: SafeArea(
          child: Column(
            children: [
              Expanded(
                child: SingleChildScrollView(
                  padding: const EdgeInsets.fromLTRB(20, 20, 20, 8),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Icon(
                        Icons.delivery_dining_outlined,
                        size: 56,
                        color: kRosePrincipal,
                      ),
                      const SizedBox(height: 16),
                      Text(
                        'Suivi GPS livraison — localisation en arrière-plan',
                        style: theme.textTheme.titleLarge?.copyWith(
                          fontWeight: FontWeight.bold,
                          color: kTexteFonce,
                        ),
                        textAlign: TextAlign.center,
                      ),
                      const SizedBox(height: 20),
                      _DisclosureSection(
                        title: 'Données collectées',
                        icon: Icons.my_location_outlined,
                        bullets: const [
                          'Votre position GPS (latitude, longitude, précision, horodatage).',
                          'Collecte en continu pendant une livraison active, y compris '
                              'lorsque l\'application est fermée ou en arrière-plan.',
                        ],
                      ),
                      const SizedBox(height: 16),
                      _DisclosureSection(
                        title: 'Pourquoi nous en avons besoin',
                        icon: Icons.info_outline,
                        bullets: const [
                          'Permettre au client de suivre sa commande en temps réel sur la carte.',
                          'Uniquement lorsque vous démarrez explicitement une livraison '
                              'depuis l\'interface livreur.',
                          'Le suivi s\'arrête dès que vous terminez la livraison ou en '
                              'changez.',
                        ],
                      ),
                      const SizedBox(height: 16),
                      _DisclosureSection(
                        title: 'Partage des données',
                        icon: Icons.people_outline,
                        bullets: const [
                          'Position transmise à nos serveurs Sugar Paper.',
                          'Visible par le client concerné via la page de suivi de sa commande.',
                          'Aucune vente ni partage publicitaire avec des tiers.',
                        ],
                      ),
                      const SizedBox(height: 16),
                      _DisclosureSection(
                        title: 'Sur Android',
                        icon: Icons.notifications_active_outlined,
                        bullets: const [
                          'Une notification persistante s\'affiche pendant la course '
                              '(exigence système).',
                          'Choisissez « Autoriser tout le temps » lorsque Android le demande.',
                        ],
                      ),
                      const SizedBox(height: 20),
                      OutlinedButton.icon(
                        onPressed: _openPrivacyPolicy,
                        icon: const Icon(Icons.open_in_new, size: 18),
                        label: const Text('Lire la politique de confidentialité'),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: kRosePrincipal,
                          side: const BorderSide(color: kRosePrincipal),
                          padding: const EdgeInsets.symmetric(vertical: 12),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      'En appuyant sur « J\'accepte », vous autorisez Sugar Paper à '
                      'collecter votre position en arrière-plan pendant une livraison active, '
                      'conformément à notre politique de confidentialité.',
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: Colors.grey.shade700,
                      ),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 12),
                    FilledButton(
                      onPressed: () => Navigator.of(context).pop(true),
                      style: FilledButton.styleFrom(
                        backgroundColor: kRosePrincipal,
                        padding: const EdgeInsets.symmetric(vertical: 14),
                      ),
                      child: const Text(
                        'J\'accepte — continuer',
                        style: TextStyle(fontWeight: FontWeight.w600),
                      ),
                    ),
                    const SizedBox(height: 8),
                    TextButton(
                      onPressed: () => Navigator.of(context).pop(false),
                      child: const Text('Refuser'),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _DisclosureSection extends StatelessWidget {
  const _DisclosureSection({
    required this.title,
    required this.icon,
    required this.bullets,
  });

  final String title;
  final IconData icon;
  final List<String> bullets;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: kRosePrincipal.withValues(alpha: 0.25)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, color: kRosePrincipal, size: 22),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(
                    fontWeight: FontWeight.w700,
                    color: kTexteFonce,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          ...bullets.map(
            (b) => Padding(
              padding: const EdgeInsets.only(bottom: 6, left: 4),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('•  ', style: TextStyle(color: kRosePrincipal)),
                  Expanded(child: Text(b)),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _PermissionRationaleDialog extends StatelessWidget {
  const _PermissionRationaleDialog({
    required this.title,
    required this.body,
    required this.icon,
    this.privacySectionUrl,
  });

  final String title;
  final String body;
  final IconData icon;
  final String? privacySectionUrl;

  Future<void> _openPrivacy() async {
    final url = privacySectionUrl ?? LegalUrls.privacyPolicy;
    final uri = Uri.parse(url);
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      icon: Icon(icon, color: kRosePrincipal, size: 36),
      title: Text(title, textAlign: TextAlign.center),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(body),
            const SizedBox(height: 12),
            TextButton.icon(
              onPressed: _openPrivacy,
              icon: const Icon(Icons.privacy_tip_outlined, size: 18),
              label: const Text('Politique de confidentialité'),
            ),
          ],
        ),
      ),
      actionsAlignment: MainAxisAlignment.spaceEvenly,
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(false),
          child: const Text('Refuser'),
        ),
        FilledButton(
          onPressed: () => Navigator.of(context).pop(true),
          style: FilledButton.styleFrom(backgroundColor: kRosePrincipal),
          child: const Text('J\'accepte'),
        ),
      ],
    );
  }
}
