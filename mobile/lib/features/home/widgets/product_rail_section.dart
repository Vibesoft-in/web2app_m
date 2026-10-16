import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/config/app_config_model.dart';
import '../../catalog/catalog_notifier.dart';
import '../../catalog/models/product_model.dart';
import '../../catalog/widgets/product_card.dart';

final railProductsProvider =
    FutureProvider.family<List<Product>, String>((ref, source) async {
  final repo = ref.watch(catalogRepositoryProvider);
  try {
    final list = await repo.fetchProducts(page: 1, perPage: 6);
    if (list.isNotEmpty) return list;
  } catch (_) {}
  return _mockRailProducts;
});

const _mockRailProducts = [
  Product(
    id: '1',
    title: 'Vitamin C Brightening Serum',
    price: 29.99,
    compareAtPrice: 39.99,
    currency: 'USD',
    images: ['https://images.unsplash.com/photo-1620916566398-39f1143ab7be?w=500&q=80'],
    description: 'Brightening vitamin C facial serum for radiant skin.',
    inStock: true,
    variants: [],
    categories: ['Serums'],
  ),
  Product(
    id: '2',
    title: 'Hydrating Face Cream',
    price: 24.50,
    currency: 'USD',
    images: ['https://images.unsplash.com/photo-1556228720-195a672e8a03?w=500&q=80'],
    description: 'Deep moisture cream with hyaluronic acid.',
    inStock: true,
    variants: [],
    categories: ['Skincare'],
  ),
  Product(
    id: '3',
    title: 'Botanical Body Oil',
    price: 34.00,
    compareAtPrice: 45.00,
    currency: 'USD',
    images: ['https://images.unsplash.com/photo-1608248597359-00f68285517d?w=500&q=80'],
    description: 'Nourishing organic botanical body oil.',
    inStock: true,
    variants: [],
    categories: ['Bath & Body'],
  ),
];

class ProductRailSection extends ConsumerWidget {
  final HomeSection section;
  final void Function(Product product)? onProductTap;

  const ProductRailSection({
    super.key,
    required this.section,
    this.onProductTap,
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final productsAsync =
        ref.watch(railProductsProvider(section.source ?? 'recent'));
    final theme = Theme.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16.0, vertical: 8.0),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                section.title ?? 'Featured Products',
                style: theme.textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.bold,
                ),
              ),
              Text(
                'View all',
                style: TextStyle(
                  fontSize: 12,
                  color: theme.colorScheme.primary,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ),
        SizedBox(
          height: 230,
          child: productsAsync.when(
            loading: () => const Center(
              child: SizedBox(
                width: 24,
                height: 24,
                child: CircularProgressIndicator(strokeWidth: 2),
              ),
            ),
            error: (_, __) => const SizedBox.shrink(),
            data: (products) {
              if (products.isEmpty) return const SizedBox.shrink();

              return ListView.separated(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 16.0),
                itemCount: products.length,
                separatorBuilder: (_, __) => const SizedBox(width: 12),
                itemBuilder: (context, index) {
                  final p = products[index];
                  return SizedBox(
                    width: 150,
                    child: ProductCard(
                      product: p,
                      onTap: () => onProductTap?.call(p),
                    ),
                  );
                },
              );
            },
          ),
        ),
      ],
    );
  }
}
