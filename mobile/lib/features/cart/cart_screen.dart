import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../core/providers/core_providers.dart';
import '../../core/widgets/empty_view.dart';

class CartScreen extends ConsumerStatefulWidget {
  const CartScreen({super.key});

  @override
  ConsumerState<CartScreen> createState() => _CartScreenState();
}

class _CartScreenState extends ConsumerState<CartScreen> {
  bool _isCheckingOut = false;

  Future<void> _initiateCheckout() async {
    setState(() => _isCheckingOut = true);
    final dio = ref.read(dioProvider);

    try {
      // Revalidate cart items and obtain secure checkout URL from backend
      final response = await dio.post('/v1/cart/checkout-url', data: {
        'items': [
          {'product_id': '101', 'quantity': 1},
        ],
      });

      final checkoutUrl = response.data['checkout_url'] as String?;

      if (checkoutUrl != null && mounted) {
        context.push('/checkout?url=${Uri.encodeComponent(checkoutUrl)}');
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Checkout failed: ${e.toString()}')),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _isCheckingOut = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Shopping Cart')),
      body: EmptyView(
        title: 'Your cart is ready',
        message: 'Proceed to checkout or explore additional catalog products.',
        icon: Icons.shopping_bag_outlined,
        actionLabel: _isCheckingOut ? 'Preparing Checkout...' : 'Proceed to Checkout',
        onAction: _isCheckingOut ? null : _initiateCheckout,
      ),
    );
  }
}
