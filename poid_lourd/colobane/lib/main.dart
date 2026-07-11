import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_native_splash/flutter_native_splash.dart';
import 'package:flutter_inappwebview/flutter_inappwebview.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:image_picker/image_picker.dart';
import 'package:geolocator/geolocator.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:flutter/foundation.dart' show kIsWeb, ValueListenable;
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:app_links/app_links.dart';
import 'package:share_plus/share_plus.dart';
import 'package:url_launcher/url_launcher.dart';
import 'dart:convert';
import 'dart:collection';
import 'dart:async';
import 'dart:io' show Platform;
import 'firebase_options.dart';
import 'services/fcm_service.dart';
import 'services/social_auth_service.dart';
import 'services/native_permission_service.dart';
import 'widgets/app_version_gate.dart';

/// URL de la marketplace chargée dans la WebView (production)
const String kMarketplaceBaseUrl = 'https://colobanes.com/';
const Color kBleuPrincipal = Color(0xFF3564A6);
const Color kBleuPrincipalFonce = Color(0xFF2D5690);
const Color kOrangePromo = Color(0xFFFF6B35);
/// Durée max de l'écran de chargement initial
const Duration kInitialLoaderMaxDuration = Duration(seconds: 5);
/// Logo splash + chargement initial (identique au splash natif iOS/Android)
const String kSplashLogoAsset = 'assets/images/colobane_splash_logo.png';

Future<void>? _firebaseBootstrapFuture;

/// Initialise Firebase sans bloquer l'affichage du splash natif.
Future<void> ensureFirebaseInitialized() {
  _firebaseBootstrapFuture ??= _bootstrapFirebase();
  return _firebaseBootstrapFuture!;
}

Future<void> _bootstrapFirebase() async {
  print('🔥 Initialisation de Firebase...');
  try {
    if (kIsWeb) {
      await Firebase.initializeApp(options: DefaultFirebaseOptions.web);
    } else {
      try {
        await Firebase.initializeApp();
      } catch (initErr) {
        print('⚠️ Firebase init native, repli options web: $initErr');
        await Firebase.initializeApp(
          options: DefaultFirebaseOptions.currentPlatform,
        );
      }
    }
    print('✅ Firebase initialisé avec succès');
  } catch (e) {
    print('❌ ERREUR lors de l\'initialisation Firebase: $e');
  }
}
/// Bleu marine proche du logo « banes »
const Color kBleuLogoMarine = Color(0xFF1A3A5C);

bool _isMarketplaceHost(String host) {
  final h = host.toLowerCase();
  return h == 'colobanes.com' || h == 'www.colobanes.com';
}

/// Normalise les liens partagés (apex sans www, chemins boutique conservés).
String normalizeMarketplaceUrl(String url) {
  if (url.isEmpty) {
    return kMarketplaceBaseUrl;
  }
  try {
    final uri = Uri.parse(url);
    if (!uri.hasScheme || !uri.hasAuthority) {
      return resolveRelativeMarketUrl(url);
    }
    var host = uri.host.toLowerCase();
    if (host == 'www.colobanes.com') {
      host = 'colobanes.com';
    }
    if (!_isMarketplaceHost(host)) {
      return url;
    }
    return uri.replace(host: host).toString();
  } catch (_) {
    return url;
  }
}

String resolveRelativeMarketUrl(String href) {
  if (href.isEmpty) {
    return kMarketplaceBaseUrl;
  }
  if (href.startsWith('http://') || href.startsWith('https://')) {
    return href;
  }
  final base = kMarketplaceBaseUrl.replaceAll(RegExp(r'/$'), '');
  final path = href.startsWith('/') ? href : '/$href';
  return '$base$path';
}

// Handler pour les notifications en arrière-plan
@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
  print(
    '📬 Notification reçue en arrière-plan: ${message.notification?.title}',
  );
}

void main() {
  final widgetsBinding = WidgetsFlutterBinding.ensureInitialized();
  FlutterNativeSplash.preserve(widgetsBinding: widgetsBinding);

  print('🔥 Démarrage de l\'application...');

  FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);
  ensureFirebaseInitialized();

  runApp(const ColobanesApp());
}

class ColobanesApp extends StatelessWidget {
  const ColobanesApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'COLObanes',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        scaffoldBackgroundColor: Colors.white,
        colorScheme: ColorScheme.fromSeed(
          seedColor: kBleuPrincipal,
          primary: kBleuPrincipal,
          secondary: kOrangePromo,
          surface: Colors.white,
        ),
        useMaterial3: true,
      ),
      home: const AppVersionGate(child: WebViewScreen()),
    );
  }
}

class WebViewScreen extends StatefulWidget {
  const WebViewScreen({super.key});

  @override
  State<WebViewScreen> createState() => _WebViewScreenState();
}

class _WebViewScreenState extends State<WebViewScreen>
    with WidgetsBindingObserver {
  InAppWebViewController? webViewController;
  String? _currentUrl;
  String? _deepLinkInitUrl;
  bool _marketplaceEntryUrlReady = false;
  String _marketplaceEntryUrl = kMarketplaceBaseUrl;
  Timer? _loaderMaxTimer;
  Timer? _progressSimTimer;
  StreamSubscription<Uri>? _deepLinkSub;

  // ValueNotifiers : les mises à jour ne rebuilde PAS la WebView,
  // uniquement les widgets qui les écoutent (ValueListenableBuilder).
  final _isInitialLoadNotifier = ValueNotifier<bool>(true);
  final _isPageLoadingNotifier = ValueNotifier<bool>(false);
  final _loaderProgressNotifier = ValueNotifier<double>(0.0);

  // Progression simulée locale (pas besoin de setState)
  double _rawProgress = 0;
  double _simProgress = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _startInitialLoadTimers();
    _initializeFCM();
    _resolveMarketplaceEntryUrl();
  }

  /// Attend le deep link (App Links / Universal Links) avant de créer la WebView.
  Future<void> _resolveMarketplaceEntryUrl() async {
    await _initDeepLinks();
    await _loadSavedUrl();
    if (!mounted) return;
    final resolved = (_currentUrl != null && _currentUrl!.isNotEmpty)
        ? normalizeMarketplaceUrl(_currentUrl!)
        : kMarketplaceBaseUrl;
    setState(() {
      _marketplaceEntryUrl = resolved;
      _currentUrl = resolved;
      _marketplaceEntryUrlReady = true;
    });
  }

  void _openMarketplaceUrl(String url) {
    final normalized = normalizeMarketplaceUrl(url);
    _currentUrl = normalized;
    _deepLinkInitUrl = normalized;
    if (webViewController != null) {
      webViewController!.loadUrl(
        urlRequest: URLRequest(url: WebUri(normalized)),
      );
    }
  }

  void _startInitialLoadTimers() {
    _loaderMaxTimer = Timer(kInitialLoaderMaxDuration, _forceFinishInitialLoad);
    // Mise à jour du progress simulé sans setState sur le widget parent
    // 100 ms suffit pour la barre de chargement ; 50 ms provoquait des repaints inutiles pendant le scroll WebView.
    _progressSimTimer = Timer.periodic(const Duration(milliseconds: 100), (_) {
      if (!mounted || !_isInitialLoadNotifier.value) return;
      if (_simProgress < 0.9) {
        _simProgress = (_simProgress + 0.018).clamp(0.0, 0.9);
        final best = _rawProgress > _simProgress ? _rawProgress : _simProgress;
        _loaderProgressNotifier.value = best;
      }
    });
  }

  void _forceFinishInitialLoad() {
    if (!mounted || !_isInitialLoadNotifier.value) return;
    _finishInitialLoad();
  }

  void _finishInitialLoad() {
    _loaderMaxTimer?.cancel();
    _progressSimTimer?.cancel();
    if (!mounted) return;
    _loaderProgressNotifier.value = 1.0;
    _isInitialLoadNotifier.value = false;
    _isPageLoadingNotifier.value = false;
  }

  Future<void> _postLoadSetup() async {
    await _injectWebViewPerformanceOptimizations();
    await _injectJavaScript();
    await _registerFCMTokenInWebView();
  }

  @override
  void dispose() {
    _loaderMaxTimer?.cancel();
    _progressSimTimer?.cancel();
    _deepLinkSub?.cancel();
    _isInitialLoadNotifier.dispose();
    _isPageLoadingNotifier.dispose();
    _loaderProgressNotifier.dispose();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  // Observer le cycle de vie de l'application
  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    super.didChangeAppLifecycleState(state);
    if (state == AppLifecycleState.paused) {
      // Application en arrière-plan - sauvegarder l'URL
      _saveCurrentUrl();
    } else if (state == AppLifecycleState.resumed) {
      // Application revenue au premier plan - restaurer l'URL si nécessaire
      _restoreUrlIfNeeded();
    }
  }

  // Sauvegarder l'URL actuelle
  Future<void> _saveCurrentUrl() async {
    if (webViewController != null) {
      try {
        final currentUrl = await webViewController!.getUrl();
        if (currentUrl != null) {
          _currentUrl = currentUrl.toString();
          final prefs = await SharedPreferences.getInstance();
          await prefs.setString('last_webview_url', _currentUrl!);
        }
      } catch (e) {
        print('Erreur lors de la sauvegarde de l\'URL: $e');
      }
    }
  }

  // Initialiser la gestion des deep links (Android App Links + iOS Universal Links)
  Future<void> _initDeepLinks() async {
    final appLinks = AppLinks();

    // Lien ayant lancé l'app à froid (cold start)
    try {
      final initialUri = await appLinks.getInitialLink();
      if (initialUri != null && _isMarketplaceHost(initialUri.host)) {
        _deepLinkInitUrl = normalizeMarketplaceUrl(initialUri.toString());
        _currentUrl = _deepLinkInitUrl;
      }
    } catch (_) {}

    // Liens reçus quand l'app est déjà ouverte (warm/hot start)
    _deepLinkSub = appLinks.uriLinkStream.listen((uri) {
      if (_isMarketplaceHost(uri.host)) {
        _openMarketplaceUrl(uri.toString());
      }
    }, onError: (_) {});
  }

  // Charger l'URL sauvegardée (invalide l'ancien domaine Aria)
  // Ne s'applique que si aucun deep link n'a été reçu au lancement
  Future<void> _loadSavedUrl() async {
    // Un deep link est prioritaire sur l'URL sauvegardée
    if (_deepLinkInitUrl != null) return;
    try {
      final prefs = await SharedPreferences.getInstance();
      final savedUrl = prefs.getString('last_webview_url');
      if (savedUrl != null && savedUrl.isNotEmpty) {
        if (savedUrl.contains('aria-edu.com') ||
            savedUrl.contains('samapiece.it.com')) {
          await prefs.remove('last_webview_url');
          _currentUrl = null;
        } else {
          try {
            final u = Uri.parse(savedUrl);
            if (u.hasAuthority && _isMarketplaceHost(u.host)) {
              _currentUrl = savedUrl;
            } else {
              await prefs.remove('last_webview_url');
              _currentUrl = null;
            }
          } catch (_) {
            await prefs.remove('last_webview_url');
            _currentUrl = null;
          }
        }
      }
    } catch (e) {
      print('Erreur lors du chargement de l\'URL sauvegardée: $e');
    }
  }

  // Restaurer l'URL si nécessaire (seulement si différente de l'URL actuelle)
  Future<void> _restoreUrlIfNeeded() async {
    if (webViewController != null && _currentUrl != null) {
      try {
        final currentUrl = await webViewController!.getUrl();
        // Si la WebView est toujours sur la bonne page, ne rien faire
        if (currentUrl != null && currentUrl.toString() == _currentUrl) {
          // La page est déjà chargée, pas besoin de recharger
          return;
        }
        // Sinon, recharger l'URL sauvegardée (utilise le cache si disponible)
        await webViewController!.loadUrl(
          urlRequest: URLRequest(url: WebUri(_currentUrl!)),
        );
      } catch (e) {
        print('Erreur lors de la restauration de l\'URL: $e');
      }
    }
  }

  // Initialiser Firebase Cloud Messaging
  Future<void> _initializeFCM() async {
    print('🔥 Début de l\'initialisation FCM...');
    try {
      await ensureFirebaseInitialized();
      // Définir le callback pour la navigation depuis les notifications
      print('🔥 Configuration du callback de navigation...');
      FCMService.setNotificationTapCallback((url) {
        if (webViewController != null && url.isNotEmpty) {
          final fullUrl = resolveRelativeMarketUrl(url);
          webViewController?.loadUrl(
            urlRequest: URLRequest(url: WebUri(fullUrl)),
          );
        }
      });
      print('✅ Callback de navigation configuré');

      print('🔥 Initialisation de FCMService avec URL: $kMarketplaceBaseUrl');
      final token = await FCMService.initialize(kMarketplaceBaseUrl);

      if (token != null) {
        print(
          '✅ FCM initialisé avec succès, token: ${token.substring(0, 20)}...',
        );
      } else {
        print('⚠️ FCM initialisé mais token non obtenu');
      }

      // Configurer le rafraîchissement automatique du token
      print('🔥 Configuration du rafraîchissement automatique du token...');
      FCMService.setupTokenRefresh();
      print('✅ Rafraîchissement automatique configuré');

      print('✅ FCM complètement initialisé avec succès');
    } catch (e, stackTrace) {
      print('❌ ERREUR lors de l\'initialisation FCM: $e');
      print('❌ Stack trace: $stackTrace');
    }
  }

  // Gérer les messages JavaScript depuis la WebView
  void _setupJavaScriptHandlers() {
    webViewController?.addJavaScriptHandler(
      handlerName: 'requestCamera',
      callback: (args) async {
        return await _handleCameraRequest();
      },
    );

    webViewController?.addJavaScriptHandler(
      handlerName: 'requestLocation',
      callback: (args) async {
        return await _handleLocationRequest();
      },
    );

    webViewController?.addJavaScriptHandler(
      handlerName: 'saveData',
      callback: (args) async {
        if (args.isNotEmpty) {
          final data = args[0];
          return await _handleSaveData(data);
        }
        return {'success': false, 'error': 'No data provided'};
      },
    );

    webViewController?.addJavaScriptHandler(
      handlerName: 'getData',
      callback: (args) async {
        if (args.isNotEmpty) {
          final key = args[0] as String;
          return await _handleGetData(key);
        }
        return {'success': false, 'error': 'No key provided'};
      },
    );

    webViewController?.addJavaScriptHandler(
      handlerName: 'showNotification',
      callback: (args) async {
        if (args.length >= 2) {
          final title = args[0] as String;
          final body = args[1] as String;
          return await _handleShowNotification(title, body);
        }
        return {'success': false, 'error': 'Invalid parameters'};
      },
    );

    webViewController?.addJavaScriptHandler(
      handlerName: 'signInWithGoogle',
      callback: (args) async {
        return SocialAuthService.signInWithGoogle();
      },
    );

    webViewController?.addJavaScriptHandler(
      handlerName: 'signInWithApple',
      callback: (args) async {
        return SocialAuthService.signInWithApple();
      },
    );

    webViewController?.addJavaScriptHandler(
      handlerName: 'shareContent',
      callback: (args) async {
        final payload = args.isNotEmpty ? args[0] : <String, dynamic>{};
        return await _handleShareContent(payload);
      },
    );

    webViewController?.addJavaScriptHandler(
      handlerName: 'openExternalUrl',
      callback: (args) async {
        if (args.isEmpty) {
          return {'success': false, 'error': 'No url'};
        }
        return await _handleOpenExternalUrl(args[0].toString());
      },
    );
  }

  Future<Map<String, dynamic>> _handleOpenExternalUrl(String url) async {
    try {
      final uri = Uri.parse(url.trim());
      if (!await canLaunchUrl(uri)) {
        return {'success': false, 'error': 'Cannot launch url'};
      }
      await launchUrl(uri, mode: LaunchMode.externalApplication);
      return {'success': true};
    } catch (e) {
      return {'success': false, 'error': e.toString()};
    }
  }

  Map<String, dynamic> _normalizeSharePayload(dynamic payload) {
    if (payload is Map) {
      return payload.map((key, value) => MapEntry(key.toString(), value));
    }
    if (payload is String && payload.trim().isNotEmpty) {
      try {
        final decoded = jsonDecode(payload);
        if (decoded is Map) {
          return decoded.map((key, value) => MapEntry(key.toString(), value));
        }
      } catch (_) {
        return {'text': payload.trim()};
      }
    }
    return {};
  }

  Rect _sharePositionOrigin() {
    if (!mounted) {
      return const Rect.fromLTWH(0, 0, 1, 1);
    }
    final size = MediaQuery.sizeOf(context);
    final padding = MediaQuery.paddingOf(context);
    final centerX = size.width / 2;
    final centerY = (size.height - padding.bottom) * 0.72;
    return Rect.fromCenter(
      center: Offset(centerX, centerY),
      width: 48,
      height: 48,
    );
  }

  // Ouvre la feuille de partage native du système (Android / iOS)
  Future<Map<String, dynamic>> _handleShareContent(dynamic payload) async {
    try {
      if (!mounted) {
        return {'success': false, 'error': 'Application non prête'};
      }

      final map = _normalizeSharePayload(payload);
      final title = (map['title'] ?? '').toString();
      final text = (map['text'] ?? '').toString();
      final url = (map['url'] ?? '').toString();

      var shareText = text.trim();
      if (url.isNotEmpty && shareText.contains(url)) {
        shareText = shareText.replaceAll(url, '').replaceAll(RegExp(r'\s*:\s*$'), '').trim();
      }

      final shareBody = [shareText, url]
          .where((s) => s.trim().isNotEmpty)
          .join(shareText.contains(url) || url.isEmpty ? '' : '\n')
          .trim();

      final shareValue = url.isNotEmpty
          ? url
          : (shareBody.isNotEmpty ? shareBody : title.trim());
      if (shareValue.isEmpty) {
        return {'success': false, 'error': 'Contenu de partage vide'};
      }

      final origin = _sharePositionOrigin();

      // iOS (surtout iPad) exige une ancre valide pour UIActivityViewController.
      if (!kIsWeb && Platform.isIOS && url.isNotEmpty) {
        final uri = Uri.tryParse(url);
        if (uri != null) {
          final uriResult = await Share.shareUri(
            uri,
            sharePositionOrigin: origin,
          );
          if (uriResult.status != ShareResultStatus.unavailable) {
            return {'success': true};
          }
        }
      }

      final result = await Share.share(
        shareValue,
        subject: title.isNotEmpty ? title : null,
        sharePositionOrigin: origin,
      );
      if (result.status == ShareResultStatus.unavailable) {
        return {'success': false, 'error': 'Partage indisponible'};
      }
      return {'success': true};
    } catch (e) {
      return {'success': false, 'error': e.toString()};
    }
  }

  // Gérer la demande de caméra
  Future<Map<String, dynamic>> _handleCameraRequest() async {
    try {
      if (!mounted) {
        return {'success': false, 'error': 'Application non prête.'};
      }
      final granted = await NativePermissionService.requestCameraWithRationale(
        context,
      );
      if (!granted) {
        return {
          'success': false,
          'error': 'L\'accès à la caméra a été refusé.',
        };
      }

      final ImagePicker picker = ImagePicker();
      final XFile? image = await picker.pickImage(
        source: ImageSource.camera,
        imageQuality: 85,
      );

      if (image != null) {
        final bytes = await image.readAsBytes();
        final base64Image = base64Encode(bytes);
        return {
          'success': true,
          'image': 'data:image/jpeg;base64,$base64Image',
          'path': image.path,
        };
      }
      return {'success': false, 'error': 'Aucune image sélectionnée'};
    } catch (e) {
      return {'success': false, 'error': e.toString()};
    }
  }

  // Gérer la demande de localisation
  Future<Map<String, dynamic>> _handleLocationRequest() async {
    try {
      bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
      if (!serviceEnabled) {
        return {
          'success': false,
          'error': 'Les services de localisation sont désactivés sur cet appareil.',
        };
      }

      if (!mounted) {
        return {'success': false, 'error': 'Application non prête.'};
      }
      final permission =
          await NativePermissionService.requestLocationWithRationale(context);
      if (permission == LocationPermission.denied) {
        return {
          'success': false,
          'error': 'Autorisation de localisation refusée.',
        };
      }
      if (permission == LocationPermission.deniedForever) {
        return {
          'success': false,
          'error':
              'Autorisation de localisation refusée définitivement. Activez-la dans les paramètres de l\'application.',
        };
      }

      Position position = await Geolocator.getCurrentPosition(
        desiredAccuracy: LocationAccuracy.high,
      );

      return {
        'success': true,
        'latitude': position.latitude,
        'longitude': position.longitude,
        'accuracy': position.accuracy,
        'altitude': position.altitude,
        'speed': position.speed,
        'timestamp': position.timestamp.toIso8601String(),
      };
    } catch (e) {
      return {'success': false, 'error': e.toString()};
    }
  }

  // Sauvegarder des données localement
  Future<Map<String, dynamic>> _handleSaveData(dynamic data) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      if (data is Map) {
        for (var entry in data.entries) {
          final key = entry.key.toString();
          final value = entry.value;
          if (value is String) {
            await prefs.setString(key, value);
          } else if (value is int) {
            await prefs.setInt(key, value);
          } else if (value is double) {
            await prefs.setDouble(key, value);
          } else if (value is bool) {
            await prefs.setBool(key, value);
          } else {
            await prefs.setString(key, jsonEncode(value));
          }
        }
        return {'success': true};
      }
      return {'success': false, 'error': 'Invalid data format'};
    } catch (e) {
      return {'success': false, 'error': e.toString()};
    }
  }

  // Récupérer des données localement
  Future<Map<String, dynamic>> _handleGetData(String key) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final value = prefs.get(key);
      if (value != null) {
        return {'success': true, 'data': value};
      }
      return {'success': false, 'error': 'Key not found'};
    } catch (e) {
      return {'success': false, 'error': e.toString()};
    }
  }

  // Afficher une notification
  Future<Map<String, dynamic>> _handleShowNotification(
    String title,
    String body,
  ) async {
    try {
      // Ici vous pouvez intégrer flutter_local_notifications
      // Pour l'instant, on retourne un succès
      // Vous pouvez aussi afficher un snackbar ou dialog
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('$title: $body'),
            duration: const Duration(seconds: 3),
          ),
        );
      }
      return {'success': true};
    } catch (e) {
      return {'success': false, 'error': e.toString()};
    }
  }

  // Injecter le code JavaScript pour la communication
  Future<void> _injectJavaScript() async {
    const jsCode = '''
      (function() {
        window.__COLOBANES_NATIVE_APP = true;
        window.ColobanesNative = {
          // Demander l'accès à la caméra
          requestCamera: function() {
            return new Promise((resolve, reject) => {
              window.flutter_inappwebview.callHandler('requestCamera')
                .then(result => {
                  if (result.success) {
                    resolve(result);
                  } else {
                    reject(new Error(result.error || 'Camera request failed'));
                  }
                })
                .catch(error => reject(error));
            });
          },
          
          // Demander la localisation
          requestLocation: function() {
            return new Promise((resolve, reject) => {
              window.flutter_inappwebview.callHandler('requestLocation')
                .then(result => {
                  if (result.success) {
                    resolve(result);
                  } else {
                    reject(new Error(result.error || 'Location request failed'));
                  }
                })
                .catch(error => reject(error));
            });
          },
          
          // Sauvegarder des données
          saveData: function(data) {
            return new Promise((resolve, reject) => {
              window.flutter_inappwebview.callHandler('saveData', data)
                .then(result => {
                  if (result.success) {
                    resolve(result);
                  } else {
                    reject(new Error(result.error || 'Save failed'));
                  }
                })
                .catch(error => reject(error));
            });
          },
          
          // Récupérer des données
          getData: function(key) {
            return new Promise((resolve, reject) => {
              window.flutter_inappwebview.callHandler('getData', key)
                .then(result => {
                  if (result.success) {
                    resolve(result.data);
                  } else {
                    reject(new Error(result.error || 'Get failed'));
                  }
                })
                .catch(error => reject(error));
            });
          },
          
          // Afficher une notification
          showNotification: function(title, body) {
            return new Promise((resolve, reject) => {
              window.flutter_inappwebview.callHandler('showNotification', title, body)
                .then(result => {
                  if (result.success) {
                    resolve(result);
                  } else {
                    reject(new Error(result.error || 'Notification failed'));
                  }
                })
                .catch(error => reject(error));
            });
          },

          // Connexion Google native (contourne le blocage WebView 403)
          signInWithGoogle: function() {
            return new Promise((resolve, reject) => {
              window.flutter_inappwebview.callHandler('signInWithGoogle')
                .then(result => {
                  if (result && result.success && result.idToken) {
                    resolve(result);
                  } else {
                    reject(new Error((result && result.error) ? result.error : 'Connexion Google impossible'));
                  }
                })
                .catch(error => reject(error));
            });
          },

          signInWithApple: function() {
            return new Promise((resolve, reject) => {
              window.flutter_inappwebview.callHandler('signInWithApple')
                .then(result => {
                  if (result && result.success && result.idToken) {
                    resolve(result);
                  } else {
                    reject(new Error((result && result.error) ? result.error : 'Connexion Apple impossible'));
                  }
                })
                .catch(error => reject(error));
            });
          },

          // Partage natif : ouvre la feuille de partage du système (Android / iOS)
          shareContent: function(payload) {
            return new Promise((resolve, reject) => {
              window.flutter_inappwebview.callHandler('shareContent', payload || {})
                .then(result => {
                  if (result && result.success) {
                    resolve(result);
                  } else {
                    reject(new Error((result && result.error) ? result.error : 'Share failed'));
                  }
                })
                .catch(error => reject(error));
            });
          },

          openExternalUrl: function(url) {
            return new Promise((resolve, reject) => {
              window.flutter_inappwebview.callHandler('openExternalUrl', url || '')
                .then(result => {
                  if (result && result.success) {
                    resolve(result);
                  } else {
                    reject(new Error((result && result.error) ? result.error : 'Open url failed'));
                  }
                })
                .catch(error => reject(error));
            });
          },

          isNativeApp: function() {
            return true;
          }
        };
        window.AriaNative = window.ColobanesNative;

        function colobanesNativeSocialSignIn(button, provider, handlerName) {
          if (!window.flutter_inappwebview) return;

          var wrap = button.closest('.social-auth');
          var msgEl = null;
          var accountType = button.getAttribute('data-social-auth-type')
            || button.getAttribute('data-google-auth-type') || 'auto';
          var redirect = button.getAttribute('data-social-auth-redirect')
            || button.getAttribute('data-google-auth-redirect') || '';
          var originalHtml = button.innerHTML;

          function setMsg(text, isError) {
            if (!wrap) return;
            if (!text) {
              if (msgEl) { msgEl.remove(); msgEl = null; }
              return;
            }
            if (!msgEl) {
              msgEl = document.createElement('p');
              msgEl.className = 'social-auth-message';
              msgEl.setAttribute('aria-live', 'polite');
              var buttons = wrap.querySelector('.social-auth__buttons');
              if (buttons) {
                buttons.insertAdjacentElement('afterend', msgEl);
              } else {
                wrap.appendChild(msgEl);
              }
            }
            msgEl.textContent = text;
            msgEl.classList.toggle('is-error', !!isError);
          }

          if (wrap) {
            wrap.querySelectorAll('.google-auth-btn, .apple-auth-btn').forEach(function(btn) {
              btn.disabled = true;
            });
          }
          setMsg('', false);

          window.flutter_inappwebview.callHandler(handlerName)
            .then(function(result) {
              if (!result || !result.success || !result.idToken) {
                throw new Error((result && result.error) ? result.error : 'Connexion impossible.');
              }
              return fetch('/auth-firebase-callback.php', {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'Accept': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                  idToken: result.idToken,
                  accountType: accountType,
                  redirect: redirect,
                  provider: provider
                })
              });
            })
            .then(function(response) {
              return response.json().catch(function() {
                throw new Error('Réponse serveur invalide.');
              });
            })
            .then(function(data) {
              if (!data || !data.success || !data.redirect) {
                throw new Error((data && data.message) ? data.message : 'Connexion refusée.');
              }
              window.location.href = data.redirect;
            })
            .catch(function(error) {
              setMsg(error && error.message ? error.message : 'Connexion annulée ou impossible.', true);
              if (wrap) {
                wrap.querySelectorAll('.google-auth-btn, .apple-auth-btn').forEach(function(btn) {
                  btn.disabled = false;
                });
              }
              button.innerHTML = originalHtml;
            });
        }

        // Intercepter Google / Apple AVANT le JS du site (évite popup bloqué en WebView)
        if (document.documentElement.getAttribute('data-colobanes-social-hook') !== '1') {
          document.documentElement.setAttribute('data-colobanes-social-hook', '1');
          document.addEventListener('click', function(event) {
            var googleBtn = event.target.closest('.google-auth-btn');
            var appleBtn = event.target.closest('.apple-auth-btn');
            var btn = googleBtn || appleBtn;
            if (!btn || !window.flutter_inappwebview) return;

            event.preventDefault();
            event.stopImmediatePropagation();

            if (googleBtn) {
              colobanesNativeSocialSignIn(googleBtn, 'google', 'signInWithGoogle');
            } else {
              colobanesNativeSocialSignIn(appleBtn, 'apple', 'signInWithApple');
            }
          }, true);
          console.log('ColobanesNative social auth hooks active');
        }
        
        console.log('ColobanesNative API initialized');
        try {
          window.dispatchEvent(new Event('colobanesNativeReady'));
        } catch (e) {}
      })();
    ''';

    await webViewController?.evaluateJavascript(source: jsCode);
  }

  /// Allège le rendu CSS/JS côté page (blur, AOS, carrousels) pour un scroll fluide.
  Future<void> _injectWebViewPerformanceOptimizations() async {
    const perfJs = '''
(function(){
  document.documentElement.classList.add('is-native-app');
  if (window.ColobanesPerf && typeof window.ColobanesPerf.refresh === 'function') {
    window.ColobanesPerf.refresh();
    return;
  }
  if (typeof AOS !== 'undefined' && AOS.init) {
    try { AOS.init({ disable: true }); } catch (e) {}
  }
  document.documentElement.classList.remove('aos-not-ready', 'sk-shimmer-pending');
  document.documentElement.classList.add('sk-shimmer-done');
})();
''';
    await webViewController?.evaluateJavascript(source: perfJs);
  }

  // Enregistrer le token FCM dans la WebView (utilise la session authentifiée)
  Future<void> _registerFCMTokenInWebView() async {
    try {
      final fcmToken = FCMService.getToken();
      if (fcmToken != null) {
        // Injecter le script pour enregistrer le token via la WebView
        final script = FCMService.getTokenRegistrationScript(fcmToken);
        await webViewController?.evaluateJavascript(source: script);
        print('📤 Token FCM envoyé via WebView');
      }
    } catch (e) {
      print('❌ Erreur lors de l\'enregistrement du token FCM: $e');
    }
  }

  // Gérer le bouton retour Android
  Future<bool> _handleBackButton() async {
    if (webViewController != null) {
      // Vérifier si la WebView peut revenir en arrière
      final canGoBack = await webViewController!.canGoBack();
      if (canGoBack) {
        // Revenir à la page précédente dans l'historique
        await webViewController!.goBack();
        return false; // Empêcher la fermeture de l'app
      }
    }
    // Si on ne peut pas revenir en arrière, fermer l'application
    return true; // Permettre la fermeture de l'app
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvoked: (didPop) async {
        if (!didPop) {
          final shouldPop = await _handleBackButton();
          if (shouldPop && context.mounted) {
            SystemNavigator.pop();
          }
        }
      },
      child: Scaffold(
        backgroundColor: Colors.white,
        resizeToAvoidBottomInset: false,
        body: Stack(
          children: [
            // WebView masquée pendant le chargement initial (évite flash blanc sous le logo).
            if (_marketplaceEntryUrlReady)
            ValueListenableBuilder<bool>(
              valueListenable: _isInitialLoadNotifier,
              builder: (context, isInitialLoad, child) {
                return Opacity(
                  opacity: isInitialLoad ? 0 : 1,
                  child: child,
                );
              },
              child: RepaintBoundary(
                child: SafeArea(
                  child: InAppWebView(
                  initialUrlRequest: URLRequest(
                    url: WebUri(_marketplaceEntryUrl),
                  ),
                  initialUserScripts: UnmodifiableListView<UserScript>([
                    UserScript(
                      source: '''
(function(){
  window.__COLOBANES_NATIVE_APP = true;
  document.documentElement.classList.add('is-native-app');
})();
''',
                      injectionTime: UserScriptInjectionTime.AT_DOCUMENT_START,
                    ),
                  ]),
                  initialSettings: InAppWebViewSettings(
                    applicationNameForUserAgent: 'ColobanesApp',
                    javaScriptEnabled: true,
                    domStorageEnabled: true,
                    databaseEnabled: true,
                    useShouldOverrideUrlLoading: true,
                    mediaPlaybackRequiresUserGesture: false,
                    allowsInlineMediaPlayback: true,
                    iframeAllow: "camera; geolocation",
                    iframeAllowFullscreen: true,
                    geolocationEnabled: true,
                    // ─── Rendu WebView Android ───────────────────────────────
                    // Hybrid Composition (true) = défaut recommandé en
                    // flutter_inappwebview v6 sur Android 10+ : scroll fluide 60fps.
                    // Virtual Display (false) = mode hérité, provoque le jank / les
                    // saccades au scroll (~12fps). On garde donc true.
                    useHybridComposition: true,
                    // ────────────────────────────────────────────────────────
                    hardwareAcceleration: true,
                    useOnLoadResource: false,
                    useOnDownloadStart: false,
                    useShouldInterceptRequest: false,
                    thirdPartyCookiesEnabled: true,
                    cacheEnabled: true,
                    clearCache: false,
                    transparentBackground: false,
                    supportZoom: true,
                    builtInZoomControls: false,
                    displayZoomControls: false,
                    verticalScrollBarEnabled: false,
                    horizontalScrollBarEnabled: false,
                    disableVerticalScroll: false,
                    disableHorizontalScroll: false,
                    // Désactive la page d'erreur par défaut (page blanche propre)
                    disableDefaultErrorPage: true,
                    // Latence tactile réduite
                    overScrollMode: OverScrollMode.NEVER,
                  ),
                  onWebViewCreated: (controller) {
                    webViewController = controller;
                    _setupJavaScriptHandlers();
                  },
                  onLoadStart: (controller, url) {
                    if (!_isInitialLoadNotifier.value) {
                      _isPageLoadingNotifier.value = true;
                      _loaderProgressNotifier.value = 0.0;
                    }
                  },
                  onLoadStop: (controller, url) async {
                    if (url != null) {
                      _currentUrl = url.toString();
                      unawaited(_saveCurrentUrl());
                    }
                    _finishInitialLoad();
                    _isPageLoadingNotifier.value = false;
                    _loaderProgressNotifier.value = 1.0;
                    unawaited(_postLoadSetup());
                  },
                  onProgressChanged: (controller, progress) {
                    final newProgress = progress / 100;
                    if ((newProgress - _rawProgress).abs() > 0.01) {
                      _rawProgress = newProgress;
                      final best = _rawProgress > _simProgress ? _rawProgress : _simProgress;
                      _loaderProgressNotifier.value = best;
                      if (!_isInitialLoadNotifier.value) {
                        _isPageLoadingNotifier.value = progress < 100;
                      }
                    }
                  },
                  onPermissionRequest: (controller, request) async {
                    final granted = <PermissionResourceType>[];
                    for (final resource in request.resources) {
                      final name = resource.toString().toLowerCase();
                      if (name.contains('camera')) {
                        if (!mounted) break;
                        final cameraOk =
                            await NativePermissionService.requestCameraWithRationale(
                          context,
                        );
                        if (cameraOk) {
                          granted.add(resource);
                        }
                        continue;
                      }
                      if (name.contains('geolocation') || name.contains('location')) {
                        if (!mounted) break;
                        final geoPerm =
                            await NativePermissionService.requestLocationWithRationale(
                          context,
                        );
                        if (geoPerm == LocationPermission.always ||
                            geoPerm == LocationPermission.whileInUse) {
                          granted.add(resource);
                        }
                      }
                    }
                    if (granted.isEmpty) {
                      return PermissionResponse(
                        resources: request.resources,
                        action: PermissionResponseAction.DENY,
                      );
                    }
                    return PermissionResponse(
                      resources: granted,
                      action: PermissionResponseAction.GRANT,
                    );
                  },
                  onGeolocationPermissionsShowPrompt:
                      (controller, origin) async {
                    if (!mounted) {
                      return GeolocationPermissionShowPromptResponse(
                        origin: origin,
                        allow: false,
                        retain: false,
                      );
                    }
                    final geoPerm =
                        await NativePermissionService.requestLocationWithRationale(
                      context,
                    );
                    final allowed = geoPerm == LocationPermission.always ||
                        geoPerm == LocationPermission.whileInUse;
                    return GeolocationPermissionShowPromptResponse(
                      origin: origin,
                      allow: allowed,
                      retain: allowed,
                    );
                  },
                  onReceivedError: (controller, request, error) {
                    print('WebView Error: ${error.description}');
                  },
                  shouldOverrideUrlLoading: (controller, navigationAction) async {
                    final uri = navigationAction.request.url;
                    if (uri != null) {
                      final scheme = uri.scheme.toLowerCase();
                      // Schémas d'apps externes : on les ouvre hors WebView pour
                      // éviter la page blanche (la WebView ne sait pas les charger).
                      const externalSchemes = {
                        'whatsapp', 'tg', 'fb', 'fb-messenger', 'mailto',
                        'tel', 'sms', 'intent', 'market', 'googlegmail',
                        'twitter', 'instagram', 'snapchat', 'viber',
                        'geo', 'maps', 'comgooglemaps'
                      };
                      if (externalSchemes.contains(scheme)) {
                        try {
                          if (await canLaunchUrl(uri)) {
                            await launchUrl(uri,
                                mode: LaunchMode.externalApplication);
                          }
                        } catch (_) {}
                        return NavigationActionPolicy.CANCEL;
                      }
                      if (scheme == 'https' || scheme == 'http') {
                        final host = uri.host.toLowerCase();
                        final path = uri.path.toLowerCase();
                        final isGoogleMaps = host.contains('google.com')
                            && (path.contains('maps') || uri.query.contains('destination'));
                        if (isGoogleMaps || host == 'maps.google.com') {
                          try {
                            if (await canLaunchUrl(uri)) {
                              await launchUrl(uri,
                                  mode: LaunchMode.externalApplication);
                            }
                          } catch (_) {}
                          return NavigationActionPolicy.CANCEL;
                        }
                      }
                    }
                    return NavigationActionPolicy.ALLOW;
                  },
                ),
                ),
              ),
            ),

            // Loader initial — logo + anneau (identique au splash natif)
            if (!_marketplaceEntryUrlReady)
              const _MarketplaceLoader()
            else
            ValueListenableBuilder<bool>(
              valueListenable: _isInitialLoadNotifier,
              builder: (context, isInitialLoad, _) {
                if (!isInitialLoad) return const SizedBox.shrink();
                return const _MarketplaceLoader();
              },
            ),

            // Barre de progression pour les navigations suivantes
            ValueListenableBuilder<bool>(
              valueListenable: _isInitialLoadNotifier,
              builder: (context, isInitialLoad, _) {
                if (isInitialLoad) return const SizedBox.shrink();
                return ValueListenableBuilder<bool>(
                  valueListenable: _isPageLoadingNotifier,
                  builder: (context, isLoading, _) {
                    if (!isLoading) return const SizedBox.shrink();
                    return ValueListenableBuilder<double>(
                      valueListenable: _loaderProgressNotifier,
                      builder: (context, progress, _) {
                        return _TopProgressBar(progress: progress);
                      },
                    );
                  },
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}

// Écran de chargement initial — même logo que le splash natif + anneau de progression
class _MarketplaceLoader extends StatefulWidget {
  const _MarketplaceLoader();

  @override
  State<_MarketplaceLoader> createState() => _MarketplaceLoaderState();
}

class _MarketplaceLoaderState extends State<_MarketplaceLoader> {
  static bool _nativeSplashRemoved = false;
  bool _showLoadingRing = false;

  @override
  void initState() {
    super.initState();
    // 1) Splash natif = logo seul sur fond blanc
    // 2) Puis Flutter reprend : même logo + anneau de chargement
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!_nativeSplashRemoved) {
        _nativeSplashRemoved = true;
        FlutterNativeSplash.remove();
      }
      if (mounted) {
        setState(() => _showLoadingRing = true);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final size = MediaQuery.of(context).size;
    final ringSize = (size.width * 0.78).clamp(260.0, 340.0);
    final logoWidth = ringSize * 0.68;

    return ColoredBox(
      color: const Color(0xFFFFFFFF),
      child: SizedBox.expand(
        child: Center(
          child: _MarketplaceLoaderContent(
            ringSize: ringSize,
            logoWidth: logoWidth,
            showLoadingRing: _showLoadingRing,
          ),
        ),
      ),
    );
  }
}

class _MarketplaceLoaderContent extends StatelessWidget {
  const _MarketplaceLoaderContent({
    required this.ringSize,
    required this.logoWidth,
    required this.showLoadingRing,
  });

  final double ringSize;
  final double logoWidth;
  final bool showLoadingRing;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: ringSize,
      height: ringSize,
      child: Stack(
        alignment: Alignment.center,
        children: [
          AnimatedOpacity(
            opacity: showLoadingRing ? 1 : 0,
            duration: const Duration(milliseconds: 280),
            curve: Curves.easeOut,
            child: SizedBox(
              width: ringSize,
              height: ringSize,
              child: const CircularProgressIndicator(
                strokeWidth: 4.5,
                strokeCap: StrokeCap.round,
                backgroundColor: Color(0x1A3564A6),
                valueColor: AlwaysStoppedAnimation<Color>(kOrangePromo),
              ),
            ),
          ),
          Image.asset(
            kSplashLogoAsset,
            width: logoWidth,
            fit: BoxFit.contain,
            filterQuality: FilterQuality.high,
            errorBuilder: (context, error, stackTrace) {
              return Image.asset(
                'assets/images/logo_market.png',
                width: logoWidth,
                fit: BoxFit.contain,
                filterQuality: FilterQuality.high,
              );
            },
          ),
        ],
      ),
    );
  }
}

// Barre de progression discrète en haut pour les navigations suivantes
class _TopProgressBar extends StatelessWidget {
  final double progress;

  const _TopProgressBar({required this.progress});

  @override
  Widget build(BuildContext context) {
    final p = progress.clamp(0.0, 1.0);

    return Positioned(
      top: 0,
      left: 0,
      right: 0,
      child: TweenAnimationBuilder<double>(
        tween: Tween<double>(end: p),
        duration: const Duration(milliseconds: 280),
        curve: Curves.easeOutCubic,
        builder: (context, animatedP, _) {
          return SizedBox(
            height: 3,
            child: Align(
              alignment: Alignment.centerLeft,
              child: FractionallySizedBox(
                widthFactor: animatedP.clamp(0.0, 1.0),
                child: Container(
                  decoration: BoxDecoration(
                    color: kOrangePromo,
                    boxShadow: [
                      BoxShadow(
                        color: kOrangePromo.withValues(alpha: 0.35),
                        blurRadius: 4,
                        offset: const Offset(0, 1),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}
