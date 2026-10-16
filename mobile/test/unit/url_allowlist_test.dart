import 'package:flutter_test/flutter_test.dart';
import 'package:webintoapp_mobile/core/security/url_allowlist.dart';

void main() {
  group('UrlAllowlist Security Tests', () {
    final allowlist = UrlAllowlist(
      allowedHosts: ['store.glowbeauty.com', 'payment.razorpay.com'],
    );

    test('permits HTTPS URLs matching exact allowed hosts', () {
      expect(
        allowlist.isAllowed('https://store.glowbeauty.com/checkout'),
        isTrue,
      );
      expect(
        allowlist.isAllowed('https://payment.razorpay.com/pay'),
        isTrue,
      );
    });

    test('permits subdomains of allowed hosts', () {
      expect(
        allowlist.isAllowed('https://checkout.store.glowbeauty.com/order'),
        isTrue,
      );
    });

    test('blocks non-HTTPS schemes', () {
      expect(
        allowlist.isAllowed('http://store.glowbeauty.com/checkout'),
        isFalse,
      );
      expect(
        allowlist.isAllowed('javascript:alert(1)'),
        isFalse,
      );
      expect(
        allowlist.isAllowed('file:///android_asset/index.html'),
        isFalse,
      );
    });

    test('blocks localhost and loopback IP addresses', () {
      expect(allowlist.isAllowed('https://localhost/checkout'), isFalse);
      expect(allowlist.isAllowed('https://127.0.0.1/checkout'), isFalse);
      expect(allowlist.isAllowed('https://[::1]/checkout'), isFalse);
    });

    test('blocks private and cloud metadata IP ranges', () {
      expect(allowlist.isAllowed('https://169.254.169.254/latest/meta-data'), isFalse);
      expect(allowlist.isAllowed('https://10.0.0.1/admin'), isFalse);
      expect(allowlist.isAllowed('https://192.168.1.100/config'), isFalse);
      expect(allowlist.isAllowed('https://172.16.0.1/internal'), isFalse);
    });

    test('blocks untrusted external domains not in allowlist', () {
      expect(allowlist.isAllowed('https://evil-phishing.com/login'), isFalse);
      expect(allowlist.isAllowed('https://google.com'), isFalse);
    });
  });
}
