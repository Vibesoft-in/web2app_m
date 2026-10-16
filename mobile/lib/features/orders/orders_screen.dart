import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../core/network/app_exception.dart';
import '../../core/network/error_mapper.dart';
import '../../core/providers/core_providers.dart';
import '../../core/widgets/empty_view.dart';
import '../../core/widgets/error_view.dart';
import '../../core/widgets/loading_view.dart';

final ordersProvider = FutureProvider.autoDispose<List<Map<String, dynamic>>>((ref) async {
  final dio = ref.watch(dioProvider);
  try {
    final response = await dio.get('/v1/orders');
    final data = response.data;
    if (data is Map<String, dynamic> && data['data'] is List) {
      return List<Map<String, dynamic>>.from(data['data']);
    }
    return [];
  } catch (e) {
    if (e is AppException) rethrow;
    throw ErrorMapper.fromDio(e as dynamic);
  }
});

class OrdersScreen extends ConsumerWidget {
  const OrdersScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final ordersAsync = ref.watch(ordersProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('My Orders')),
      body: ordersAsync.when(
        loading: () => const LoadingView(message: 'Fetching your orders...'),
        error: (err, _) => ErrorView(
          title: 'Unable to load orders',
          message: err is AppException ? err.message : err.toString(),
          onRetry: () => ref.refresh(ordersProvider),
        ),
        data: (orders) {
          if (orders.isEmpty) {
            return EmptyView(
              title: 'No orders yet',
              message: 'When you place an order, it will appear here with live tracking status.',
              icon: Icons.receipt_long_outlined,
              actionLabel: 'Browse Products',
              onAction: () => context.go('/catalog'),
            );
          }

          return RefreshIndicator(
            onRefresh: () async => ref.refresh(ordersProvider),
            child: ListView.separated(
              padding: const EdgeInsets.all(16.0),
              itemCount: orders.length,
              separatorBuilder: (_, __) => const SizedBox(height: 12),
              itemBuilder: (context, index) {
                final order = orders[index];
                final status = (order['status'] as String? ?? 'processing').toUpperCase();
                final total = order['total']?.toString() ?? '0.00';
                final currency = order['currency'] == 'INR' ? '₹' : '\$';
                final orderNum = order['order_number']?.toString() ?? order['id']?.toString() ?? '';

                return Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16.0),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              'Order #$orderNum',
                              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                            ),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                              decoration: BoxDecoration(
                                color: status == 'COMPLETED'
                                    ? const Color(0xFF059669).withOpacity(0.1)
                                    : Colors.blue.withOpacity(0.1),
                                borderRadius: BorderRadius.circular(6),
                              ),
                              child: Text(
                                status,
                                style: TextStyle(
                                  fontSize: 10,
                                  fontWeight: FontWeight.bold,
                                  color: status == 'COMPLETED'
                                      ? const Color(0xFF059669)
                                      : const Color(0xFF2563EB),
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 10),
                        Text(
                          '${order['items_count'] ?? 1} item(s) • Total: $currency$total',
                          style: TextStyle(color: Colors.grey.shade700, fontSize: 13),
                        ),
                        if (order['created_at'] != null) ...[
                          const SizedBox(height: 4),
                          Text(
                            'Placed on ${order['created_at']}',
                            style: TextStyle(color: Colors.grey.shade500, fontSize: 11),
                          ),
                        ],
                      ],
                    ),
                  ),
                );
              },
            ),
          );
        },
      ),
    );
  }
}
