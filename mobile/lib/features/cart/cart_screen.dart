import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../core/providers/shop_providers.dart';

class CartScreen extends ConsumerStatefulWidget {
  const CartScreen({super.key});

  @override
  ConsumerState<CartScreen> createState() => _CartScreenState();
}

class _CartScreenState extends ConsumerState<CartScreen> {
  final TextEditingController _promoController = TextEditingController();
  bool _promoApplied = true;
  double _promoDiscount = 25.00;

  @override
  void dispose() {
    _promoController.dispose();
    super.dispose();
  }

  void _applyPromo() {
    final code = _promoController.text.trim().toUpperCase();
    if (code.isNotEmpty) {
      setState(() {
        _promoApplied = true;
        _promoDiscount = 25.00;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Promo code applied successfully! (- \$25.00)'),
          backgroundColor: Color(0xFF704FEE),
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final cartItems = ref.watch(cartItemsProvider);
    final totalCount = ref.watch(cartCountProvider);
    final wishlistedIds = ref.watch(wishlistProvider);
    final unreadNotifCount = ref.watch(unreadNotificationsCountProvider);

    const primaryColor = Color(0xFF704FEE);
    const darkTextColor = Color(0xFF1E1B4B);

    final subtotal = cartItems.fold<double>(
      0.0,
      (sum, item) => sum + (item.price * item.quantity),
    );
    final discount = _promoApplied && subtotal > 0 ? _promoDiscount : 0.0;
    const shipping = 5.00;
    final total = (subtotal - discount + (subtotal > 0 ? shipping : 0.0))
        .clamp(0.0, double.infinity);

    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 18, color: darkTextColor),
          onPressed: () {
            if (context.canPop()) {
              context.pop();
            } else {
              context.go('/home');
            }
          },
        ),
        title: const Text(
          'My Cart',
          style: TextStyle(
            color: darkTextColor,
            fontSize: 18,
            fontWeight: FontWeight.w800,
          ),
        ),
        centerTitle: true,
        actions: [
          // Wishlist Icon with live badge
          _buildHeaderIconButton(
            icon: Icons.favorite_border_rounded,
            badgeCount: wishlistedIds.isNotEmpty ? wishlistedIds.length : null,
            onTap: () => context.push('/wishlist'),
          ),
          const SizedBox(width: 8),

          // Notification Bell with LIVE dynamic badge
          _buildHeaderIconButton(
            icon: Icons.notifications_none_rounded,
            badgeCount: unreadNotifCount > 0 ? unreadNotifCount : null,
            onTap: () => context.push('/notifications'),
          ),
          const SizedBox(width: 14),
        ],
      ),
      body: SafeArea(
        child: cartItems.isEmpty
            ? _buildEmptyCart(context, primaryColor, darkTextColor)
            : SingleChildScrollView(
                padding: const EdgeInsets.symmetric(horizontal: 18.0, vertical: 8.0),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Items Header Count & Clear All Button
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Row(
                          children: [
                            const Icon(Icons.shopping_bag_outlined, size: 18, color: darkTextColor),
                            const SizedBox(width: 8),
                            Text(
                              '$totalCount Items',
                              style: const TextStyle(
                                fontSize: 13.5,
                                fontWeight: FontWeight.w700,
                                color: darkTextColor,
                              ),
                            ),
                          ],
                        ),
                        TextButton(
                          onPressed: () {
                            ref.read(cartItemsProvider.notifier).clearCart();
                          },
                          child: const Text(
                            'Clear All',
                            style: TextStyle(
                              color: Color(0xFFEF4444),
                              fontWeight: FontWeight.w600,
                              fontSize: 12.5,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),

                    // List of Cart Items
                    ListView.separated(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      itemCount: cartItems.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 16),
                      itemBuilder: (context, index) {
                        final item = cartItems[index];
                        return _buildCartItemCard(
                          item: item,
                          primaryColor: primaryColor,
                          darkTextColor: darkTextColor,
                        );
                      },
                    ),
                    const SizedBox(height: 24),

                    // Promo Code Section
                    _buildPromoSection(primaryColor, darkTextColor),
                    const SizedBox(height: 24),

                    // Order Summary Section
                    _buildOrderSummary(
                      subtotal: subtotal,
                      discount: discount,
                      shipping: shipping,
                      total: total,
                      primaryColor: primaryColor,
                      darkTextColor: darkTextColor,
                    ),
                    const SizedBox(height: 22),

                    // Checkout Buttons
                    _buildCheckoutButtons(primaryColor, darkTextColor),
                    const SizedBox(height: 24),
                  ],
                ),
              ),
      ),
    );
  }

  /// Cart Item Row (matching 3rd reference image)
  Widget _buildCartItemCard({
    required CartItem item,
    required Color primaryColor,
    required Color darkTextColor,
  }) {
    return Container(
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: const Color(0xFFF9FAFB),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: Colors.grey.shade100),
      ),
      child: Row(
        children: [
          // Thumbnail Image
          Container(
            width: 76,
            height: 76,
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(14),
            ),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(14),
              child: CachedNetworkImage(
                imageUrl: item.image,
                fit: BoxFit.cover,
                placeholder: (_, __) => const Center(
                  child: CircularProgressIndicator(strokeWidth: 2),
                ),
                errorWidget: (_, __, ___) =>
                    const Icon(Icons.image, color: Colors.grey),
              ),
            ),
          ),
          const SizedBox(width: 12),

          // Title & Price
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontSize: 13.5,
                    fontWeight: FontWeight.w700,
                    color: darkTextColor,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  '\$${item.price.toStringAsFixed(2)}',
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w900,
                    color: darkTextColor,
                  ),
                ),
              ],
            ),
          ),

          // Quantity controls [- 1 +]
          Container(
            height: 36,
            padding: const EdgeInsets.symmetric(horizontal: 4),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: Colors.grey.shade200),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                IconButton(
                  padding: EdgeInsets.zero,
                  constraints: const BoxConstraints(minWidth: 24),
                  icon: const Icon(Icons.remove, size: 14),
                  onPressed: () {
                    ref.read(cartItemsProvider.notifier).decrementQuantity(item.id);
                  },
                ),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 4.0),
                  child: Text(
                    '${item.quantity}',
                    style: TextStyle(
                      fontSize: 12.5,
                      fontWeight: FontWeight.bold,
                      color: darkTextColor,
                    ),
                  ),
                ),
                IconButton(
                  padding: EdgeInsets.zero,
                  constraints: const BoxConstraints(minWidth: 24),
                  icon: const Icon(Icons.add, size: 14),
                  onPressed: () {
                    ref.read(cartItemsProvider.notifier).incrementQuantity(item.id);
                  },
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),

          // Delete Trash Can Icon
          IconButton(
            icon: const Icon(Icons.delete_outline_rounded, size: 19, color: Color(0xFF9CA3AF)),
            onPressed: () {
              ref.read(cartItemsProvider.notifier).removeItem(item.id);
            },
          ),
        ],
      ),
    );
  }

  /// Promo Code Box
  Widget _buildPromoSection(Color primaryColor, Color darkTextColor) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFFF9FAFB),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: Colors.grey.shade100),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: const [
              Text('💎', style: TextStyle(fontSize: 14)),
              SizedBox(width: 6),
              Text(
                'Have a promo code?',
                style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: Color(0xFF374151),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: Container(
                  height: 44,
                  padding: const EdgeInsets.symmetric(horizontal: 14),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: Colors.grey.shade200),
                  ),
                  child: TextField(
                    controller: _promoController,
                    decoration: InputDecoration(
                      hintText: _promoApplied ? 'DISCOUNT25' : 'Enter code',
                      hintStyle: TextStyle(
                        color: _promoApplied ? primaryColor : Colors.grey.shade400,
                        fontSize: 13,
                        fontWeight: _promoApplied ? FontWeight.bold : FontWeight.normal,
                      ),
                      border: InputBorder.none,
                      enabledBorder: InputBorder.none,
                      focusedBorder: InputBorder.none,
                      contentPadding: EdgeInsets.zero,
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              ElevatedButton(
                onPressed: _applyPromo,
                style: ElevatedButton.styleFrom(
                  backgroundColor: primaryColor,
                  foregroundColor: Colors.white,
                  elevation: 0,
                  padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                child: const Text(
                  'Apply',
                  style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  /// Order Summary
  Widget _buildOrderSummary({
    required double subtotal,
    required double discount,
    required double shipping,
    required double total,
    required Color primaryColor,
    required Color darkTextColor,
  }) {
    return Column(
      children: [
        _buildSummaryRow('Subtotal', '\$${subtotal.toStringAsFixed(2)}', darkTextColor),
        const SizedBox(height: 8),
        _buildSummaryRow(
          'Discount',
          '- \$${discount.toStringAsFixed(2)}',
          const Color(0xFF10B981),
        ),
        const SizedBox(height: 8),
        _buildSummaryRow('Shipping', '\$${shipping.toStringAsFixed(2)}', darkTextColor),
        const Padding(
          padding: EdgeInsets.symmetric(vertical: 12.0),
          child: Divider(color: Color(0xFFE5E7EB)),
        ),
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              'Total',
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w800,
                color: darkTextColor,
              ),
            ),
            Text(
              '\$${total.toStringAsFixed(2)}',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w900,
                color: primaryColor,
              ),
            ),
          ],
        ),
      ],
    );
  }

  Widget _buildSummaryRow(String label, String value, Color valueColor) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          label,
          style: TextStyle(
            fontSize: 13,
            color: Colors.grey.shade600,
            fontWeight: FontWeight.w500,
          ),
        ),
        Text(
          value,
          style: TextStyle(
            fontSize: 13.5,
            fontWeight: FontWeight.w700,
            color: valueColor,
          ),
        ),
      ],
    );
  }

  /// Checkout Buttons
  Widget _buildCheckoutButtons(Color primaryColor, Color darkTextColor) {
    return Column(
      children: [
        SizedBox(
          width: double.infinity,
          height: 50,
          child: ElevatedButton(
            onPressed: () {
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(
                  content: Text('Order placed successfully! Thank you for shopping with us 🎉'),
                  backgroundColor: Color(0xFF10B981),
                  behavior: SnackBarBehavior.floating,
                ),
              );
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: primaryColor,
              foregroundColor: Colors.white,
              elevation: 0,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(16),
              ),
            ),
            child: const Text(
              'Checkout',
              style: TextStyle(
                fontSize: 15,
                fontWeight: FontWeight.bold,
              ),
            ),
          ),
        ),
        const SizedBox(height: 12),
        SizedBox(
          width: double.infinity,
          height: 48,
          child: OutlinedButton(
            onPressed: () {
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(
                  content: Text('Redirecting to Apple Pay / Google Pay...'),
                  behavior: SnackBarBehavior.floating,
                ),
              );
            },
            style: OutlinedButton.styleFrom(
              side: const BorderSide(color: Color(0xFFE5E7EB)),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(16),
              ),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: const [
                Icon(Icons.apple_rounded, size: 20, color: Color(0xFF1E1B4B)),
                SizedBox(width: 4),
                Text(
                  'Pay',
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.bold,
                    color: Color(0xFF1E1B4B),
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }

  /// Empty Cart View
  Widget _buildEmptyCart(BuildContext context, Color primaryColor, Color darkTextColor) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32.0),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              width: 90,
              height: 90,
              decoration: BoxDecoration(
                color: const Color(0xFFF5F3FF),
                shape: BoxShape.circle,
              ),
              child: const Icon(
                Icons.remove_shopping_cart_outlined,
                size: 42,
                color: Color(0xFF704FEE),
              ),
            ),
            const SizedBox(height: 20),
            Text(
              'Your cart is empty',
              style: TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.w800,
                color: darkTextColor,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              'Looks like you haven\'t added anything yet.',
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 13.5,
                color: Colors.grey.shade500,
              ),
            ),
            const SizedBox(height: 24),
            ElevatedButton(
              onPressed: () => context.go('/home'),
              style: ElevatedButton.styleFrom(
                backgroundColor: primaryColor,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(14),
                ),
              ),
              child: const Text('Start Shopping', style: TextStyle(fontWeight: FontWeight.bold)),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildHeaderIconButton({
    required IconData icon,
    int? badgeCount,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        width: 38,
        height: 38,
        decoration: BoxDecoration(
          color: const Color(0xFFF9FAFB),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: Colors.grey.shade200, width: 1),
        ),
        child: Stack(
          alignment: Alignment.center,
          children: [
            Icon(icon, size: 19, color: const Color(0xFF1E1B4B)),
            if (badgeCount != null)
              Positioned(
                top: 3,
                right: 3,
                child: Container(
                  padding: const EdgeInsets.all(3),
                  decoration: const BoxDecoration(
                    color: Color(0xFF704FEE),
                    shape: BoxShape.circle,
                  ),
                  constraints: const BoxConstraints(minWidth: 16, minHeight: 16),
                  child: Center(
                    child: Text(
                      '$badgeCount',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 8.5,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
