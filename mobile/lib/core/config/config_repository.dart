import 'package:dio/dio.dart';
import '../env/app_env.dart';
import '../network/app_exception.dart';
import '../network/error_mapper.dart';
import 'app_config_model.dart';
import 'config_cache.dart';

/// Repository responsible for fetching runtime app configuration, caching it,
/// and evaluating maintenance mode and minimum version compatibility.
class ConfigRepository {
  final Dio _dio;
  final ConfigCache _cache;

  ConfigRepository({
    required Dio dio,
    required ConfigCache cache,
  })  : _dio = dio,
        _cache = cache;

  /// Fetches runtime configuration from GET /v1/app-config
  ///
  /// Flow per docs/03 §4:
  /// 1. Try remote fetch
  /// 2. Cache valid JSON
  /// 3. If offline/error, use cached config if available
  /// 4. Check maintenance flag
  /// 5. Check min_version against current app version
  Future<AppConfig> getAppConfig() async {
    try {
      final response = await _dio.get('/v1/app-config');
      final data = response.data;

      if (data is Map<String, dynamic>) {
        final config = AppConfig.fromJson(data);
        await _cache.save(data);
        _evaluateConfigConstraints(config);
        return config;
      }

      throw const ServerException('Invalid configuration payload received.');
    } on DioException catch (dioErr) {
      // Offline fallback: try reading cached config
      final cached = await _cache.getCached();
      if (cached != null) {
        _evaluateConfigConstraints(cached);
        return cached;
      }
      throw ErrorMapper.fromDio(dioErr);
    } catch (e) {
      if (e is AppException) rethrow;

      final cached = await _cache.getCached();
      if (cached != null) {
        _evaluateConfigConstraints(cached);
        return cached;
      }
      throw AppException(e.toString());
    }
  }

  void _evaluateConfigConstraints(AppConfig config) {
    if (config.app.maintenance) {
      throw const MaintenanceException('Store is currently undergoing scheduled maintenance.');
    }

    if (_isVersionOutdated(AppEnv.appVersion, config.app.minVersion)) {
      throw ForceUpdateException(
        minVersion: config.app.minVersion,
        currentVersion: AppEnv.appVersion,
      );
    }
  }

  /// Compares semantic versions (e.g. "1.0.0" vs "1.2.0")
  /// Returns true if currentVersion < minVersion.
  bool _isVersionOutdated(String currentVersion, String minVersion) {
    try {
      final currentParts = currentVersion
          .split('+')
          .first
          .split('.')
          .map((e) => int.tryParse(e) ?? 0)
          .toList();
      final minParts = minVersion
          .split('+')
          .first
          .split('.')
          .map((e) => int.tryParse(e) ?? 0)
          .toList();

      while (currentParts.length < 3) currentParts.add(0);
      while (minParts.length < 3) minParts.add(0);

      for (int i = 0; i < 3; i++) {
        if (currentParts[i] < minParts[i]) return true;
        if (currentParts[i] > minParts[i]) return false;
      }
      return false;
    } catch (_) {
      return false;
    }
  }
}
