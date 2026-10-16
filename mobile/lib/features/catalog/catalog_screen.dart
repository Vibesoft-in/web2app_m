import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../core/providers/shop_providers.dart';
import '../../core/services/woocommerce_service.dart';
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
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (widget.initialCategory != null && widget.initialCategory!.isNotEmpty) {
        ref.read(catalogNotifierProvider.notifier).setCategory(widget.initialCategory);
      }
    });
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
    final wcCategoriesAsync = ref.watch(wcCategoriesProvider);
    final allCategories = wcCategoriesAsync.value ?? WooCommerceService.initialCategories;
    final wishlist = ref.watch(wishlistProvider);
    final cartCount = ref.watch(cartCountProvider);

    const primaryColor = Color(0xFF704FEE);
    const darkTextColor = Color(0xFF1E1B4B);

    final categoryTabs = ['All', ...allCategories.map((c) => c.name)];
    final currentCat = catalogState.selectedCategory ?? 'All';

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 18, color: darkTextColor),
          tooltip: 'Back',
          onPressed: () {
            if (context.canPop()) {
              context.pop();
            } else {
              context.go('/home');
            }
          },
        ),
        title: const Text(
          'Categories & Catalog',
          style: TextStyle(
            color: darkTextColor,
            fontSize: 18,
            fontWeight: FontWeight.w800,
          ),
        ),
        centerTitle: true,
        actions: [
          // Wishlist Icon with live badge
          InkWell(
            onTap: () => context.push('/wishlist'),
            borderRadius: BorderRadius.circular(12),
            child: Container(
              width: 38,
              height: 38,
              decoration: BoxDecoration(
                color: const Color(0xFFF9FAFB),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: Colors.grey.shade200),
              ),
              child: Stack(
                alignment: Alignment.center,
                children: [
                  const Icon(Icons.favorite_border_rounded, size: 19, color: darkTextColor),
                  if (wishlist.isNotEmpty)
                    Positioned(
                      top: 4,
                      right: 4,
                      child: Container(
                        padding: const EdgeInsets.all(3),
                        decoration: const BoxDecoration(
                          color: primaryColor,
                          shape: BoxShape.circle,
                        ),
                        constraints: const BoxConstraints(minWidth: 14, minHeight: 14),
                        child: Center(
                          child: Text(
                            '${wishlist.length}',
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
          const SizedBox(width: 8),

          // Cart Icon with live badge
          InkWell(
            onTap: () => context.push('/cart'),
            borderRadius: BorderRadius.circular(12),
            child: Container(
              width: 38,
              height: 38,
              decoration: BoxDecoration(
                color: const Color(0xFFF9FAFB),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: Colors.grey.shade200),
              ),
              child: Stack(
                alignment: Alignment.center,
                children: [
                  const Icon(Icons.shopping_bag_outlined, size: 19, color: darkTextColor),
                  if (cartCount > 0)
                    Positioned(
                      top: 4,
                      right: 4,
                      child: Container(
                        padding: const EdgeInsets.all(3),
                        decoration: const BoxDecoration(
                          color: primaryColor,
                          shape: BoxShape.circle,
                        ),
                        constraints: const BoxConstraints(minWidth: 14, minHeight: 14),
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
          const SizedBox(width: 14),
        ],
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(108),
          child: Column(
            children: [
              // 1. Search Bar
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16.0, vertical: 4.0),
                child: Container(
                  decoration: BoxDecoration(
                    color: const Color(0xFFF1F5F9),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: TextField(
                    controller: _searchController,
                    onChanged: (val) {
                      ref.read(catalogNotifierProvider.notifier).onSearchChanged(val);
                    },
                    decoration: InputDecoration(
                      hintText: 'Search products by name or tag...',
                      hintStyle: TextStyle(color: Colors.grey.shade500, fontSize: 13.5),
                      prefixIcon: const Icon(Icons.search, size: 20, color: Color(0xFF704FEE)),
                      suffixIcon: _searchController.text.isNotEmpty
                          ? IconButton(
                              icon: const Icon(Icons.clear, size: 18),
                              onPressed: () {
                                _searchController.clear();
                                ref.read(catalogNotifierProvider.notifier).onSearchChanged('');
                              },
                            )
                          : null,
                      border: InputBorder.none,
                      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 6),

              // 2. Horizontal Category Filter Chips (All, Cat1, Cat2, Cat3)
              SizedBox(
                height: 42,
                child: ListView.builder(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.symmetric(horizontal: 14),
                  itemCount: categoryTabs.length,
                  itemBuilder: (context, index) {
                    final cat = categoryTabs[index];
                    final isSelected = currentCat.toLowerCase() == cat.toLowerCase();

                    return Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 4.0, vertical: 3.0),
                      child: InkWell(
                        onTap: () {
                          ref.read(catalogNotifierProvider.notifier).setCategory(cat == 'All' ? null : cat);
                        },
                        borderRadius: BorderRadius.circular(20),
                        child: AnimatedContainer(
                          duration: const Duration(milliseconds: 200),
                          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
                          decoration: BoxDecoration(
                            color: isSelected ? primaryColor : Colors.white,
                            borderRadius: BorderRadius.circular(20),
                            border: Border.all(
                              color: isSelected ? primaryColor : Colors.grey.shade300,
                              width: 1.2,
                            ),
                            boxShadow: isSelected
                                ? [
                                    BoxShadow(
                                      color: primaryColor.withOpacity(0.25),
                                      blurRadius: 6,
                                      offset: const Offset(0, 2),
                                    ),
                                  ]
                                : null,
                          ),
                          child: Center(
                            child: Text(
                              cat,
                              style: TextStyle(
                                fontSize: 12.5,
                                fontWeight: isSelected ? FontWeight.w700 : FontWeight.w600,
                                color: isSelected ? Colors.white : darkTextColor,
                              ),
                            ),
                          ),
                        ),
                      ),
                    );
                  },
                ),
              ),
              const SizedBox(height: 6),
            ],
          ),
        ),
      ),
      body: Builder(
        builder: (context) {
          // Loading state
          if (catalogState.isLoading) {
            return const LoadingView(message: 'Loading store products...');
          }

          // Error state
          if (catalogState.error != null) {
            return ErrorView(
              title: 'Unable to load products',
              message: catalogState.error!.message,
              onRetry: () => ref.read(catalogNotifierProvider.notifier).loadInitial(),
            );
          }

          // Empty state
          if (catalogState.isEmpty) {
            return EmptyView(
              title: 'No products found',
              message: catalogState.searchQuery.isNotEmpty
                  ? 'No products matched "${catalogState.searchQuery}". Try different keywords.'
                  : 'There are currently no products listed in category "$currentCat".',
              icon: Icons.search_off_rounded,
              actionLabel: 'Show All Products',
              onAction: () {
                _searchController.clear();
                ref.read(catalogNotifierProvider.notifier).onSearchChanged('');
                ref.read(catalogNotifierProvider.notifier).setCategory(null);
              },
            );
          }

          // Data state - Strictly showing products from testing.livaan.in
          return RefreshIndicator(
            onRefresh: () => ref.read(catalogNotifierProvider.notifier).refresh(),
            child: GridView.builder(
              controller: _scrollController,
              padding: const EdgeInsets.all(16.0),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 2,
                childAspectRatio: 0.68,
                crossAxisSpacing: 14,
                mainAxisSpacing: 16,
              ),
              itemCount: catalogState.products.length,
              itemBuilder: (context, index) {
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
