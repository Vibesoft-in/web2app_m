/// Standardized exceptions across the mobile application.
class AppException implements Exception {
  final String message;
  final String? code;
  final int? statusCode;

  const AppException(this.message, {this.code, this.statusCode});

  @override
  String toString() => 'AppException: $message (code: $code, status: $statusCode)';
}

class NetworkException extends AppException {
  const NetworkException(super.message, {super.code, super.statusCode});
}

class UnauthorizedException extends AppException {
  const UnauthorizedException(super.message, {super.code, super.statusCode = 401});
}

class NotFoundException extends AppException {
  const NotFoundException(super.message, {super.code, super.statusCode = 404});
}

class ServerException extends AppException {
  const ServerException(super.message, {super.code, super.statusCode = 500});
}

class MaintenanceException extends AppException {
  const MaintenanceException(super.message, {super.code = 'MAINTENANCE_MODE'});
}

class ForceUpdateException extends AppException {
  final String minVersion;
  final String currentVersion;

  const ForceUpdateException({
    required this.minVersion,
    required this.currentVersion,
    String message = 'Please update to the latest version to continue using the app.',
  }) : super(message, code: 'FORCE_UPDATE');
}
