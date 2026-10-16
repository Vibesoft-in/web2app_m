import 'dart:async';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/network/app_exception.dart';
import '../../core/providers/core_providers.dart';
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
  final repo = ref.watch(catalogRepositoryProvider);
  return CatalogNotifier(repo);
});

class CatalogNotifier extends StateNotifier<CatalogState> {
  final CatalogRepository _repository;
  Timer? _debounceTimer;

  CatalogNotifier(this._repository) : super(const CatalogState()) {
    loadInitial();
  }

  /// Initial load or filter change
  Future<void> loadInitial() async {
    state = state.copyWith(isLoading: true, clearError: true, page: 1);
    try {
      final items = await _repository.fetchProducts(
        page: 1,
        perPage: 12,
        search: state.searchQuery,
        categoryId: state.selectedCategory,
      );

      state = state.copyWith(
        isLoading: false,
        products: items,
        page: 1,
        hasMore: items.length >= 12,
      );
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: e is AppException ? e : AppException(e.toString()),
      );
    }
  }

  /// Pagination: load next page (infinite scroll)
  Future<void> loadMore() async {
    if (state.isLoading || state.isLoadingMore || !state.hasMore) return;

    state = state.copyWith(isLoadingMore: true);
    final nextPage = state.page + 1;

    try {
      final newItems = await _repository.fetchProducts(
        page: nextPage,
        perPage: 12,
        search: state.searchQuery,
        categoryId: state.selectedCategory,
      );

      state = state.copyWith(
        isLoadingMore: false,
        products: [...state.products, ...newItems],
        page: nextPage,
        hasMore: newItems.length >= 12,
      );
    } catch (_) {
      state = state.copyWith(isLoadingMore: false);
    }
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
    if (categoryId == null) {
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
