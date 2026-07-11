import 'dart:io' show Platform;

import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:permission_handler/permission_handler.dart';
import '../theme/app_colors.dart';

/// Textes alignés sur `ios/Runner/Info.plist` et la politique de confidentialité.
class NativePermissionCopy {
  static const locationTitle = 'Autoriser la localisation';
  static const locationBody =
      'Sugar Paper utilise votre position uniquement lorsque vous appuyez sur '
      '« Localiser », « Mettre à jour ma position » ou une action équivalente, '
      'pour :\n\n'
      '• confirmer votre adresse de livraison lors d\'une commande ;\n'
      '• enregistrer votre adresse lors de l\'inscription.\n\n'
      'La position n\'est jamais suivie en arrière-plan. Vous pouvez refuser '
      'et saisir votre adresse manuellement.';

  static const locationDeniedForeverTitle = 'Localisation désactivée';
  static const locationDeniedForeverBody =
      'L\'accès à la localisation est refusé pour Sugar Paper. '
      'Pour préremplir une adresse, activez la localisation '
      'dans les paramètres de votre appareil (Paramètres > Sugar Paper > Localisation).';

  static const deliveryTrackingTitle = 'Suivi GPS livraison';
  static const deliveryTrackingBody =
      'Pendant une livraison active, Sugar Paper transmet votre position '
      'en direct au client et à l\'équipe, y compris si vous quittez '
      'l\'écran ou mettez l\'application en arrière-plan.\n\n'
      '• Le suivi s\'arrête quand vous terminez la livraison ou en changez.\n'
      '• Une notification persistante s\'affiche sur Android pendant la course.\n\n'
      'Autorisez « Toujours » (iOS) ou « Autoriser tout le temps » (Android) '
      'pour un suivi fiable en arrière-plan.';

  static const deliveryTrackingDeniedForeverTitle =
      'Localisation arrière-plan requise';
  static const deliveryTrackingDeniedForeverBody =
      'Le suivi livraison nécessite l\'accès à la position en arrière-plan. '
      'Ouvrez les paramètres de Sugar Paper et choisissez « Toujours » '
      '(iOS) ou « Autoriser tout le temps » (Android).';

  static const cameraTitle = 'Autoriser l\'appareil photo';
  static const cameraBody =
      'Sugar Paper utilise l\'appareil photo lorsque vous appuyez sur '
      '« Prendre une photo » pour illustrer votre profil ou joindre une image.\n\n'
      'Exemple : photographier un gâteau personnalisé pour votre commande.';

  static const cameraDeniedForeverTitle = 'Caméra désactivée';
  static const cameraDeniedForeverBody =
      'L\'accès à la caméra est refusé pour Sugar Paper. '
      'Activez-la dans les paramètres de votre appareil si vous souhaitez prendre une photo.';
}

/// Boîtes de dialogue explicatives avant les autorisations système (Apple 5.1.1 / Google Play).
class NativePermissionService {
  static Future<bool> _showRationaleDialog(
    BuildContext context, {
    required String title,
    required String body,
    required IconData icon,
  }) async {
    final result = await showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => AlertDialog(
        icon: Icon(icon, color: kRosePrincipal, size: 32),
        title: Text(title),
        content: SingleChildScrollView(child: Text(body)),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(false),
            child: const Text('Plus tard'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(ctx).pop(true),
            style: FilledButton.styleFrom(
              backgroundColor: kRosePrincipal,
            ),
            child: const Text('Continuer'),
          ),
        ],
      ),
    );
    return result == true;
  }

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

  /// Demande la localisation « pendant l'utilisation » avec explication préalable.
  static Future<LocationPermission> requestLocationWithRationale(
    BuildContext context,
  ) async {
    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.always ||
        permission == LocationPermission.whileInUse) {
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
    final accepted = await _showRationaleDialog(
      context,
      title: NativePermissionCopy.locationTitle,
      body: NativePermissionCopy.locationBody,
      icon: Icons.location_on_outlined,
    );
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

  /// Localisation livreur — « toujours » / arrière-plan pour le suivi GPS en course.
  static Future<bool> requestDeliveryTrackingPermissions(
    BuildContext context,
  ) async {
    if (!await Geolocator.isLocationServiceEnabled()) {
      return false;
    }

    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.always) {
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

    if (permission == LocationPermission.denied && context.mounted) {
      final accepted = await _showRationaleDialog(
        context,
        title: NativePermissionCopy.deliveryTrackingTitle,
        body: NativePermissionCopy.deliveryTrackingBody,
        icon: Icons.delivery_dining_outlined,
      );
      if (!accepted) {
        return false;
      }
    }

    permission = await Geolocator.requestPermission();
    if (permission == LocationPermission.whileInUse) {
      if (Platform.isAndroid) {
        final bg = await Permission.locationAlways.request();
        if (bg.isGranted) {
          return true;
        }
      } else if (Platform.isIOS) {
        permission = await Geolocator.requestPermission();
      }
    }

    if (permission == LocationPermission.always) {
      return true;
    }

    if (Platform.isAndroid) {
      final bg = await Permission.locationAlways.status;
      if (bg.isGranted) {
        return true;
      }
    }

    if (permission == LocationPermission.whileInUse) {
      return true;
    }

    if (permission == LocationPermission.deniedForever && context.mounted) {
      await _showOpenSettingsDialog(
        context,
        title: NativePermissionCopy.deliveryTrackingDeniedForeverTitle,
        body: NativePermissionCopy.deliveryTrackingDeniedForeverBody,
      );
    }

    return permission == LocationPermission.always ||
        permission == LocationPermission.whileInUse;
  }

  /// Demande la caméra avec explication préalable (aligné Info.plist).
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
    final accepted = await _showRationaleDialog(
      context,
      title: NativePermissionCopy.cameraTitle,
      body: NativePermissionCopy.cameraBody,
      icon: Icons.photo_camera_outlined,
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
}
