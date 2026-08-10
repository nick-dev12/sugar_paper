import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:geolocator/geolocator.dart' as geo;
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'package:socket_io_client/socket_io_client.dart' as io;

/// Callback position → WebView (mise à jour carte livreur).
typedef LivreurPositionUiCallback = void Function(Map<String, dynamic> position);

/// Suivi GPS livreur en arrière-plan (session admin WebView + API PHP existante).
class LivreurTrackingService {
  LivreurTrackingService._();

  static final LivreurTrackingService instance = LivreurTrackingService._();

  static const _prefsKey = 'livreur_native_tracking_v1';

  StreamSubscription<geo.Position>? _positionSub;
  io.Socket? _socket;
  Timer? _statusTimer;
  Timer? _heartbeatTimer;
  LivreurTrackingConfig? _active;
  DateTime? _lastHttpPostAt;
  DateTime? _lastUiPushAt;
  bool _starting = false;
  LivreurPositionUiCallback? onPositionForUi;
  Future<String?> Function()? _getCookieHeader;

  bool get isActive => _active != null;

  LivreurTrackingConfig? get activeConfig => _active;

  Future<Map<String, dynamic>> start({
    required Map<String, dynamic> rawConfig,
    required Future<String?> Function() getCookieHeader,
    required Future<bool> Function() requestPermissions,
    LivreurPositionUiCallback? onPosition,
  }) async {
    if (_starting) {
      return {'success': false, 'error': 'Démarrage déjà en cours'};
    }
    _starting = true;
    try {
      final config = LivreurTrackingConfig.fromMap(rawConfig);
      if (!config.isValid) {
        return {'success': false, 'error': 'Configuration livraison invalide'};
      }

      final sameDelivery =
          _active != null && _active!.deliveryKey == config.deliveryKey;
      if (_active != null && !sameDelivery) {
        await stop(getCookieHeader: getCookieHeader, callApi: false);
      } else if (_active != null && sameDelivery) {
        if (onPosition != null) {
          onPositionForUi = onPosition;
        }
        _getCookieHeader = getCookieHeader;
        return {'success': true, 'already_active': true};
      }

      if (!await geo.Geolocator.isLocationServiceEnabled()) {
        return {
          'success': false,
          'error': 'Activez le GPS dans les paramètres de l\'appareil.',
        };
      }

      final granted = await requestPermissions();
      if (!granted) {
        return {
          'success': false,
          'error': 'Autorisation de localisation refusée.',
        };
      }

      if (onPosition != null) {
        onPositionForUi = onPosition;
      }
      _getCookieHeader = getCookieHeader;
      _active = config;
      await _persistSession(config);

      /* Socket + polling en parallèle — ne pas bloquer le retour à la WebView */
      unawaited(_connectSocket(config, getCookieHeader));
      await _startPositionStream(config, getCookieHeader);
      _startStatusPolling(config, getCookieHeader);
      _startHeartbeat(config, getCookieHeader);

      /* Fix immédiat si une position récente existe */
      try {
        final last = await geo.Geolocator.getLastKnownPosition();
        if (last != null) {
          await _onPosition(config, getCookieHeader, last, forceHttp: true);
        }
      } catch (_) {}

      /* Haute précision en arrière-plan (le stream prend le relais) */
      unawaited(
        geo.Geolocator.getCurrentPosition(
          desiredAccuracy: geo.LocationAccuracy.high,
          timeLimit: const Duration(seconds: 6),
        ).then(
          (first) => _onPosition(config, getCookieHeader, first, forceHttp: true),
        ).catchError((_) {}),
      );

      return {'success': true};
    } catch (e) {
      await _teardown(clearSession: true);
      return {'success': false, 'error': e.toString()};
    } finally {
      _starting = false;
    }
  }

  Future<Map<String, dynamic>> stop({
    required Future<String?> Function() getCookieHeader,
    bool callApi = true,
  }) async {
    final config = _active;
    await _teardown(clearSession: true);
    if (callApi && config != null) {
      await _callWebApi(
        config,
        getCookieHeader,
        {'action': 'stop'},
      );
    }
    return {'success': true};
  }

  Future<Map<String, dynamic>> status() async {
    return {
      'success': true,
      'active': isActive,
      'delivery_key': _active?.deliveryKey,
      'bl_id': _active?.blId ?? 0,
      'commande_id': _active?.commandeId ?? 0,
    };
  }

  Future<void> restoreIfNeeded({
    required Future<String?> Function() getCookieHeader,
    required Future<bool> Function() requestPermissions,
    LivreurPositionUiCallback? onPosition,
  }) async {
    if (isActive || _starting) {
      if (onPosition != null) {
        onPositionForUi = onPosition;
      }
      return;
    }
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_prefsKey);
    if (raw == null || raw.isEmpty) {
      return;
    }
    Map<String, dynamic> map;
    try {
      map = jsonDecode(raw) as Map<String, dynamic>;
    } catch (_) {
      await prefs.remove(_prefsKey);
      return;
    }

    final config = LivreurTrackingConfig.fromMap(map);
    if (!config.isValid) {
      await prefs.remove(_prefsKey);
      return;
    }

    final stillActive = await _fetchTrackingActive(config, getCookieHeader);
    if (!stillActive) {
      await prefs.remove(_prefsKey);
      return;
    }

    await start(
      rawConfig: map,
      getCookieHeader: getCookieHeader,
      requestPermissions: requestPermissions,
      onPosition: onPosition,
    );
  }

  Future<void> _persistSession(LivreurTrackingConfig config) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_prefsKey, jsonEncode(config.toMap()));
  }

  Future<void> _teardown({required bool clearSession}) async {
    _statusTimer?.cancel();
    _statusTimer = null;
    _heartbeatTimer?.cancel();
    _heartbeatTimer = null;
    await _positionSub?.cancel();
    _positionSub = null;
    _socket?.dispose();
    _socket = null;
    _active = null;
    _lastHttpPostAt = null;
    _lastUiPushAt = null;
    _getCookieHeader = null;
    if (clearSession) {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(_prefsKey);
      onPositionForUi = null;
    }
  }

  Future<void> _startPositionStream(
    LivreurTrackingConfig config,
    Future<String?> Function() getCookieHeader,
  ) async {
    await _positionSub?.cancel();
    _positionSub = geo.Geolocator.getPositionStream(
      locationSettings: _locationSettings(),
    ).listen(
      (pos) => _onPosition(config, getCookieHeader, pos),
      onError: (Object err) {
        debugPrint('[LivreurTracking] GPS stream error: $err');
      },
      cancelOnError: false,
    );
  }

  geo.LocationSettings _locationSettings() {
    if (!kIsWeb && Platform.isAndroid) {
      return geo.AndroidSettings(
        accuracy: geo.LocationAccuracy.bestForNavigation,
        distanceFilter: 3,
        intervalDuration: const Duration(seconds: 3),
        foregroundNotificationConfig: const geo.ForegroundNotificationConfig(
          notificationTitle: 'Livraison en cours',
          notificationText:
              'Sugar Paper transmet votre position au client en direct.',
          notificationIcon: geo.AndroidResource(
            name: 'ic_delivery_location',
            defType: 'drawable',
          ),
          enableWakeLock: true,
          enableWifiLock: true,
          setOngoing: true,
        ),
      );
    }
    if (!kIsWeb && Platform.isIOS) {
      return geo.AppleSettings(
        accuracy: geo.LocationAccuracy.bestForNavigation,
        activityType: geo.ActivityType.automotiveNavigation,
        distanceFilter: 3,
        allowBackgroundLocationUpdates: true,
        showBackgroundLocationIndicator: true,
        pauseLocationUpdatesAutomatically: false,
      );
    }
    return const geo.LocationSettings(
      accuracy: geo.LocationAccuracy.high,
      distanceFilter: 3,
    );
  }

  Future<void> _onPosition(
    LivreurTrackingConfig config,
    Future<String?> Function() getCookieHeader,
    geo.Position pos, {
    bool forceHttp = false,
  }) async {
    final payload = <String, dynamic>{
      'latitude': pos.latitude,
      'longitude': pos.longitude,
      'accuracy': pos.accuracy,
      'speed': pos.speed,
      'heading': pos.heading,
      'bl_id': config.blId,
      'commande_id': config.commandeId,
      'ts': DateTime.now().millisecondsSinceEpoch,
    };

    _pushToUi(payload);
    _emitSocket(config, pos);

    final now = DateTime.now();
    if (!forceHttp &&
        _lastHttpPostAt != null &&
        now.difference(_lastHttpPostAt!) < const Duration(seconds: 3)) {
      return;
    }
    _lastHttpPostAt = now;
    await _callWebApi(
      config,
      getCookieHeader,
      {
        'action': 'position',
        'latitude': pos.latitude,
        'longitude': pos.longitude,
        'accuracy': pos.accuracy,
      },
    );
  }

  void _pushToUi(Map<String, dynamic> payload) {
    final cb = onPositionForUi;
    if (cb == null) {
      return;
    }
    final now = DateTime.now();
    // Limiter un peu le flood JS, mais rester fluide (~3 Hz max)
    if (_lastUiPushAt != null &&
        now.difference(_lastUiPushAt!) < const Duration(milliseconds: 350)) {
      return;
    }
    _lastUiPushAt = now;
    try {
      cb(payload);
    } catch (e) {
      debugPrint('[LivreurTracking] UI push error: $e');
    }
  }

  void _emitSocket(LivreurTrackingConfig config, geo.Position pos) {
    final socket = _socket;
    if (socket == null || !socket.connected) {
      // Tenter une reconnexion paresseuse
      final getter = _getCookieHeader;
      if (getter != null && config.realtimeConfigured) {
        unawaited(_connectSocket(config, getter));
      }
      return;
    }
    final payload = <String, dynamic>{
      'latitude': pos.latitude,
      'longitude': pos.longitude,
      'accuracy': pos.accuracy,
      'speed': pos.speed,
      'heading': pos.heading,
    };
    if (config.blId > 0) {
      payload['bl_id'] = config.blId;
    } else if (config.commandeId > 0) {
      payload['commande_id'] = config.commandeId;
    }
    socket.emit('livreur:position', payload);
  }

  Future<void> _connectSocket(
    LivreurTrackingConfig config,
    Future<String?> Function() getCookieHeader,
  ) async {
    if (!config.realtimeConfigured) {
      return;
    }

    String? token = config.embeddedWatchToken.trim().isEmpty
        ? null
        : config.embeddedWatchToken.trim();
    if (token == null || token.isEmpty) {
      if (config.watchTokenUrl.isEmpty) {
        return;
      }
      token = await _fetchWatchToken(config, getCookieHeader);
    }
    if (token == null || token.isEmpty) {
      debugPrint('[LivreurTracking] watch token unavailable');
      return;
    }

    // Persister le token pour les reprises
    if (config.embeddedWatchToken != token) {
      final updated = config.copyWith(embeddedWatchToken: token);
      _active = updated;
      await _persistSession(updated);
    }

    _socket?.dispose();
    final origin =
        config.socketUrl.isNotEmpty ? config.socketUrl : config.siteOrigin;
    final socket = io.io(
      origin,
      io.OptionBuilder()
          .setTransports(['websocket', 'polling'])
          .disableAutoConnect()
          .setPath(config.socketPath)
          .enableReconnection()
          .setReconnectionAttempts(999999)
          .setReconnectionDelay(1500)
          .setAuth({
            'role': 'watch',
            'token': token,
            'commande_id': config.commandeId,
            'bl_id': config.blId,
          })
          .build(),
    );

    final completer = Completer<void>();
    Timer? timeout;

    socket.onConnect((_) {
      debugPrint('[LivreurTracking] socket connected');
      timeout?.cancel();
      if (!completer.isCompleted) {
        completer.complete();
      }
    });

    socket.onConnectError((err) {
      debugPrint('[LivreurTracking] socket connect_error: $err');
    });

    socket.onDisconnect((_) {
      debugPrint('[LivreurTracking] socket disconnected');
    });

    _socket = socket;
    socket.connect();

    timeout = Timer(const Duration(seconds: 5), () {
      if (!completer.isCompleted) {
        completer.complete();
      }
    });

    await completer.future;
  }

  Future<String?> _fetchWatchToken(
    LivreurTrackingConfig config,
    Future<String?> Function() getCookieHeader,
  ) async {
    try {
      final cookie = await getCookieHeader();
      final res = await http.get(
        Uri.parse(config.absUrl(config.watchTokenUrl)),
        headers: {
          if (cookie != null && cookie.isNotEmpty) 'Cookie': cookie,
          'Accept': 'application/json',
        },
      );
      if (res.statusCode != 200) {
        debugPrint('[LivreurTracking] watch-token HTTP ${res.statusCode}');
        return null;
      }
      final data = jsonDecode(res.body);
      if (data is Map && data['success'] == true) {
        return (data['watch_token'] ?? '').toString();
      }
    } catch (e) {
      debugPrint('[LivreurTracking] watch-token error: $e');
    }
    return null;
  }

  Future<bool> _fetchTrackingActive(
    LivreurTrackingConfig config,
    Future<String?> Function() getCookieHeader,
  ) async {
    if (config.statusUrl.isEmpty) {
      return true;
    }
    try {
      final cookie = await getCookieHeader();
      final res = await http.get(
        Uri.parse(config.absUrl(config.statusUrl)),
        headers: {
          if (cookie != null && cookie.isNotEmpty) 'Cookie': cookie,
          'Accept': 'application/json',
        },
      );
      if (res.statusCode != 200) {
        return false;
      }
      final data = jsonDecode(res.body);
      if (data is Map && data['success'] == true) {
        return data['tracking_active'] == true || data['tracking_active'] == 1;
      }
    } catch (_) {}
    return false;
  }

  void _startStatusPolling(
    LivreurTrackingConfig config,
    Future<String?> Function() getCookieHeader,
  ) {
    _statusTimer?.cancel();
    _statusTimer = Timer.periodic(const Duration(seconds: 20), (_) async {
      if (_active == null) {
        return;
      }
      final stillActive = await _fetchTrackingActive(config, getCookieHeader);
      if (!stillActive) {
        await stop(getCookieHeader: getCookieHeader, callApi: false);
      }
    });
  }

  /// Relance socket + refresh position si le flux s'essouffle en arrière-plan.
  void _startHeartbeat(
    LivreurTrackingConfig config,
    Future<String?> Function() getCookieHeader,
  ) {
    _heartbeatTimer?.cancel();
    _heartbeatTimer = Timer.periodic(const Duration(seconds: 25), (_) async {
      if (_active == null) {
        return;
      }
      final socket = _socket;
      if (socket == null || !socket.connected) {
        await _connectSocket(config, getCookieHeader);
      }
      try {
        final pos = await geo.Geolocator.getCurrentPosition(
          desiredAccuracy: geo.LocationAccuracy.high,
          timeLimit: const Duration(seconds: 8),
        );
        await _onPosition(config, getCookieHeader, pos, forceHttp: true);
      } catch (_) {
        /* silencieux */
      }
    });
  }

  Future<void> _callWebApi(
    LivreurTrackingConfig config,
    Future<String?> Function() getCookieHeader,
    Map<String, dynamic> body,
  ) async {
    try {
      final cookie = await getCookieHeader();
      final payload = <String, dynamic>{...body};
      if (config.blId > 0) {
        payload['bl_id'] = config.blId;
      } else if (config.commandeId > 0) {
        payload['commande_id'] = config.commandeId;
      }

      final res = await http.post(
        Uri.parse(config.absUrl(config.webApiUrl)),
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          if (cookie != null && cookie.isNotEmpty) 'Cookie': cookie,
        },
        body: jsonEncode(payload),
      );
      if (res.statusCode >= 400) {
        debugPrint('[LivreurTracking] API ${res.statusCode}: ${res.body}');
      }
    } catch (e) {
      debugPrint('[LivreurTracking] API error: $e');
    }
  }
}

class LivreurTrackingConfig {
  LivreurTrackingConfig({
    required this.blId,
    required this.commandeId,
    required this.siteOrigin,
    required this.webApiUrl,
    required this.watchTokenUrl,
    required this.statusUrl,
    required this.socketUrl,
    required this.socketPath,
    required this.realtimeConfigured,
    this.embeddedWatchToken = '',
  });

  final int blId;
  final int commandeId;
  final String siteOrigin;
  final String webApiUrl;
  final String watchTokenUrl;
  final String statusUrl;
  final String socketUrl;
  final String socketPath;
  final bool realtimeConfigured;
  final String embeddedWatchToken;

  factory LivreurTrackingConfig.fromMap(Map<String, dynamic> map) {
    return LivreurTrackingConfig(
      blId: _asInt(map['blId'] ?? map['bl_id']),
      commandeId: _asInt(map['commandeId'] ?? map['commande_id']),
      siteOrigin: (map['siteOrigin'] ?? map['site_origin'] ?? '').toString(),
      webApiUrl:
          (map['webApiUrl'] ?? map['web_api_url'] ?? '/api/tracking/livreur-web.php')
              .toString(),
      watchTokenUrl:
          (map['watchTokenUrl'] ?? map['watch_token_url'] ?? '').toString(),
      statusUrl: (map['statusUrl'] ?? map['status_url'] ?? '').toString(),
      socketUrl: (map['socketUrl'] ?? map['socket_url'] ?? '').toString(),
      socketPath:
          (map['socketPath'] ?? map['socket_path'] ?? '/socket.io').toString(),
      realtimeConfigured: map['realtimeConfigured'] == true ||
          map['realtime_configured'] == true,
      embeddedWatchToken: (map['embeddedWatchToken'] ??
              map['embedded_watch_token'] ??
              map['watch_token'] ??
              '')
          .toString(),
    );
  }

  LivreurTrackingConfig copyWith({String? embeddedWatchToken}) {
    return LivreurTrackingConfig(
      blId: blId,
      commandeId: commandeId,
      siteOrigin: siteOrigin,
      webApiUrl: webApiUrl,
      watchTokenUrl: watchTokenUrl,
      statusUrl: statusUrl,
      socketUrl: socketUrl,
      socketPath: socketPath,
      realtimeConfigured: realtimeConfigured,
      embeddedWatchToken: embeddedWatchToken ?? this.embeddedWatchToken,
    );
  }

  bool get isValid => siteOrigin.isNotEmpty && (blId > 0 || commandeId > 0);

  String get deliveryKey => blId > 0 ? 'bl-$blId' : 'cmd-$commandeId';

  String absUrl(String path) {
    if (path.startsWith('http://') || path.startsWith('https://')) {
      return path;
    }
    final base = siteOrigin.replaceAll(RegExp(r'/+$'), '');
    final suffix = path.startsWith('/') ? path : '/$path';
    return '$base$suffix';
  }

  Map<String, dynamic> toMap() => {
        'blId': blId,
        'commandeId': commandeId,
        'siteOrigin': siteOrigin,
        'webApiUrl': webApiUrl,
        'watchTokenUrl': watchTokenUrl,
        'statusUrl': statusUrl,
        'socketUrl': socketUrl,
        'socketPath': socketPath,
        'realtimeConfigured': realtimeConfigured,
        'embeddedWatchToken': embeddedWatchToken,
      };
}

int _asInt(dynamic value) {
  if (value == null) {
    return 0;
  }
  if (value is int) {
    return value;
  }
  return int.tryParse(value.toString()) ?? 0;
}
