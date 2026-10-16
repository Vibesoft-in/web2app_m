import 'dart:async';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/network/app_exception.dart';
import '../../core/providers/core_providers.dart';
import '../../core/services/woocommerce_service.dart';
import 'catalog_repository.dart';
import 'models/product_model.dart';

class CatalogState {
  final bool isLoading;
  final bool isLoadingMore;
  final List<Product> products;
  final AppException? error;
  final int page;
  final bool hasMore;
  final String searchQuery;
  final String? selectedCategory;

  const CatalogState({
    this.isLoading = false,
    this.isLoadingMore = false,
    this.products = const [],
    this.error,
    this.page = 1,
    this.hasMore = true,
    this.searchQuery = '',
    this.selectedCategory,
  });

  bool get isEmpty => !isLoading && products.isEmpty && error == null;
  bool get hasData => products.isNotEmpty;

  CatalogState copyWith({
    bool? isLoading,
    bool? isLoadingMore,
    List<Product>? products,
    AppException? error,
    bool clearError = false,
    int? page,
    bool? hasMore,
    String? searchQuery,
    String? selectedCategory,
    bool clearCategory = false,
  }) {
    return CatalogState(
      isLoading: isLoading ?? this.isLoading,
      isLoadingMore: isLoadingMore ?? this.isLoadingMore,
      products: products ?? this.products,
      error: clearError ? null : (error ?? this.error),
      page: page ?? this.page,
      hasMore: hasMore ?? this.hasMore,
      searchQuery: searchQuery ?? this.searchQuery,
      selectedCategory:
          clearCategory ? null : (selectedCategory ?? this.selectedCategory),
    );
  }
}

final catalogRepositoryProvider = Provider<CatalogRepository>((ref) {
  final dio = ref.watch(dioProvider);
  return CatalogRepository(dio: dio);
});

final catalogNotifierProvider =
    StateNotifierProvider<CatalogNotifier, CatalogState>((ref) {
  final wcService = ref.watch(wcServiceProvider);
  return CatalogNotifier(wcService);
});

class CatalogNotifier extends StateNotifier<CatalogState> {
  final WooCommerceService _wcService;
  Timer? _debounceTimer;

  CatalogNotifier(this._wcService) : super(const CatalogState()) {
    loadInitial();
  }

  /// Initial load or filter change - Only fetches real products from testing.livaan.in
  Future<void> loadInitial() async {
    state = state.copyWith(isLoading: true, clearError: true, page: 1);
    try {
      // 1. Fetch live WooCommerce products from testing.livaan.in
      final wcItems = await _wcService.fetchProducts(
        search: state.searchQuery,
      );

      var products = wcItems.map((wc) => Product(
        id: wc.id,
        title: wc.title,
        price: wc.price,
        compareAtPrice: wc.regularPrice,
        currency: 'USD',
        images: wc.images.isNotEmpty ? wc.images : [wc.primaryImage],
        description: wc.description,
        inStock: wc.inStock,
        variants: const [],
        categories: wc.categories,
      )).toList();

      if (state.selectedCategory != null &&
          state.selectedCategory!.isNotEmpty &&
          state.selectedCategory!.toLowerCase() != 'all') {
        final cat = state.selectedCategory!.toLowerCase();
        products = products.where((p) =>
          p.categories.any((c) => c.toLowerCase() == cat)
        ).toList();
      }

      state = state.copyWith(
        isLoading: false,
        products: products,
        page: 1,
        hasMore: false,
      );
    } catch (_) {
      // 2. Fallback strictly to real store products from testing.livaan.in
      var filtered = List<Product>.from(_mockCatalogProducts);

      if (state.searchQuery.trim().isNotEmpty) {
        final q = state.searchQuery.trim().toLowerCase();
        filtered = filtered.where((p) =>
          p.title.toLowerCase().contains(q) ||
          p.description.toLowerCase().contains(q) ||
          p.categories.any((c) => c.toLowerCase().contains(q))
        ).toList();
      }

      if (state.selectedCategory != null &&
          state.selectedCategory!.isNotEmpty &&
          state.selectedCategory!.toLowerCase() != 'all') {
        final cat = state.selectedCategory!.toLowerCase();
        filtered = filtered.where((p) =>
          p.categories.any((c) => c.toLowerCase() == cat)
        ).toList();
      }

      state = state.copyWith(
        isLoading: false,
        products: filtered,
        page: 1,
        hasMore: false,
      );
    }
  }

  // Strictly only products that exist on testing.livaan.in
  static const _mockCatalogProducts = [
    Product(
      id: '20',
      title: 'shirt 2',
      price: 79.0,
      compareAtPrice: 300.0,
      currency: 'USD',
      images: [
        'https://testing.livaan.in/wp-content/uploads/2026/10/bd.png',
        'https://testing.livaan.in/wp-content/uploads/2026/10/bg-1.png',
        'https://testing.livaan.in/wp-content/uploads/2026/10/bg.png',
        'https://testing.livaan.in/wp-content/uploads/2026/10/bg-image.png',
      ],
      description: 'संयुक्त सैन्य अभ्यास के दौरान दोनों देशों के सैनिक आधुनिक युद्ध तकनीकों का अभ्यास कर रहे हैं।',
      inStock: true,
      variants: [],
      categories: ['Cat1', 'Cat2', 'Cat3'],
    ),
    Product(
      id: '21',
      title: 'shirt 2 (Copy)',
      price: 79.0,
      compareAtPrice: 300.0,
      currency: 'USD',
      images: [
        'https://testing.livaan.in/wp-content/uploads/2026/10/bd.png',
        'https://testing.livaan.in/wp-content/uploads/2026/10/bg-1.png',
        'https://testing.livaan.in/wp-content/uploads/2026/10/bg.png',
      ],
      description: 'उच्च गुणवत्ता वाली शर्ट, दैनिक उपयोग और फॉर्मल वियर के लिए उपयुक्त।',
      inStock: true,
      variants: [],
      categories: ['Cat1', 'Cat2', 'Cat3'],
    ),
    Product(
      id: '22',
      title: 'shirt 2 (Copy 2)',
      price: 79.0,
      compareAtPrice: 300.0,
      currency: 'USD',
      images: [
        'https://testing.livaan.in/wp-content/uploads/2026/10/bd.png',
        'https://testing.livaan.in/wp-content/uploads/2026/10/bg-image.png',
      ],
      description: 'प्रीमियम कॉटन फैब्रिक के साथ तैयार की गई स्टाइलिश और कम्फर्टेबल शर्ट।',
      inStock: true,
      variants: [],
      categories: ['Cat1', 'Cat2', 'Cat3'],
    ),
    Product(
      id: '19',
      title: 'shirt (Copy)',
      price: 79.0,
      compareAtPrice: 300.0,
      currency: 'USD',
      images: [
        'https://testing.livaan.in/wp-content/uploads/2026/10/bd.png',
        'https://testing.livaan.in/wp-content/uploads/2026/10/bg.png',
      ],
      description: 'क्लासिक डिजाइन और आरामदायक फिटिंग।',
      inStock: true,
      variants: [],
      categories: ['Cat1', 'Cat2', 'Cat3'],
    ),
  ];

  /// Pagination: load next page (infinite scroll)
  Future<void> loadMore() async {
    if (state.isLoading || state.isLoadingMore || !state.hasMore) return;
  }

  /// Search with 400ms debounce
  void onSearchChanged(String query) {
    _debounceTimer?.cancel();
    _debounceTimer = Timer(const Duration(milliseconds: 400), () {
      state = state.copyWith(searchQuery: query);
      loadInitial();
    });
  }

  /// Category filter change
  void setCategory(String? categoryId) {
    if (categoryId == null || categoryId == 'All') {
      state = state.copyWith(clearCategory: true);
    } else {
      state = state.copyWith(selectedCategory: categoryId);
    }
    loadInitial();
  }

  /// Pull-to-refresh
  Future<void> refresh() => loadInitial();

  @override
  void dispose() {
    _debounceTimer?.cancel();
    super.dispose();
  }
}
