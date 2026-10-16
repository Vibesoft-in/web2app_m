import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../core/providers/shop_providers.dart';
import '../../core/services/woocommerce_service.dart';

class HomeScreen extends ConsumerStatefulWidget {
  const HomeScreen({super.key});

  @override
  ConsumerState<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends ConsumerState<HomeScreen> {
  String _selectedCategory = 'All';
  final _searchController = TextEditingController();
  String _searchQuery = '';
  bool _showAllFlashSale = false;
  bool _showAllBestSelling = false;

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  List<Map<String, dynamic>> _getSearchResults(List<WcProduct> wcProducts) {
    final query = _searchQuery.trim().toLowerCase();
    if (query.isEmpty) return [];
    return wcProducts.where((p) {
      final title = p.title.toLowerCase();
      final inCat = p.categories.any((c) => c.toLowerCase().contains(query));
      return title.contains(query) || inCat;
    }).map((p) => p.toMap()).toList();
  }

  List<Map<String, dynamic>> _getCategoriesList(List<WcCategory> wcCategories) {
    return [
      {
        'id': 'All',
        'name': 'All',
        'icon': Icons.grid_view_rounded,
        'image': null,
      },
      ...wcCategories.map((c) => {
        'id': c.name,
        'name': c.name,
        'icon': _getCategoryIcon(c.name),
        'image': c.image,
      }),
    ];
  }

  IconData _getCategoryIcon(String catName) {
    final lower = catName.toLowerCase();
    if (lower.contains('cat1') || lower.contains('shirt')) return Icons.checkroom_rounded;
    if (lower.contains('cat2')) return Icons.dry_cleaning_rounded;
    if (lower.contains('cat3')) return Icons.shopping_bag_rounded;
    if (lower.contains('women')) return Icons.woman_rounded;
    if (lower.contains('men')) return Icons.man_rounded;
    if (lower.contains('shoes')) return Icons.sports_tennis_rounded;
    if (lower.contains('bag')) return Icons.work_outline_rounded;
    return Icons.category_rounded;
  }

  List<Map<String, dynamic>> _getFlashSaleProducts(List<WcProduct> wcProducts) {
    final onSale = wcProducts.where((p) => p.discount != null || (p.salePrice != null && p.salePrice! > 0)).toList();
    if (onSale.isNotEmpty) {
      return onSale.map((p) => p.toMap()).toList();
    }
    return wcProducts.map((p) => p.toMap()).toList();
  }

  List<Map<String, dynamic>> _getFilteredProducts(List<WcProduct> wcProducts) {
    if (_selectedCategory == 'All' || _selectedCategory.isEmpty) {
      return wcProducts.map((p) => p.toMap()).toList();
    }
    return wcProducts
        .where((p) => p.categories.any((c) => c.toLowerCase() == _selectedCategory.toLowerCase()))
        .map((p) => p.toMap())
        .toList();
  }

  void _navigateToProduct(String id) {
    context.push('/product/$id');
  }

  @override
  Widget build(BuildContext context) {
    final wishlistedIds = ref.watch(wishlistProvider);
    final cartCount = ref.watch(cartCountProvider);
    final userProfile = ref.watch(userProfileProvider);
    final unreadNotifCount = ref.watch(unreadNotificationsCountProvider);

    // Watch WooCommerce Live Data
    final wcCategoriesAsync = ref.watch(wcCategoriesProvider);
    final wcProductsAsync = ref.watch(wcProductsProvider);

    final allWcProducts = wcProductsAsync.value ?? WooCommerceService.initialProducts;
    final allWcCategories = wcCategoriesAsync.value ?? WooCommerceService.initialCategories;

    final categoriesList = _getCategoriesList(allWcCategories);
    final flashSaleProducts = _getFlashSaleProducts(allWcProducts);
    final displayedProducts = _getFilteredProducts(allWcProducts);
    final searchResults = _getSearchResults(allWcProducts);

    const primaryColor = Color(0xFF704FEE);
    const darkTextColor = Color(0xFF1E1B4B);

    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 18.0, vertical: 12.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // 1. Header (Greeting + Wishlist / Bell / Cart icons with live badges)
              _buildHeader(
                primaryColor,
                darkTextColor,
                wishlistedIds.length,
                cartCount,
                unreadNotifCount,
                userProfile.name,
              ),
              const SizedBox(height: 18),

              // 2. Search Bar with Filter Icon
              _buildSearchBar(primaryColor, darkTextColor),
              const SizedBox(height: 18),

              if (_searchQuery.isNotEmpty) ...[
                // In-Place Search Results (Matches WooCommerce products!)
                _buildSearchResultsSection(primaryColor, darkTextColor, wishlistedIds, searchResults),
              ] else ...[
                // 3. Fully Responsive Hero Promo Banner
                _buildHeroPromoBanner(primaryColor, darkTextColor),
                const SizedBox(height: 22),

                // 4. Categories Row with Live WooCommerce Categories
                _buildCategoriesRow(primaryColor, darkTextColor, categoriesList),
                const SizedBox(height: 24),

                // 5. Flash Sale ⚡ Section
                _buildFlashSaleSection(primaryColor, darkTextColor, flashSaleProducts),
                const SizedBox(height: 24),

                // 6. Dynamic Store Products Section (Filtered by Category!)
                _buildBestSellingSection(primaryColor, darkTextColor, wishlistedIds, displayedProducts),
              ],
              const SizedBox(height: 20),
            ],
          ),
        ),
      ),
    );
  }

  /// 1. Top Header with dynamic badges and dynamic user name
  Widget _buildHeader(
    Color primaryColor,
    Color darkTextColor,
    int wishlistCount,
    int cartCount,
    int unreadNotifCount,
    String userName,
  ) {
    final cleanName = userName.trim();
    final displayName = cleanName.isNotEmpty ? cleanName : 'Guest';

    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        // Left: User Greeting (Protected by Expanded against overflow)
        Expanded(
          child: GestureDetector(
            onTap: () => context.go('/intro'),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Row(
                  children: [
                    Flexible(
                      child: Text(
                        'Hello, $displayName',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          fontSize: 20,
                          fontWeight: FontWeight.w800,
                          color: darkTextColor,
                          letterSpacing: -0.5,
                        ),
                      ),
                    ),
                    const SizedBox(width: 4),
                    const Text('👋', style: TextStyle(fontSize: 18)),
                  ],
                ),
                const SizedBox(height: 3),
                Text(
                  'What are you looking for?',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontSize: 13,
                    color: Colors.grey.shade500,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(width: 12),

        // Right: Wishlist + Notification + Cart Icons (Always visible!)
        Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            // Wishlist icon WITH LIVE BADGE
            _buildHeaderIconButton(
              icon: Icons.favorite_border_rounded,
              badgeCount: wishlistCount > 0 ? wishlistCount : null,
              onTap: () => context.push('/wishlist'),
            ),
            const SizedBox(width: 8),

            // Notification Bell with LIVE dynamic badge
            _buildHeaderIconButton(
              icon: Icons.notifications_none_rounded,
              badgeCount: unreadNotifCount > 0 ? unreadNotifCount : null,
              onTap: () => context.push('/notifications'),
            ),
            const SizedBox(width: 8),

            // Cart Icon with badge
            _buildHeaderIconButton(
              icon: Icons.shopping_bag_outlined,
              badgeCount: cartCount > 0 ? cartCount : null,
              onTap: () => context.push('/cart'),
            ),
          ],
        ),
      ],
    );
  }

  Widget _buildHeaderIconButton({
    required IconData icon,
    int? badgeCount,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        width: 40,
        height: 40,
        decoration: BoxDecoration(
          color: const Color(0xFFF9FAFB),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: Colors.grey.shade200, width: 1),
        ),
        child: Stack(
          alignment: Alignment.center,
          children: [
            Icon(icon, size: 20, color: const Color(0xFF1E1B4B)),
            if (badgeCount != null)
              Positioned(
                top: 4,
                right: 4,
                child: Container(
                  padding: const EdgeInsets.all(3),
                  decoration: const BoxDecoration(
                    color: Color(0xFF704FEE),
                    shape: BoxShape.circle,
                  ),
                  constraints: const BoxConstraints(minWidth: 16, minHeight: 16),
                  child: Center(
                    child: Text(
                      '$badgeCount',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 8.5,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  /// 2. Search Bar with Filter Button (In-place live search, no redirect!)
  Widget _buildSearchBar(Color primaryColor, Color darkTextColor) {
    return Row(
      children: [
        Expanded(
          child: Container(
            height: 48,
            decoration: BoxDecoration(
              color: const Color(0xFFF5F3FF).withOpacity(0.6),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: const Color(0xFFEDE9FE)),
            ),
            padding: const EdgeInsets.symmetric(horizontal: 14),
            child: Row(
              children: [
                Icon(Icons.search_rounded, color: Colors.grey.shade400, size: 22),
                const SizedBox(width: 10),
                Expanded(
                  child: TextField(
                    controller: _searchController,
                    onChanged: (query) {
                      setState(() => _searchQuery = query.trim());
                    },
                    onSubmitted: (query) {
                      setState(() => _searchQuery = query.trim());
                    },
                    decoration: InputDecoration(
                      hintText: 'Search products (e.g. Hoodie, Watch, Nike)...',
                      hintStyle: TextStyle(color: Colors.grey.shade400, fontSize: 13.5),
                      border: InputBorder.none,
                      enabledBorder: InputBorder.none,
                      focusedBorder: InputBorder.none,
                      contentPadding: EdgeInsets.zero,
                      filled: false,
                    ),
                  ),
                ),
                if (_searchQuery.isNotEmpty)
                  GestureDetector(
                    onTap: () {
                      _searchController.clear();
                      setState(() => _searchQuery = '');
                    },
                    child: Container(
                      padding: const EdgeInsets.all(3),
                      decoration: BoxDecoration(
                        color: Colors.grey.shade300,
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(
                        Icons.close_rounded,
                        size: 14,
                        color: Colors.white,
                      ),
                    ),
                  ),
              ],
            ),
          ),
        ),
        const SizedBox(width: 10),
        InkWell(
          onTap: () {
            // Filter icon action: reset or show all products
            setState(() {
              _showAllBestSelling = true;
              _showAllFlashSale = true;
              _searchQuery = '';
              _searchController.clear();
            });
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(
                content: Text('Showing all available products'),
                duration: Duration(seconds: 1),
                behavior: SnackBarBehavior.floating,
              ),
            );
          },
          borderRadius: BorderRadius.circular(16),
          child: Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              color: const Color(0xFFF5F3FF),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: const Color(0xFFEDE9FE)),
            ),
            child: const Icon(
              Icons.tune_rounded,
              size: 20,
              color: Color(0xFF1E1B4B),
            ),
          ),
        ),
      ],
    );
  }

  /// 3. Responsive Hero Promo Banner (Features latest WooCommerce product)
  Widget _buildHeroPromoBanner(Color primaryColor, Color darkTextColor) {
    return GestureDetector(
      onTap: () => _navigateToProduct('20'),
      child: Container(
        width: double.infinity,
        decoration: BoxDecoration(
          color: const Color(0xFFEDE9FE),
          borderRadius: BorderRadius.circular(24),
        ),
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 16),
        child: Row(
          children: [
            // Left Content
            Expanded(
              flex: 6,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(
                    'Special Offer ⚡',
                    style: TextStyle(
                      fontSize: 11.5,
                      fontWeight: FontWeight.w600,
                      color: Colors.purple.shade900,
                    ),
                  ),
                  const SizedBox(height: 4),
                  const Text(
                    'Exclusive\nShirt 2',
                    style: TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.w900,
                      color: Color(0xFF1E1B4B),
                      height: 1.15,
                      letterSpacing: -0.4,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Special 74% OFF • Only \$79',
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                      color: Colors.purple.shade800,
                    ),
                  ),
                  const SizedBox(height: 14),
                  Material(
                    color: Colors.transparent,
                    child: InkWell(
                      onTap: () => _navigateToProduct('20'),
                      borderRadius: BorderRadius.circular(24),
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 20,
                          vertical: 10,
                        ),
                        decoration: BoxDecoration(
                          gradient: const LinearGradient(
                            colors: [Color(0xFF1E1B4B), Color(0xFF2E2A68)],
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                          ),
                          borderRadius: BorderRadius.circular(24),
                          boxShadow: [
                            BoxShadow(
                              color: const Color(0xFF1E1B4B).withOpacity(0.28),
                              blurRadius: 10,
                              offset: const Offset(0, 4),
                            ),
                          ],
                        ),
                        child: const Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(
                              'Shop Now',
                              style: TextStyle(
                                color: Colors.white,
                                fontSize: 13,
                                fontWeight: FontWeight.w700,
                                letterSpacing: 0.2,
                              ),
                            ),
                            SizedBox(width: 6),
                            Icon(
                              Icons.arrow_forward_rounded,
                              color: Colors.white,
                              size: 14,
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),

            // Right Product Image (From testing.livaan.in)
            Expanded(
              flex: 5,
              child: AspectRatio(
                aspectRatio: 1.05,
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(18),
                  child: CachedNetworkImage(
                    imageUrl:
                        'https://testing.livaan.in/wp-content/uploads/2026/10/bd.png',
                    fit: BoxFit.cover,
                    placeholder: (_, __) => const Center(
                      child: CircularProgressIndicator(strokeWidth: 2),
                    ),
                    errorWidget: (_, __, ___) => const Icon(
                      Icons.shopping_bag_rounded,
                      size: 50,
                      color: Color(0xFF704FEE),
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  /// 4. Categories Row with Live WooCommerce Categories
  Widget _buildCategoriesRow(
    Color primaryColor,
    Color darkTextColor,
    List<Map<String, dynamic>> categories,
  ) {
    return SizedBox(
      height: 86,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        clipBehavior: Clip.none,
        itemCount: categories.length,
        separatorBuilder: (_, __) => const SizedBox(width: 14),
        itemBuilder: (context, index) {
          final cat = categories[index];
          final isSelected = cat['id'] == _selectedCategory;
          final catImage = cat['image'] as String?;

          return GestureDetector(
            onTap: () {
              setState(() => _selectedCategory = cat['id'] as String);
            },
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                AnimatedContainer(
                  duration: const Duration(milliseconds: 200),
                  width: 52,
                  height: 52,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: isSelected
                        ? const Color(0xFF704FEE)
                        : const Color(0xFFF3F4F6),
                    border: isSelected
                        ? Border.all(color: const Color(0xFF704FEE), width: 2)
                        : Border.all(color: Colors.grey.shade200),
                    boxShadow: isSelected
                        ? [
                            BoxShadow(
                              color: const Color(0xFF704FEE).withOpacity(0.35),
                              blurRadius: 10,
                              offset: const Offset(0, 4),
                            ),
                          ]
                        : null,
                  ),
                  child: ClipOval(
                    child: catImage != null && catImage.isNotEmpty
                        ? CachedNetworkImage(
                            imageUrl: catImage,
                            width: 52,
                            height: 52,
                            fit: BoxFit.cover,
                            errorWidget: (_, __, ___) => Icon(
                              cat['icon'] as IconData,
                              size: 22,
                              color: isSelected ? Colors.white : const Color(0xFF4B5563),
                            ),
                          )
                        : Icon(
                            cat['icon'] as IconData,
                            size: 22,
                            color: isSelected ? Colors.white : const Color(0xFF4B5563),
                          ),
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  cat['name'] as String,
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: isSelected ? FontWeight.bold : FontWeight.w500,
                    color: isSelected ? primaryColor : const Color(0xFF4B5563),
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }

  /// 5. Flash Sale ⚡ Section (Connected to WooCommerce Live Products)
  Widget _buildFlashSaleSection(
    Color primaryColor,
    Color darkTextColor,
    List<Map<String, dynamic>> products,
  ) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Row(
              children: [
                Text(
                  'Flash Sale',
                  style: TextStyle(
                    fontSize: 17,
                    fontWeight: FontWeight.w800,
                    color: darkTextColor,
                    letterSpacing: -0.3,
                  ),
                ),
                const SizedBox(width: 4),
                const Text('⚡', style: TextStyle(fontSize: 16)),
              ],
            ),
            if (products.length > 2)
              GestureDetector(
                onTap: () {
                  setState(() => _showAllFlashSale = !_showAllFlashSale);
                },
                child: Text(
                  _showAllFlashSale ? 'Show Less' : 'See All',
                  style: const TextStyle(
                    fontSize: 12.5,
                    fontWeight: FontWeight.w600,
                    color: Color(0xFF704FEE),
                  ),
                ),
              ),
          ],
        ),
        const SizedBox(height: 12),
        if (_showAllFlashSale)
          GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            itemCount: products.length,
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 2,
              crossAxisSpacing: 14,
              mainAxisSpacing: 18,
              childAspectRatio: 0.73,
            ),
            itemBuilder: (context, index) {
              final item = products[index];
              return _buildProductCardItem(item, darkTextColor, ref.watch(wishlistProvider));
            },
          )
        else
          SizedBox(
            height: 175,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              clipBehavior: Clip.none,
              itemCount: products.length,
              separatorBuilder: (_, __) => const SizedBox(width: 14),
              itemBuilder: (context, index) {
                final item = products[index];
                return GestureDetector(
                  onTap: () => _navigateToProduct(item['id'] as String),
                  child: SizedBox(
                    width: 110,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Stack(
                          children: [
                            Container(
                              width: 110,
                              height: 110,
                              decoration: BoxDecoration(
                                color: const Color(0xFFF3F4F6),
                                borderRadius: BorderRadius.circular(18),
                              ),
                              child: ClipRRect(
                                borderRadius: BorderRadius.circular(18),
                                child: CachedNetworkImage(
                                  imageUrl: item['image'] as String,
                                  width: 110,
                                  height: 110,
                                  fit: BoxFit.cover,
                                  placeholder: (_, __) =>
                                      Container(color: Colors.grey.shade100),
                                  errorWidget: (_, __, ___) =>
                                      const Icon(Icons.image, color: Colors.grey),
                                ),
                              ),
                            ),
                            if (item['discount'] != null)
                              Positioned(
                                top: 6,
                                left: 6,
                                child: Container(
                                  padding: const EdgeInsets.symmetric(
                                      horizontal: 6, vertical: 2.5),
                                  decoration: BoxDecoration(
                                    color: const Color(0xFF704FEE),
                                    borderRadius: BorderRadius.circular(6),
                                  ),
                                  child: Text(
                                    item['discount'] as String,
                                    style: const TextStyle(
                                      color: Colors.white,
                                      fontSize: 9,
                                      fontWeight: FontWeight.w800,
                                    ),
                                  ),
                                ),
                              ),
                          ],
                        ),
                        const SizedBox(height: 6),
                        Text(
                          item['title'] as String,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                            color: darkTextColor,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Row(
                          children: [
                            Text(
                              '\$${item['price']}',
                              style: const TextStyle(
                                fontSize: 12.5,
                                fontWeight: FontWeight.w800,
                                color: Color(0xFF704FEE),
                              ),
                            ),
                            if (item['originalPrice'] != null) ...[
                              const SizedBox(width: 4),
                              Text(
                                '\$${item['originalPrice']}',
                                style: TextStyle(
                                  fontSize: 10,
                                  color: Colors.grey.shade400,
                                  decoration: TextDecoration.lineThrough,
                                ),
                              ),
                            ],
                          ],
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
          ),
      ],
    );
  }

  /// 6. Dynamic Store Products Section (Filtered by Category!)
  Widget _buildBestSellingSection(
    Color primaryColor,
    Color darkTextColor,
    Set<String> wishlistedIds,
    List<Map<String, dynamic>> products,
  ) {
    final displayedProducts = _showAllBestSelling
        ? products
        : products.take(4).toList();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Row(
              children: [
                Text(
                  _selectedCategory == 'All' ? 'Products' : '$_selectedCategory Collection',
                  style: TextStyle(
                    fontSize: 17,
                    fontWeight: FontWeight.w800,
                    color: darkTextColor,
                    letterSpacing: -0.3,
                  ),
                ),
                const SizedBox(width: 6),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                  decoration: BoxDecoration(
                    color: const Color(0xFFEDE9FE),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(
                    '${products.length}',
                    style: const TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.bold,
                      color: Color(0xFF704FEE),
                    ),
                  ),
                ),
              ],
            ),
            if (products.length > 2)
              GestureDetector(
                onTap: () {
                  setState(() => _showAllBestSelling = !_showAllBestSelling);
                },
                child: Text(
                  _showAllBestSelling ? 'Show Less' : 'See All',
                  style: const TextStyle(
                    fontSize: 12.5,
                    fontWeight: FontWeight.w600,
                    color: Color(0xFF704FEE),
                  ),
                ),
              ),
          ],
        ),
        const SizedBox(height: 14),

        if (displayedProducts.isEmpty)
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(vertical: 30, horizontal: 20),
            decoration: BoxDecoration(
              color: const Color(0xFFF9FAFB),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: Colors.grey.shade200),
            ),
            child: Column(
              children: [
                Icon(Icons.inventory_2_outlined, size: 36, color: Colors.grey.shade400),
                const SizedBox(height: 8),
                Text(
                  'No products found in category "$_selectedCategory"',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: Colors.grey.shade600, fontSize: 13),
                ),
              ],
            ),
          )
        else
          GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            itemCount: displayedProducts.length,
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 2,
              crossAxisSpacing: 14,
              mainAxisSpacing: 18,
              childAspectRatio: 0.73,
            ),
            itemBuilder: (context, index) {
              final item = displayedProducts[index];
              return _buildProductCardItem(item, darkTextColor, wishlistedIds);
            },
          ),
      ],
    );
  }

  /// 7. In-Place Search Results (Matches WooCommerce products!)
  Widget _buildSearchResultsSection(
    Color primaryColor,
    Color darkTextColor,
    Set<String> wishlistedIds,
    List<Map<String, dynamic>> results,
  ) {

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              'Search Results (${results.length})',
              style: TextStyle(
                fontSize: 17,
                fontWeight: FontWeight.w800,
                color: darkTextColor,
                letterSpacing: -0.3,
              ),
            ),
            TextButton.icon(
              onPressed: () {
                _searchController.clear();
                setState(() => _searchQuery = '');
              },
              icon: const Icon(Icons.close_rounded, size: 16),
              label: const Text('Clear'),
              style: TextButton.styleFrom(
                foregroundColor: primaryColor,
                padding: EdgeInsets.zero,
              ),
            ),
          ],
        ),
        const SizedBox(height: 14),
        if (results.isEmpty)
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(vertical: 40, horizontal: 20),
            decoration: BoxDecoration(
              color: const Color(0xFFF9FAFB),
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: Colors.grey.shade200),
            ),
            child: Column(
              children: [
                Icon(Icons.search_off_rounded, size: 48, color: Colors.grey.shade400),
                const SizedBox(height: 12),
                Text(
                  'No products found matching "$_searchQuery"',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                    color: darkTextColor,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  'Try searching for "Hoodie", "Watch", "Nike", "Bag", or "Perfume"',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 12.5,
                    color: Colors.grey.shade500,
                  ),
                ),
                const SizedBox(height: 16),
                ElevatedButton(
                  onPressed: () {
                    _searchController.clear();
                    setState(() => _searchQuery = '');
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: primaryColor,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                  ),
                  child: const Text('Clear Search'),
                ),
              ],
            ),
          )
        else
          GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            itemCount: results.length,
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 2,
              crossAxisSpacing: 14,
              mainAxisSpacing: 18,
              childAspectRatio: 0.73,
            ),
            itemBuilder: (context, index) {
              final item = results[index];
              return _buildProductCardItem(item, darkTextColor, wishlistedIds);
            },
          ),
      ],
    );
  }

  /// Reusable 2-Column Product Card
  Widget _buildProductCardItem(
    Map<String, dynamic> item,
    Color darkTextColor,
    Set<String> wishlistedIds,
  ) {
    final isWishlisted = wishlistedIds.contains(item['id']);

    return GestureDetector(
      onTap: () => _navigateToProduct(item['id'] as String),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Studio Image Container with Floating Heart
          AspectRatio(
            aspectRatio: 1.0,
            child: Stack(
              children: [
                Container(
                  width: double.infinity,
                  height: double.infinity,
                  decoration: BoxDecoration(
                    color: const Color(0xFFF3F4F6),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(20),
                    child: CachedNetworkImage(
                      imageUrl: item['image'] as String,
                      width: double.infinity,
                      height: double.infinity,
                      fit: BoxFit.cover,
                      placeholder: (_, __) => const Center(
                        child: SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        ),
                      ),
                      errorWidget: (_, __, ___) =>
                          const Icon(Icons.image, color: Colors.grey),
                    ),
                  ),
                ),
                // Heart Icon (Clean white circle with subtle shadow)
                Positioned(
                  top: 10,
                  right: 10,
                  child: GestureDetector(
                    onTap: () {
                      ref
                          .read(wishlistProvider.notifier)
                          .toggle(item['id'] as String);
                    },
                    child: Container(
                      width: 32,
                      height: 32,
                      decoration: BoxDecoration(
                        color: Colors.white,
                        shape: BoxShape.circle,
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withOpacity(0.08),
                            blurRadius: 6,
                            offset: const Offset(0, 2),
                          ),
                        ],
                      ),
                      child: Icon(
                        isWishlisted
                            ? Icons.favorite_rounded
                            : Icons.favorite_border_rounded,
                        size: 16,
                        color: isWishlisted
                            ? const Color(0xFFEF4444)
                            : const Color(0xFF9CA3AF),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 10),

          // Product Title
          Text(
            item['title'] as String,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w700,
              color: Color(0xFF1E1B4B),
            ),
          ),
          const SizedBox(height: 3),

          // Product Price
          Text(
            '\$${item['price']}',
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w900,
              color: Color(0xFF1E1B4B),
            ),
          ),
        ],
      ),
    );
  }
}
