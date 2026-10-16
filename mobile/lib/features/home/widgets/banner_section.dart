import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import '../../../core/config/app_config_model.dart';

class BannerSection extends StatelessWidget {
  final HomeSection section;
  final void Function(String link)? onBannerTap;

  const BannerSection({
    super.key,
    required this.section,
    this.onBannerTap,
  });

  @override
  Widget build(BuildContext context) {
    if (section.items.isEmpty) return const SizedBox.shrink();

    return SizedBox(
      height: 160,
      child: PageView.builder(
        itemCount: section.items.length,
        itemBuilder: (context, index) {
          final item = section.items[index];
          return Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16.0),
            child: GestureDetector(
              onTap: () {
                if (item.link.isNotEmpty) {
                  onBannerTap?.call(item.link);
                }
              },
              child: ClipRRect(
                borderRadius: BorderRadius.circular(12),
                child: item.image.isNotEmpty
                    ? CachedNetworkImage(
                        imageUrl: item.image,
                        fit: BoxFit.cover,
                        placeholder: (_, __) => Container(
                          color: Colors.grey.shade200,
                          child: const Center(
                            child: CircularProgressIndicator(strokeWidth: 2),
                          ),
                        ),
                        errorWidget: (_, __, ___) => _buildFallbackBanner(context),
                      )
                    : _buildFallbackBanner(context),
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _buildFallbackBanner(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [theme.colorScheme.primary, const Color(0xFF312E81)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
      ),
      padding: const EdgeInsets.all(20),
      alignment: Alignment.bottomLeft,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
            decoration: BoxDecoration(
              color: Colors.white.withOpacity(0.2),
              borderRadius: BorderRadius.circular(4),
            ),
            child: const Text(
              'SPECIAL OFFER',
              style: TextStyle(
                color: Colors.white,
                fontSize: 10,
                fontWeight: FontWeight.bold,
              ),
            ),
          ),
          const SizedBox(height: 6),
          const Text(
            'Discover New Season Arrivals',
            style: TextStyle(
              color: Colors.white,
              fontSize: 18,
              fontWeight: FontWeight.bold,
            ),
          ),
        ],
      ),
    );
  }
}
