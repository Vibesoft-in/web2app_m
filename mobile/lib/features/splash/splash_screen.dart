import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../core/env/app_env.dart';
import '../../core/network/app_exception.dart';
import '../../core/providers/core_providers.dart';
import '../../core/widgets/error_view.dart';
import 'force_update_screen.dart';
import 'maintenance_screen.dart';

class SplashScreen extends ConsumerWidget {
  const SplashScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final configAsync = ref.watch(appConfigProvider);

    return Scaffold(
      body: configAsync.when(
        loading: () => Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: Theme.of(context).colorScheme.primary.withOpacity(0.1),
                  shape: BoxShape.circle,
                ),
                child: Icon(
                  Icons.storefront_rounded,
                  size: 64,
                  color: Theme.of(context).colorScheme.primary,
                ),
              ),
              const SizedBox(height: 20),
              Text(
                AppEnv.appName,
                style: const TextStyle(
                  fontSize: 22,
                  fontWeight: FontWeight.bold,
                  letterSpacing: -0.5,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                'Connecting to store catalog...',
                style: TextStyle(
                  fontSize: 13,
                  color: Colors.grey.shade500,
                ),
              ),
              const SizedBox(height: 32),
              const SizedBox(
                width: 24,
                height: 24,
                child: CircularProgressIndicator(strokeWidth: 2.5),
              ),
            ],
          ),
        ),
        error: (error, _) {
          if (error is MaintenanceException) {
            return MaintenanceScreen(
              onRetry: () => ref.read(appConfigProvider.notifier).reload(),
            );
          }

          if (error is ForceUpdateException) {
            return ForceUpdateScreen(
              minVersion: error.minVersion,
              currentVersion: error.currentVersion,
            );
          }

          return ErrorView(
            title: 'Failed to load store',
            message: error is AppException
                ? error.message
                : 'Could not connect to the store servers. Please check your internet connection.',
            onRetry: () => ref.read(appConfigProvider.notifier).reload(),
          );
        },
        data: (config) {
          // Once config is successfully loaded and theme applied, transition to home
          WidgetsBinding.instance.addPostFrameCallback((_) {
            if (context.mounted) {
              context.go('/home');
            }
          });

          return Center(
            child: CircularProgressIndicator(
              color: Theme.of(context).colorScheme.primary,
            ),
          );
        },
      ),
    );
  }
}
