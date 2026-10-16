import 'package:dio/dio.dart';
import '../env/app_env.dart';
import '../storage/secure_store.dart';
import 'interceptors/app_key_interceptor.dart';
import 'interceptors/auth_interceptor.dart';

/// Central Dio client configuring base URLs, timeouts, and required security interceptors.
class DioClient {
  final Dio dio;

  DioClient({
    required SecureStore secureStore,
    Dio? customDio,
    void Function()? onUnauthorized,
  }) : dio = customDio ??
            Dio(
              BaseOptions(
                baseUrl: AppEnv.apiBaseUrl,
                connectTimeout: const Duration(seconds: 15),
                receiveTimeout: const Duration(seconds: 15),
                sendTimeout: const Duration(seconds: 15),
                contentType: 'application/json',
                responseType: ResponseType.json,
              ),
            ) {
    dio.interceptors.addAll([
      AppKeyInterceptor(),
      AuthInterceptor(
        secureStore: secureStore,
        onUnauthorized: onUnauthorized,
      ),
    ]);
  }
}
