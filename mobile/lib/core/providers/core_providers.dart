import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../config/app_config_model.dart';
import '../config/config_cache.dart';
import '../config/config_repository.dart';
import '../network/dio_client.dart';
import '../storage/secure_store.dart';
import '../theme/theme_engine.dart';

final secureStoreProvider = Provider<SecureStore>((ref) {
  return SecureStore();
});

final configCacheProvider = Provider<ConfigCache>((ref) {
  return ConfigCache();
});

final dioClientProvider = Provider<DioClient>((ref) {
  final secureStore = ref.watch(secureStoreProvider);
  return DioClient(secureStore: secureStore);
});

final dioProvider = Provider((ref) {
  return ref.watch(dioClientProvider).dio;
});

final configRepositoryProvider = Provider<ConfigRepository>((ref) {
  final dio = ref.watch(dioProvider);
  final cache = ref.watch(configCacheProvider);
  return ConfigRepository(dio: dio, cache: cache);
});

/// Async notifier for loading and holding runtime app configuration
final appConfigProvider =
    AsyncNotifierProvider<AppConfigNotifier, AppConfig>(AppConfigNotifier.new);

class AppConfigNotifier extends AsyncNotifier<AppConfig> {
  @override
  Future<AppConfig> build() async {
    final repo = ref.read(configRepositoryProvider);
    try {
      return await repo.getAppConfig();
    } catch (_) {
      return AppConfig.fallback();
    }
  }

  Future<void> reload() async {
    state = const AsyncValue.loading();
    state = await AsyncValue.guard(() async {
      final repo = ref.read(configRepositoryProvider);
      try {
        return await repo.getAppConfig();
      } catch (_) {
        return AppConfig.fallback();
      }
    });
  }
}

/// Provides active ThemeData dynamically computed from AppConfig
final themeDataProvider = Provider<ThemeData>((ref) {
  final configAsync = ref.watch(appConfigProvider);
  final config = configAsync.valueOrNull ?? AppConfig.fallback();
  return ThemeEngine.buildTheme(config.theme);
});
