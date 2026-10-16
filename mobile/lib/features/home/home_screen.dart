import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../core/config/app_config_model.dart';
import '../../core/providers/core_providers.dart';
import '../../core/widgets/error_view.dart';
import '../../core/widgets/loading_view.dart';
import 'section_renderer.dart';

class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final configAsync = ref.watch(appConfigProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: Text(configAsync.valueOrNull?.app.name ?? 'Store'),
        actions: [
          IconButton(
            icon: const Icon(Icons.search),
            onPressed: () => context.push('/catalog'),
          ),
          IconButton(
            icon: const Icon(Icons.shopping_bag_outlined),
            onPressed: () => context.push('/cart'),
          ),
        ],
      ),
      body: configAsync.when(
        loading: () => const LoadingView(message: 'Loading store home...'),
        error: (err, _) => ErrorView(
          title: 'Unable to load home',
          message: err.toString(),
          onRetry: () => ref.read(appConfigProvider.notifier).reload(),
        ),
        data: (config) {
          final sections = config.home;

          if (sections.isEmpty) {
            return const Center(
              child: Text('Welcome to our store! Check out our catalog.'),
            );
          }

          return RefreshIndicator(
            onRefresh: () => ref.read(appConfigProvider.notifier).reload(),
            child: ListView.separated(
              padding: const EdgeInsets.symmetric(vertical: 16.0),
              itemCount: sections.length,
              separatorBuilder: (_, __) => const SizedBox(height: 20),
              itemBuilder: (context, index) {
                final section = sections[index];
                return SectionRenderer(
                  section: section,
                  onBannerTap: (link) {
                    if (link.startsWith('category:')) {
                      final catId = link.split(':').last;
                      context.push('/catalog?category=$catId');
                    } else if (link.startsWith('product:')) {
                      final prodId = link.split(':').last;
                      context.push('/product/$prodId');
                    } else {
                      context.push('/catalog');
                    }
                  },
                  onCategoryTap: (category) {
                    context.push('/catalog?category=${category.slug}');
                  },
                  onProductTap: (product) {
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
