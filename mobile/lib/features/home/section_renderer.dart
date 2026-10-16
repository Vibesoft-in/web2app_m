import 'package:flutter/material.dart';
import '../../core/config/app_config_model.dart';
import '../catalog/models/product_model.dart';
import 'widgets/banner_section.dart';
import 'widgets/categories_section.dart';
import 'widgets/product_rail_section.dart';

/// Renders a dynamic home section by type according to AppConfig contract.
///
/// CRITICAL REQUIREMENT:
/// Must safely ignore unknown section types without throwing or crashing
/// to ensure forward-compatibility with future backend extensions.
class SectionRenderer extends StatelessWidget {
  final HomeSection section;
  final void Function(String link)? onBannerTap;
  final void Function(Category category)? onCategoryTap;
  final void Function(Product product)? onProductTap;

  const SectionRenderer({
    super.key,
    required this.section,
    this.onBannerTap,
    this.onCategoryTap,
    this.onProductTap,
  });

  @override
  Widget build(BuildContext context) {
    switch (section.type.toLowerCase()) {
      case 'banner':
        return BannerSection(
          section: section,
          onBannerTap: onBannerTap,
        );

      case 'categories':
        return CategoriesSection(
          onCategoryTap: onCategoryTap,
        );

      case 'product_rail':
        return ProductRailSection(
          section: section,
          onProductTap: onProductTap,
        );

      default:
        // Safely ignore unknown section types (forward-compatibility)
        return const SizedBox.shrink();
    }
  }
}
