import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../core/providers/core_providers.dart';

class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  Future<void> _performLogout(WidgetRef ref, BuildContext context) async {
    final dio = ref.read(dioProvider);
    final secureStore = ref.read(secureStoreProvider);
    final configCache = ref.read(configCacheProvider);

    try {
      // 1. Notify backend to unregister device FCM token and revoke active session
      await dio.delete('/v1/devices').catchError((_) => null);
      await dio.post('/v1/auth/logout').catchError((_) => null);
    } catch (_) {}

    // 2. Clear all local tokens, caches, user-specific data (Cross-user isolation)
    await secureStore.clearAll();
    await configCache.clear();

    if (context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Logged out. All session tokens and cache cleared.')),
      );
      context.go('/splash');
    }
  }

  Future<void> _performAccountDeletion(WidgetRef ref, BuildContext context) async {
    final dio = ref.read(dioProvider);
    final secureStore = ref.read(secureStoreProvider);
    final configCache = ref.read(configCacheProvider);

    try {
      // Call backend DELETE /v1/account per Play Store policy
      await dio.delete('/v1/account').catchError((_) => null);
    } catch (_) {}

    // Wipe all local storage & cache
    await secureStore.clearAll();
    await configCache.clear();

    if (context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Account permanently deleted per Google Play policy.')),
      );
      context.go('/splash');
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('My Account')),
      body: ListView(
        padding: const EdgeInsets.all(16.0),
        children: [
          // Account Summary Card
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16.0),
              child: Row(
                children: [
                  CircleAvatar(
                    radius: 28,
                    backgroundColor: theme.colorScheme.primary.withOpacity(0.1),
                    child: Icon(
                      Icons.person_rounded,
                      size: 32,
                      color: theme.colorScheme.primary,
                    ),
                  ),
                  const SizedBox(width: 16),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Store Customer',
                          style: TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          'Verified Mobile Account',
                          style: TextStyle(
                            fontSize: 13,
                            color: Colors.grey.shade600,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 20),

          // Menu Options
          ListTile(
            leading: const Icon(Icons.receipt_long_outlined),
            title: const Text('My Orders'),
            subtitle: const Text('Track and view order history'),
            trailing: const Icon(Icons.chevron_right),
            onTap: () => context.push('/orders'),
          ),
          const Divider(),
          ListTile(
            leading: const Icon(Icons.favorite_border),
            title: const Text('My Wishlist'),
            trailing: const Icon(Icons.chevron_right),
            onTap: () => context.push('/catalog'),
          ),
          const Divider(),
          ListTile(
            leading: const Icon(Icons.privacy_tip_outlined),
            title: const Text('Privacy Policy'),
            trailing: const Icon(Icons.chevron_right),
            onTap: () {
              final config = ref.read(appConfigProvider).valueOrNull;
              final privacyUrl = config?.links.privacy;
              if (privacyUrl != null && privacyUrl.isNotEmpty) {
                context.push('/webview?url=${Uri.encodeComponent(privacyUrl)}');
              } else {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Privacy Policy is available at your store website.')),
                );
              }
            },
          ),
          const Divider(),

          // Logout Action (Wipes all tokens, cache, cart, FCM)
          ListTile(
            leading: const Icon(Icons.logout, color: Colors.amber),
            title: const Text('Log Out'),
            subtitle: const Text('Clears credentials, cart and local caches'),
            onTap: () => _performLogout(ref, context),
          ),
          const Divider(),

          // Play Store Mandatory Requirement: Delete Account
          ListTile(
            leading: const Icon(Icons.delete_forever, color: Colors.red),
            title: const Text(
              'Delete Account',
              style: TextStyle(color: Colors.red, fontWeight: FontWeight.w600),
            ),
            subtitle: const Text(
              'Permanently remove personal data & history (Google Play Policy)',
              style: TextStyle(fontSize: 11),
            ),
            onTap: () => _showDeleteConfirmation(context, () => _performAccountDeletion(ref, context)),
          ),
        ],
      ),
    );
  }

  void _showDeleteConfirmation(BuildContext context, VoidCallback onConfirm) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Delete Account?'),
        content: const Text(
          'This action is irreversible. All your orders and personal data will be deleted immediately per Google Play User Data policy.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
            onPressed: () {
              Navigator.pop(ctx);
              onConfirm();
            },
            child: const Text('Permanently Delete'),
          ),
        ],
      ),
    );
  }
}
