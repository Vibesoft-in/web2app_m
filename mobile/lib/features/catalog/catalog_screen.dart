import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../core/widgets/empty_view.dart';
import '../../core/widgets/error_view.dart';
import '../../core/widgets/loading_view.dart';
import 'catalog_notifier.dart';
import 'widgets/product_card.dart';

class CatalogScreen extends ConsumerStatefulWidget {
  final String? initialCategory;

  const CatalogScreen({super.key, this.initialCategory});

  @override
  ConsumerState<CatalogScreen> createState() => _CatalogScreenState();
}

class _CatalogScreenState extends ConsumerState<CatalogScreen> {
  final ScrollController _scrollController = ScrollController();
  final TextEditingController _searchController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_onScroll);
  }

  void _onScroll() {
    if (_scrollController.position.pixels >=
        _scrollController.position.maxScrollExtent - 200) {
      ref.read(catalogNotifierProvider.notifier).loadMore();
    }
  }

  @override
  void dispose() {
    _scrollController.dispose();
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final catalogState = ref.watch(catalogNotifierProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Catalog'),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(60),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16.0, vertical: 8.0),
            child: TextField(
              controller: _searchController,
              onChanged: (val) {
                ref.read(catalogNotifierProvider.notifier).onSearchChanged(val);
              },
              decoration: InputDecoration(
                hintText: 'Search products by name or tag...',
                prefixIcon: const Icon(Icons.search, size: 20),
                suffixIcon: _searchController.text.isNotEmpty
                    ? IconButton(
                        icon: const Icon(Icons.clear, size: 18),
                        onPressed: () {
                          _searchController.clear();
                          ref
                              .read(catalogNotifierProvider.notifier)
                              .onSearchChanged('');
                        },
                      )
                    : null,
                contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 0),
              ),
            ),
          ),
        ),
      ),
      body: Builder(
        builder: (context) {
          // Loading state
          if (catalogState.isLoading) {
            return const LoadingView(message: 'Loading products...');
          }

          // Error state
          if (catalogState.error != null) {
            return ErrorView(
              title: 'Unable to load products',
              message: catalogState.error!.message,
              onRetry: () =>
                  ref.read(catalogNotifierProvider.notifier).loadInitial(),
            );
          }

          // Empty state
          if (catalogState.isEmpty) {
            return EmptyView(
              title: 'No products found',
              message: catalogState.searchQuery.isNotEmpty
                  ? 'No results matched "${catalogState.searchQuery}". Try different keywords.'
                  : 'There are currently no products listed in this catalog.',
              icon: Icons.search_off_rounded,
              actionLabel: catalogState.searchQuery.isNotEmpty ? 'Clear Search' : null,
              onAction: catalogState.searchQuery.isNotEmpty
                  ? () {
                      _searchController.clear();
                      ref.read(catalogNotifierProvider.notifier).onSearchChanged('');
                    }
                  : null,
            );
          }

          // Data state with pagination
          return RefreshIndicator(
            onRefresh: () => ref.read(catalogNotifierProvider.notifier).refresh(),
            child: GridView.builder(
              controller: _scrollController,
              padding: const EdgeInsets.all(16.0),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 2,
                childAspectRatio: 0.68,
                crossAxisSpacing: 12,
                mainAxisSpacing: 12,
              ),
              itemCount: catalogState.products.length +
                  (catalogState.isLoadingMore ? 2 : 0),
              itemBuilder: (context, index) {
                if (index >= catalogState.products.length) {
                  return const Center(
                    child: Padding(
                      padding: EdgeInsets.all(16.0),
                      child: CircularProgressIndicator(strokeWidth: 2),
                    ),
                  );
                }

                final product = catalogState.products[index];
                return ProductCard(
                  product: product,
                  onTap: () {
                    context.push('/product/${product.id}');
                  },
                );
              },
            ),
          );
        },
      ),
    );
  }
}
