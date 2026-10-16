import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/network/app_exception.dart';
import '../../core/widgets/error_view.dart';
import '../../core/widgets/loading_view.dart';
import '../catalog/catalog_notifier.dart';
import '../catalog/models/product_model.dart';

final productDetailsProvider =
    FutureProvider.family<Product, String>((ref, productId) async {
  final repo = ref.watch(catalogRepositoryProvider);
  return repo.fetchProduct(productId);
});

class ProductDetailsScreen extends ConsumerStatefulWidget {
  final String productId;

  const ProductDetailsScreen({super.key, required this.productId});

  @override
  ConsumerState<ProductDetailsScreen> createState() =>
      _ProductDetailsScreenState();
}

class _ProductDetailsScreenState extends ConsumerState<ProductDetailsScreen> {
  int _selectedImageIndex = 0;
  ProductVariant? _selectedVariant;
  bool _isWishlisted = false;

  @override
  Widget build(BuildContext context) {
    final productAsync = ref.watch(productDetailsProvider(widget.productId));
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Product Details'),
        actions: [
          IconButton(
            icon: Icon(
              _isWishlisted ? Icons.favorite : Icons.favorite_border,
              color: _isWishlisted ? Colors.rose : null,
            ),
            onPressed: () {
              setState(() {
                _isWishlisted = !_isWishlisted;
              });
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(
                  content: Text(
                    _isWishlisted
                        ? 'Added to wishlist'
                        : 'Removed from wishlist',
                  ),
                  duration: const Duration(seconds: 1),
                ),
              );
            },
          ),
        ],
      ),
      body: productAsync.when(
        loading: () => const LoadingView(message: 'Loading product details...'),
        error: (err, _) => ErrorView(
          title: 'Product Unavailable',
          message: err is AppException ? err.message : err.toString(),
          onRetry: () => ref.refresh(productDetailsProvider(widget.productId)),
        ),
        data: (product) {
          final activeVariant = _selectedVariant ??
              (product.variants.isNotEmpty ? product.variants.first : null);
          final activePrice = activeVariant?.price ?? product.price;
          final isInStock = activeVariant?.inStock ?? product.inStock;
          final currencySymbol = product.currency == 'INR' ? '₹' : '\$';

          return Column(
            children: [
              Expanded(
                child: SingleChildScrollView(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // Image Gallery
                      AspectRatio(
                        aspectRatio: 1.1,
                        child: Container(
                          color: Colors.grey.shade50,
                          child: product.images.isNotEmpty
                              ? CachedNetworkImage(
                                  imageUrl: product.images[_selectedImageIndex],
                                  fit: BoxFit.contain,
                                  placeholder: (_, __) => const Center(
                                    child: CircularProgressIndicator(),
                                  ),
                                  errorWidget: (_, __, ___) => Icon(
                                    Icons.broken_image_rounded,
                                    size: 48,
                                    color: Colors.grey.shade400,
                                  ),
                                )
                              : Icon(
                                  Icons.shopping_bag_outlined,
                                  size: 64,
                                  color: Colors.grey.shade300,
                                ),
                        ),
                      ),

                      // Image Thumbnails row
                      if (product.images.length > 1)
                        Container(
                          height: 72,
                          padding: const EdgeInsets.symmetric(
                              horizontal: 16, vertical: 8),
                          child: ListView.separated(
                            scrollDirection: Axis.horizontal,
                            itemCount: product.images.length,
                            separatorBuilder: (_, __) =>
                                const SizedBox(width: 8),
                            itemBuilder: (context, idx) {
                              final isSelected = _selectedImageIndex == idx;
                              return GestureDetector(
                                onTap: () {
                                  setState(() {
                                    _selectedImageIndex = idx;
                                  });
                                },
                                child: Container(
                                  width: 56,
                                  decoration: BoxDecoration(
                                    border: Border.all(
                                      color: isSelected
                                          ? theme.colorScheme.primary
                                          : Colors.grey.shade300,
                                      width: isSelected ? 2 : 1,
                                    ),
                                    borderRadius: BorderRadius.circular(8),
                                  ),
                                  clipBehavior: Clip.antiAlias,
                                  child: CachedNetworkImage(
                                    imageUrl: product.images[idx],
                                    fit: BoxFit.cover,
                                  ),
                                ),
                              );
                            },
                          ),
                        ),

                      Padding(
                        padding: const EdgeInsets.all(20.0),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            // Title & Price
                            Text(
                              product.title,
                              style: theme.textTheme.titleLarge?.copyWith(
                                fontWeight: FontWeight.bold,
                                letterSpacing: -0.3,
                              ),
                            ),
                            const SizedBox(height: 12),
                            Row(
                              children: [
                                Text(
                                  '$currencySymbol${activePrice.toStringAsFixed(2)}',
                                  style: TextStyle(
                                    fontSize: 22,
                                    fontWeight: FontWeight.bold,
                                    color: theme.colorScheme.primary,
                                  ),
                                ),
                                if (product.compareAtPrice != null &&
                                    product.compareAtPrice! > activePrice) ...[
                                  const SizedBox(width: 10),
                                  Text(
                                    '$currencySymbol${product.compareAtPrice!.toStringAsFixed(2)}',
                                    style: TextStyle(
                                      fontSize: 16,
                                      color: Colors.grey.shade400,
                                      decoration: TextDecoration.lineThrough,
                                    ),
                                  ),
                                ],
                                const Spacer(),
                                Container(
                                  padding: const EdgeInsets.symmetric(
                                      horizontal: 10, vertical: 4),
                                  decoration: BoxDecoration(
                                    color: isInStock
                                        ? Colors.emerald.withOpacity(0.1)
                                        : Colors.red.withOpacity(0.1),
                                    borderRadius: BorderRadius.circular(6),
                                  ),
                                  child: Text(
                                    isInStock ? 'IN STOCK' : 'OUT OF STOCK',
                                    style: TextStyle(
                                      fontSize: 11,
                                      fontWeight: FontWeight.bold,
                                      color: isInStock
                                          ? const Color(0xFF059669)
                                          : const Color(0xFFDC2626),
                                    ),
                                  ),
                                ),
                              ],
                            ),

                            // Variants Selector
                            if (product.variants.isNotEmpty) ...[
                              const SizedBox(height: 24),
                              Text(
                                'Select Variant',
                                style: theme.textTheme.titleSmall?.copyWith(
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                              const SizedBox(height: 10),
                              Wrap(
                                spacing: 8,
                                runSpacing: 8,
                                children: product.variants.map((v) {
                                  final isSelected = activeVariant?.id == v.id;
                                  return ChoiceChip(
                                    label: Text(v.title),
                                    selected: isSelected,
                                    onSelected: (selected) {
                                      if (selected) {
                                        setState(() {
                                          _selectedVariant = v;
                                        });
                                      }
                                    },
                                    selectedColor: theme.colorScheme.primary
                                        .withOpacity(0.15),
                                    labelStyle: TextStyle(
                                      color: isSelected
                                          ? theme.colorScheme.primary
                                          : Colors.grey.shade800,
                                      fontWeight: isSelected
                                          ? FontWeight.bold
                                          : FontWeight.normal,
                                    ),
                                  );
                                }).toList(),
                              ),
                            ],

                            // Description
                            const SizedBox(height: 24),
                            Text(
                              'Product Description',
                              style: theme.textTheme.titleSmall?.copyWith(
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            const SizedBox(height: 8),
                            Text(
                              product.description.isNotEmpty
                                  ? product.description
                                  : 'No additional description provided for this item.',
                              style: TextStyle(
                                fontSize: 13,
                                color: Colors.grey.shade700,
                                height: 1.5,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),

              // Bottom Action Bar: Add to Cart
              Container(
                padding: const EdgeInsets.all(16.0),
                decoration: BoxDecoration(
                  color: theme.scaffoldBackgroundColor,
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withOpacity(0.05),
                      blurRadius: 10,
                      offset: const Offset(0, -2),
                    ),
                  ],
                ),
                child: SafeArea(
                  child: Row(
                    children: [
                      Expanded(
                        child: ElevatedButton.icon(
                          onPressed: isInStock
                              ? () {
                                  ScaffoldMessenger.of(context).showSnackBar(
                                    SnackBar(
                                      content: Text(
                                        'Added ${product.title} to your cart!',
                                      ),
                                    ),
                                  );
                                }
                              : null,
                          icon: const Icon(Icons.shopping_bag_outlined),
                          label: Text(
                            isInStock ? 'Add to Cart' : 'Currently Unavailable',
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}
