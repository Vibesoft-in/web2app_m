/// Enforces webview URL navigation safety by checking against tenant allowlisted domains.
/// Blocks non-HTTPS schemes, private/loopback/link-local addresses, and unauthorized hosts.
class UrlAllowlist {
  final List<String> allowedHosts;

  UrlAllowlist({required this.allowedHosts});

  /// Evaluates whether a navigation URL is safe to open inside the embedded webview.
  bool isAllowed(String urlString) {
    try {
      final uri = Uri.parse(urlString);

      // Must be secure HTTPS
      if (uri.scheme.toLowerCase() != 'https') {
        return false;
      }

      final host = uri.host.toLowerCase();
      if (host.isEmpty) return false;

      // Block local/private IP ranges
      if (_isPrivateOrLoopbackHost(host)) {
        return false;
      }

      // Exact match or subdomain match against allowedHosts
      for (final allowed in allowedHosts) {
        final cleanAllowed = allowed.toLowerCase().trim();
        if (host == cleanAllowed || host.endsWith('.$cleanAllowed')) {
          return true;
        }
      }

      return false;
    } catch (_) {
      return false;
    }
  }

  bool _isPrivateOrLoopbackHost(String host) {
    if (host == 'localhost' || host == '127.0.0.1' || host == '::1') {
      return true;
    }
    if (host.startsWith('10.') ||
        host.startsWith('192.168.') ||
        host.startsWith('169.254.')) {
      return true;
    }
    if (RegExp(r'^172\.(1[6-9]|2[0-9]|3[0-1])\.').hasMatch(host)) {
      return true;
    }
    return false;
  }
}
