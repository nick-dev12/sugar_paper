import 'dart:io' show Platform;

import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:permission_handler/permission_handler.dart';

import '../config/legal_urls.dart';
import '../theme/app_colors.dart';
import '../widgets/prominent_disclosure_dialog.dart';

/// Textes alignés sur `ios/Runner/Info.plist` et la politique de confidentialité.
class NativePermissionCopy {
  static const locationDeniedForeverTitle = 'Localisation désactivée';
  static const locationDeniedForeverBody =
      'L\'accès à la localisation est refusé pour Sugar Paper. '
      'Pour préremplir une adresse, activez la localisation '
      'dans les paramètres de votre appareil (Paramètres > Sugar Paper > Localisation).';

  static const deliveryTrackingDeniedForeverTitle =
      'Localisation requise pour le suivi';
  static const deliveryTrackingDeniedForeverBody =
      'Le suivi livraison nécessite l\'accès à la position. '
      'Ouvrez les paramètres de Sugar Paper et autorisez la localisation '
      '(iOS : « Toujours » ; Android : « Pendant l\'utilisation de l\'app »).';

  static const cameraTitle = 'Autoriser l\'appareil photo';
  static const cameraBody =
      'Sugar Paper collecte des images via l\'appareil photo lorsque vous '
      'appuyez sur « Prendre une photo » pour illustrer votre profil ou '
      'joindre une image à une commande.\n\n'
      'Exemple : photographier un gâteau personnalisé.';

  static const cameraDeniedForeverTitle = 'Caméra désactivée';
  static const cameraDeniedForeverBody =
      'L\'accès à la caméra est refusé pour Sugar Paper. '
      'Activez-la dans les paramètres de votre appareil si vous souhaitez prendre une photo.';

  static const contactsTitle = 'Autoriser l\'accès aux contacts';
  static const contactsBody =
      'Sugar Paper accède à vos contacts uniquement lorsque vous '
      'appuyez sur « Importer » dans l\'espace commercial '
      'pour ajouter des clients à votre carnet.\n\n'
      '• Vous choisissez explicitement quels contacts importer.\n'
      '• Seuls le nom, le prénom, le téléphone et l\'e-mail '
      'sont enregistrés dans votre carnet clients.\n'
      '• Aucune lecture automatique du répertoire en arrière-plan.\n'
      '• Vous pouvez refuser et importer un fichier .vcf / .csv à la place.';

  static const contactsDeniedForeverTitle = 'Contacts désactivés';
  static const contactsDeniedForeverBody =
      'L\'accès aux contacts est refusé pour Sugar Paper. '
      'Activez-le dans les paramètres (Sugar Paper > Contacts) '
      'ou importez un fichier .vcf / .csv.';
}

/// Boîtes de dialogue explicatives avant les autorisations système (Apple 5.1.1 / Google Play).
class NativePermissionService {
  static Future<void> _showOpenSettingsDialog(
    BuildContext context, {
    required String title,
    required String body,
  }) async {
    await showDialog<void>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(title),
        content: Text(body),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(),
            child: const Text('Fermer'),
          ),
          FilledButton(
            onPressed: () {
              Navigator.of(ctx).pop();
              openAppSettings();
            },
            style: FilledButton.styleFrom(
              backgroundColor: kRosePrincipal,
            ),
            child: const Text('Ouvrir les paramètres'),
          ),
        ],
      ),
    );
  }

  static bool _locationGranted(LocationPermission permission) {
    return permission == LocationPermission.always ||
        permission == LocationPermission.whileInUse;
  }

  /// Localisation client — écran plein page (communiqué visible Google Play) puis permission.
  static Future<LocationPermission> requestLocationWithRationale(
    BuildContext context,
  ) async {
    var permission = await Geolocator.checkPermission();
    if (_locationGranted(permission)) {
      return permission;
    }

    if (permission == LocationPermission.deniedForever) {
      if (context.mounted) {
        await _showOpenSettingsDialog(
          context,
          title: NativePermissionCopy.locationDeniedForeverTitle,
          body: NativePermissionCopy.locationDeniedForeverBody,
        );
      }
      return permission;
    }

    if (!context.mounted) return permission;
    final accepted =
        await ProminentDisclosureDialog.showLocationCollection(context);
    if (!accepted) return LocationPermission.denied;

    permission = await Geolocator.requestPermission();
    if (permission == LocationPermission.deniedForever && context.mounted) {
      await _showOpenSettingsDialog(
        context,
        title: NativePermissionCopy.locationDeniedForeverTitle,
        body: NativePermissionCopy.locationDeniedForeverBody,
      );
    }
    return permission;
  }

  /// Suivi livreur.
  ///
  /// Android : « Pendant l'utilisation » + service de premier plan (FGS location).
  /// Pas de [ACCESS_BACKGROUND_LOCATION] — évite le rejet Play Console.
  ///
  /// iOS : « Toujours » autorisé après divulgation (UIBackgroundModes location).
  static Future<bool> requestDeliveryTrackingPermissions(
    BuildContext context,
  ) async {
    if (!await Geolocator.isLocationServiceEnabled()) {
      return false;
    }

    var permission = await Geolocator.checkPermission();

    if (Platform.isAndroid && _locationGranted(permission)) {
      await _requestBatteryOptimizationExemption();
      return true;
    }

    if (Platform.isIOS && permission == LocationPermission.always) {
      return true;
    }

    if (permission == LocationPermission.deniedForever) {
      if (context.mounted) {
        await _showOpenSettingsDialog(
          context,
          title: NativePermissionCopy.deliveryTrackingDeniedForeverTitle,
          body: NativePermissionCopy.deliveryTrackingDeniedForeverBody,
        );
      }
      return false;
    }

    if (!context.mounted) return false;
    final accepted =
        await ProminentDisclosureDialog.showDeliveryTracking(context);
    if (!accepted) {
      return false;
    }

    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }

    if (Platform.isIOS &&
        permission == LocationPermission.whileInUse) {
      permission = await Geolocator.requestPermission();
    }

    if (Platform.isAndroid && _locationGranted(permission)) {
      await _requestBatteryOptimizationExemption();
      return true;
    }

    if (Platform.isIOS &&
        (permission == LocationPermission.always ||
            permission == LocationPermission.whileInUse)) {
      return true;
    }

    if (permission == LocationPermission.deniedForever && context.mounted) {
      await _showOpenSettingsDialog(
        context,
        title: NativePermissionCopy.deliveryTrackingDeniedForeverTitle,
        body: NativePermissionCopy.deliveryTrackingDeniedForeverBody,
      );
    }

    return _locationGranted(permission);
  }

  static Future<void> _requestBatteryOptimizationExemption() async {
    if (!Platform.isAndroid) {
      return;
    }
    try {
      final status = await Permission.ignoreBatteryOptimizations.status;
      if (status.isGranted) {
        return;
      }
      await Permission.ignoreBatteryOptimizations.request();
    } catch (_) {
      /* certains OEM refusent silencieusement */
    }
  }

  static Future<bool> requestCameraWithRationale(BuildContext context) async {
    var status = await Permission.camera.status;
    if (status.isGranted) return true;

    if (status.isPermanentlyDenied) {
      if (context.mounted) {
        await _showOpenSettingsDialog(
          context,
          title: NativePermissionCopy.cameraDeniedForeverTitle,
          body: NativePermissionCopy.cameraDeniedForeverBody,
        );
      }
      return false;
    }

    if (!context.mounted) return false;
    final accepted = await ProminentDisclosureDialog.showPermissionRationale(
      context,
      title: NativePermissionCopy.cameraTitle,
      body: NativePermissionCopy.cameraBody,
      icon: Icons.photo_camera_outlined,
      privacySectionUrl: LegalUrls.privacyPolicy,
    );
    if (!accepted) return false;

    status = await Permission.camera.request();
    if (status.isPermanentlyDenied && context.mounted) {
      await _showOpenSettingsDialog(
        context,
        title: NativePermissionCopy.cameraDeniedForeverTitle,
        body: NativePermissionCopy.cameraDeniedForeverBody,
      );
      return false;
    }
    return status.isGranted;
  }

  static Future<bool> requestContactsWithRationale(BuildContext context) async {
    var status = await Permission.contacts.status;
    if (status.isGranted) return true;

    if (status.isPermanentlyDenied) {
      if (context.mounted) {
        await _showOpenSettingsDialog(
          context,
          title: NativePermissionCopy.contactsDeniedForeverTitle,
          body: NativePermissionCopy.contactsDeniedForeverBody,
        );
      }
      return false;
    }

    if (!context.mounted) return false;
    final accepted = await ProminentDisclosureDialog.showPermissionRationale(
      context,
      title: NativePermissionCopy.contactsTitle,
      body: NativePermissionCopy.contactsBody,
      icon: Icons.contacts_outlined,
      privacySectionUrl: LegalUrls.privacyPolicy,
    );
    if (!accepted) return false;

    status = await Permission.contacts.request();
    if (status.isPermanentlyDenied && context.mounted) {
      await _showOpenSettingsDialog(
        context,
        title: NativePermissionCopy.contactsDeniedForeverTitle,
        body: NativePermissionCopy.contactsDeniedForeverBody,
      );
      return false;
    }
    return status.isGranted;
  }
}
