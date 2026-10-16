import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../core/data/mock_products.dart';
import '../../core/providers/shop_providers.dart';
import '../../core/services/woocommerce_service.dart';

class ProductDetailsScreen extends ConsumerStatefulWidget {
  final String productId;

  const ProductDetailsScreen({super.key, required this.productId});

  @override
  ConsumerState<ProductDetailsScreen> createState() =>
      _ProductDetailsScreenState();
}

class _ProductDetailsScreenState extends ConsumerState<ProductDetailsScreen> {
  int _selectedColorIndex = 0;
  int _quantity = 1;
  bool _isDescriptionExpanded = false;

  Color _parseHex(String hexString) {
    final buffer = StringBuffer();
    if (hexString.length == 6 || hexString.length == 7) buffer.write('ff');
    buffer.write(hexString.replaceFirst('#', ''));
    return Color(int.parse(buffer.toString(), radix: 16));
  }

  @override
  Widget build(BuildContext context) {
    final productsAsync = ref.watch(wcProductsProvider);
    final allWc = productsAsync.value ?? WooCommerceService.initialProducts;
    DetailedProduct? resolvedWcProduct;
    for (final p in allWc) {
      if (p.id == widget.productId) {
        resolvedWcProduct = p.toDetailedProduct();
        break;
      }
    }
    final product = resolvedWcProduct ?? getProductById(widget.productId);
    final wishlistedIds = ref.watch(wishlistProvider);
    final cartCount = ref.watch(cartCountProvider);
    final isWishlisted = wishlistedIds.contains(product.id);

    const primaryColor = Color(0xFF704FEE);
    const darkTextColor = Color(0xFF1E1B4B);

    return Scaffold(
      backgroundColor: const Color(0xFFF3F4F6),
      body: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 500),
          child: Container(
            color: Colors.white,
            child: SafeArea(
              child: Column(
          children: [
            // Top Custom Bar
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 18.0, vertical: 10.0),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  // Back Button (circular)
                  InkWell(
                    onTap: () => context.pop(),
                    borderRadius: BorderRadius.circular(20),
                    child: Container(
                      width: 40,
                      height: 40,
                      decoration: BoxDecoration(
                        color: const Color(0xFFF9FAFB),
                        shape: BoxShape.circle,
                        border: Border.all(color: Colors.grey.shade200),
                      ),
                      child: const Icon(
                        Icons.arrow_back_ios_new_rounded,
                        size: 16,
                        color: darkTextColor,
                      ),
                    ),
                  ),

                  // Right Icons: Wishlist + Cart
                  Row(
                    children: [
                      // Wishlist Toggle
                      InkWell(
                        onTap: () {
                          ref.read(wishlistProvider.notifier).toggle(product.id);
                          ScaffoldMessenger.of(context).showSnackBar(
                            SnackBar(
                              content: Text(
                                !isWishlisted
                                    ? 'Added to Wishlist'
                                    : 'Removed from Wishlist',
                              ),
                              duration: const Duration(seconds: 1),
                              behavior: SnackBarBehavior.floating,
                            ),
                          );
                        },
                        borderRadius: BorderRadius.circular(20),
                        child: Container(
                          width: 40,
                          height: 40,
                          decoration: BoxDecoration(
                            color: const Color(0xFFF9FAFB),
                            shape: BoxShape.circle,
                            border: Border.all(color: Colors.grey.shade200),
                          ),
                          child: Icon(
                            isWishlisted
                                ? Icons.favorite_rounded
                                : Icons.favorite_border_rounded,
                            size: 20,
                            color: isWishlisted ? const Color(0xFFEF4444) : darkTextColor,
                          ),
                        ),
                      ),
                      const SizedBox(width: 10),

                      // Cart with Badge
                      InkWell(
                        onTap: () => context.push('/cart'),
                        borderRadius: BorderRadius.circular(20),
                        child: Container(
                          width: 40,
                          height: 40,
                          decoration: BoxDecoration(
                            color: const Color(0xFFF9FAFB),
                            shape: BoxShape.circle,
                            border: Border.all(color: Colors.grey.shade200),
                          ),
                          child: Stack(
                            alignment: Alignment.center,
                            children: [
                              const Icon(
                                Icons.shopping_bag_outlined,
                                size: 20,
                                color: darkTextColor,
                              ),
                              if (cartCount > 0)
                                Positioned(
                                  top: 6,
                                  right: 6,
                                  child: Container(
                                    padding: const EdgeInsets.all(3),
                                    decoration: const BoxDecoration(
                                      color: primaryColor,
                                      shape: BoxShape.circle,
                                    ),
                                    constraints: const BoxConstraints(
                                      minWidth: 14,
                                      minHeight: 14,
                                    ),
                                    child: Center(
                                      child: Text(
                                        '$cartCount',
                                        style: const TextStyle(
                                          color: Colors.white,
                                          fontSize: 8,
                                          fontWeight: FontWeight.bold,
                                        ),
                                      ),
                                    ),
                                  ),
                                ),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),

            // Scrollable Content
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.symmetric(horizontal: 18.0),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Hero Image on Lilac Pedestal Container
                    Container(
                      width: double.infinity,
                      height: 290,
                      decoration: BoxDecoration(
                        color: const Color(0xFFF5F3FF),
                        borderRadius: BorderRadius.circular(28),
                      ),
                      child: Stack(
                        alignment: Alignment.center,
                        children: [
                          ClipRRect(
                            borderRadius: BorderRadius.circular(28),
                            child: CachedNetworkImage(
                              imageUrl: product.images.first,
                              width: double.infinity,
                              height: double.infinity,
                              fit: BoxFit.cover,
                              placeholder: (_, __) => const Center(
                                child: CircularProgressIndicator(strokeWidth: 2),
                              ),
                              errorWidget: (_, __, ___) => const Icon(
                                Icons.image,
                                size: 60,
                                color: Colors.grey,
                              ),
                            ),
                          ),

                          // 1/5 Pill Indicator on bottom right
                          Positioned(
                            bottom: 14,
                            right: 14,
                            child: Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 10,
                                vertical: 4,
                              ),
                              decoration: BoxDecoration(
                                color: Colors.white.withOpacity(0.9),
                                borderRadius: BorderRadius.circular(12),
                                boxShadow: [
                                  BoxShadow(
                                    color: Colors.black.withOpacity(0.06),
                                    blurRadius: 4,
                                  ),
                                ],
                              ),
                              child: const Text(
                                '1/5',
                                style: TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.bold,
                                  color: darkTextColor,
                                ),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 18),

                    // Title
                    Text(
                      product.title,
                      style: const TextStyle(
                        fontSize: 22,
                        fontWeight: FontWeight.w800,
                        color: darkTextColor,
                        letterSpacing: -0.4,
                      ),
                    ),
                    const SizedBox(height: 8),

                    // Price & Rating Row
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          '\$${product.price.toStringAsFixed(2)}',
                          style: const TextStyle(
                            fontSize: 20,
                            fontWeight: FontWeight.w900,
                            color: darkTextColor,
                          ),
                        ),
                        Row(
                          children: [
                            const Icon(
                              Icons.star_rounded,
                              size: 18,
                              color: Color(0xFFF59E0B),
                            ),
                            const SizedBox(width: 4),
                            Text(
                              '${product.rating}',
                              style: const TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.bold,
                                color: darkTextColor,
                              ),
                            ),
                            const SizedBox(width: 4),
                            const Icon(
                              Icons.star_rounded,
                              size: 14,
                              color: Color(0xFFCBD5E1),
                            ),
                            const Icon(
                              Icons.star_rounded,
                              size: 14,
                              color: Color(0xFFCBD5E1),
                            ),
                            const SizedBox(width: 4),
                            Text(
                              '(${product.reviewsCount})',
                              style: TextStyle(
                                fontSize: 12,
                                color: Colors.grey.shade500,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                    const SizedBox(height: 20),

                    // Color Selector
                    const Text(
                      'Color',
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.bold,
                        color: darkTextColor,
                      ),
                    ),
                    const SizedBox(height: 10),
                    Row(
                      children: List.generate(product.colors.length, (index) {
                        final colorHex = product.colors[index];
                        final isSelected = _selectedColorIndex == index;
                        final color = _parseHex(colorHex);

                        return GestureDetector(
                          onTap: () => setState(() => _selectedColorIndex = index),
                          child: Container(
                            margin: const EdgeInsets.only(right: 12),
                            padding: const EdgeInsets.all(3),
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              border: Border.all(
                                color: isSelected
                                    ? primaryColor
                                    : Colors.transparent,
                                width: 2,
                              ),
                            ),
                            child: Container(
                              width: 28,
                              height: 28,
                              decoration: BoxDecoration(
                                color: color,
                                shape: BoxShape.circle,
                                border: Border.all(
                                  color: Colors.grey.shade300,
                                  width: 0.8,
                                ),
                              ),
                            ),
                          ),
                        );
                      }),
                    ),
                    const SizedBox(height: 22),

                    // Description Section
                    const Text(
                      'Description',
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.bold,
                        color: darkTextColor,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      product.description,
                      maxLines: _isDescriptionExpanded ? null : 3,
                      overflow: _isDescriptionExpanded
                          ? TextOverflow.visible
                          : TextOverflow.ellipsis,
                      style: TextStyle(
                        fontSize: 13,
                        color: Colors.grey.shade600,
                        height: 1.5,
                      ),
                    ),
                    const SizedBox(height: 4),
                    GestureDetector(
                      onTap: () {
                        setState(() {
                          _isDescriptionExpanded = !_isDescriptionExpanded;
                        });
                      },
                      child: Text(
                        _isDescriptionExpanded ? 'Read Less' : 'Read More',
                        style: const TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.bold,
                          color: primaryColor,
                        ),
                      ),
                    ),
                    const SizedBox(height: 22),

                    // Features Section
                    const Text(
                      'Features',
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.bold,
                        color: darkTextColor,
                      ),
                    ),
                    const SizedBox(height: 12),
                    ...product.features.map(
                      (feat) => Padding(
                        padding: const EdgeInsets.only(bottom: 8.0),
                        child: Row(
                          children: [
                            Container(
                              padding: const EdgeInsets.all(2),
                              decoration: BoxDecoration(
                                shape: BoxShape.circle,
                                border: Border.all(
                                  color: const Color(0xFF6B7280),
                                  width: 1.5,
                                ),
                              ),
                              child: const Icon(
                                Icons.check,
                                size: 11,
                                color: Color(0xFF374151),
                              ),
                            ),
                            const SizedBox(width: 10),
                            Text(
                              feat,
                              style: const TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.w500,
                                color: Color(0xFF374151),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 24),
                  ],
                ),
              ),
            ),

            // Bottom Sticky Bar (Quantity + Add to Cart)
            Container(
              padding: const EdgeInsets.fromLTRB(18, 12, 18, 16),
              decoration: BoxDecoration(
                color: Colors.white,
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.05),
                    blurRadius: 10,
                    offset: const Offset(0, -3),
                  ),
                ],
              ),
              child: Row(
                children: [
                  // Quantity Box [- 1 +]
                  Container(
                    height: 50,
                    decoration: BoxDecoration(
                      color: const Color(0xFFF9FAFB),
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: Colors.grey.shade200),
                    ),
                    padding: const EdgeInsets.symmetric(horizontal: 10),
                    child: Row(
                      children: [
                        IconButton(
                          padding: EdgeInsets.zero,
                          constraints: const BoxConstraints(minWidth: 28),
                          icon: const Icon(Icons.remove, size: 16),
                          onPressed: () {
                            if (_quantity > 1) {
                              setState(() => _quantity--);
                            }
                          },
                        ),
                        Text(
                          '$_quantity',
                          style: const TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.bold,
                            color: darkTextColor,
                          ),
                        ),
                        IconButton(
                          padding: EdgeInsets.zero,
                          constraints: const BoxConstraints(minWidth: 28),
                          icon: const Icon(Icons.add, size: 16),
                          onPressed: () {
                            setState(() => _quantity++);
                          },
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 14),

                  // Add to Cart Button
                  Expanded(
                    child: SizedBox(
                      height: 50,
                      child: ElevatedButton.icon(
                        onPressed: () {
                          ref.read(cartItemsProvider.notifier).addItem(
                                id: product.id,
                                title: product.title,
                                price: product.price,
                                image: product.images.first,
                                quantity: _quantity,
                                selectedColor: product.colors.isNotEmpty
                                    ? product.colors[_selectedColorIndex]
                                    : '',
                              );
                          ScaffoldMessenger.of(context).showSnackBar(
                            SnackBar(
                              content: Row(
                                children: [
                                  const Icon(Icons.check_circle, color: Colors.white, size: 18),
                                  const SizedBox(width: 8),
                                  Text('Added $_quantity "${product.title}" to cart!'),
                                ],
                              ),
                              backgroundColor: primaryColor,
                              behavior: SnackBarBehavior.floating,
                              duration: const Duration(seconds: 2),
                            ),
                          );
                        },
                        icon: const Icon(Icons.shopping_cart_outlined, size: 18),
                        label: const Text(
                          'Add to Cart',
                          style: TextStyle(
                            fontSize: 14.5,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: primaryColor,
                          foregroundColor: Colors.white,
                          elevation: 0,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(16),
                          ),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    ),
  ),
),
);
  }
}
