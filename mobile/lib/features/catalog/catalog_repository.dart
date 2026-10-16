import 'package:dio/dio.dart';
import '../../core/network/app_exception.dart';
import '../../core/network/error_mapper.dart';
import 'models/product_model.dart';

class CatalogRepository {
  final Dio _dio;

  CatalogRepository({required Dio dio}) : _dio = dio;

  /// Fetches paginated products with optional search query and category filter
  Future<List<Product>> fetchProducts({
    int page = 1,
    int perPage = 10,
    String? search,
    String? categoryId,
  }) async {
    try {
      final queryParams = <String, dynamic>{
        'page': page,
        'per_page': perPage,
      };

      if (search != null && search.trim().isNotEmpty) {
        queryParams['search'] = search.trim();
      }

      if (categoryId != null && categoryId.isNotEmpty) {
        queryParams['category'] = categoryId;
      }

      final response = await _dio.get(
        '/v1/products',
        queryParameters: queryParams,
      );

      final data = response.data;
      final products = <Product>[];

      if (data is List) {
        for (final item in data) {
          if (item is Map<String, dynamic>) {
            products.add(Product.fromJson(item));
          }
        }
      } else if (data is Map<String, dynamic> && data['data'] is List) {
        for (final item in (data['data'] as List)) {
          if (item is Map<String, dynamic>) {
            products.add(Product.fromJson(item));
          }
        }
      }

      return products;
    } on DioException catch (e) {
      throw ErrorMapper.fromDio(e);
    } catch (e) {
      if (e is AppException) rethrow;
      throw AppException(e.toString());
    }
  }

  /// Fetches single product details
  Future<Product> fetchProduct(String id) async {
    try {
      final response = await _dio.get('/v1/products/$id');
      final data = response.data;

      if (data is Map<String, dynamic>) {
        final productMap = data['data'] is Map<String, dynamic>
            ? data['data'] as Map<String, dynamic>
            : data;
        return Product.fromJson(productMap);
      }

      throw const NotFoundException('Product not found.');
    } on DioException catch (e) {
      throw ErrorMapper.fromDio(e);
    } catch (e) {
      if (e is AppException) rethrow;
      throw AppException(e.toString());
    }
  }

  /// Fetches store categories
  Future<List<Category>> fetchCategories() async {
    try {
      final response = await _dio.get('/v1/categories');
      final data = response.data;
      final categories = <Category>[];

      final list = data is List
          ? data
          : (data is Map<String, dynamic> && data['data'] is List)
              ? data['data'] as List
              : [];

      for (final item in list) {
        if (item is Map<String, dynamic>) {
          categories.add(Category.fromJson(item));
        }
      }

      return categories;
    } on DioException catch (e) {
      throw ErrorMapper.fromDio(e);
    } catch (e) {
      if (e is AppException) rethrow;
      throw AppException(e.toString());
    }
  }
}
