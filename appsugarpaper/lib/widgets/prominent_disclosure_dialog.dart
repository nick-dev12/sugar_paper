import 'dart:io' show Platform;

import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../config/legal_urls.dart';
import '../theme/app_colors.dart';

/// Divulgations conformes Google Play (communiqué visible + consentement explicite).
/// Format recommandé : « [App] collecte/transmet [données] pour [fonctionnalité], [circonstances]. »
class ProminentDisclosureDialog {
  /// Localisation client (« Localiser » adresse) — écran plein page avant la permission.
  static Future<bool> showLocationCollection(BuildContext context) async {
    return _showFullScreenDisclosure(
      context,
      config: _DisclosurePageConfig(
        icon: Icons.location_on_outlined,
        title: 'Localisation — confirmation d\'adresse',
        leadSentence:
            'Sugar Paper collecte et transmet vos données de localisation GPS '
            'pour permettre la confirmation de votre adresse de livraison ou '
            'd\'inscription, uniquement lorsque vous appuyez sur « Localiser », '
            '« Mettre à jour ma position » ou une action équivalente.',
        sections: const [
          _DisclosureSectionData(
            title: 'Données collectées',
            icon: Icons.my_location_outlined,
            bullets: [
              'Position GPS (latitude, longitude, précision).',
              'Uniquement au moment où vous déclenchez l\'action « Localiser ».',
              'Aucun suivi en arrière-plan pour les clients.',
            ],
          ),
          _DisclosureSectionData(
            title: 'Utilisation et partage',
            icon: Icons.share_outlined,
            bullets: [
              'Préremplir votre adresse sur la carte ou dans le formulaire.',
              'Enregistrement sur nos serveurs uniquement si vous validez le formulaire.',
              'Aucune vente ni partage publicitaire avec des tiers.',
            ],
          ),
        ],
        consentLabel:
            'En appuyant sur « J\'accepte », vous autorisez Sugar Paper à accéder '
            'à votre position à cet instant, conformément à notre politique de confidentialité.',
      ),
    );
  }

  /// Suivi livreur — collecte pendant une course (FGS Android / arrière-plan iOS).
  static Future<bool> showDeliveryTracking(BuildContext context) async {
    final isAndroid = !Platform.isIOS;
    return _showFullScreenDisclosure(
      context,
      config: _DisclosurePageConfig(
        icon: Icons.delivery_dining_outlined,
        title: 'Suivi GPS livraison',
        leadSentence: isAndroid
            ? 'Sugar Paper collecte et transmet vos données de localisation GPS '
                'pour permettre le suivi de livraison en direct par le client, '
                'pendant une livraison active que vous démarrez explicitement, '
                'y compris lorsque l\'application est en arrière-plan via un '
                'service de premier plan et une notification persistante.'
            : 'Sugar Paper collecte et transmet vos données de localisation GPS '
                'pour permettre le suivi de livraison en direct par le client, '
                'pendant une livraison active que vous démarrez explicitement, '
                'y compris lorsque l\'application est fermée ou en arrière-plan.',
        sections: [
          _DisclosureSectionData(
            title: 'Données collectées',
            icon: Icons.my_location_outlined,
            bullets: [
              'Position GPS en continu (latitude, longitude, précision, horodatage).',
              'Collecte uniquement pendant une livraison active démarrée par vous.',
              if (isAndroid)
                'Sur Android : service de premier plan avec notification « Livraison en cours ».'
              else
                'Sur iPhone : indicateur système de localisation en arrière-plan.',
            ],
          ),
          _DisclosureSectionData(
            title: 'Partage',
            icon: Icons.people_outline,
            bullets: const [
              'Transmises à nos serveurs Sugar Paper.',
              'Visibles par le client concerné via la page de suivi de sa commande.',
              'Le suivi s\'arrête dès que vous terminez la livraison.',
              'Aucune vente ni partage publicitaire avec des tiers.',
            ],
          ),
          if (isAndroid)
            const _DisclosureSectionData(
              title: 'Autorisation Android',
              icon: Icons.notifications_active_outlined,
              bullets: [
                'Choisissez « Pendant l\'utilisation de l\'app » lorsque Android le demande.',
                'Une notification persistante reste affichée pendant la course.',
                'Sugar Paper ne demande pas « Autoriser tout le temps » sur Android.',
              ],
            )
          else
            const _DisclosureSectionData(
              title: 'Autorisation iPhone',
              icon: Icons.settings_outlined,
              bullets: [
                'Choisissez « Toujours » ou « Lorsque l\'app est active » selon la boîte système.',
                'Nécessaire pour un suivi fiable si vous quittez l\'écran pendant la course.',
              ],
            ),
        ],
        consentLabel:
            'En appuyant sur « J\'accepte », vous autorisez la collecte de votre '
            'position pendant la livraison active, conformément à notre politique '
            'de confidentialité.',
        privacyUrl: LegalUrls.privacyPolicyGpsAnchor,
      ),
    );
  }

  /// Dialogue pour caméra, contacts, etc.
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

  static Future<bool> _showFullScreenDisclosure(
    BuildContext context, {
    required _DisclosurePageConfig config,
  }) async {
    final result = await Navigator.of(context).push<bool>(
      MaterialPageRoute<bool>(
        fullscreenDialog: true,
        builder: (ctx) => _FullScreenDisclosurePage(config: config),
      ),
    );
    return result == true;
  }
}

class _DisclosurePageConfig {
  const _DisclosurePageConfig({
    required this.icon,
    required this.title,
    required this.leadSentence,
    required this.sections,
    required this.consentLabel,
    this.privacyUrl,
  });

  final IconData icon;
  final String title;
  final String leadSentence;
  final List<_DisclosureSectionData> sections;
  final String consentLabel;
  final String? privacyUrl;
}

class _DisclosureSectionData {
  const _DisclosureSectionData({
    required this.title,
    required this.icon,
    required this.bullets,
  });

  final String title;
  final IconData icon;
  final List<String> bullets;
}

class _FullScreenDisclosurePage extends StatelessWidget {
  const _FullScreenDisclosurePage({required this.config});

  final _DisclosurePageConfig config;

  Future<void> _openPrivacy() async {
    final uri = Uri.parse(config.privacyUrl ?? LegalUrls.privacyPolicy);
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
          title: const Text('Collecte de données'),
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
                      Icon(config.icon, size: 56, color: kRosePrincipal),
                      const SizedBox(height: 16),
                      Text(
                        config.title,
                        style: theme.textTheme.titleLarge?.copyWith(
                          fontWeight: FontWeight.bold,
                          color: kTexteFonce,
                        ),
                        textAlign: TextAlign.center,
                      ),
                      const SizedBox(height: 16),
                      Container(
                        padding: const EdgeInsets.all(14),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(
                            color: kRosePrincipal.withValues(alpha: 0.35),
                            width: 1.5,
                          ),
                        ),
                        child: Text(
                          config.leadSentence,
                          style: const TextStyle(
                            fontWeight: FontWeight.w600,
                            height: 1.45,
                            color: kTexteFonce,
                          ),
                        ),
                      ),
                      const SizedBox(height: 16),
                      ...config.sections.map(
                        (s) => Padding(
                          padding: const EdgeInsets.only(bottom: 12),
                          child: _DisclosureSection(
                            title: s.title,
                            icon: s.icon,
                            bullets: s.bullets,
                          ),
                        ),
                      ),
                      OutlinedButton.icon(
                        onPressed: _openPrivacy,
                        icon: const Icon(Icons.open_in_new, size: 18),
                        label: const Text('Politique de confidentialité'),
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
                      config.consentLabel,
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
                        'J\'accepte',
                        style: TextStyle(fontWeight: FontWeight.w700),
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
