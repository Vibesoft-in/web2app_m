import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../features/cart/cart_screen.dart';
import '../../features/catalog/catalog_screen.dart';
import '../../features/checkout/checkout_webview.dart';
import '../../features/home/home_screen.dart';
import '../../features/orders/orders_screen.dart';
import '../../features/product/product_details_screen.dart';
import '../../features/profile/profile_screen.dart';
import '../../features/splash/splash_screen.dart';
import '../../layouts/main_layout.dart';

final _rootNavigatorKey = GlobalKey<NavigatorState>();
final _shellNavigatorHome = GlobalKey<NavigatorState>();
final _shellNavigatorCatalog = GlobalKey<NavigatorState>();
final _shellNavigatorCart = GlobalKey<NavigatorState>();
final _shellNavigatorProfile = GlobalKey<NavigatorState>();

final appRouterProvider = Provider<GoRouter>((ref) {
  return GoRouter(
    navigatorKey: _rootNavigatorKey,
    initialLocation: '/splash',
    routes: [
      GoRoute(
        path: '/splash',
        builder: (context, state) => const SplashScreen(),
      ),
      StatefulShellRoute.indexedStack(
        builder: (context, state, navigationShell) {
          return MainLayout(navigationShell: navigationShell);
        },
        branches: [
          StatefulShellBranch(
            navigatorKey: _shellNavigatorHome,
            routes: [
              GoRoute(
                path: '/home',
                builder: (context, state) => const HomeScreen(),
              ),
            ],
          ),
          StatefulShellBranch(
            navigatorKey: _shellNavigatorCatalog,
            routes: [
              GoRoute(
                path: '/catalog',
                builder: (context, state) {
                  final cat = state.uri.queryParameters['category'];
                  return CatalogScreen(initialCategory: cat);
                },
              ),
            ],
          ),
          StatefulShellBranch(
            navigatorKey: _shellNavigatorCart,
            routes: [
              GoRoute(
                path: '/cart',
                builder: (context, state) => const CartScreen(),
              ),
            ],
          ),
          StatefulShellBranch(
            navigatorKey: _shellNavigatorProfile,
            routes: [
              GoRoute(
                path: '/profile',
                builder: (context, state) => const ProfileScreen(),
              ),
            ],
          ),
        ],
      ),
      GoRoute(
        path: '/product/:id',
        parentNavigatorKey: _rootNavigatorKey,
        builder: (context, state) {
          final id = state.pathParameters['id'] ?? '';
          return ProductDetailsScreen(productId: id);
        },
      ),
      GoRoute(
        path: '/checkout',
        parentNavigatorKey: _rootNavigatorKey,
        builder: (context, state) {
          final url = state.uri.queryParameters['url'] ?? '';
          return CheckoutWebviewScreen(checkoutUrl: url);
        },
      ),
      GoRoute(
        path: '/orders',
        parentNavigatorKey: _rootNavigatorKey,
        builder: (context, state) => const OrdersScreen(),
      ),
    ],
  );
});
