import 'package:flutter_test/flutter_test.dart';
import 'package:webintoapp_mobile/core/config/app_config_model.dart';

void main() {
  group('AppConfig Model Unit Tests', () {
    test('parses full valid config JSON accurately', () {
      final json = {
        'schema_version': 1,
        'config_version': 14,
        'app': {
          'name': 'Glow Beauty',
          'currency': 'INR',
          'min_version': '1.0.0',
          'maintenance': false,
        },
        'theme': {
          'layout': 'theme_a',
          'colors': {
            'primary': '#E91E63',
            'secondary': '#222222',
            'background': '#FFFFFF',
            'surface': '#FAFAFA',
            'text': '#111111',
          },
          'radius': 12,
          'font': 'Poppins',
          'nav': 'bottom',
        },
        'features': {
          'wishlist': true,
          'otp_login': true,
          'google_login': true,
          'notifications': true,
          'reviews': false,
        },
        'home': [
          {
            'type': 'banner',
            'items': [
              {'image': 'https://example.com/banner.jpg', 'link': 'category:12'},
            ],
          },
          {'type': 'categories'},
          {'type': 'product_rail', 'title': 'New Arrivals', 'source': 'recent'},
        ],
        'links': {
          'privacy': 'https://glowbeauty.com/privacy',
          'terms': 'https://glowbeauty.com/terms',
          'support_phone': '+919876543210',
        },
        'webview_allowed_hosts': ['glowbeauty.com', 'store.glowbeauty.com'],
      };

      final config = AppConfig.fromJson(json);

      expect(config.schemaVersion, 1);
      expect(config.configVersion, 14);
      expect(config.app.name, 'Glow Beauty');
      expect(config.app.currency, 'INR');
      expect(config.app.maintenance, false);
      expect(config.theme.colors.primary, '#E91E63');
      expect(config.theme.radius, 12.0);
      expect(config.features.wishlist, true);
      expect(config.features.reviews, false);
      expect(config.home.length, 3);
      expect(config.home[0].type, 'banner');
      expect(config.home[1].type, 'categories');
      expect(config.home[2].type, 'product_rail');
      expect(config.webviewAllowedHosts, contains('glowbeauty.com'));
    });

    test('safely handles unknown and future section types without crashing (forward-compatibility)', () {
      final json = {
        'schema_version': 2,
        'config_version': 20,
        'app': {'name': 'Future Store'},
        'home': [
          {'type': 'stories_reel', 'items': []}, // Future section type
          {'type': 'live_stream', 'stream_id': 'xyz'}, // Unknown section type
          {'type': 'categories'}, // Standard section
        ],
      };

      final config = AppConfig.fromJson(json);

      expect(config.home.length, 3);
      expect(config.home[0].type, 'stories_reel');
      expect(config.home[1].type, 'live_stream');
      expect(config.home[2].type, 'categories');
    });

    test('falls back gracefully on empty or malformed inputs', () {
      final config = AppConfig.fromJson({});

      expect(config.schemaVersion, 1);
      expect(config.app.name, 'Store');
      expect(config.app.currency, 'USD');
      expect(config.theme.colors.primary, '#4F46E5');
      expect(config.home, isEmpty);
      expect(config.webviewAllowedHosts, isEmpty);
    });
  });
}
