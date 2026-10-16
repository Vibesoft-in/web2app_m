import '../storage/secure_store.dart';

/// Navigation route guard to verify authentication for protected routes (orders, profile, checkout).
class RouteGuards {
  final SecureStore _secureStore;

  RouteGuards({required SecureStore secureStore}) : _secureStore = secureStore;

  /// Returns true if user has an active session token in secure storage
  Future<bool> isAuthenticated() async {
    final token = await _secureStore.getAccessToken();
    return token != null && token.isNotEmpty;
  }
}
