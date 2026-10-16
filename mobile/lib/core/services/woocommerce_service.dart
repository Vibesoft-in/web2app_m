import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../data/mock_products.dart';

class WcCategory {
  final int id;
  final String name;
  final String slug;
  final int count;
  final String? image;

  const WcCategory({
    required this.id,
    required this.name,
    required this.slug,
    this.count = 0,
    this.image,
  });

  factory WcCategory.fromJson(Map<String, dynamic> json) {
    String? imgUrl;
    if (json['image'] is Map && json['image']['src'] is String) {
      imgUrl = json['image']['src'] as String;
    } else if (json['image'] is String) {
      imgUrl = json['image'] as String;
    }

    return WcCategory(
      id: (json['id'] as num?)?.toInt() ?? 0,
      name: (json['name'] as String?)?.trim() ?? '',
      slug: (json['slug'] as String?)?.trim() ?? '',
      count: (json['count'] as num?)?.toInt() ?? 0,
      image: imgUrl,
    );
  }
}

class WcProduct {
  final String id;
  final String title;
  final double price;
  final double? regularPrice;
  final double? salePrice;
  final String? discount;
  final List<String> images;
  final List<String> categories;
  final List<int> categoryIds;
  final String description;
  final String shortDescription;
  final double rating;
  final int reviewsCount;
  final bool inStock;

  const WcProduct({
    required this.id,
    required this.title,
    required this.price,
    this.regularPrice,
    this.salePrice,
    this.discount,
    required this.images,
    required this.categories,
    required this.categoryIds,
    this.description = '',
    this.shortDescription = '',
    this.rating = 4.8,
    this.reviewsCount = 120,
    this.inStock = true,
  });

  String get primaryImage {
    if (images.isNotEmpty && images.first.isNotEmpty) {
      return images.first;
    }
    return 'https://testing.livaan.in/wp-content/uploads/2026/10/bd.png';
  }

  factory WcProduct.fromJson(Map<String, dynamic> json) {
    final imgs = <String>[];
    if (json['images'] is List) {
      for (final img in (json['images'] as List)) {
        if (img is Map && img['src'] is String && (img['src'] as String).isNotEmpty) {
          imgs.add(img['src'] as String);
        } else if (img is String && img.isNotEmpty) {
          imgs.add(img);
        }
      }
    }

    final catNames = <String>[];
    final catIds = <int>[];
    if (json['categories'] is List) {
      for (final c in (json['categories'] as List)) {
        if (c is Map) {
          if (c['name'] is String) catNames.add(c['name'] as String);
          if (c['id'] is num) catIds.add((c['id'] as num).toInt());
        } else if (c is String) {
          catNames.add(c);
        }
      }
    }

    final parsedPrice = double.tryParse(json['price']?.toString() ?? '') ?? 0.0;
    final parsedRegPrice = double.tryParse(json['regular_price']?.toString() ?? '');
    final parsedSalePrice = double.tryParse(json['sale_price']?.toString() ?? '');

    String? discount;
    if (parsedRegPrice != null && parsedRegPrice > parsedPrice && parsedPrice > 0) {
      final pct = (((parsedRegPrice - parsedPrice) / parsedRegPrice) * 100).round();
      discount = '-$pct%';
    }

    // Clean html tags from descriptions
    final rawDesc = (json['description'] as String?) ?? '';
    final cleanDesc = rawDesc.replaceAll(RegExp(r'<[^>]*>|&[^;]+;'), ' ').trim();

    final rawShort = (json['short_description'] as String?) ?? '';
    final cleanShort = rawShort.replaceAll(RegExp(r'<[^>]*>|&[^;]+;'), ' ').trim();

    return WcProduct(
      id: json['id']?.toString() ?? '',
      title: (json['name'] as String?)?.trim() ?? 'Untitled Product',
      price: parsedPrice > 0 ? parsedPrice : (parsedRegPrice ?? 79.0),
      regularPrice: parsedRegPrice,
      salePrice: parsedSalePrice,
      discount: discount,
      images: imgs.isNotEmpty ? imgs : ['https://testing.livaan.in/wp-content/uploads/2026/10/bd.png'],
      categories: catNames,
      categoryIds: catIds,
      description: cleanDesc.isNotEmpty ? cleanDesc : 'Premium high quality product from our store collection.',
      shortDescription: cleanShort,
      rating: double.tryParse(json['average_rating']?.toString() ?? '') ?? 4.8,
      reviewsCount: (json['rating_count'] as num?)?.toInt() ?? 12,
      inStock: json['stock_status'] == 'instock',
    );
  }

  Map<String, dynamic> toMap() => {
    'id': id,
    'title': title,
    'price': price,
    'originalPrice': regularPrice,
    'discount': discount,
    'image': primaryImage,
    'categories': categories,
  };

  DetailedProduct toDetailedProduct() => DetailedProduct(
    id: id,
    title: title,
    price: price,
    originalPrice: regularPrice,
    rating: rating,
    reviewsCount: reviewsCount > 0 ? reviewsCount : 45,
    images: images.isNotEmpty ? images : [primaryImage],
    description: description,
    colors: ['#704FEE', '#1E1B4B', '#F59E0B', '#EF4444'],
    features: [
      '100% Authentic Quality',
      'Direct from Testing Livaan',
      'Fast Shipping Available',
      'Easy 7-Day Returns',
    ],
  );
}

class WooCommerceService {
  static const String baseUrl = 'https://testing.livaan.in/wp-json/wc/v3';
  static const String consumerKey = 'ck_3461f2923d2dd8ae7605abc8dc3841ad4ca83bd7';
  static const String consumerSecret = 'cs_226ca5eaf2a2b3519803fa4d1b864fea9d5df87e';

  final Dio _dio = Dio(BaseOptions(
    connectTimeout: const Duration(seconds: 10),
    receiveTimeout: const Duration(seconds: 10),
  ));

  /// Seed Categories (matching live site testing.livaan.in)
  static final List<WcCategory> initialCategories = [
    const WcCategory(
      id: 17,
      name: 'Cat1',
      slug: 'cat1',
      count: 4,
      image: 'https://testing.livaan.in/wp-content/uploads/2026/10/bg-image.png',
    ),
    const WcCategory(
      id: 18,
      name: 'Cat2',
      slug: 'cat2',
      count: 4,
      image: 'https://testing.livaan.in/wp-content/uploads/2026/10/bd.png',
    ),
    const WcCategory(
      id: 19,
      name: 'Cat3',
      slug: 'cat3',
      count: 4,
      image: 'https://testing.livaan.in/wp-content/uploads/2026/10/bd.png',
    ),
  ];

  /// Seed Products (matching live site testing.livaan.in)
  static final List<WcProduct> initialProducts = [
    const WcProduct(
      id: '20',
      title: 'shirt 2',
      price: 79.0,
      regularPrice: 300.0,
      salePrice: 79.0,
      discount: '-74%',
      images: [
        'https://testing.livaan.in/wp-content/uploads/2026/10/bd.png',
        'https://testing.livaan.in/wp-content/uploads/2026/10/bg-1.png',
        'https://testing.livaan.in/wp-content/uploads/2026/10/bg.png',
        'https://testing.livaan.in/wp-content/uploads/2026/10/bg-image.png',
      ],
      categories: ['Cat1', 'Cat2', 'Cat3'],
      categoryIds: [17, 18, 19],
      description: 'संयुक्त सैन्य अभ्यास के दौरान दोनों देशों के सैनिक आधुनिक युद्ध तकनीकों का अभ्यास कर रहे हैं।',
      rating: 4.9,
      reviewsCount: 86,
    ),
    const WcProduct(
      id: '21',
      title: 'shirt 2 (Copy)',
      price: 79.0,
      regularPrice: 300.0,
      salePrice: 79.0,
      discount: '-74%',
      images: [
        'https://testing.livaan.in/wp-content/uploads/2026/10/bd.png',
        'https://testing.livaan.in/wp-content/uploads/2026/10/bg-1.png',
        'https://testing.livaan.in/wp-content/uploads/2026/10/bg.png',
      ],
      categories: ['Cat1', 'Cat2', 'Cat3'],
      categoryIds: [17, 18, 19],
      description: 'उच्च गुणवत्ता वाली शर्ट, दैनिक उपयोग और फॉर्मल वियर के लिए उपयुक्त।',
      rating: 4.8,
      reviewsCount: 54,
    ),
    const WcProduct(
      id: '22',
      title: 'shirt 2 (Copy 2)',
      price: 79.0,
      regularPrice: 300.0,
      salePrice: 79.0,
      discount: '-74%',
      images: [
        'https://testing.livaan.in/wp-content/uploads/2026/10/bd.png',
        'https://testing.livaan.in/wp-content/uploads/2026/10/bg-image.png',
      ],
      categories: ['Cat1', 'Cat2', 'Cat3'],
      categoryIds: [17, 18, 19],
      description: 'प्रीमियम कॉटन फैब्रिक के साथ तैयार की गई स्टाइलिश और कम्फर्टेबल शर्ट।',
      rating: 4.7,
      reviewsCount: 38,
    ),
    const WcProduct(
      id: '19',
      title: 'shirt (Copy)',
      price: 79.0,
      regularPrice: 300.0,
      salePrice: 79.0,
      discount: '-74%',
      images: [
        'https://testing.livaan.in/wp-content/uploads/2026/10/bd.png',
        'https://testing.livaan.in/wp-content/uploads/2026/10/bg.png',
      ],
      categories: ['Cat1', 'Cat2', 'Cat3'],
      categoryIds: [17, 18, 19],
      description: 'क्लासिक डिजाइन और आरामदायक फिटिंग।',
      rating: 4.9,
      reviewsCount: 92,
    ),
  ];

  Future<List<WcCategory>> fetchCategories() async {
    try {
      final response = await _dio.get(
        '$baseUrl/products/categories',
        queryParameters: {
          'consumer_key': consumerKey,
          'consumer_secret': consumerSecret,
          'per_page': 50,
          'hide_empty': true,
        },
      );

      final data = response.data;
      if (data is List) {
        final list = <WcCategory>[];
        for (final item in data) {
          if (item is Map<String, dynamic>) {
            final cat = WcCategory.fromJson(item);
            // Ignore uncategorized if empty or unwanted
            if (cat.name.toLowerCase() != 'uncategorized') {
              list.add(cat);
            }
          }
        }
        if (list.isNotEmpty) return list;
      }
    } catch (_) {
      // Fallback to initialCategories
    }
    return initialCategories;
  }

  Future<List<WcProduct>> fetchProducts({int? categoryId, String? search}) async {
    try {
      final queryParams = <String, dynamic>{
        'consumer_key': consumerKey,
        'consumer_secret': consumerSecret,
        'per_page': 50,
        'status': 'publish',
      };

      if (categoryId != null && categoryId > 0) {
        queryParams['category'] = categoryId;
      }

      if (search != null && search.trim().isNotEmpty) {
        queryParams['search'] = search.trim();
      }

      final response = await _dio.get(
        '$baseUrl/products',
        queryParameters: queryParams,
      );

      final data = response.data;
      if (data is List) {
        final list = <WcProduct>[];
        for (final item in data) {
          if (item is Map<String, dynamic>) {
            final p = WcProduct.fromJson(item);
            // Only include products with price or images
            if (p.price > 0 || p.images.isNotEmpty) {
              list.add(p);
            }
          }
        }
        if (list.isNotEmpty) return list;
      }
    } catch (_) {
      // Fallback
    }

    var result = List<WcProduct>.from(initialProducts);
    if (categoryId != null && categoryId > 0) {
      result = result.where((p) => p.categoryIds.contains(categoryId)).toList();
    }
    if (search != null && search.trim().isNotEmpty) {
      final q = search.trim().toLowerCase();
      result = result.where((p) => p.title.toLowerCase().contains(q)).toList();
    }
    return result;
  }
}

// Global Providers
final wcServiceProvider = Provider<WooCommerceService>((ref) {
  return WooCommerceService();
});

final wcCategoriesProvider = FutureProvider<List<WcCategory>>((ref) async {
  final service = ref.watch(wcServiceProvider);
  return service.fetchCategories();
});

final wcProductsProvider = FutureProvider<List<WcProduct>>((ref) async {
  final service = ref.watch(wcServiceProvider);
  return service.fetchProducts();
});

// Currently selected category filter: 'All', 'Cat1', 'Cat2', 'Cat3', etc.
final selectedCategoryFilterProvider = StateProvider<String>((ref) => 'All');

// Filtered products based on selectedCategoryFilterProvider
final filteredWcProductsProvider = Provider<List<WcProduct>>((ref) {
  final productsAsync = ref.watch(wcProductsProvider);
  final allProducts = productsAsync.value ?? WooCommerceService.initialProducts;
  final selectedCat = ref.watch(selectedCategoryFilterProvider);

  if (selectedCat == 'All' || selectedCat.isEmpty) {
    return allProducts;
  }

  return allProducts.where((p) {
    return p.categories.any((c) => c.toLowerCase() == selectedCat.toLowerCase());
  }).toList();
});
