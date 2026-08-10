# Selbuildi — Product & Technical Plan

> Building the Infrastructure of Trust
> Laravel 13 · Blade + Livewire (Volt) · Tailwind CSS · MySQL · Deployed on Dreamhost

This is the working plan for Selbuildi end to end: who uses it, what the database looks like, how each screen behaves, how it looks and moves, and the order we build it in. Update this file as decisions change — it's the source of truth, not a one-time spec.

---

## Table of Contents

1. [Vision Recap](#1-vision-recap)
2. [User Roles](#2-user-roles)
3. [Design System](#3-design-system)
4. [Database Schema](#4-database-schema)
5. [Procurement Trust Score & Credit](#5-procurement-trust-score--credit)
6. [Core Screens & Flows](#6-core-screens--flows)
7. [Tech Stack Decisions](#7-tech-stack-decisions)
8. [Deployment Plan (Dreamhost)](#8-deployment-plan-dreamhost)
9. [Roadmap](#9-roadmap)
10. [Open Decisions](#10-open-decisions)

---

## 1. Vision Recap

Selbuildi connects Africans at home and abroad with suppliers, contractors, and (eventually) financial institutions across four layers:

- **Commerce** — buy building materials from verified suppliers, from anywhere.
- **Logistics** — track delivery of every order in real time.
- **Finance** — a Procurement Trust Score built from purchase history, unlocking credit.
- **Trust** — every transaction visible, every party verified, no dependence on informal middlemen.

MVP focus: a customer can browse materials, buy them, pay, and track delivery to a building site — with the data model already shaped to support contractor accounts, supplier onboarding, and credit later without a rebuild.

---

## 2. User Roles

| Role | Description | MVP scope |
|---|---|---|
| **Customer** | Individual buyer — diaspora or local — buying materials for a home/project | Full: browse, cart, checkout, track, credit (once eligible) |
| **Contractor** | Verified business/professional buyer, usually higher order volume, may run multiple projects | Customer capabilities + Project grouping, bulk pricing, priority credit review |
| **Supplier** | Vendor listing and fulfilling materials | Phase 5: product & inventory management, order fulfillment dashboard |
| **Admin** | Platform operator | Phase 5: verification queue, catalog moderation, order oversight, trust score/credit approval |
| **Financial Partner** *(future)* | External institution consuming trust-score data to extend real financing | Post-launch: read/API access only |

Implementation: `spatie/laravel-permission` for roles, with `users.role` as the primary discriminator (customer / contractor / supplier / admin) plus fine-grained permissions layered on top for admin sub-roles later. Contractor is not a separate table — it's a `users` row with `role = contractor` and an optional `contractor_profile`.

---

## 3. Design System

### 3.1 Color palette

Extracted directly from `public/images/logo.jpeg` (pixel-sampled, not eyeballed) and expanded into a tint/shade scale.

| Token | Hex | Use |
|---|---|---|
| `navy-900` | `#060F27` | Hero/footer backgrounds, deepest surfaces |
| `navy-700` | `#0A1B47` | Primary brand color (logo navy) — headers, primary text on light bg |
| `navy-500` | `#606B87` | Secondary surfaces, muted text, hover states on navy elements |
| `navy-100` | `#E6E8ED` | Subtle tinted backgrounds, dividers |
| `gold-700` | `#A36F00` | Pressed/active accent |
| `gold-500` | `#D99400` | Primary accent (logo gold) — CTAs, highlights, active states |
| `gold-300` | `#E4B44C` | Hover/lighter accent |
| `gold-100` | `#F9EFD9` | Subtle accent backgrounds (badges, callouts) |
| `neutral-50` | `#F7F8FA` | Page background |
| `neutral-900` | `#1A1D23` | Body text |
| Semantic | green/red/blue (standard) | Success / error / info — kept distinct from gold to avoid confusion with brand accent |

Rule: **navy carries authority, gold carries action.** Every primary CTA is gold-on-navy or gold-on-white; navy is never used for a button a user needs to notice urgently.

### 3.2 Typography

- **Headings**: Sora or Plus Jakarta Sans — geometric, blocky, echoes the logo's angular "S/B" mark.
- **Body**: Inter — high legibility at small sizes on mobile.
- Self-hosted (Bunny Fonts or local) for performance and privacy — no Google Fonts CDN call.

### 3.3 Motion principles

Since we're on Blade + Livewire (no SPA framework), motion comes from **Alpine.js (bundled with Livewire) + Tailwind transitions + `wire:navigate`** — no heavy animation library needed, which also keeps the site fast on slower mobile connections.

- **Durations**: 150ms micro (hover, focus), 250ms standard (dropdowns, drawers), 400–600ms section reveals.
- **Easing**: `ease-out` for entrances, `ease-in` for exits — nothing linear.
- **Hover**: subtle scale (1.02–1.05) + shadow elevation, not color-only.
- **Scroll reveals**: Intersection Observer (small Alpine directive) fades/slides sections into view once, not on every scroll pass.
- **Loading states**: Livewire `wire:loading` skeleton shimmer (navy/gold-tinted gray), never a blank screen or generic spinner on product grids.
- **Page transitions**: `wire:navigate` for SPA-like nav with a thin gold progress bar at the top — avoids full page reloads between browse → product → cart.
- **Respect `prefers-reduced-motion`** — all non-essential motion disabled for users who request it.

### 3.4 Component inventory

Buttons (primary/secondary/ghost), product card, order card, badges (trust tier chips: Bronze/Silver/Gold/Platinum), stepper/timeline (reused across checkout, order tracking, and the landing "How it Works" section), slide-over drawer (cart), modal, toast (Livewire flash messages), floating-label form inputs, empty states, skeleton loaders.

---

## 4. Database Schema

Field-level plan (not full migrations yet) — grouped by domain.

### 4.1 Identity

- **users** — id, name, email, phone, password, role (`customer|contractor|supplier|admin`), country, is_diaspora, kyc_status, avatar, email_verified_at, phone_verified_at
- **addresses** — polymorphic (`addressable_type/id`), label, recipient_name, phone, country, region, city, street, landmark, lat, lng, is_default
- **contractor_profiles** — user_id, company_name, license_no, verified_at
- **supplier_profiles** — user_id, business_name, registration_no, description, logo_path, verified_at
- **kyc_documents** — polymorphic documentable, type, file_path, status, reviewed_at, reviewed_by

### 4.2 Catalog

- **categories** — id, parent_id (nested: Cement, Steel/Rebar, Roofing, Tiles, Sanitary, Electrical, Plumbing, Paint, Tools, Aggregates, Blocks, Timber, Doors & Windows), name, slug, image, sort_order
- **brands** — id, name, logo_path
- **products** — id, supplier_id, category_id, brand_id, name, slug, sku, description, unit (`bag|ton|piece|meter|liter|roll`), price, compare_at_price, min_order_qty, weight_kg, is_active, is_featured
- **product_images** — product_id, path, sort_order
- **product_variants** — product_id, name (e.g. "50kg bag" vs "25kg bag"), sku, price, stock_qty
- **warehouses** — supplier_id, name, address_id, supports_pickup
- **inventories** — product_id/variant_id, warehouse_id, quantity_available, quantity_reserved

### 4.3 Cart & Orders

- **carts** — user_id (nullable for guest via session_id)
- **cart_items** — cart_id, product_id, variant_id, quantity, unit_price_snapshot
- **projects** *(contractor/customer project grouping)* — user_id, name, address_id, budget, start_date, status
- **orders** — id, order_number, user_id, project_id (nullable), status (`pending→confirmed→processing→shipped→out_for_delivery→delivered→cancelled/refunded`), subtotal, shipping_fee, tax, discount, total, currency, payment_status, payment_method, shipping_address_id, placed_at
- **order_items** — order_id, product_id, variant_id, supplier_id, quantity, unit_price, total_price, fulfillment_status *(multi-supplier orders split fulfillment per item)*
- **order_status_history** — order_id, status, note, changed_by, created_at
- **shipments** — order_id/supplier grouping, carrier, tracking_number, status, estimated_delivery_date, delivered_at
- **shipment_events** — shipment_id, status, location, note, occurred_at *(drives the live tracking timeline)*
- **payments** — order_id, provider, amount, currency, status, reference, paid_at
- **reviews** — product_id, user_id, order_item_id, rating, comment, images

### 4.4 Trust & Credit

- **procurement_trust_scores** — user_id, score (0–100), tier (`unrated|bronze|silver|gold|platinum`), calculated_at *(cached current value)*
- **trust_score_events** — user_id, event_type (`order_completed|on_time_payment|late_payment|dispute|cancellation`), points_delta, related_order_id, created_at *(event-sourced — score is derived, never hand-edited)*
- **credit_accounts** — user_id, credit_limit, available_credit, status (`none|pending|approved|suspended`), approved_at, reviewed_by
- **credit_transactions** — credit_account_id, order_id (nullable), type (`drawdown|repayment|fee|adjustment`), amount, balance_after, due_date, paid_at, status (`pending|due|overdue|paid`)

---

## 5. Procurement Trust Score & Credit

**Score**: 0–100, event-sourced from `trust_score_events`, recalculated after every order lifecycle event (not stored as a raw editable number, so it's auditable).

| Tier | Score | Unlocks |
|---|---|---|
| Unrated | new account, no history | Pay in full only (card / mobile money / bank transfer) |
| Bronze | 1–40 | Same as Unrated + eligible to apply for credit |
| Silver | 41–65 | 30% deposit + balance-on-delivery for small orders |
| Gold | 66–85 | Net-15 credit up to a set limit |
| Platinum | 86–100 | Net-30 credit, higher limit, priority support |

**Contributing events**: on-time full payment (+), order completed without dispute (+), order frequency/volume (+), verified KYC/phone/email (+ one-time). Late payment (−), cancelled/disputed order (−), chargeback (−, heavier weight).

**Credit flow**: customer requests credit → system checks tier → auto-approve within tier limit, or route to admin review for larger asks → `credit_accounts` created/updated → at checkout, "Pay with Selbuildi Credit" appears only if `available_credit >= order total` → on order confirmation, a `drawdown` transaction reduces available credit → repayment schedule generated with due dates → reminders sent → on repayment, a positive trust event fires; on overdue, a negative one fires and further credit is frozen until resolved.

---

## 6. Core Screens & Flows

### 6.1 Landing page

1. **Hero** — full-bleed navy background, gold CTA, animated headline fade-in, "Shop Materials" (primary) + "Learn about Credit" (secondary).
2. **Trust bar** — animated count-up stats (orders fulfilled, verified suppliers, cities covered).
3. **Category grid** — icon tiles per material category, hover lift + shadow.
4. **Featured products carousel** — quick add-to-cart, skeleton loading state.
5. **How it Works** — Browse & Order → Verified & Packed → Track Delivery → Build with Confidence, scroll-triggered reveal with a connecting animated line.
6. **Diaspora section** — "Building from abroad?" messaging with a dashboard/phone mockup — this is a key differentiator, give it real visual weight.
7. **Trust & Credit teaser** — animated gauge/meter explaining the Procurement Trust Score.
8. **Testimonials** slider.
9. **Supplier CTA** — "Are you a supplier? Join Selbuildi."
10. **Footer** — navy background, gold links, newsletter signup.

### 6.2 Auth (login/signup)

- Two-column layout: left brand panel (navy gradient, logo, rotating tagline), right form panel with floating-label inputs.
- Signup captures: name, email/phone, password, country, and a diaspora toggle ("I'm ordering from abroad") that separates *residence country* from *delivery country*.
- Account type at signup: Individual/Customer vs Contractor (business) — Supplier signup routes to a separate vetting application, not instant activation.
- Local Cameroon numbers get OTP phone verification.
- Micro-interactions: shake animation on validation error, success check animation on submit, gold focus rings.
- Already scaffolded via Breeze (Livewire) — this phase extends it with the fields/branding above.

### 6.3 Cart

- Persistent header cart icon with live item count (Livewire, no reload).
- Slide-over drawer from the right: line items, quantity steppers, remove, subtotal, "Checkout" + "Continue Shopping".
- Guest cart via session, merged into account cart on login.
- **Multi-supplier aware** — items grouped by supplier since fulfillment/shipping splits per supplier.
- Empty-state illustration, not just blank space.

### 6.4 Checkout / order flow

1. **Cart review** — grouped by supplier, quantity edits, promo code.
2. **Delivery details** — select/add address, delivery type (site delivery vs warehouse pickup), optional link to a Project.
3. **Delivery schedule** — preferred date/window where available.
4. **Payment** — card, mobile money (MTN/Orange), bank transfer, Selbuildi Credit (shown only if eligible, with repayment terms displayed up front), and an international-card path for diaspora customers.
5. **Review & place order** — summary + terms.
6. **Confirmation** — order number, animated success check, estimated delivery timeline, "Track Order" CTA.

### 6.5 Order tracking

Visual stepper: Placed → Confirmed → Processing → Shipped → Out for Delivery → Delivered, with per-supplier sub-status since one order can involve multiple vendors. Live updates via `shipment_events`; SMS + email on each major status change (SMS matters more than email for trust in this market).

### 6.6 Supplier & Admin (Phase 5)

- **Supplier dashboard**: product/inventory management, order fulfillment queue, payout ledger.
- **Admin dashboard**: verification queue (KYC), catalog moderation, order oversight, trust score audit log, credit approval console, basic analytics (GMV, active users, top categories). Recommend building this on **Filament** rather than custom Blade — it gets you a polished, functional back-office fast without spending design budget where the client won't be looking day to day; the customer-facing site stays fully custom for brand control.

---

## 7. Tech Stack Decisions

| Concern | Choice | Why |
|---|---|---|
| Framework | Laravel 13 | Already set up |
| Frontend | Blade + Livewire/Volt + Tailwind | Decided — no separate JS build complexity, server-rendered, fastest path to a polished mobile-responsive site |
| Roles/Permissions | `spatie/laravel-permission` | Standard, well-supported |
| Media | `spatie/laravel-medialibrary` | Product images, KYC docs, responsive conversions |
| Audit trail | `spatie/laravel-activitylog` | Needed for trust score & credit changes — this is a financial trust product, changes must be auditable |
| Search | Start with MySQL full-text; upgrade to Laravel Scout + Meilisearch if catalog grows | Avoid infra overhead until it's needed |
| Notifications | Laravel Notifications: mail + database + SMS channel | SMS is trusted more than email in this market for delivery updates |
| Queues | Database driver | Already default; deployment implications below |
| Admin panel | Filament (Phase 5) | Fast, polished, keeps custom design effort on the customer-facing site |
| Payments | Flutterwave primary (strong XAF / mobile money support), Paystack as alternate, manual bank transfer fallback | Needs confirming — see Open Decisions |

---

## 8. Deployment Plan (Dreamhost)

**Status: implemented**, also moved to `github.com/ebubelife/selbuildiapp` (replaces the earlier `selbuildi` repo). Went through a fair number of iterations to find what this specific shared hosting account actually tolerates:
1. Plain FTP — never worked (530 Login incorrect, every time). Turned out the "FTP" credentials were really SFTP/SSH credentials; this account has full SSH shell access, not plain FTP.
2. rsync-over-SSH of the whole app (including a CI-built `vendor/`) — got its connection killed partway through (`broken pipe`, `0 bytes received`) once it had to negotiate thousands of small files in one held-open session.
3. Shipped only source (no `vendor/`) as a single tarball, ran `composer install` on the server via SSH exec — the *upload* worked fine, but **any** non-interactive SSH command execution (even a trivial `echo`, tested in isolation) either hung indefinitely or returned a fake-looking success after ~2 minutes with zero actual output. This wasn't a tooling problem (ruled out FTP-Deploy-Action, rsync, and scp-action, each with their own unrelated quirks along the way) — it's specific to non-interactive SSH exec on this account, while interactive sessions (password login, FileZilla SFTP) always work correctly.
4. Tried shipping source only + a `/deploy-hook` web route to run `composer install` remotely over HTTP instead of SSH — missed that the hook route is itself part of the app, so it can't be what installs `vendor/` on the very first deploy (nothing can boot Laravel without `vendor/autoload.php` already existing). Chicken-and-egg, not a hosting quirk.
5. **Current approach**: build `vendor/` on CI like originally, ship it via `scp -r` (not rsync — betting rsync's own bidirectional protocol was what got killed in #2, not sheer size) and use the web hook only for what genuinely needs to run *after* the app can already boot: migrations and cache warmup.

### 8.1 Server layout

Dreamhost's file manager gives us `/home/dh_p722sp/selbuildi.com/` as the web-accessible document root for the domain, with no ability to point it at a `public/` subfolder. So the app is split across two sibling directories under the same account:

```
/home/dh_p722sp/
├── selbuildi-app/        ← NOT web-accessible — the whole Laravel app
│   ├── app/, bootstrap/, config/, database/, routes/, storage/, vendor/, ...
│   └── .env               ← lives here only, managed by hand, never touched by CI
│
└── selbuildi.com/         ← web-accessible (Apache doc root) — public/'s contents only
    ├── index.php
    ├── .htaccess
    ├── build/              ← compiled CSS/JS
    └── images/, favicons, etc.
```

To make this work, two files are environment-aware (safe for both local dev and this layout, no separate "deploy" copies to keep in sync):
- **`public/index.php`** — detects whether `vendor/` is a direct sibling (local dev) or whether it needs to reach into `../selbuildi-app/` (production), via a simple `is_dir()` check.
- **`bootstrap/app.php`** — after building `$app`, checks whether a sibling `selbuildi.com` directory exists; if so, calls `$app->usePublicPath(...)` so `asset()`/Vite manifest resolution points at the right place.

### 8.2 CI/CD — GitHub Actions

`.github/workflows/deploy.yml` runs on every push to `main`, and **never invokes SSH command execution at all** — only SSH-based file transfer (`scp`, which Dreamhost handles fine) plus a plain HTTPS request:
1. Checkout, `composer install --no-dev --optimize-autoloader` and `npm run build` on the CI runner.
2. Locally (on the runner, no network involved) stage a clean copy with `rsync -a --exclude=...`, dropping `.git`, `node_modules`, `tests`, `.github`, `public`, `.env` — `vendor/` *is* included this time.
3. `scp -r` the staged tree directly into `selbuildi-app/`, and `public/`'s contents directly into `selbuildi.com/` — no server-side extraction needed, so no SSH command execution required for file placement.
4. `curl -X POST https://selbuildi.com/deploy-hook` with a secret token — a Laravel route (`DeployController`) that runs `storage:link`, `migrate --force`, `config:cache`, `route:cache`, `view:cache`, all **inside the normal PHP-FPM/Apache request path**, not over SSH. It doesn't run `composer install` — by the time this route is reachable at all, `vendor/` already shipped with the `scp` step above, since the route itself is part of the app that needs `vendor/` to boot.

**Why the web-hook exists**: the artisan commands still need *some* form of remote command execution — but since SSH exec is what's broken here, that execution happens through the one channel that reliably works instead: a plain web request to the app's own server. The route is protected by `hash_equals()` comparing against `DEPLOY_HOOK_TOKEN` (set in the server's `.env`, never committed) and is excluded from CSRF verification in `bootstrap/app.php` since it's called externally without a Laravel session.

**Required GitHub repo secrets**: `SSH_HOST`, `SSH_USERNAME`, `SSH_PRIVATE_KEY` (dedicated `github-actions-deploy@selbuildi` keypair, added to `~/.ssh/authorized_keys`, independently revocable — used only for `scp`, never for running commands), and `DEPLOY_HOOK_TOKEN` (matches the server's `.env` value).

### 8.3 One-time manual setup (not automated, and shouldn't be)

- Create `~/selbuildi-app/` on the server once, manually over SFTP — `scp -r` can't create a brand-new top-level directory it's copying multiple files into.
- Create `selbuildi-app/.env` by hand on the server (production `APP_KEY`, DB credentials, `APP_URL=https://selbuildi.com`, `APP_ENV=production`, `APP_DEBUG=false`, and a random `DEPLOY_HOOK_TOKEN` matching the GitHub secret) — deliberately never touched by CI.
- Create the production MySQL database via the Dreamhost panel (migrations then run automatically on the next deploy once `.env` is in place).
- SSL via Dreamhost's Let's Encrypt panel option — confirmed already working (`https://selbuildi.com` resolves).
- **Still an open item**: no persistent queue worker or scheduler daemon on shared hosting — anything queued needs `QUEUE_CONNECTION=sync` for now, or a cron-triggered `queue:work --stop-when-empty` / `schedule:run` if Dreamhost cron jobs are available on this plan.

---

## 9. Roadmap

- **Phase 0 — Foundation** ✅ *(done)*: Laravel + MySQL + Breeze (Livewire) auth, base project structure.
- **Phase 1 — Design System & Landing** ✅ *(done)*: Tailwind theme with brand colors/fonts, component library, animated landing page, updated auth screens, responsive nav/footer.
- **Phase 2 — Catalog & Browsing** *(next up)*: categories, products, product detail page, search & filters, public supplier profiles.
- **Phase 3 — Cart & Checkout**: cart drawer, multi-supplier cart, addresses, checkout flow, first payment integration, order confirmation.
- **Phase 4 — Order Tracking & Notifications**: status timeline, shipment events, email/SMS notifications, order history.
- **Phase 5 — Roles Expansion**: contractor projects, supplier dashboard, Filament admin panel.
- **Phase 6 — Trust & Credit**: trust score engine, credit checkout option, admin credit approval workflow.
- **Phase 7 — Polish & Launch**: performance pass, accessibility, SEO, security review, Dreamhost deployment, monitoring. *(Deployment pipeline built — §8 — currently blocked on the hosting account's SSH/PHP-version setup, pending the site manager.)*
- **Phase 8+ — Future**: financial institution integrations, artisan/architect marketplace, mobile app, multi-country expansion.

---

## 10. Open Decisions

These need your input before the relevant phase starts — flagged here rather than guessed at:

1. **Payment provider** — Flutterwave vs Paystack vs both; how diaspora card payments are handled if the primary provider restricts foreign-issued cards.
2. ~~Exact brand colors~~ — **decided**: extracted from the logo, see §3.1.
3. **SMS provider** for delivery notifications (local Cameroon aggregator vs a regional/international one like Twilio).
4. ~~Launch country scope~~ — **decided**: Cameroon-only at launch. Region/city fields in `addresses` should still be structured generically (not hardcoded to Cameroon) so multi-country expansion later doesn't require a schema change — just data.
5. **Supplier onboarding gatekeeping** — self-serve signup with async admin verification, or fully manual admin-created supplier accounts at launch.
