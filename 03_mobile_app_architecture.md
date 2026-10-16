# FILE 3 — Mobile App (Flutter White-Label Engine): Flow, Architecture, Detail

> Ek hi Flutter codebase sabhi clients ke liye. Har client ka fark sirf config + build-time values se aata hai.

---

## 1. Core idea

- **Build-time (har client alag):** `applicationId/package`, app name, icon, splash, `google-services.json`, `APP_KEY`, `API_BASE_URL`.
- **Runtime (bina rebuild ke badle):** theme colors, fonts, layout sections, feature toggles, banners, texts → `GET /v1/app-config`.
- App mein **koi secret nahi**. Sirf public `APP_KEY`. Store ki keys, Razorpay/Firebase service keys kabhi nahi.

## 2. Stack

- Flutter 3.x stable, Dart null-safety
- State: Riverpod (ya Bloc, ek hi use karo)
- Network: Dio + interceptors (auth, retry, logging off in release)
- Navigation: go_router (deep links ke saath)
- Storage: `flutter_secure_storage` (tokens), Hive/Isar (cache)
- Push: firebase_messaging + flutter_local_notifications
- Webview: `webview_flutter` / `flutter_inappwebview`
- Images: cached_network_image
- Flavors: dart-define se `APP_KEY`, `API_BASE_URL`
- Crash: Sentry/Firebase Crashlytics

## 3. Folder structure

```
lib/
 ├── main.dart
 ├── core/
 │    ├── env/ (AppEnv: APP_KEY, API_BASE_URL via String.fromEnvironment)
 │    ├── network/ (dio_client.dart, interceptors/auth, app_key, retry, error_mapper)
 │    ├── config/ (app_config_model.dart, config_repository.dart, config_cache.dart)
 │    ├── theme/ (theme_engine.dart: tokens → ThemeData)
 │    ├── routing/ (router.dart, guards.dart)
 │    ├── storage/ (secure_store.dart)
 │    ├── security/ (url_allowlist.dart, root_check(optional))
 │    └── widgets/ (loaders, error_view, empty_view)
 ├── features/
 │    ├── splash/ (config fetch, version check, force update)
 │    ├── auth/ (phone_login, otp, google_login, session)
 │    ├── home/ (section_renderer: banners, categories, product rails)
 │    ├── catalog/ (list, filters, search)
 │    ├── product/ (details, variants, gallery)
 │    ├── wishlist/
 │    ├── cart/ (local cart → checkout url)
 │    ├── checkout/ (webview + return handling)
 │    ├── orders/ (list, details, tracking)
 │    ├── profile/ (edit, address, delete account)
 │    ├── notifications/ (inbox, permission, topic)
 │    └── courses|booking/ (Phase 2 modules, feature flag se)
 ├── layouts/ (theme_a/, theme_b/, theme_c/ — sirf layout presets)
 └── l10n/
```

## 4. Startup flow

```
App open
 → Splash
 → GET /v1/app-config (X-App-Key, X-App-Version)
     ├─ fail → cached config use (offline-safe) / retry screen
     ├─ maintenance=true → maintenance screen
     ├─ min_version > current → force-update screen
     └─ ok → ThemeEngine apply → Router
 → Session check (secure storage token)
     ├─ valid → Home
     └─ guest → Home (guest browsing allowed) / login jab zaroori ho
```

## 5. app-config JSON (contract)

```json
{
  "schema_version": 1,
  "config_version": 14,
  "app": { "name": "Glow Beauty", "currency": "INR", "min_version": "1.0.0", "maintenance": false },
  "theme": {
    "layout": "theme_a",
    "colors": { "primary": "#E91E63", "secondary": "#222222", "background": "#FFFFFF", "surface": "#FAFAFA", "text": "#111111" },
    "radius": 12, "font": "Poppins", "nav": "bottom"
  },
  "features": { "wishlist": true, "otp_login": true, "google_login": true, "notifications": true, "reviews": false },
  "home": [
    { "type": "banner", "items": [{ "image": "https://...", "link": "category:12" }] },
    { "type": "categories" },
    { "type": "product_rail", "title": "New Arrivals", "source": "recent" }
  ],
  "links": { "privacy": "https://...", "terms": "https://...", "support_phone": "+91..." },
  "webview_allowed_hosts": ["store.example.com"]
}
```
App unknown `type`/field par **crash nahi karega**, ignore karega (forward-compat). Colors/links validate karke hi use.

## 6. Screens & flows

1. **Home:** `home[]` sections se dynamic render.
2. **Catalog/Search:** pagination (infinite scroll), filters, debounce search (400ms).
3. **Product:** gallery, variants, stock, add to cart/wishlist.
4. **Cart:** local state (Hive) → checkout par backend se validate (price/stock backend/store ki sachai).
5. **Checkout:** `POST /v1/cart/checkout-url` → URL milta hai → webview mein khulta hai.
6. **Orders:** login required, `GET /v1/orders` (backend sirf us customer ke orders deta hai), tracking.
7. **Profile:** details edit, logout, **delete account** (Play Store mandatory).
8. **Notifications:** inbox + deep link open.

## 7. Checkout webview — safe tareeka

- Webview sirf `webview_allowed_hosts` ke URLs allow kare; baaki URL **external browser** mein ya block.
- JavaScript enabled, par file access/`allowFileAccess` off, mixed content block.
- Payment gateways ke redirects (Razorpay/UPI intents) handle karne ke liye gateway domains allowlist mein rakhne padte hain; UPI apps ke liye `upi://` intent handle.
- Success detection: order-received URL pattern ya `postMessage`; phir `GET /v1/orders` se **server se confirm**, sirf URL par bharosa nahi.
- Webview mein auth cookies shared nahi hote; guest ya store ka login jaisa chahiye usi hisab se plan.
- Back button/close par cart sync.

## 8. Auth flow

```
Phone → POST /v1/auth/otp/request
OTP   → POST /v1/auth/otp/verify → { access_token, refresh_token, user }
Tokens → flutter_secure_storage (Keychain/Keystore)
401 → refresh once → fail → logout + login screen
```
- Token kisi bhi log/analytics mein nahi.
- Logout par: token delete, FCM token unregister (`DELETE /v1/devices`), cart/user cache clear.
- **Same phone par user switch:** purana user ka cache/orders memory mein na rahe (logout par sab clear) — customer data mix hone ka sabse common bug.

## 9. Push notifications

- Permission Android 13+ (POST_NOTIFICATIONS) runtime par mango, sahi moment par.
- Login ke baad `POST /v1/devices` fcm_token; token refresh par update.
- Payload mein `deep_link` (`product:123`, `order:456`) → router handle kare, **validate** karke (arbitrary URL nahi).
- Tenant topics: apna Firebase app hi alag hai, isliye cross-client notification possible hi nahi.

## 10. Caching & offline

- Config: last good config disk par.
- Products/categories: short TTL (5 min) cache + stale-while-revalidate.
- **User-specific data (orders/profile/cart) cache key mein user id**, logout par wipe.
- No internet par saaf screen + retry.

## 11. Error handling

- Error mapper: backend `error.code` → user-friendly message.
- Global error boundary (`FlutterError.onError`, `runZonedGuarded`) → Sentry.
- Har list screen ke 4 states: loading / data / empty / error.

## 12. Security (app side)

- Release build: `--obfuscate --split-debug-info`, ProGuard/R8 on.
- HTTPS only (`usesCleartextTraffic=false`), optional certificate pinning (pinning galat karne par app brick hota hai, isliye backup pin rakho).
- Koi API secret/ck/cs/service key app mein nahi (ye sabse badi galti hai).
- Play Integrity API (optional, baad mein) + backend par check.
- Debug logs release mein off, screenshots sensitive screens par (optional).
- Deep links verify (assetlinks.json, App Links).
- Dependencies pinned, `flutter pub outdated` audit.

## 13. Build-time injection (worker karta hai)

| Cheez | Kahan badalti hai |
|---|---|
| package name | `android/app/build.gradle` (`applicationId`) |
| app name | `AndroidManifest` label / strings.xml |
| icon/splash | `flutter_launcher_icons`, `flutter_native_splash` |
| `google-services.json` | `android/app/` |
| APP_KEY, API_BASE_URL | `--dart-define` |
| versionCode/Name | `--build-number`, `--build-name` |
| signing | `key.properties` (keystore tenant-wise) |

Template ka koi aur code badalta nahi. Isse har client ka app same tested engine hota hai.

## 14. Play Store ready checklist

- [ ] Privacy policy URL, account delete URL/in-app
- [ ] Data Safety form (phone, email, device id/FCM)
- [ ] targetSdk latest requirement
- [ ] 64-bit, AAB format
- [ ] Permissions minimal (INTERNET, POST_NOTIFICATIONS)
- [ ] App icon, feature graphic, screenshots
- [ ] Content rating, ads declaration
- [ ] Signed with upload key + Play App Signing on

## 15. Testing

- Unit: config parser, price formatting, cart logic
- Widget tests: home renderer, cart
- Integration test: login → browse → add cart → checkout url
- Manual matrix: Android 8–15, small + large screens, slow network, offline
- **Cross-user test:** User A login → logout → User B login, A ka data kahin na dikhe

## 16. Phase 2 modules (feature flag)

Courses (video lessons, progress, certificates), Booking (slots, reminders), Loyalty/coupons, Reviews, Multi-language.
