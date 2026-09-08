# Stakeholder Feedback Tracker

Feedback from Sir George (project owner), relayed via WhatsApp in dated rounds. Each round becomes its own dated section below. Within a section, every implementation step is a checkbox — check them off as they land. This started as a plan to work from, not a changelog — as of this update, Round 1 sections 1–6 have been implemented and tested (each section's own **Tests** line names the coverage); unchecked boxes are either not yet started or explicitly blocked on a decision noted under that section's **Open decisions**. Round 2 (sections 7–9) has not been started.

Each section follows the same shape: the ask (summarized from the original message), what already exists in the codebase today (verified against the actual code, not assumed), the concrete steps to close the gap, and any open decision that needs an answer before or during implementation.

---

## Round 1 — received 26 Aug 2026

### 1. Homepage & E-Commerce Experience — visual marketplace feel

**The ask:** The homepage should immediately feel like a real building-materials marketplace. Categories need real, high-quality photography (cement shows cement, roofing shows roofing sheets, plumbing shows pipes/fittings, etc.), not just line-icon art. Featured Materials/Popular Products should carry strong imagery + real info (name, spec, price, supplier, availability, verification). Overall journey to communicate: **Discover → Compare → Purchase → Pay → Track → Build Trust.**

**Current state:**
- Categories render a hand-drawn line icon only (`<x-icon>` component, ~12 shapes drawn for this project). The `categories.image` column already exists in the DB (added at some point, currently unused by any view).
- Newer categories added this year (Sand & Granite, Timber & Boards, Plumbing, Electrical, Doors & Windows, Paints & Finishing, Adhesives & Supplies) have **no icon at all** — they fall back to a generic cart icon.
- Featured Materials section on the homepage shows: category icon (not a photo), product name, price. No supplier name, no verification badge, no availability.
- No "journey" narrative element exists yet tying Discover→Track→Trust together (the closest thing is the "Four Connected Infrastructures" section already built).

**Implementation steps:**
- [x] Decide the image sourcing approach for categories — going with AI-generated product photography (a consistent prompt template across all 13 categories, so the set reads as one cohesive photo shoot rather than mismatched stock images)
- [x] Wire `welcome.blade.php`'s "Shop by Category" grid to render the real photo from `categories.image` when one's been uploaded, falling back cleanly to the existing icon when it hasn't (so this ships category-by-category as photos come in, never a broken/empty image in the meantime)
- [x] Upgrade the Featured Materials card (homepage) **and** the shop page's product cards to show: the product's own photo (falls back to the category icon if a supplier hasn't uploaded one yet), supplier business name with a "Verified Supplier" shield badge, and a live In Stock/Out of Stock badge pulled from real `Inventory` data — tested (12 new tests across `ShopTest`/`HomepageTest`)
- [x] Journey narrative — added as a strapline under the "How it Works" heading rather than a whole new section (that section already covers the same journey step-by-step; a second block saying it again would be clutter, not clarity): *Discover → Compare → Purchase → Pay → Track → Build Trust*
- [ ] Upload the actual category photos once generated (admin → Categories → edit → Photo) — the upload field and rendering are both live now, this is just content entry as images are ready
- [ ] Draw/source icons for the 7 categories currently on the generic cart-icon fallback — lower priority now that real photos are the primary visual per category; only matters for any category still without a photo

**Open decisions:** none remaining for this item — resolved by going with AI-generated photography.

---

### 2. Product Catalogue & Procurement Experience

**The ask:** Real products with rich per-product info (image, name, brand/manufacturer, spec/size, price+unit, available quantity, supplier, Verified Supplier status, delivery location/availability, Add to Cart, Request a Quote where applicable). Strong search/filtering — someone should be able to search "50kg cement," "12mm reinforcement steel," "PVC pipe," a brand name, etc. and filter results. Critically: a **"Can't Find What You Need?"** feature — customer submits material/quantity/location/notes (+ optional image/spec upload), it goes to the Selbuildi backend for the team to source and quote.

**Current state:**
- `Product` already has: name, SKU, description, unit, price, compare-at price, min order qty, weight, category, supplier, is_active/is_featured — and a `brand_id` foreign key to an existing (but currently unused/unmanaged) `brands` table + `Brand` model.
- `Product::images()` is a real one-to-many relation (`ProductImage`) supporting multiple photos per product — but the supplier's own product form only lets them upload **one** image today, so the multi-image capability is unused in practice.
- Stock is tracked per product via `Inventory` (quantity available/reserved), but isn't surfaced anywhere on the product page or product card.
- Search is a single `name LIKE %term%` match — doesn't match against brand, spec, SKU, or description. Searching "12mm reinforcement steel" would currently only work if that exact phrase is in the product's name.
- Filtering is category selection + a sort dropdown (featured/price/newest) only. No price range, no verified-supplier-only, no in-stock-only, no brand filter.
- "Request a Quote" and "Can't Find What You Need" don't exist in any form yet.

**Implementation steps:**
- [x] Added a `specification` field to `products` (migration) — went with free-text (e.g. "12mm, Grade 60, 12m length") rather than structured columns: faster to ship and covers Sir George's own examples; can be split into structured fields later if faceted spec filtering is ever needed
- [x] Brand management: `BrandResource` in the admin panel (CRUD, same pattern as `CategoryResource`), and the supplier product form now has a brand select wired to it
- [x] Supplier product form now accepts **multiple** images in one upload (array file input), plus a gallery of existing photos with a remove button per photo
- [x] Real stock/availability surfaced on the product detail page and product cards — three tiers (In Stock / Low Stock / Out of Stock) via `Product::stockStatus()`, using a **10-unit low-stock cutoff as a sensible default** (not a number Sir George specified; easy to move to config if he wants a different threshold — unlike the logistics "delayed" threshold in #4, which is genuinely undefined and left alone)
- [x] Product cards sitewide upgraded to show: photo, brand, price+unit, supplier + verified badge, stock status tier
- [x] Search rebuilt: token-split (so "50kg cement" matches regardless of word order), matching across name, brand, SKU, description, and specification
- [x] Filters added to the shop page: price range (min/max), verified-supplier-only, in-stock-only, brand
- [x] "Request a Quote" and "Can't Find What You Need" shipped as one system: `SupportTicket` with a `type` of `quote_request` vs `procurement_request`, not two parallel systems (see #3)
- [x] "Can't Find What You Need?" flow: customer-facing form (`support/new`) with material/quantity/location description, optional file/image upload, submission confirmation; entry points added on the shop page (empty-state CTA + persistent bottom-of-page strip) and product page ("Request a Quote" link)
- [x] Submitted requests land directly in the `SupportTicketResource` backend inbox (see #3) for the team to review, respond, and quote

**Tests:** `tests/Feature/ShopTest.php` (search across sku/description/spec/brand, out-of-order tokens, price range, verified-only, in-stock-only, brand filter, low-stock tier, spec/brand display on product page), `tests/Feature/BrandAdminTest.php`, updated `tests/Feature/SupplierDashboardTest.php` for multi-image upload. All passing.

---

### 3. Customer Communication & Backend Management

**The ask:** A proper backend system for managing all customer communication — enquiries, procurement requests, questions, complaints — instead of things disappearing into an email inbox. Should show: Customer → Message/Request → Date → Status → Response → Assigned Team Member. Statuses: **New → In Progress → Responded → Resolved.**

**Current state:** Nothing like this exists yet. There's no support-ticket/message model, no customer-facing contact form beyond whatever's implied by the site's own auth/account flows, and no admin inbox. Admin can already send outbound broadcast emails (built this session), but there's no inbound channel at all.

**Implementation steps:**
- [x] New `support_tickets` table: customer (`user_id`, required — see resolved decision below), type (general enquiry / procurement request / quote request / complaint / other), subject, body, optional attachment, status enum (new/in_progress/responded/resolved), assigned admin (nullable FK to `users`), single `response` + `responded_at`, timestamps
- [x] Model + relations (`user()`, `assignedTo()`, `product()` for quote requests) — `SupportTicket`
- [x] Customer-facing submission point — one unified form (`support/new`, Volt component `support.create`) doubling as both the general "Contact/Support" form and the "Can't Find What You Need" flow; visiting it with `?product={id}` (from a product page's "Request a Quote" link) pre-fills it as a `quote_request` tied to that product
- [x] Admin-facing `SupportTicketResource` (Filament): list showing customer, type, date, status, assigned team member; filters by status/type/date range; a detail view (via `ViewAction`) showing the full message + any response; `assign` and `reply` record actions; `markResolved` action
- [x] Wire replies to actually email the customer (`SupportTicketReplied` notification, using the existing branded mail template) so the loop closes without the admin needing a separate email client
- [x] Navigation badge on the admin sidebar showing the count of `new` tickets

**Resolved decisions (my own default, per this session's recommendation — flag if Sir George wants otherwise):**
- Submitting requires a login (`support/new` sits behind `auth` middleware) — simpler and spam-resistant; no anonymous submission for now.
- "Request a Quote" and "Can't Find What You Need" are **one system** (`SupportTicket` with a `type` discriminator), not two parallel ones — matches recommendation in #2 below.
- Replies are single-shot (one `response`/`responded_at` pair), not a threaded conversation — matches Sir George's own literal framing (Customer → Message → Date → Status → Response → Assigned Team Member).

**Tests:** `tests/Feature/SupportTicketTest.php` — submission (general + quote-request pre-fill via product page), guest redirect, admin assign/reply(+notification)/markResolved, navigation badge count. All passing.

---

### 4. Logistics & Supply Chain — deeper backend layer

**The ask:** Go beyond customer-facing delivery tracking to a real internal supply-chain layer: monitor supplier fulfillment, track material movement supplier→delivery, record delivery/fulfillment performance, flag delayed/incomplete/problematic orders, keep delivery history, manage logistics info per procurement, and see the full order journey from supplier through to customer.

**Current state:** Orders have one overall `status` (pending → confirmed → processing → shipped → out_for_delivery → delivered / cancelled / refunded), tracked with a full timestamped history (`OrderStatusHistory`). Each order line item also has its own `fulfillment_status` per supplier (`OrderItem.fulfillment_status`), and it cascades up to the order-level status **only** when every item on the order belongs to one supplier — a multi-supplier order's overall status has to be set by hand today. A dedicated `Shipment` model, admin `ShipmentResource`, and a basic delivery-performance widget now exist (built this session — see below); there is still no delay detection or supplier-side reliability scoring.

**Implementation steps:**
- [x] New `Shipment` model — one per supplier-portion of an order, auto-created at checkout (one per distinct supplier on the cart). Captures carrier, tracking reference, dispatched/expected/delivered timestamps, proof-of-delivery note, internal notes, and its own `status` (reuses the existing `Order::STATUSES` vocabulary rather than a second parallel status set). Status is kept in sync automatically: a supplier advancing their item's fulfillment status updates their shipment (stamping `dispatched_at`/`delivered_at` the first time each is reached, never overwriting once set); an admin-forced order-level status change (still the only option for multi-supplier orders) syncs every shipment on that order.
- [x] Built a dedicated `ShipmentResource` (Filament) rather than extending `OrderResource` — lists every shipment with its order, supplier, status, carrier, tracking #, dispatch/expected/delivered dates, and an on-time indicator; filterable by status/supplier/delivery date; admin actions to edit logistics info (carrier, tracking, expected delivery date, internal notes), mark dispatched, and mark delivered (with a proof-of-delivery note)
- [ ] **Still blocked on a real decision, deliberately not guessed at:** what "delayed" means concretely (an SLA/threshold per status, e.g. "processing for more than 48h"). No delay flag or filter has been built — the data layer (dispatched/expected/delivered timestamps) is there and ready for one the moment a real number exists.
- [x] Built basic delivery performance reporting: `DeliveryPerformanceWidget` (a Filament table widget on the admin dashboard) shows, per supplier, delivered-shipment count, average transit time (dispatch → delivery), and on-time rate (delivered-by-expected-date vs. not) — computed in PHP rather than raw SQL date-diff functions, since those aren't portable between the app's MySQL database and the SQLite test suite
- [ ] Still need to clarify with Sir George whether "problematic supplier" data here should also feed a supplier-side reliability signal, separate from the customer-facing Trust Score (#6)

**Open decisions:**
- What counts as "delayed" per status, concretely (a time threshold)?
- Is supplier-side reliability scoring part of this ask, or a separate future concept?

**Tests:** `tests/Feature/ShipmentTest.php` (checkout creates one shipment per supplier, status/timestamp syncing from both the per-item and admin-forced order-level paths, timestamps never overwritten once set, `isOnTime()` logic, admin resource actions, delivery performance widget computation), plus a shipment-creation assertion added to `tests/Feature/CheckoutTest.php`. All passing.

---

### 5. Payment & Multi-Currency Infrastructure

**The ask:** Since Selbuildi serves both local and diaspora customers, payments should support multiple countries/currencies where the provider allows. Needs: secure gateway integration, multi-currency support, clear transaction records per purchase, payment status per order, a full status lifecycle (successful/pending/failed/refunded/cancelled), backend reconciliation of payments to orders/customers, and a structure that supports cross-border/diaspora payment — with transaction history eventually feeding the Trust Score.

**Current state:** Flutterwave, Paystack, and Fapshi (Cameroon mobile money) are already integrated end-to-end — hosted checkout redirect, webhook + browser-callback confirmation, signature verification, amount-tamper checking, encrypted credentials, admin on/off toggle per provider (built this session, live on production). Every payment has its own record (`Payment`: provider, amount, currency, status, reference, paid_at) linked to its order. `Order.currency`/`Payment.currency` fields exist and default to XAF; a `preferred_currency` field (XAF/USD/EUR/GBP) is already captured at registration but **isn't wired to anything** — pricing and checkout are XAF-only today regardless of what a diaspora customer selected at signup. There is no admin page to browse/reconcile payments — only orders. `Payment.status` is pending/paid/failed/refunded; there's no distinct "cancelled" payment state (order-level cancellation exists separately), and there's no refund-initiation flow at all (the `refunded` status exists in the schema but nothing ever sets it).

**Implementation steps:**
- [x] Built `PaymentResource` (Filament) for admin reconciliation — lists all payments with their order and customer, filterable by status/provider/date (reuses the existing `HasDateRangeFilter` pattern), with a read-only detail view
- [x] Added a refund flow: **admin-recorded only** (my own recommended default, per the open decision below) — a `refund` action on `PaymentResource` (visible only on `paid` payments) sets `Payment.status` and `Order.payment_status` to `refunded`, advances the order status (with its usual status-history entry + customer email), and logs who recorded it. It does **not** call any provider's refund API — the admin processes the actual money movement with the provider directly first, then records it here.
- [ ] **Still blocked on a real decision:** multi-currency pricing strategy (XAF stored, converted for display/charging into `preferred_currency`) — needs an exchange-rate source (live API vs. admin-set manual rates) and provider-currency rules (Fapshi is XAF-only; Flutterwave/Paystack support more). Deliberately not guessed at — implementing the wrong strategy here would need to be unwound. `preferred_currency` remains captured at registration but inert.
- [ ] Wire `preferred_currency` into checkout/pricing display once the above is decided
- [ ] Add a distinct `cancelled` payment status if the business logic actually needs it (open decision below) — not added yet since `refunded` already covers "money taken then returned," and no concrete case for a separate `cancelled` payment state has come up

**Open decisions:**
- Exchange-rate source: live API or admin-managed manual rates? **(blocking multi-currency work above)**
- Does `Payment.status` need a distinct `cancelled` state, or is `refunded` sufficient?

**Tests:** `tests/Feature/PaymentAdminTest.php` (list view, refund recording + its side effects, refund action hidden for non-paid payments). All passing.

---

### 6. Procurement Trust Score & Financial Infrastructure — Phase One

**The ask:** Make the Trust Score a real, functional part of the platform (not just a concept on the marketing page), built from actual user activity — purchases, payment status, completed orders, fulfillment/delivery records, transaction activity, cancellations/failures. Shown in the user's account, updated as activity develops. An admin view where the team can review a user's procurement history and what's contributing to their score. **Explicitly out of scope for this phase:** the actual credit/lending system, credit limits, lending decisions, embedded financial products, institutional banking partnerships.

**Current state:** This is further along than the ask assumes — the framework already exists and is functioning: `ProcurementTrustScore` (score 0–100, tier) and an event-sourced `TrustScoreEvent` log (every score change is a discrete, auditable event, never edited directly — the score is always the recalculated sum). Events currently tracked: `order_completed` (+4, fires on delivery), `on_time_payment` (+3) / `late_payment` (−5) (both from the existing procurement-credit drawdown flow), `dispute` (−8, not currently triggered anywhere in the code), `cancellation` (−3, fires on order cancellation), `kyc_verified` (+5, fires on contractor verification). The score is displayed on the customer/contractor dashboard today. **Note:** a full procurement-credit system (`CreditAccount`, `CreditService`, admin approval flow) was already built in an earlier phase — worth flagging directly to Sir George, since his message frames credit/lending as explicitly out of scope for "this phase," but foundational credit infrastructure already exists and is live. Needs a conversation about whether that's considered ahead-of-schedule, needs to be reconciled with this framing, or is simply a different piece than what he's referring to.
There is **no** admin view today for reviewing one user's full activity/event history — only the aggregate `CreditAccount` resource from that earlier phase.

**Implementation steps:**
- [ ] Confirm/extend event coverage to match everything Sir George listed: order placement itself currently earns no points (only *completed* orders do) — decide if that's intentional (stricter, harder-to-game scoring) or if a smaller "order placed" event should exist too (open decision, not guessed at)
- [x] Added a `payment_failed` event type (−2 points) and wired it into `PaymentVerificationService` — fires on both failure paths (provider reports unsuccessful, or the paid amount doesn't match what was expected) so failed payment attempts now show up in a user's history, not just successful ones
- [x] Built an admin "Procurement History" view: a `ViewAction` on `UserResource` showing current score/tier plus a chronological (oldest-first) list of every `TrustScoreEvent` (type, points, related order, date) — read-only for this phase, matching the existing `OrderResource` status-history modal pattern rather than a full relation-manager page
- [ ] Still need the direct conversation with Sir George about the already-built `CreditAccount`/credit-approval infrastructure vs. his "no credit system this phase" framing

**Open decisions:**
- Should placing an order (not just completing it) earn any trust points?
- How does the existing credit-account infrastructure fit into "Phase One doesn't include credit" — needs Sir George's input directly.

**Tests:** `tests/Feature/TrustScoreServiceTest.php` (payment_failed event + point deduction), `tests/Feature/PaymentIntegrationTest.php` (payment_failed fires on both failure paths), `tests/Feature/ProcurementHistoryTest.php` (admin view renders, events ordered oldest-first). All passing.

---

## Suggested sequencing (not yet agreed — for discussion)

Some of these have real dependencies on each other:
1. **#3 (backend communication system)** is a dependency of **#2's** "Can't Find What You Need" / "Request a Quote" — those need somewhere to land. Worth building #3 first, or at least in the same pass.
2. **#1 and #2** share a lot of surface area (product cards, category display) — natural to do together.
3. **#5's** admin reconciliation view and **#6's** admin history view are small, high-value, low-risk additions that could ship early and independently of the bigger content/UX work in #1/#2.
4. **#4** (logistics/shipment model) is the largest single piece of new data modeling here — worth its own dedicated pass rather than folding into another round.

---

## Round 2 — received 8 Sep 2026

### 7. User Registration, Profiles & Dashboards

**The ask:** Registration and account structure should clearly distinguish the different people using Selbuildi — Customers/Buyers (including diaspora), Contractors, and Suppliers, each with role-appropriate registration fields, profile info, permissions, and dashboard functions rather than one identical experience for everyone. If Developers, Artisans, or other professional roles are part of Phase One, they should be structured the same way.

**Current state — this is already substantially built, not a gap:**
- Registration already branches by role: customer/contractor share first/last name, phone, country of residence, project country, city, account type, preferred currency; contractor adds business details, specialization, years of experience, ID document + photo upload for verification; supplier has its own simpler business-name flow.
- Dashboards already differ meaningfully by role: supplier sees product/order-fulfillment stats and management links; customer/contractor see order history, addresses, Trust Score, a "Shop Materials" section, and a Quick Actions row; contractor additionally sees a Projects section.
- Permissions already differ by role at the route/policy level (e.g. only verified suppliers can manage products, only contractors have Projects, admin/super-admin routes are guard-separated from the storefront entirely).

**What's genuinely new here:**
- [ ] Clarify whether **Developer** and **Artisan** are real Phase One roles or a future consideration — the message itself hedges ("where... included in the Phase One platform"). Needs a direct answer before any schema/registration work happens, since adding a role isn't just a label — it implies its own registration fields, verification rules, and dashboard, same as contractor/supplier got.
- [ ] If yes: design each new role's specific registration fields, verification requirements (if any), and dashboard content, following the same pattern already established for contractor/supplier.

**Open decisions:**
- Are Developer/Artisan roles actually in Phase One scope, or later?

---

### 8. Admin Dashboard & Platform Management

**The ask:** Authorized admins should be able to run the platform day-to-day without needing the developer involved — create/edit/approve/deactivate/manage: user accounts and status, supplier/contractor onboarding & verification, product listings and categories, inventory, orders and procurement records, customer enquiries/requests, platform notifications, and basic analytics/reports.

**Current state and what shipped in this round:**

| Area | Status |
|---|---|
| User accounts | ✅ existed (edit, role, impersonate) |
| **User account status (activate/deactivate)** | ✅ **built this round** — see below |
| Supplier/contractor onboarding & verification | ✅ existed (approve/reject actions, ID document review) |
| Product listings | ✅ existed |
| **Categories** | ✅ **built this round** — see below |
| Inventory | ❌ no dedicated admin view yet — only reachable indirectly via a supplier's own product form |
| Orders/procurement records | ✅ existed (including full per-order status history and date filtering) |
| Customer enquiries/requests | ❌ doesn't exist yet — this is the backend communication system from Round 1, §3 |
| Platform notifications | ⚠️ partial — admin can broadcast email to all/one user (built earlier); unclear if Sir George means something broader (in-app announcements, system banners) — needs clarification |
| Basic analytics/reports | ✅ existed — admin dashboard already has signup/order/visit stats and charts |

**Implemented in this round:**
- [x] **User account status** — `is_active` flag on every user. A deactivated account: can't log in (clear message, not a misleading "wrong password"), gets signed out of an already-active session on their very next request (doesn't wait for the session to expire), and — for admin/super-admin accounts specifically — also loses admin panel access immediately. Toggle available from both the Users and Admins lists in `/s/admin/build`, one click, no need to open the full edit form. Fully tested (5 dedicated tests).
- [x] **Category management** — full CRUD (`/s/admin/build` → Categories, under Commerce): name, slug (auto-filled from name), optional parent category (for subcategories), sort order, and **photo upload** — this is also the concrete first step toward Round 1 §1's "real category photography" ask, since the `categories.image` field already existed in the schema but had no admin UI to manage it until now. Fully tested (3 dedicated tests).

**Still to do:**
- [ ] Admin **Inventory** view — list all products' stock across suppliers/warehouses, with the ability to adjust quantity directly rather than only through a supplier's own form
- [ ] Backend communication/enquiry system — tracked in Round 1 §3, not duplicated here
- [ ] Clarify what "platform notifications" means beyond the existing admin broadcast-email feature (in-app announcements? system status banners?) — **needs Sir George's input**

**Open decisions:**
- What does "platform notifications" mean beyond the existing broadcast-email tool?

---

### 9. Conclusion & Phase One Readiness

**The ask (not a feature request — direction/guidance):** Sir George considers the platform now reflecting the core Selbuildi vision much more clearly. Focus from here should be on completing, refining, and testing the *agreed* Phase One functionality — the full user journey (registration → procurement → payment → delivery → procurement history → Trust Score) plus the backend administration behind it — so Phase One ships stable, functional, and polished.

**Nothing to check off here** — this is a call to prioritize finishing and hardening what's already agreed (this file) over continuing to add new scope, once Round 1 + Round 2's items are worked through. Worth keeping in mind as new rounds keep arriving: the tracker will keep growing unless a line gets drawn somewhere on what's actually "Phase One."

---

*(Round 3 feedback from Sir George pending — will be appended as a new dated section below when it arrives.)*
