# FILE 4 — Security, Key Points, Development Roadmap & Feature Coverage

> Yeh file sabse zaroori hai. Har coding session mein Antigravity ko yeh bhi do. Yahan likha har rule "non-negotiable" hai.

---

## PART A — Security: 20 non-negotiable rules

### 1. Secrets & credentials
1. Client ki keys (ck/cs, Shopify tokens, WhatsApp token, Firebase service account) DB mein **encrypted** (Laravel `encrypted` cast ya libsodium). Key `.env`/KMS mein, repo mein kabhi nahi.
2. Keys kabhi API response, logs, error pages, Sentry, browser mein nahi. Sirf masked (`ck_****1234`).
3. Mobile app mein koi secret nahi. Sirf public `APP_KEY`.
4. `.env`, keystore files, `*.jks`, service JSON `.gitignore` mein; git history mein leak ho jaye toh turant rotate.
5. Keystore passwords encrypted + alag backup (offline). Keystore khoya = us app ki update band.

### 2. Tenant isolation (customer data mismatch roknay ke liye)
6. Har tenant table mein `tenant_id`; global scope; tenant_id request se kabhi nahi, sirf auth context se.
7. **IDOR block:** public IDs ULID, route binding tenant-scoped, Policy har controller par. Doosre tenant ka ID → **404** (403 nahi, taaki existence leak na ho).
8. Cache keys mein tenant + app (+ user agar personal). Shared cache mein personal data nahi.
9. Orders/profile endpoints par: token → app_user → `store_customer_id` match; backend store se laaye data ko us customer ke ID se filter kare (Woo keys admin-level hain, isliye yeh filter backend ka kaam hai).
10. Queue jobs/logs/files mein tenant context. Storage paths `tenants/{id}/...`, private disk, direct public URL nahi.

### 3. Direct page / route access rokna
11. **Dono taraf guard:** frontend redirect (UX) + backend enforcement (asli security). Setup step URL se skip: backend 403 `STEP_LOCKED`.
12. Admin panel alag domain, 2FA mandatory, IP allowlist (optional), login rate limit. `/admin` client domain par exist hi na kare.
13. Downloads (APK/AAB, invoices): **signed temporary URL** (10–15 min) + tenant check. Public storage URL nahi.
14. Debug/dev routes (`telescope`, `horizon`, `phpinfo`, `/_ignition`) production mein band ya admin-auth ke peeche.
15. Directory listing off, `.git`/`.env`/`storage`/`vendor` web root ke bahar. Nginx rules se block.

### 4. Input & injection
16. Har endpoint par FormRequest validation (type, length, enum). Mass assignment: `$fillable` strict, `$guarded=[]` kabhi nahi.
17. SQL: Eloquent/query builder bindings, raw queries mein bhi bindings. XSS: output escape, store kiya HTML (product description) **sanitize** (HTMLPurifier / DOMPurify). CSRF: cookie auth par CSRF token/SameSite.
18. **SSRF:** client ka URL fetch karte waqt UrlGuard (https only, private IP/metadata block, redirect limit, timeout, size cap). Yeh SaaS ka #1 risk hai kyunki users URL dete hain.
19. File uploads: MIME + extension + size + dimension check, re-encode image, random filename, executable upload block. SVG avoid.
20. Webhooks: HMAC signature verify (constant-time compare), timestamp tolerance, `event_id` unique (replay block), idempotent processing.

### 5. Auth hardening
- Password: bcrypt/argon2, min length, breach check optional; login rate limit + lockout; email verification.
- Sanctum tokens: expiry, abilities (`app:{id}`), revoke on logout/password change.
- OTP: hashed, expiry 5 min, 5 attempts, per-phone + per-IP + per-device throttles (SMS bombing/cost attack se bachne ke liye), CAPTCHA jab abuse dikhe.
- Dashboard cookie: `HttpOnly; Secure; SameSite=Lax/Strict`.
- Sensitive actions (key replace, delete app, change email) par password/OTP re-confirm.
- Impersonation: sirf owner, banner dikhaye, `audit_logs` mein entry.

### 6. Payments
- Amount server-side calculate; Razorpay signature verify; success **sirf webhook** se.
- Payment ID unique constraint (double-credit nahi).
- Refund/cancel flows webhook-driven; subscription expiry par features lock.

### 7. Infra & transport
- HTTPS everywhere, HSTS, TLS 1.2+.
- Security headers: CSP, X-Content-Type-Options, X-Frame-Options/frame-ancestors, Referrer-Policy, Permissions-Policy.
- CORS: sirf apne dashboard origins; `*` kabhi nahi.
- Rate limiting: IP + tenant + user (login, OTP, verify-connection, public API). Cloudflare/WAF aage.
- Server: SSH keys only, firewall (80/443 hi open), fail2ban, auto security updates, DB public nahi (private network/localhost).
- Backups: daily DB + storage, off-site, **restore test** monthly. Keystore backup alag.
- Dependencies: `composer audit`, `npm audit`, Dependabot; pinned versions.
- Build worker: container isolated, secrets sirf us build ke liye, logs sanitized, outbound network limited.

### 8. Privacy & compliance
- Privacy Policy, Terms, Refund policy, data deletion flow (user delete → app_users, devices, tokens purge).
- India DPDP Act ke hisaab se consent + purpose limitation; store ka asli data hum nahi rakhte, sirf cache.
- Play Store Data Safety form sahi bharna (galat declaration = app reject).
- Audit logs 1 saal retain.

---

## PART B — "Loophole checklist" (Antigravity ko bug-hunt ke liye)

| # | Attack / bug | Kaise test karein | Expected |
|---|---|---|---|
| 1 | Tenant A token se Tenant B ka app/order maangna | ID badal ke request | 404 |
| 2 | Wizard step skip (URL/Postman se) | step 8 API direct call | 403 STEP_LOCKED |
| 3 | Build bina payment | build API direct | 403 |
| 4 | Doosre ka APK download link guess | URL badalna | 404/403, link expire |
| 5 | SSRF: site URL = `http://169.254.169.254` / `localhost` / redirect trick | verify call | blocked |
| 6 | Secret leak | API responses/logs/grep `cs_` | kahin nahi |
| 7 | OTP brute force / SMS bombing | 100 requests | throttle + lock |
| 8 | Fake Razorpay webhook | galat signature | 400 |
| 9 | Webhook replay | same event_id | ignore |
| 10 | Mass assignment (`role=owner`, `tenant_id=x`) | extra fields bhejna | ignore/422 |
| 11 | XSS in product description/app name | `<script>` payload | escaped/sanitized |
| 12 | User A logout → User B login same phone | app data check | A ka kuch nahi |
| 13 | Shared cache leakage | do users parallel | alag data |
| 14 | Malicious deep link (`javascript:`/external) | push payload | reject |
| 15 | Webview arbitrary URL | config tamper | allowlist block |
| 16 | Admin URL on client domain | `/admin` | 404 |
| 17 | Expired subscription par feature use | push/whatsapp send | 403 |
| 18 | Race condition: double-click payment/build | parallel requests | ek hi build/payment |
| 19 | Large upload / zip bomb / SVG script | upload | reject |
| 20 | APK reverse engineer | strings/jadx se keys | koi secret nahi |

**Race conditions:** payment, build creation, OTP verify, coupon par DB transaction + `lockForUpdate` / unique constraints / idempotency keys.

---

## PART C — Key points (yaad rakhne wale)

1. **Ek Flutter codebase, per-client config.** Code copy kabhi nahi.
2. **App → Backend → Store**, app direct store se nahi.
3. **Payment & shipping store ka hi** (webview checkout). Hum naya gateway nahi banate.
4. **Backend state machine hi truth hai**, frontend sirf dikhata hai.
5. **Config snapshot version** se build; draft aur published alag.
6. **Android pehle, iOS baad mein** (Mac runner + Apple account chahiye).
7. **WooCommerce pehle, Shopify baad mein.**
8. **Client apna Play Console account** banaye; aap AAB do.
9. Customization limit rakho (themes + tokens + toggles), warna maintenance maar dega.
10. **Cost tracking shuru se** (`usage_events`), plan price pilot data se.
11. Firebase: ek project mein apps ki limit hoti hai, `firebase_project_id` per app store karo.
12. Platform API versions pin karo aur quarterly upgrade karo.
13. Har release se pehle loophole checklist (Part B) chalao.
14. Woo REST keys admin-level hain: customer data filtering backend ki zimmedari hai.
15. Pehle 2–3 pilot clients, phir public launch.

---

## PART D — Development roadmap (realistic, solo dev + AI coding)

| Phase | Deliverable | Done kab maane |
|---|---|---|
| **0** (week 1) | Repo, Docker, Laravel base, migrations, auth, tenants, CI | Login + tenant create + tests green |
| **1** (wk 2–3) | Woo connector, UrlGuard, verify, CommonProduct, encrypted creds | Test Woo store verify + products JSON |
| **2** (wk 4–6) | Flutter engine: splash, config, home, catalog, product, search | Real Woo products app mein |
| **3** (wk 7–8) | app-config API, 3 themes, dashboard wizard (steps 1–8), live preview | Theme badalte hi preview badle |
| **4** (wk 9–10) | Cart, webview checkout, OTP login, orders, profile, delete account | End-to-end order test |
| **5** (wk 11–13) | Build pipeline (Android), keystore, Firebase provisioner | Ek client ka signed AAB download |
| **6** (wk 14–15) | Razorpay billing, plans/entitlements, usage tracking | Payment → build unlock |
| **7** (wk 16–17) | FCM campaigns, order push, WhatsApp (guided) | Order status par push |
| **8** (wk 18–19) | Security audit, load test, monitoring, backups, docs | Part B ke saare tests pass |
| **9** (wk 20–22) | Closed beta 3–5 clients, bug fixes | Real feedback incorporated |
| **10** | Shopify connector | Shopify test store live |
| **11** | iOS build, readiness score ML/AI, courses/booking modules | Roadmap ke hisaab se |

**Rule:** har phase ke end mein demo + tests + security checklist. Phase complete hue bina agla nahi.

---

## PART E — Feature coverage matrix

| Feature | MVP | Phase 2 |
|---|---|---|
| WooCommerce connect | ✅ | |
| Shopify connect | | ✅ |
| Android build | ✅ | |
| iOS build | | ✅ |
| Themes (3) | ✅ | more |
| Live preview | ✅ | |
| Cart + webview checkout | ✅ | native checkout |
| Phone OTP, Google login | ✅ | Facebook/Apple |
| Push notifications | ✅ | segmentation, automation |
| WhatsApp | | ✅ |
| Razorpay subscription | ✅ | usage billing |
| Readiness score | basic | AI |
| Courses / booking | | ✅ |
| Analytics dashboard | basic | advanced |
| Multi-language | | ✅ |
| Team roles | | ✅ |

---

## PART F — Definition of Done (har feature ke liye)

- [ ] Validation + Policy + tenant scope
- [ ] Tests (happy + abuse cases)
- [ ] Error codes + user-friendly messages
- [ ] Logs mein secrets nahi
- [ ] Rate limit laga
- [ ] Loading/empty/error states (UI)
- [ ] Docs/README update
