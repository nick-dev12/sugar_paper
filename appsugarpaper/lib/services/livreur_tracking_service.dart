import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:geolocator/geolocator.dart' as geo;
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'package:socket_io_client/socket_io_client.dart' as io;

/// Suivi GPS livreur en arrière-plan (session admin WebView + API PHP existante).
class LivreurTrackingService {
  LivreurTrackingService._();

  static final LivreurTrackingService instance = LivreurTrackingService._();

  static const _prefsKey = 'livreur_native_tracking_v1';

  StreamSubscription<geo.Position>? _positionSub;
  io.Socket? _socket;
  Timer? _statusTimer;
  LivreurTrackingConfig? _active;
  DateTime? _lastHttpPostAt;
  bool _starting = false;

  bool get isActive => _active != null;

  LivreurTrackingConfig? get activeConfig => _active;

  Future<Map<String, dynamic>> start({
    required Map<String, dynamic> rawConfig,
    required Future<String?> Function() getCookieHeader,
    required Future<bool> Function() requestPermissions,
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

      final sameDelivery = _active != null && _active!.deliveryKey == config.deliveryKey;
      if (_active != null && !sameDelivery) {
        await stop(getCookieHeader: getCookieHeader, callApi: false);
      } else if (_active != null && sameDelivery) {
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

      _active = config;
      await _persistSession(config);

      await _connectSocket(config, getCookieHeader);
      await _startPositionStream(config, getCookieHeader);
      _startStatusPolling(config, getCookieHeader);

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
  }) async {
    if (isActive || _starting) {
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
    );
  }

  Future<void> _persistSession(LivreurTrackingConfig config) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_prefsKey, jsonEncode(config.toMap()));
  }

  Future<void> _teardown({required bool clearSession}) async {
    _statusTimer?.cancel();
    _statusTimer = null;
    await _positionSub?.cancel();
    _positionSub = null;
    _socket?.dispose();
    _socket = null;
    _active = null;
    _lastHttpPostAt = null;
    if (clearSession) {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(_prefsKey);
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
      onError: (_) {},
    );
  }

  geo.LocationSettings _locationSettings() {
    if (!kIsWeb && Platform.isAndroid) {
      return geo.AndroidSettings(
        accuracy: geo.LocationAccuracy.high,
        distanceFilter: 5,
        intervalDuration: const Duration(seconds: 4),
        foregroundNotificationConfig: geo.ForegroundNotificationConfig(
          notificationTitle: 'Livraison en cours',
          notificationText:
              'Sugar Paper transmet votre position au client en direct.',
          notificationIcon:
              geo.AndroidResource(name: 'ic_launcher', defType: 'mipmap'),
          enableWakeLock: true,
        ),
      );
    }
    if (!kIsWeb && Platform.isIOS) {
      return geo.AppleSettings(
        accuracy: geo.LocationAccuracy.high,
        activityType: geo.ActivityType.automotiveNavigation,
        distanceFilter: 5,
        allowBackgroundLocationUpdates: true,
        showBackgroundLocationIndicator: true,
        pauseLocationUpdatesAutomatically: false,
      );
    }
    return const geo.LocationSettings(
      accuracy: geo.LocationAccuracy.high,
      distanceFilter: 5,
    );
  }

  Future<void> _onPosition(
    LivreurTrackingConfig config,
    Future<String?> Function() getCookieHeader,
    geo.Position pos,
  ) async {
    _emitSocket(config, pos);
    final now = DateTime.now();
    if (_lastHttpPostAt != null &&
        now.difference(_lastHttpPostAt!) < const Duration(seconds: 4)) {
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

  void _emitSocket(LivreurTrackingConfig config, geo.Position pos) {
    final socket = _socket;
    if (socket == null || !socket.connected) {
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
    if (!config.realtimeConfigured || config.watchTokenUrl.isEmpty) {
      return;
    }
    final token = await _fetchWatchToken(config, getCookieHeader);
    if (token == null || token.isEmpty) {
      return;
    }

    _socket?.dispose();
    final origin = config.socketUrl.isNotEmpty ? config.socketUrl : config.siteOrigin;
    final socket = io.io(
      origin,
      io.OptionBuilder()
          .setTransports(['polling'])
          .disableAutoConnect()
          .setPath(config.socketPath)
          .enableReconnection()
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
      timeout?.cancel();
      if (!completer.isCompleted) {
        completer.complete();
      }
    });

    socket.onConnectError((_) {
      /* reconnexion automatique */
    });

    _socket = socket;
    socket.connect();

    timeout = Timer(const Duration(seconds: 12), () {
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
        return null;
      }
      final data = jsonDecode(res.body);
      if (data is Map && data['success'] == true) {
        return (data['watch_token'] ?? '').toString();
      }
    } catch (_) {}
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

      await http.post(
        Uri.parse(config.absUrl(config.webApiUrl)),
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          if (cookie != null && cookie.isNotEmpty) 'Cookie': cookie,
        },
        body: jsonEncode(payload),
      );
    } catch (_) {
      /* silencieux — prochaine position réessaiera */
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

  factory LivreurTrackingConfig.fromMap(Map<String, dynamic> map) {
    return LivreurTrackingConfig(
      blId: _asInt(map['blId'] ?? map['bl_id']),
      commandeId: _asInt(map['commandeId'] ?? map['commande_id']),
      siteOrigin: (map['siteOrigin'] ?? map['site_origin'] ?? '').toString(),
      webApiUrl: (map['webApiUrl'] ?? map['web_api_url'] ?? '/api/tracking/livreur-web.php')
          .toString(),
      watchTokenUrl: (map['watchTokenUrl'] ?? map['watch_token_url'] ?? '').toString(),
      statusUrl: (map['statusUrl'] ?? map['status_url'] ?? '').toString(),
      socketUrl: (map['socketUrl'] ?? map['socket_url'] ?? '').toString(),
      socketPath: (map['socketPath'] ?? map['socket_path'] ?? '/socket.io').toString(),
      realtimeConfigured: map['realtimeConfigured'] == true ||
          map['realtime_configured'] == true,
    );
  }

  bool get isValid =>
      siteOrigin.isNotEmpty && (blId > 0 || commandeId > 0);

  String get deliveryKey =>
      blId > 0 ? 'bl-$blId' : 'cmd-$commandeId';

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
