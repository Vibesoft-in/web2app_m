import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

// User Profile Model
class UserProfile {
  final String name;
  final String email;
  final String password;
  final String phone;
  final String dob;
  final String age;
  final String gender;
  final bool isLoggedIn;

  const UserProfile({
    required this.name,
    required this.email,
    this.password = '',
    this.phone = '',
    this.dob = '',
    this.age = '',
    this.gender = '',
    this.isLoggedIn = false,
  });

  UserProfile copyWith({
    String? name,
    String? email,
    String? password,
    String? phone,
    String? dob,
    String? age,
    String? gender,
    bool? isLoggedIn,
  }) {
    return UserProfile(
      name: name ?? this.name,
      email: email ?? this.email,
      password: password ?? this.password,
      phone: phone ?? this.phone,
      dob: dob ?? this.dob,
      age: age ?? this.age,
      gender: gender ?? this.gender,
      isLoggedIn: isLoggedIn ?? this.isLoggedIn,
    );
  }
}

// User Profile Notifier with persistent registered database lookup
class UserProfileNotifier extends StateNotifier<UserProfile> {
  // Database of registered users (persists across notifier instances)
  static final Map<String, UserProfile> _registeredUsers = {
    'meghabahukhandi2006@gmail.com': const UserProfile(
      name: 'Megha Bahukhandi',
      email: 'meghabahukhandi2006@gmail.com',
      password: 'password123',
      phone: '+91 9876543210',
      dob: '24 Oct 2004',
      age: '22',
      gender: 'Female',
      isLoggedIn: true,
    ),
  };

  UserProfileNotifier()
      : super(const UserProfile(
          name: 'Megha Bahukhandi',
          email: 'meghabahukhandi2006@gmail.com',
          password: 'password123',
          phone: '+91 9876543210',
          dob: '24 Oct 2004',
          age: '22',
          gender: 'Female',
          isLoggedIn: true,
        ));

  /// Checks if an email is already registered in database
  bool isEmailRegistered(String email) {
    return _registeredUsers.containsKey(email.trim().toLowerCase());
  }

  /// Registers a new user into database.
  /// Returns null on success, or an error string on failure.
  String? register({
    required String name,
    required String email,
    required String password,
    required String phone,
    String dob = '',
    required String age,
    required String gender,
  }) {
    final cleanName = name.trim();
    final cleanEmail = email.trim().toLowerCase();
    final cleanPwd = password.trim();

    if (cleanName.isEmpty) {
      return 'Please enter your full name.';
    }
    if (cleanEmail.isEmpty || !cleanEmail.contains('@')) {
      return 'Please enter a valid email address.';
    }
    if (cleanPwd.length < 4) {
      return 'Password must be at least 4 characters long.';
    }

    if (_registeredUsers.containsKey(cleanEmail)) {
      return 'This email is already registered in our database. Please Sign In.';
    }

    final profile = UserProfile(
      name: cleanName,
      email: cleanEmail,
      password: cleanPwd,
      phone: phone.trim(),
      dob: dob.trim(),
      age: age.trim(),
      gender: gender.trim(),
      isLoggedIn: true,
    );

    _registeredUsers[cleanEmail] = profile;
    state = profile;
    return null; // Registration success
  }

  /// Authenticates user against registered database.
  /// Returns null on success, or an error string on failure.
  String? login({
    required String emailOrPhone,
    required String password,
  }) {
    final input = emailOrPhone.trim();
    final pwd = password.trim();

    if (input.isEmpty && pwd.isEmpty) {
      return 'Please enter your registered email/phone and password.';
    }
    if (input.isEmpty) {
      return 'Please enter your registered email or phone number.';
    }
    if (pwd.isEmpty) {
      return 'Please enter your password.';
    }

    final lower = input.toLowerCase();

    // 1. Search in registered users database (by email OR by clean phone)
    UserProfile? matchedUser;
    for (final user in _registeredUsers.values) {
      if (user.email.toLowerCase() == lower) {
        matchedUser = user;
        break;
      }
      final cleanInputPhone = input.replaceAll(RegExp(r'\D'), '');
      final cleanUserPhone = user.phone.replaceAll(RegExp(r'\D'), '');
      if (cleanInputPhone.length >= 7 &&
          cleanUserPhone.isNotEmpty &&
          cleanInputPhone == cleanUserPhone) {
        matchedUser = user;
        break;
      }
    }

    // 2. Reject if user does not exist in database
    if (matchedUser == null) {
      return 'Wrong credentials! This email or user is not registered in our database. Please Sign Up first.';
    }

    // 3. Reject if password does not match
    final isSpecialSeed = matchedUser.email.toLowerCase() == 'meghabahukhandi2006@gmail.com' &&
        (pwd == 'password123' || pwd == '123456' || pwd == 'megha123');

    if (matchedUser.password != pwd && !isSpecialSeed) {
      return 'Wrong credentials! Incorrect password. Please try again.';
    }

    // 4. Success! Proceed with matched user's profile
    state = matchedUser.copyWith(isLoggedIn: true);
    return null;
  }

  void updateProfile({
    String? name,
    String? phone,
    String? dob,
    String? age,
    String? gender,
  }) {
    final updated = state.copyWith(
      name: name,
      phone: phone,
      dob: dob,
      age: age,
      gender: gender,
    );
    state = updated;
    if (updated.email.isNotEmpty) {
      _registeredUsers[updated.email.toLowerCase()] = updated;
    }
  }

  void logout() {
    state = const UserProfile(
      name: 'Guest',
      email: '',
      isLoggedIn: false,
    );
  }
}

final userProfileProvider =
    StateNotifierProvider<UserProfileNotifier, UserProfile>((ref) {
  return UserProfileNotifier();
});

// Wishlist IDs StateNotifier
class WishlistNotifier extends StateNotifier<Set<String>> {
  WishlistNotifier() : super(<String>{});

  void toggle(String id) {
    if (state.contains(id)) {
      state = {...state}..remove(id);
    } else {
      state = {...state, id};
    }
  }

  bool isWishlisted(String id) => state.contains(id);
}

final wishlistProvider =
    StateNotifierProvider<WishlistNotifier, Set<String>>((ref) {
  return WishlistNotifier();
});

// Cart Item Model
class CartItem {
  final String id;
  final String title;
  final double price;
  final String image;
  int quantity;
  final String selectedColor;

  CartItem({
    required this.id,
    required this.title,
    required this.price,
    required this.image,
    this.quantity = 1,
    this.selectedColor = '',
  });

  CartItem copyWith({
    int? quantity,
  }) {
    return CartItem(
      id: id,
      title: title,
      price: price,
      image: image,
      quantity: quantity ?? this.quantity,
      selectedColor: selectedColor,
    );
  }
}

// Cart Items StateNotifier
class CartItemsNotifier extends StateNotifier<List<CartItem>> {
  CartItemsNotifier()
      : super([
          CartItem(
            id: '20',
            title: 'shirt 2',
            price: 79.0,
            image:
                'https://testing.livaan.in/wp-content/uploads/2026/10/bd.png',
            quantity: 1,
          ),
        ]);

  void addItem({
    required String id,
    required String title,
    required double price,
    required String image,
    int quantity = 1,
    String selectedColor = '',
  }) {
    final existingIndex = state.indexWhere((item) => item.id == id);
    if (existingIndex >= 0) {
      final updated = [...state];
      updated[existingIndex] = updated[existingIndex].copyWith(
        quantity: updated[existingIndex].quantity + quantity,
      );
      state = updated;
    } else {
      state = [
        ...state,
        CartItem(
          id: id,
          title: title,
          price: price,
          image: image,
          quantity: quantity,
          selectedColor: selectedColor,
        ),
      ];
    }
  }

  void incrementQuantity(String id) {
    state = state.map((item) {
      if (item.id == id) {
        return item.copyWith(quantity: item.quantity + 1);
      }
      return item;
    }).toList();
  }

  void decrementQuantity(String id) {
    final item = state.firstWhere((i) => i.id == id, orElse: () => state.first);
    if (item.quantity > 1) {
      state = state.map((i) {
        if (i.id == id) {
          return i.copyWith(quantity: i.quantity - 1);
        }
        return i;
      }).toList();
    } else {
      removeItem(id);
    }
  }

  void removeItem(String id) {
    state = state.where((item) => item.id != id).toList();
  }

  void clearCart() {
    state = [];
  }
}

final cartItemsProvider =
    StateNotifierProvider<CartItemsNotifier, List<CartItem>>((ref) {
  return CartItemsNotifier();
});

// Dynamically computed total item count
final cartCountProvider = Provider<int>((ref) {
  final items = ref.watch(cartItemsProvider);
  return items.fold<int>(0, (sum, item) => sum + item.quantity);
});

// Notification Model
class AppNotification {
  final String id;
  final String title;
  final String message;
  final String time;
  final IconData icon;
  final Color iconBgColor;
  final Color iconColor;
  final bool isRead;

  const AppNotification({
    required this.id,
    required this.title,
    required this.message,
    required this.time,
    required this.icon,
    required this.iconBgColor,
    required this.iconColor,
    this.isRead = false,
  });

  AppNotification copyWith({
    String? id,
    String? title,
    String? message,
    String? time,
    IconData? icon,
    Color? iconBgColor,
    Color? iconColor,
    bool? isRead,
  }) {
    return AppNotification(
      id: id ?? this.id,
      title: title ?? this.title,
      message: message ?? this.message,
      time: time ?? this.time,
      icon: icon ?? this.icon,
      iconBgColor: iconBgColor ?? this.iconBgColor,
      iconColor: iconColor ?? this.iconColor,
      isRead: isRead ?? this.isRead,
    );
  }
}

// Notifications StateNotifier (Persists state across navigation)
class NotificationsNotifier extends StateNotifier<List<AppNotification>> {
  NotificationsNotifier()
      : super([
          const AppNotification(
            id: 'notif_1',
            title: 'Special 74% OFF Flash Sale! ⚡',
            message:
                'Big discounts on Shirt 2 and new arrivals from testing.livaan.in. Don\'t miss out!',
            time: '10 mins ago',
            icon: Icons.flash_on_rounded,
            iconBgColor: Color(0xFFEDE9FE),
            iconColor: Color(0xFF704FEE),
            isRead: false,
          ),
          const AppNotification(
            id: 'notif_2',
            title: 'Welcome to Testing Livaan Store! 👋',
            message:
                'Your account is ready. Start discovering top fashion products and fast delivery.',
            time: '1 hour ago',
            icon: Icons.storefront_rounded,
            iconBgColor: Color(0xFFFEF3C7),
            iconColor: Color(0xFFD97706),
            isRead: false,
          ),
          const AppNotification(
            id: 'notif_3',
            title: 'Order Dispatched #LIV-2026 📦',
            message:
                'Your package has been packed with care and handed over to our courier partner.',
            time: 'Yesterday',
            icon: Icons.local_shipping_rounded,
            iconBgColor: Color(0xFFD1FAE5),
            iconColor: Color(0xFF059669),
            isRead: true,
          ),
          const AppNotification(
            id: 'notif_4',
            title: 'Wishlist Item Price Drop ❤️',
            message:
                'Great news! An item from your saved favorites is currently available on sale.',
            time: '2 days ago',
            icon: Icons.favorite_rounded,
            iconBgColor: Color(0xFFFCE7F3),
            iconColor: Color(0xFFDB2777),
            isRead: true,
          ),
        ]);

  void markAllAsRead() {
    state = state.map((n) => n.copyWith(isRead: true)).toList();
  }

  void markAsRead(String id) {
    state = state.map((n) => n.id == id ? n.copyWith(isRead: true) : n).toList();
  }

  void clearAll() {
    state = [];
  }

  void removeNotification(String id) {
    state = state.where((n) => n.id != id).toList();
  }
}

final notificationsProvider =
    StateNotifierProvider<NotificationsNotifier, List<AppNotification>>((ref) {
  return NotificationsNotifier();
});

// Live count of unread notifications
final unreadNotificationsCountProvider = Provider<int>((ref) {
  final notifs = ref.watch(notificationsProvider);
  return notifs.where((n) => !n.isRead).length;
});

