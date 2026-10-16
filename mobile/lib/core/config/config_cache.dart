import 'dart:convert';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'app_config_model.dart';

/// Manages caching of the last known good configuration for offline readiness.
class ConfigCache {
  final FlutterSecureStorage _storage;
  static const String _cacheKey = 'last_good_app_config_json';

  ConfigCache({FlutterSecureStorage? storage})
      : _storage = storage ?? const FlutterSecureStorage();

  /// Saves valid config JSON string to disk cache
  Future<void> save(Map<String, dynamic> configJson) async {
    try {
      final jsonString = jsonEncode(configJson);
      await _storage.write(key: _cacheKey, value: jsonString);
    } catch (_) {
      // Non-fatal if disk write fails
    }
  }

  /// Retrieves the last valid config from cache if available
  Future<AppConfig?> getCached() async {
    try {
      final jsonString = await _storage.read(key: _cacheKey);
      if (jsonString != null && jsonString.isNotEmpty) {
        final decoded = jsonDecode(jsonString);
        if (decoded is Map<String, dynamic>) {
          return AppConfig.fromJson(decoded);
        }
      }
    } catch (_) {
      // Corrupt cache safely ignored
    }
    return null;
  }

  /// Clears cache
  Future<void> clear() async {
    try {
      await _storage.delete(key: _cacheKey);
    } catch (_) {}
  }
}
