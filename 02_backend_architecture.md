# FILE 2 — Backend (Laravel + MySQL) Architecture & Flow

> Antigravity ko yeh file backend banane ke liye do. Security rules ke liye File 4 zaroor saath do.

---

## 1. Stack

- PHP 8.3, Laravel 11, MySQL 8 (utf8mb4), Redis
- Auth: Laravel Sanctum
- Owner panel: Filament 3
- Queues: Laravel Horizon (Redis)
- Scheduler: cron `* * * * * php artisan schedule:run`
- Tests: Pest/PHPUnit, Larastan (static analysis), Pint (style)
- Monitoring: Sentry, Laravel Telescope (sirf local/staging)

## 2. High-level flow

```
Customer App ──(X-App-Key + user token)──► Public API (/v1)
Client Dashboard ──(cookie token)────────► Dashboard API (/api)
Owner (Filament) ────────────────────────► /admin
                       │
        ┌──────────────┼───────────────┐
   Connector layer   Config layer   Build layer
   (Woo/Shopify)     (themes JSON)  (queue → worker)
        │
 Client store REST API (HTTPS, tenant ki encrypted keys se)
```

Teen alag API zones, teeno ki auth alag:
1. `/api/*` — dashboard (user + tenant scoped, Sanctum)
2. `/v1/*` — mobile app (app_key + app_user token)
3. `/webhooks/*` — Razorpay, Shopify, Woo (signature verified, auth nahi)

## 3. Folder structure

```
app/
 ├── Models/ (Tenant, User, App, Connection, AppConfig, Build, Plan, Subscription, AppUser, UsageEvent, AuditLog ...)
 ├── Models/Concerns/BelongsToTenant.php   ← global scope
 ├── Http/Controllers/Api/...              ← dashboard
 ├── Http/Controllers/V1/...               ← mobile
 ├── Http/Controllers/Webhooks/...
 ├── Http/Middleware/ (ResolveTenant, EnsureStepAllowed, ResolveAppKey, ThrottleByTenant, SecurityHeaders)
 ├── Http/Requests/                        ← har endpoint ka FormRequest
 ├── Policies/                             ← har model ki Policy
 ├── Services/
 │    ├── Connectors/ (ConnectorInterface, WooConnector, ShopifyConnector, ConnectorFactory)
 │    ├── Catalog/ (CommonProduct DTO, Mappers)
 │    ├── Config/ (ConfigValidator, ConfigPublisher)
 │    ├── Build/ (BuildOrchestrator, KeystoreService, FirebaseProvisioner)
 │    ├── Billing/ (RazorpayService, EntitlementService, PricingCalculator)
 │    ├── Notifications/ (FcmService, WhatsAppService)
 │    ├── Auth/ (OtpService, SocialLoginService)
 │    └── Security/ (UrlGuard (SSRF), CredentialVault)
 ├── Jobs/ (VerifyConnection, SyncCatalog, RunBuild, SendPush, HealthCheck, ProcessWebhook)
 └── Enums/ (SetupStep, BuildStatus, ConnectionStatus, Platform)
database/migrations/
routes/ (api.php, v1.php, webhooks.php)
```

## 4. Database schema (MySQL)

Sabhi IDs: `ulid` public ke liye, `bigint` internal. Har tenant-owned table mein `tenant_id` + index.

```
tenants: id, ulid, name, owner_user_id, status(active|suspended|trial), created_at
users: id, tenant_id, name, email(unique), phone, password, role(owner|editor|viewer), 2fa_secret(enc), email_verified_at
plans: id, name, price_monthly, features(json), limits(json)
subscriptions: id, tenant_id, app_id, plan_id, status, razorpay_sub_id, current_period_end
payments: id, tenant_id, razorpay_payment_id(unique), amount, status, raw(json)   ← idempotent

apps: id, ulid, tenant_id, name, platform(woo|shopify), package_name(unique), app_key(unique, public),
      setup_step, status(draft|verified|configured|paid|building|live), firebase_project_id, firebase_app_id
connections: id, tenant_id, app_id, platform, site_url, credentials(encrypted json),
             status(not_started|configured|verified|failed), last_verified_at, last_error_code
app_configs: id, tenant_id, app_id, version, state(draft|published), config(json), published_at
themes: id, key, name, preview_url, schema_version, defaults(json), is_active
integrations: id, tenant_id, app_id, type(firebase|whatsapp|otp|google), status, credentials(enc), meta(json)
builds: id, tenant_id, app_id, config_version, status, logs_path, artifact_path, version_code, version_name, error
keystores: id, tenant_id, app_id, path(enc storage), alias, passwords(enc), sha1, sha256   ← backup zaroori

app_users: id, tenant_id, app_id, phone, email, store_customer_id, name, last_login_at
           unique(tenant_id, app_id, phone)
app_user_tokens: Sanctum personal_access_tokens (abilities: tenant+app scoped)
push_devices: id, tenant_id, app_id, app_user_id, fcm_token, platform
notification_campaigns: id, tenant_id, app_id, title, body, audience(json), scheduled_at, status
whatsapp_wallets: tenant_id, balance ; whatsapp_messages: ...
usage_events: id, tenant_id, app_id, type, qty, cost, created_at
webhook_events: id, source, event_id(unique), payload(json), processed_at   ← replay/idempotency
audit_logs: id, tenant_id, actor_id, action, target, ip, user_agent, meta(json), created_at
```

Rules: foreign keys with `ON DELETE RESTRICT/CASCADE` soch kar; `deleted_at` soft deletes; `app_users` ke unique constraint mein hamesha `tenant_id` + `app_id`.

## 5. Multi-tenancy (data mismatch na ho)

1. `BelongsToTenant` trait: global scope `where tenant_id = current` + `creating` event par `tenant_id` auto-set.
2. Tenant resolve: dashboard → logged-in user ka `tenant_id`; mobile → `app_key` → app → tenant. **Request body/URL se tenant_id kabhi nahi lena.**
3. Route model binding mein scope laga hua ho, taaki doosre tenant ka ULID daalne par 404 aaye.
4. Har controller par `$this->authorize()` (Policy).
5. Cache key hamesha `t:{tenant}:a:{app}:...` ; personalized data (cart, orders) **shared cache mein nahi**, ya key mein `u:{app_user}` ho.
6. Queue jobs mein `tenant_id` payload mein, aur job ke start par context set.
7. Tests: "Tenant A ka token Tenant B ka resource maange → 404" har endpoint ke liye.

## 6. Setup state machine

```
enum SetupStep: platform, connect, analysis, features, theme, branding, preview, integrations, payment, build
```
- `apps.setup_step` current step. `EnsureStepAllowed` middleware: route ke step ka order <= current+1 ho warna 403 `STEP_LOCKED`.
- Step complete hone par hi `setup_step` aage badhe (transaction ke andar).
- Peeche jaane par agle steps invalidate (e.g. platform badli → connection reset).
- `build_allowed` = connection verified AND config published/valid AND subscription active AND integrations required verified.

## 7. Connector layer

```php
interface ConnectorInterface {
  public function verify(): VerifyResult;
  public function getProducts(array $q): Paginated; // CommonProduct[]
  public function getProduct(string $id): CommonProduct;
  public function getCategories(): array;
  public function searchProducts(string $term): array;
  public function createCheckoutUrl(Cart $cart, ?AppUser $u): string;
  public function getOrders(AppUser $u, array $q): array;   // sirf us customer ke
  public function getOrderTracking(string $orderId): ?Tracking;
}
```

**CommonProduct:** `id, title, description_html(sanitized), price, sale_price, currency, images[], variants[], in_stock, category_ids[], url`.

### Woo
- Base: `{site}/wp-json/wc/v3/`, Basic auth (ck/cs) **sirf HTTPS par**.
- Verify: `GET /system_status` ya `/products?per_page=1`; permission check (read/write).
- Pagination header `X-WP-TotalPages`. Rate limit/timeout (10s), retry 2 baar with backoff.
- Customer orders: Woo keys admin-level hain, isliye `GET /orders?customer={store_customer_id}` aur **backend filter zaroor lagaye**; response mein doosre customer ka data kabhi return nahi.
- Webhooks register: `product.updated`, `order.updated` → `/webhooks/woo/{app_ulid}` with secret, HMAC (`X-WC-Webhook-Signature`) verify.
- Optional (Phase 2): chhota **WordPress companion plugin** (AWB/tracking, JWT customer login, secure order endpoints).

### Shopify
- Storefront API (GraphQL) cart + `checkoutUrl`; Admin API products/orders. Version pinned (e.g. `2025-01`, upgrade quarterly).
- Webhooks: `products/update`, `orders/updated`, `app/uninstalled`, HMAC (`X-Shopify-Hmac-Sha256`) verify.
- Rate limits (cost-based) handle karo.

### UrlGuard (SSRF protection — must)
Client ka URL hum fetch karte hain, isliye: sirf `https`, DNS resolve karke private/loopback/link-local/metadata IPs (127.0.0.0/8, 10/8, 172.16/12, 192.168/16, 169.254.169.254, ::1) **block**, redirects limit/revalidate, port 443 only, response size/time limit.

## 8. Dashboard API (`/api`) — main endpoints

```
POST /auth/register | login | logout | forgot | reset
POST /apps                         create (platform)
GET  /apps/{ulid}/setup-state
POST /apps/{ulid}/connection/verify
GET  /apps/{ulid}/analysis
PUT  /apps/{ulid}/features
PUT  /apps/{ulid}/config/draft     (ConfigValidator JSON-schema)
POST /apps/{ulid}/config/publish
GET  /apps/{ulid}/preview-data
PUT  /apps/{ulid}/integrations/whatsapp
POST /apps/{ulid}/checkout         (Razorpay order create)
POST /apps/{ulid}/builds           (build_allowed check)
GET  /apps/{ulid}/builds/{id}
GET  /apps/{ulid}/builds/{id}/download   (signed temp URL)
POST /apps/{ulid}/notifications/campaigns
```

## 9. Mobile API (`/v1`)

Headers: `X-App-Key`, `Authorization: Bearer <app_user_token>`, `X-App-Version`, `X-Platform`.

```
GET  /v1/app-config
POST /v1/auth/otp/request   | /otp/verify | /social/google | /logout | DELETE /v1/account
GET  /v1/home | /products | /products/{id} | /categories | /search?q=
POST /v1/cart/checkout-url        (webview URL)
GET  /v1/orders | /orders/{id}    (sirf logged-in customer ke)
POST /v1/devices                  (FCM token register)
```
Public endpoints (config/products) ko bhi `X-App-Key` + throttle chahiye. Orders/profile/devices par token + app_user ownership check.

## 10. Auth (customer app)

- OTP: 6 digit, 5 min expiry, **hash karke store**, max 5 attempts, 3 requests/10 min per phone+IP, resend cooldown 30s. Provider MSG91/Fast2SMS (DLT).
- Token abilities: `app:{app_id}`; middleware verify kare ki token ka app == header ka app_key ka app.
- Store customer link: phone/email se Woo/Shopify customer find/create, `store_customer_id` save.
- Google login: backend par ID token verify (Google ke public keys).
- Refresh tokens + logout-all + account deletion (Play policy).

## 11. Build pipeline

```
POST builds → validate (build_allowed) → Build row(queued) → RunBuild job
Worker (alag server/CI):
 1 config snapshot (version pinned) + assets fetch
 2 FirebaseProvisioner: Android app register, google-services.json, SHA-1 add
 3 KeystoreService: keystore generate/load (encrypted store)
 4 Flutter template copy → inject: package_name, app_name, icon/splash, app_key, API base URL
 5 flutter build appbundle --release (+ apk)
 6 sign → upload (private storage) → Build ready
 7 usage_event + notification
```
- Worker **sirf trusted template** build karta hai. Client ka koi code/script execute nahi hota, sirf validated values (string/image) inject hoti hain.
- Assets: image type/size/dimension validate, re-encode (EXIF strip).
- Build isolated container, no secrets in logs, timeout 30 min, retries 1.
- Concurrency limit per tenant.

## 12. Billing & entitlements

- Razorpay Subscriptions; webhook `payment.captured`, `subscription.charged/cancelled` → `webhook_events` (unique event_id) → idempotent process.
- `EntitlementService::can($tenant, 'whatsapp')`. Plan limits (builds/month, push/month, apps).
- PricingCalculator server-side hi price nikale (client se amount kabhi nahi lena).
- Dunning: expire hone par feature lock, app chalta rahe par push/WhatsApp band (grace 7 din).

## 13. Notifications

- FCM HTTP v1, queue se batch, invalid token cleanup.
- WhatsApp Cloud API: template-only business messages, wallet debit pehle, delivery webhook.
- Triggers: order status change (webhook se) → push to us customer ka device.

## 14. Scheduler (cron jobs)

- Har ghante: `HealthCheck` (connection verify, key expiry, plugin deactive) → client ko alert
- Roz: subscription expiry check, usage rollup, backup verify
- Har 5 min: stuck builds timeout

## 15. Logging & errors

- Structured logs, `request_id` har response mein
- Secrets/PII logs mein mask
- Standard error format: `{ "error": { "code": "STEP_LOCKED", "message": "...", "request_id": "..." } }`
- Production mein stack trace kabhi nahi, `APP_DEBUG=false`

## 16. Testing minimum

- Feature tests: har endpoint, auth, tenant isolation, step lock
- Connector tests: HTTP fakes (Woo/Shopify sample JSON)
- Webhook tests: galat signature reject, replay reject
- Security tests: IDOR, SSRF URLs, mass assignment, rate limits
- CI: Pint + Larastan level 8 + Pest, merge se pehle pass
