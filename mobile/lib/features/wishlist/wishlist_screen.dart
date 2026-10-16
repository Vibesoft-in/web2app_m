import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../core/widgets/empty_view.dart';

class WishlistScreen extends StatelessWidget {
  const WishlistScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('My Wishlist')),
      body: EmptyView(
        title: 'Your wishlist is empty',
        message: 'Save items you love to find them quickly later.',
        icon: Icons.favorite_border,
        actionLabel: 'Explore Catalog',
        onAction: () => context.go('/catalog'),
      ),
    );
  }
}
