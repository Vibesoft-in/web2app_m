class ProductVariant {
  final String id;
  final String title;
  final double price;
  final double? compareAtPrice;
  final bool inStock;
  final String? sku;
  final Map<String, String> options;

  const ProductVariant({
    required this.id,
    required this.title,
    required this.price,
    this.compareAtPrice,
    this.inStock = true,
    this.sku,
    this.options = const {},
  });

  factory ProductVariant.fromJson(Map<String, dynamic> json) {
    final opts = <String, String>{};
    if (json['options'] is Map) {
      (json['options'] as Map).forEach((k, v) {
        opts[k.toString()] = v.toString();
      });
    }

    return ProductVariant(
      id: json['id']?.toString() ?? '',
      title: json['title'] as String? ?? 'Standard',
      price: (json['price'] as num?)?.toDouble() ?? 0.0,
      compareAtPrice: (json['compare_at_price'] as num?)?.toDouble(),
      inStock: json['in_stock'] != false,
      sku: json['sku'] as String?,
      options: opts,
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'title': title,
        'price': price,
        if (compareAtPrice != null) 'compare_at_price': compareAtPrice,
        'in_stock': inStock,
        if (sku != null) 'sku': sku,
        'options': options,
      };
}

class Product {
  final String id;
  final String title;
  final double price;
  final double? compareAtPrice;
  final String currency;
  final List<String> images;
  final String description;
  final bool inStock;
  final List<ProductVariant> variants;
  final List<String> categories;

  const Product({
    required this.id,
    required this.title,
    required this.price,
    this.compareAtPrice,
    this.currency = 'USD',
    this.images = const [],
    this.description = '',
    this.inStock = true,
    this.variants = const [],
    this.categories = const [],
  });

  factory Product.fromJson(Map<String, dynamic> json) {
    final imgs = <String>[];
    if (json['images'] is List) {
      for (final img in (json['images'] as List)) {
        if (img is String && img.isNotEmpty) {
          imgs.add(img);
        } else if (img is Map && img['src'] is String) {
          imgs.add(img['src'] as String);
        }
      }
    }

    final vars = <ProductVariant>[];
    if (json['variants'] is List) {
      for (final v in (json['variants'] as List)) {
        if (v is Map<String, dynamic>) {
          vars.add(ProductVariant.fromJson(v));
        }
      }
    }

    final cats = <String>[];
    if (json['categories'] is List) {
      for (final c in (json['categories'] as List)) {
        if (c is String) {
          cats.add(c);
        } else if (c is Map && c['name'] is String) {
          cats.add(c['name'] as String);
        }
      }
    }

    return Product(
      id: json['id']?.toString() ?? '',
      title: json['title'] as String? ?? 'Untitled Product',
      price: (json['price'] as num?)?.toDouble() ?? 0.0,
      compareAtPrice: (json['compare_at_price'] as num?)?.toDouble(),
      currency: (json['currency'] as String?) ?? 'USD',
      images: imgs,
      description: json['description'] as String? ?? '',
      inStock: json['in_stock'] != false,
      variants: vars,
      categories: cats,
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'title': title,
        'price': price,
        if (compareAtPrice != null) 'compare_at_price': compareAtPrice,
        'currency': currency,
        'images': images,
        'description': description,
        'in_stock': inStock,
        'variants': variants.map((v) => v.toJson()).toList(),
        'categories': categories,
      };
}

class Category {
  final String id;
  final String name;
  final String slug;
  final int count;
  final String? image;

  const Category({
    required this.id,
    required this.name,
    required this.slug,
    this.count = 0,
    this.image,
  });

  factory Category.fromJson(Map<String, dynamic> json) {
    return Category(
      id: json['id']?.toString() ?? '',
      name: json['name'] as String? ?? '',
      slug: json['slug'] as String? ?? '',
      count: (json['count'] as num?)?.toInt() ?? 0,
      image: json['image'] as String?,
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'slug': slug,
        'count': count,
        if (image != null) 'image': image,
      };
}
