# FILE 1 — Client Website + Dashboard (WebIntoApp)

> Is file ko Antigravity ko do jab client-facing website, onboarding wizard aur dashboard banana ho.
> Backend ke API contracts File 2 mein hain. Security rules File 4 mein hain. Dono bhi saath do.

---

## 1. Is website ke 3 hisse

| Hissa | Domain (example) | Kaun use kare |
|---|---|---|
| Marketing site | `webintoapp.com` | Visitors (SEO, pricing, demo) |
| Client dashboard | `app.webintoapp.com` | Paying/trial clients |
| Owner admin | `admin.webintoapp.com` | Sirf aap (alag domain, alag login, 2FA) |

Teeno alag subdomain par rakho. Admin ko kabhi client dashboard ke saath mix mat karo.

## 2. Tech stack

- Next.js 14+ (App Router) + TypeScript + Tailwind + shadcn/ui
- Forms: react-hook-form + zod (frontend validation, par asli validation backend karega)
- Data fetching: TanStack Query
- Auth: backend se Sanctum token, **httpOnly secure cookie** mein (localStorage mein kabhi nahi)
- Live preview: phone-frame React component (config JSON se render)
- Payment: Razorpay Checkout (subscription)

## 3. Marketing site pages

`/` home, `/pricing`, `/features`, `/themes` (demo gallery), `/how-it-works`, `/woocommerce-app`, `/shopify-app` (SEO landing pages), `/blog`, `/contact`, `/privacy`, `/terms`, `/refund-policy`.

Local/SEO ke liye har platform ki alag landing page rakho, schema markup aur fast loading ke saath.

## 4. Auth pages

`/signup`, `/login`, `/forgot-password`, `/verify-email`, `/verify-phone` (OTP).
Rules: email verify hue bina dashboard nahi, rate limit login par, 2FA optional (client ke liye), password min 10 chars.

## 5. Onboarding wizard (sabse important)

Wizard ek **server-driven state machine** hai. Frontend sirf dikhata hai, asli control backend ka hai.

```
/dashboard/apps/new
  Step 1  platform      (Shopify / WooCommerce)
  Step 2  connect       (URL + keys)  → Verify
  Step 3  analysis      (App Readiness Score)
  Step 4  features
  Step 5  theme
  Step 6  branding      (app name, icon, colors, splash)
  Step 7  preview
  Step 8  integrations  (push auto, WhatsApp optional, OTP/login)
  Step 9  plan + payment
  Step 10 build + download
```

**Direct page open rokne ka rule (server-side):**
- Har step ka URL `/dashboard/apps/{appId}/setup/{step}` hai.
- Page load par frontend `GET /api/apps/{id}/setup-state` call karta hai. Response: `{ current_step, completed: [...], allowed: [...] }`.
- Agar user ne URL se aage ka step khola jo `allowed` mein nahi, **redirect** to `current_step`.
- Frontend guard sirf UX hai. **Backend har step ka API khud bhi reject karta hai** agar pichla step verified nahi (403 `STEP_LOCKED`). Dono jagah check zaroori hai.
- `appId` ek random **ULID/UUID** ho, 1,2,3 nahi (guess nahi hoga). Aur backend har request mein check kare ki app is user ke tenant ka hai.

### Step 2 detail (Connect)

WooCommerce form:
- Site URL (https zaroori)
- Consumer Key (`ck_...`), Consumer Secret (`cs_...`)
- Help panel: "WooCommerce → Settings → Advanced → REST API → Add key → Read/Write"
- Button: **Verify Connection**

Shopify form:
- Store domain (`xyz.myshopify.com`)
- Admin API access token, Storefront access token
- Help: Custom app kaise banate hain (screenshots)

Verify ke baad dikhao: store name, products count, currency, WooCommerce version. Fail hone par saaf errors:
`URL_UNREACHABLE`, `NOT_HTTPS`, `REST_BLOCKED`, `INVALID_KEYS`, `INSUFFICIENT_PERMISSION`, `PLUGIN_MISSING`.

Secret field: save hone ke baad dobara kabhi na dikhao (sirf `ck_****abcd` masked). Edit karna ho toh "Replace key".

### Step 3 Readiness Score
Backend score deta hai (0–100) catalog size, order frequency, repeat customers ke basis par, aur reasons list. UI: gauge + "Recommended / Not yet recommended" + tips.

### Step 5 Theme
Gallery mein 3+ themes (shuru mein 3). Har card par live mini-preview. Theme badalne par config draft mein save hota hai, build mein nahi.

### Step 7 Live preview
- Left: form fields. Right: phone frame.
- Field change → 400ms debounce → `PUT /apps/{id}/config/draft` → preview re-render.
- Preview **draft** config se, published app **published** config se.
- Real products dikhao (backend preview endpoint se), dummy nahi, taaki client ko asli feel mile.

### Step 8 Integrations
- Push: "Auto-configured" green tick (aap Firebase handle karte ho).
- Login methods toggles: Phone OTP, Google, Email.
- WhatsApp (optional): token, phone number ID, template names, "Send test message".

### Step 9 Payment
Plan cards + dynamic price calculator (base + Android + iOS + features). Razorpay checkout. Payment ki sachai **sirf webhook** se maani jaye (frontend success par bharosa nahi).

### Step 10 Build
- "Generate App" button tab hi enabled jab backend `build_allowed: true` de.
- Build status live (queued → preparing → building → signing → ready) polling ya SSE se.
- Download: **signed, expiring URL** (15 min), sirf owner tenant ko.
- Guide: Play Console par kaise upload karein, Data Safety form, privacy policy URL.

## 6. Dashboard pages

| Route | Kaam |
|---|---|
| `/dashboard` | Apps list, health badges, alerts |
| `/dashboard/apps/{id}` | App overview, status, version |
| `.../design` | Theme/branding edit (published ke liye "Publish changes") |
| `.../features` | Feature toggles (plan ke hisaab se lock) |
| `.../notifications` | Push campaigns, schedule, history |
| `.../whatsapp` | Setup, templates, wallet |
| `.../customers` | App users count, anonymized stats |
| `.../orders` | Orders read-only (store se) |
| `.../builds` | Build history, logs, download |
| `.../integrations` | Connection health, replace keys |
| `/dashboard/billing` | Plan, invoices, usage, wallet |
| `/dashboard/team` | Team members + roles (owner/editor/viewer) |
| `/dashboard/support` | Tickets |
| `/dashboard/settings` | Profile, 2FA, delete account |

## 7. Owner admin (Filament, Laravel mein)

Tenants, Apps, Builds (retry, logs), Plans & features, Subscriptions, Usage & cost, Connection health, Support, Impersonate (audit log ke saath), Audit logs, Feature flags. Alag domain, 2FA mandatory, optional IP allowlist.

## 8. UI/UX rules

- Mobile-responsive dashboard
- Har form par loading, error, success states
- Verify fail par exact reason + fix link
- Unsaved changes warning
- Empty states (pehla app banao CTA)
- Hindi/English toggle (baad mein)

## 9. Acceptance checklist (Antigravity ko do)

- [ ] Koi bhi step URL se skip nahi hota (frontend + backend dono)
- [ ] Doosre tenant ka appId daalne par 404
- [ ] Secret key response/UI/console/logs mein kahin nahi
- [ ] Token httpOnly cookie mein hai
- [ ] Payment success sirf webhook se confirm
- [ ] Download link expiring + tenant-checked
- [ ] Sab forms par zod + server-side validation
- [ ] Lighthouse mobile 90+ on marketing site
