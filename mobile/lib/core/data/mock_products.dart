class DetailedProduct {
  final String id;
  final String title;
  final double price;
  final double? originalPrice;
  final double rating;
  final int reviewsCount;
  final List<String> images;
  final String description;
  final List<String> colors;
  final List<String> features;

  const DetailedProduct({
    required this.id,
    required this.title,
    required this.price,
    this.originalPrice,
    required this.rating,
    required this.reviewsCount,
    required this.images,
    required this.description,
    required this.colors,
    required this.features,
  });
}

const mockProductList = <DetailedProduct>[
  DetailedProduct(
    id: '20',
    title: 'shirt 2',
    price: 79.0,
    originalPrice: 300.0,
    rating: 4.9,
    reviewsCount: 86,
    images: [
      'https://testing.livaan.in/wp-content/uploads/2026/10/bd.png',
      'https://testing.livaan.in/wp-content/uploads/2026/10/bg-1.png',
      'https://testing.livaan.in/wp-content/uploads/2026/10/bg.png',
      'https://testing.livaan.in/wp-content/uploads/2026/10/bg-image.png',
    ],
    description:
        'संयुक्त सैन्य अभ्यास के दौरान दोनों देशों के सैनिक आधुनिक युद्ध तकनीकों का अभ्यास कर रहे हैं। उच्च गुणवत्ता वाली शर्ट, दैनिक उपयोग और फॉर्मल वियर के लिए उपयुक्त।',
    colors: ['#704FEE', '#1E1B4B', '#F59E0B', '#EF4444'],
    features: [
      '100% Authentic Quality',
      'Direct from Testing Livaan',
      'Fast Shipping Available',
      'Easy 7-Day Returns',
    ],
  ),
  DetailedProduct(
    id: '21',
    title: 'shirt 2 (Copy)',
    price: 79.0,
    originalPrice: 300.0,
    rating: 4.8,
    reviewsCount: 54,
    images: [
      'https://testing.livaan.in/wp-content/uploads/2026/10/bd.png',
      'https://testing.livaan.in/wp-content/uploads/2026/10/bg-1.png',
      'https://testing.livaan.in/wp-content/uploads/2026/10/bg.png',
    ],
    description:
        'प्रीमियम कॉटन फैब्रिक के साथ तैयार की गई स्टाइलिश और कम्फर्टेबल शर्ट।',
    colors: ['#704FEE', '#1E1B4B', '#F59E0B'],
    features: [
      '100% Authentic Quality',
      'Direct from Testing Livaan',
      'Fast Shipping Available',
    ],
  ),
  DetailedProduct(
    id: '22',
    title: 'shirt 2 (Copy 2)',
    price: 79.0,
    originalPrice: 300.0,
    rating: 4.7,
    reviewsCount: 38,
    images: [
      'https://testing.livaan.in/wp-content/uploads/2026/10/bd.png',
      'https://testing.livaan.in/wp-content/uploads/2026/10/bg-image.png',
    ],
    description:
        'क्लासिक डिजाइन और आरामदायक फिटिंग।',
    colors: ['#704FEE', '#1E1B4B'],
    features: [
      '100% Authentic Quality',
      'Direct from Testing Livaan',
    ],
  ),
  DetailedProduct(
    id: '19',
    title: 'shirt (Copy)',
    price: 79.0,
    originalPrice: 300.0,
    rating: 4.9,
    reviewsCount: 92,
    images: [
      'https://testing.livaan.in/wp-content/uploads/2026/10/bd.png',
      'https://testing.livaan.in/wp-content/uploads/2026/10/bg.png',
    ],
    description:
        'प्रीमियम फैब्रिक, ड्यूरेबल स्टिचिंग और बेहतरीन लुक।',
    colors: ['#704FEE', '#1E1B4B', '#EF4444'],
    features: [
      '100% Authentic Quality',
      'Direct from Testing Livaan',
    ],
  ),
];

DetailedProduct getProductById(String id) {
  return mockProductList.firstWhere(
    (p) => p.id == id,
    orElse: () => mockProductList.first,
  );
}
