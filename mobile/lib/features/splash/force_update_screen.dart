import 'package:flutter/material.dart';

class ForceUpdateScreen extends StatelessWidget {
  final String minVersion;
  final String currentVersion;
  final VoidCallback? onUpdate;

  const ForceUpdateScreen({
    super.key,
    required this.minVersion,
    required this.currentVersion,
    this.onUpdate,
  });

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
                  color: Colors.blue.shade50,
                  shape: BoxShape.circle,
                ),
                child: Icon(
                  Icons.system_update_rounded,
                  size: 64,
                  color: Colors.blue.shade700,
                ),
              ),
              const SizedBox(height: 24),
              const Text(
                'Update Required',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 22,
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(height: 12),
              Text(
                'A new version of the app is available with essential performance improvements and security fixes. Please update to continue.',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 14,
                  color: Colors.grey.shade600,
                  height: 1.5,
                ),
              ),
              const SizedBox(height: 16),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                decoration: BoxDecoration(
                  color: Colors.grey.shade100,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  'Current: v$currentVersion  →  Required: v$minVersion',
                  style: TextStyle(
                    fontSize: 12,
                    fontFamily: 'monospace',
                    color: Colors.grey.shade700,
                  ),
                ),
              ),
              const SizedBox(height: 32),
              ElevatedButton.icon(
                onPressed: onUpdate ?? () {},
                icon: const Icon(Icons.download_rounded, size: 18),
                label: const Text('Update Now on Google Play'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
