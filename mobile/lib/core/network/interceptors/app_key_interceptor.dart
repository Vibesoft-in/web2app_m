import 'package:dio/dio.dart';
import '../../env/app_env.dart';

/// Injects public tenant identifier and client version headers on every outgoing request.
/// NO secret credentials are sent in these headers.
class AppKeyInterceptor extends Interceptor {
  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    options.headers['X-App-Key'] = AppEnv.appKey;
    options.headers['X-App-Version'] = AppEnv.appVersion;
    options.headers['X-App-Platform'] = 'flutter';
    options.headers['Accept'] = 'application/json';
    handler.next(options);
  }
}
