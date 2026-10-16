import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:webintoapp_mobile/core/config/config_cache.dart';
import 'package:webintoapp_mobile/core/storage/secure_store.dart';

void main() {
  group('Cross-User Device Switch Isolation Tests', () {
    late SecureStore secureStore;
    late ConfigCache configCache;

    setUp(() {
      FlutterSecureStorage.setMockInitialValues({});
      secureStore = SecureStore();
      configCache = ConfigCache();
    });

    test('User switch on the same device leaves no residual data or tokens from previous user', () async {
      // 1. User A logs in on device
      await secureStore.saveSession(
        accessToken: 'bearer_token_user_a_secret_123',
        refreshToken: 'refresh_token_user_a_456',
        userId: 'usr_alpha_101',
        email: 'alice@alpha.com',
      );

      await configCache.save({
        'schema_version': 1,
        'app': {'name': 'Alice Store'},
      });

      // Confirm User A is securely stored
      expect(await secureStore.getAccessToken(), 'bearer_token_user_a_secret_123');
      expect(await secureStore.getUserId(), 'usr_alpha_101');
      expect(await secureStore.getUserEmail(), 'alice@alpha.com');
      expect(await configCache.getCached(), isNotNull);

      // 2. User A logs out -> triggers full device session wipe
      await secureStore.clearAll();
      await configCache.clear();

      // Verify all session tokens and customer keys are wiped completely
      expect(await secureStore.getAccessToken(), isNull);
      expect(await secureStore.getRefreshToken(), isNull);
      expect(await secureStore.getUserId(), isNull);
      expect(await secureStore.getUserEmail(), isNull);
      expect(await configCache.getCached(), isNull);

      // 3. User B logs in on the same physical phone
      await secureStore.saveSession(
        accessToken: 'bearer_token_user_b_token_789',
        refreshToken: 'refresh_token_user_b_012',
        userId: 'usr_beta_202',
        email: 'bob@beta.com',
      );

      // Confirm only User B data exists, zero trace of User A
      expect(await secureStore.getAccessToken(), 'bearer_token_user_b_token_789');
      expect(await secureStore.getUserId(), 'usr_beta_202');
      expect(await secureStore.getUserEmail(), 'bob@beta.com');

      // Crucial: User A's token or ID is completely absent
      expect(await secureStore.getAccessToken(), isNot('bearer_token_user_a_secret_123'));
      expect(await secureStore.getUserId(), isNot('usr_alpha_101'));
      expect(await secureStore.getUserEmail(), isNot('alice@alpha.com'));
    });
  });
}
