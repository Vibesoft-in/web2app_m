import 'package:dio/dio.dart';
import '../../storage/secure_store.dart';

/// Injects Bearer token from secure storage into authenticated requests.
/// Handles 401 unauthorized responses cleanly.
class AuthInterceptor extends Interceptor {
  final SecureStore _secureStore;
  final void Function()? onUnauthorized;

  AuthInterceptor({
    required SecureStore secureStore,
    this.onUnauthorized,
  }) : _secureStore = secureStore;

  @override
  Future<void> onRequest(
    RequestOptions options,
    RequestInterceptorHandler handler,
  ) async {
    final token = await _secureStore.getAccessToken();
    if (token != null && token.isNotEmpty) {
      options.headers['Authorization'] = 'Bearer $token';
    }
    handler.next(options);
  }

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) {
    if (err.response?.statusCode == 401) {
      onUnauthorized?.call();
    }
    handler.next(err);
  }
}
