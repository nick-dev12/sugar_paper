import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:permission_handler/permission_handler.dart';

/// Textes alignés sur `ios/Runner/Info.plist` et la politique de confidentialité (section 9).
class NativePermissionCopy {
  static const locationTitle = 'Autoriser la localisation';
  static const locationBody =
      'COLObanes utilise votre position uniquement lorsque vous appuyez sur '
      '« Localiser », « Mettre à jour ma position » ou une action équivalente sur la carte, '
      'pour :\n\n'
      '• confirmer votre adresse de livraison lors d\'une commande ;\n'
      '• enregistrer votre adresse lors de l\'inscription ;\n'
      '• afficher les boutiques à proximité ;\n'
      '• indiquer l\'emplacement de votre boutique si vous êtes vendeur.\n\n'
      'Exemple : positionner votre point de livraison à Dakar sur la carte.\n\n'
      'La position n\'est jamais suivie en arrière-plan. Vous pouvez refuser '
      'et saisir votre adresse manuellement.';

  static const locationDeniedForeverTitle = 'Localisation désactivée';
  static const locationDeniedForeverBody =
      'L\'accès à la localisation est refusé pour COLObanes. '
      'Pour préremplir une adresse sur la carte, activez la localisation '
      'dans les paramètres de votre appareil (Paramètres > COLObanes > Localisation).';

  static const cameraTitle = 'Autoriser l\'appareil photo';
  static const cameraBody =
      'COLObanes utilise l\'appareil photo lorsque vous appuyez sur '
      '« Prendre une photo » pour illustrer votre profil, une fiche produit '
      'vendeur ou joindre une image à un message.\n\n'
      'Exemple : photographier un article que vous mettez en vente sur la marketplace.';

  static const cameraDeniedForeverTitle = 'Caméra désactivée';
  static const cameraDeniedForeverBody =
      'L\'accès à la caméra est refusé pour COLObanes. '
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
        icon: Icon(icon, color: const Color(0xFF3564A6), size: 32),
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
              backgroundColor: const Color(0xFF3564A6),
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
              backgroundColor: const Color(0xFF3564A6),
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
