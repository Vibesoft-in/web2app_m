import 'package:dio/dio.dart';
import 'app_exception.dart';

/// Maps Dio network failures and API JSON error objects into strongly typed AppExceptions.
class ErrorMapper {
  ErrorMapper._();

  static AppException fromDio(DioException error) {
    switch (error.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.sendTimeout:
      case DioExceptionType.receiveTimeout:
        return const NetworkException(
          'Connection timed out. Please check your internet connection.',
          code: 'TIMEOUT',
        );

      case DioExceptionType.connectionError:
        return const NetworkException(
          'Unable to reach the server. Please check your network connection.',
          code: 'OFFLINE',
        );

      case DioExceptionType.badResponse:
        final response = error.response;
        final statusCode = response?.statusCode;
        final data = response?.data;

        String message = 'An unexpected server error occurred.';
        String? code;

        if (data is Map<String, dynamic>) {
          message = data['message'] as String? ?? message;
          code = data['code'] as String? ?? data['error'] as String?;
        }

        if (statusCode == 401) {
          return UnauthorizedException(
            message.isNotEmpty ? message : 'Session expired. Please log in again.',
            code: code ?? 'UNAUTHORIZED',
          );
        } else if (statusCode == 404) {
          return NotFoundException(
            message.isNotEmpty ? message : 'Requested resource was not found.',
            code: code ?? 'NOT_FOUND',
          );
        } else if (statusCode == 503 || code == 'MAINTENANCE_MODE') {
          return MaintenanceException(
            message.isNotEmpty ? message : 'Store is undergoing scheduled maintenance.',
          );
        }

        return ServerException(
          message,
          code: code ?? 'SERVER_ERROR',
          statusCode: statusCode,
        );

      case DioExceptionType.cancel:
        return const AppException('Request was cancelled.', code: 'CANCELLED');

      default:
        return AppException(
          error.message ?? 'An unexpected network error occurred.',
          code: 'UNKNOWN',
        );
    }
  }
}
