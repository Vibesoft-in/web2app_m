import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/providers/core_providers.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _phoneController = TextEditingController();
  final _otpController = TextEditingController();
  bool _otpSent = false;
  bool _isLoading = false;

  @override
  void dispose() {
    _phoneController.dispose();
    _otpController.dispose();
    super.dispose();
  }

  Future<void> _requestOtp() async {
    if (_phoneController.text.trim().isEmpty) return;
    setState(() => _isLoading = true);
    // Simulate OTP request
    await Future.delayed(const Duration(milliseconds: 500));
    setState(() {
      _isLoading = false;
      _otpSent = true;
    });
  }

  Future<void> _verifyOtp() async {
    if (_otpController.text.trim().isEmpty) return;
    setState(() => _isLoading = true);
    // Store token securely
    await ref.read(secureStoreProvider).saveSession(
          accessToken: 'mock_access_token_123',
          refreshToken: 'mock_refresh_token_123',
          userId: 'user_123',
        );
    setState(() => _isLoading = false);
    if (mounted) {
      Navigator.of(context).pop();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Sign In')),
      body: Padding(
        padding: const EdgeInsets.all(24.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text(
              'Welcome Back',
              style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            Text(
              'Enter your mobile number to receive a one-time login code.',
              style: TextStyle(color: Colors.grey.shade600, fontSize: 13),
            ),
            const SizedBox(height: 24),
            TextField(
              controller: _phoneController,
              keyboardType: TextInputType.phone,
              decoration: const InputDecoration(
                labelText: 'Phone Number',
                hintText: '+91 98765 43210',
                prefixIcon: Icon(Icons.phone_outlined),
              ),
            ),
            if (_otpSent) ...[
              const SizedBox(height: 16),
              TextField(
                controller: _otpController,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(
                  labelText: '6-digit OTP',
                  hintText: '123456',
                  prefixIcon: Icon(Icons.lock_clock_outlined),
                ),
              ),
            ],
            const SizedBox(height: 24),
            ElevatedButton(
              onPressed: _isLoading ? null : (_otpSent ? _verifyOtp : _requestOtp),
              child: _isLoading
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                    )
                  : Text(_otpSent ? 'Verify OTP & Continue' : 'Send One-Time Code'),
            ),
          ],
        ),
      ),
    );
  }
}
