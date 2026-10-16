import 'package:flutter/material.dart';

class MaintenanceScreen extends StatelessWidget {
  final VoidCallback? onRetry;

  const MaintenanceScreen({super.key, this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(32.0),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                padding: const EdgeInsets.all(24),
                decoration: BoxDecoration(
                  color: Colors.amber.shade50,
                  shape: BoxShape.circle,
                ),
                child: Icon(
                  Icons.construction_rounded,
                  size: 64,
                  color: Colors.amber.shade800,
                ),
              ),
              const SizedBox(height: 24),
              const Text(
                'Store Under Maintenance',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 22,
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(height: 12),
              Text(
                'We are performing scheduled improvements to enhance your shopping experience. Please check back shortly.',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 14,
                  color: Colors.grey.shade600,
                  height: 1.5,
                ),
              ),
              if (onRetry != null) ...[
                const SizedBox(height: 32),
                ElevatedButton.icon(
                  onPressed: onRetry,
                  icon: const Icon(Icons.refresh_rounded, size: 18),
                  label: const Text('Check Again'),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
