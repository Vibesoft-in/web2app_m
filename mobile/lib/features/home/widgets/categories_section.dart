import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../catalog/catalog_notifier.dart';
import '../../catalog/models/product_model.dart';

final categoriesProvider = FutureProvider<List<Category>>((ref) async {
  final repo = ref.watch(catalogRepositoryProvider);
  return repo.fetchCategories();
});

class CategoriesSection extends ConsumerWidget {
  final void Function(Category category)? onCategoryTap;

  const CategoriesSection({super.key, this.onCategoryTap});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final categoriesAsync = ref.watch(categoriesProvider);
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
                'Shop by Category',
                style: theme.textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.bold,
                ),
              ),
              Text(
                'See all',
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
          height: 48,
          child: categoriesAsync.when(
            loading: () => const Center(
              child: SizedBox(
                width: 20,
                height: 20,
                child: CircularProgressIndicator(strokeWidth: 2),
              ),
            ),
            error: (_, __) => _buildFallbackCategories(context),
            data: (cats) {
              final list = cats.isNotEmpty
                  ? cats
                  : const [
                      Category(id: '1', name: 'Skincare', slug: 'skincare'),
                      Category(id: '2', name: 'Serums', slug: 'serums'),
                      Category(id: '3', name: 'Organic', slug: 'organic'),
                      Category(id: '4', name: 'Bestsellers', slug: 'best'),
                    ];

              return ListView.separated(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 16.0),
                itemCount: list.length,
                separatorBuilder: (_, __) => const SizedBox(width: 8),
                itemBuilder: (context, index) {
                  final cat = list[index];
                  return ActionChip(
                    label: Text(cat.name),
                    onPressed: () => onCategoryTap?.call(cat),
                    backgroundColor: theme.colorScheme.surface,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(20),
                      side: BorderSide(color: Colors.grey.shade200),
                    ),
                    labelStyle: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
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

  Widget _buildFallbackCategories(BuildContext context) {
    const fallbacks = ['Skincare', 'Serums', 'Organic', 'Bath & Body'];
    return ListView.separated(
      scrollDirection: Axis.horizontal,
      padding: const EdgeInsets.symmetric(horizontal: 16.0),
      itemCount: fallbacks.length,
      separatorBuilder: (_, __) => const SizedBox(width: 8),
      itemBuilder: (context, index) {
        return Chip(
          label: Text(fallbacks[index]),
          backgroundColor: Colors.grey.shade100,
          labelStyle: const TextStyle(fontSize: 12),
        );
      },
    );
  }
}
