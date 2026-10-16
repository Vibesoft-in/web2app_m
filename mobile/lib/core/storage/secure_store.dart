import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Secure token & session credentials storage using Keystore / Keychain.
/// Secrets & bearer tokens are never written to SharedPreferences or unencrypted storage.
class SecureStore {
  final FlutterSecureStorage _storage;

  static const String _keyAccessToken = 'sec_access_token';
  static const String _keyRefreshToken = 'sec_refresh_token';
  static const String _keyUserId = 'sec_user_id';
  static const String _keyUserEmail = 'sec_user_email';

  SecureStore({FlutterSecureStorage? storage})
      : _storage = storage ??
            const FlutterSecureStorage(
              aOptions: AndroidOptions(
                encryptedSharedPreferences: true,
              ),
              iOptions: IOSOptions(
                accessibility: KeychainAccessibility.first_unlock,
              ),
            );

  Future<String?> getAccessToken() async {
    return _storage.read(key: _keyAccessToken);
  }

  Future<void> setAccessToken(String token) async {
    await _storage.write(key: _keyAccessToken, value: token);
  }

  Future<String?> getRefreshToken() async {
    return _storage.read(key: _keyRefreshToken);
  }

  Future<void> setRefreshToken(String token) async {
    await _storage.write(key: _keyRefreshToken, value: token);
  }

  Future<String?> getUserId() async {
    return _storage.read(key: _keyUserId);
  }

  Future<void> setUserId(String userId) async {
    await _storage.write(key: _keyUserId, value: userId);
  }

  Future<String?> getUserEmail() async {
    return _storage.read(key: _keyUserEmail);
  }

  Future<void> setUserEmail(String email) async {
    await _storage.write(key: _keyUserEmail, value: email);
  }

  Future<void> saveSession({
    required String accessToken,
    required String refreshToken,
    required String userId,
    String? email,
  }) async {
    await setAccessToken(accessToken);
    await setRefreshToken(refreshToken);
    await setUserId(userId);
    if (email != null) {
      await setUserEmail(email);
    }
  }

  /// Wipes all credentials upon user logout.
  /// Prevents cross-user data leakage when multiple users share the same device.
  Future<void> clearAll() async {
    await _storage.delete(key: _keyAccessToken);
    await _storage.delete(key: _keyRefreshToken);
    await _storage.delete(key: _keyUserId);
    await _storage.delete(key: _keyUserEmail);
  }
}
