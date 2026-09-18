# Promo Engine

Promotions for WooCommerce: percentage and fixed discounts, buy X get Y, fixed-price bundles, tiered cart discounts, a popup with a countdown (with an optional A/B test), a `/deals/` page and per-promotion analytics.

Requires WordPress 6.4+, WooCommerce 8.0+ and PHP 8.0+. HPOS compatible.

## Installation

1. Copy `promo-engine` to `wp-content/plugins/` and activate it. Activation creates two tables and the `/deals/` rewrite rule.
2. Load the demo promotions, either in *Promotions → All promotions* (the "Demo promotions" box under the list: pick three categories, click **Load demo**) or with WP-CLI:

   ```
   wp promo-engine seed --cat1=<id|slug> --cat2=<id|slug> --cat3=<id|slug> [--flash=1,2,3,4,5]
   ```

   Loading again replaces the previous demo set and leaves other promotions alone. Without `--flash`, "Flash −30%" takes two products from category 1, two from category 2 and one from category 3, so the overlap examples (3 and 5) can be reproduced.

### Categories used on the test site

| Spec        | Category      |
|-------------|---------------|
| Category 1  | _fill in_     |
| Category 2  | _fill in_     |
| Category 3  | _fill in_     |

### Development

```
composer install
composer test    # PHPUnit: the calculator (spec examples 1–9) and the A/B significance test
composer lint    # PHPCS with WordPress Coding Standards and PHPCompatibilityWP
wp i18n make-pot . languages/promo-engine.pot --exclude=vendor,tests
```

## How discounts are calculated

Everything happens in `woocommerce_before_calculate_totals`. Product prices in the database are never touched; the plugin sets the price of each cart item in memory and WooCommerce takes it from there.

1. Item discounts (percentage / fixed) per line, from the current price — the sale price if the product is on sale.
2. Buy X get Y and bundles, on the prices from step 1.
3. Cart discount on the subtotal after steps 1–2. Only the highest reached tier counts (`subtotal >= threshold`).
4. WooCommerce coupons, on the prices after promotions.
5. Taxes, calculated by WooCommerce.

### Where the spec left room for interpretation

- The combination rule is applied within a step. When several promotions of the same step match an item and one of them doesn't combine, only the highest priority one applies; otherwise all apply in priority order and percentages multiply. Steps themselves form a pipeline, which is how example 5 works: "Flash −30%" (not combinable) in step 1, then "Buy 2 get 1" in step 2.
- Equal priority: the lower ID wins.
- There are two caps. `max_discount` on a promotion limits that promotion. The global cap in *Promotions → Settings* (70% by default) limits the combined step 1 discount per unit — that is what turns −50% and −60% (−80%) into −70% in example 8. It doesn't apply to buy X get Y and bundles, where a free item is 100% off by definition.
- Buy X get Y works on units, so a line with quantity 3 is three units. Units are sorted from the most expensive and split into groups of X+Y; the Y cheapest in each group are discounted, incomplete groups are not. Ties are broken by cart order to keep the result stable.
- Bundles group units the same way and only apply when the bundle is actually cheaper. Pairing expensive items together gives the customer the biggest saving. The bundle discount is split across its units by price.
- A unit can be part of one step 2 deal only, so an item can't be free and in a bundle at the same time. Deals claim units in priority order.
- The cart discount is spread over the line prices instead of being added as a negative fee. A percentage coupon is calculated from line prices, so otherwise example 7 would come out as $182.40 instead of $184.68.
- Money is kept in cents inside the calculator. Splitting an amount across lines uses the largest remainder method, so the parts always add up exactly.
- Categories include their subcategories. For "selected products" either a parent product (all sizes) or a single variation can be chosen.

### No double discounting

`before_calculate_totals` runs several times per request. The first time a product object is seen, its price is stored in a `WeakMap` keyed by that object, and every later pass starts from it. Objects rebuilt from the session get a fresh entry. A `$running` flag protects against re-entry when something calls `calculate_totals()` from inside the hook.

The same calculation also runs on `woocommerce_cart_loaded_from_session`: when WooCommerce restores totals from the session without recalculating (fragment refreshes, for example), the mini-cart would otherwise show undiscounted item prices.

## Code layout

```
includes/
  pricing/     Calculator, Line, Result — no WordPress dependencies, unit tested
  promotion/   Promotion entity, Repository (storage and cache), Labels
  cart/        Cart_Discounts, Order_Recorder, Line_Factory
  analytics/   Event_Logger, Tracker, Reports, Ab_Test
  front/       Deals_Page, Popup, Cart_Summary, Assets, Template
  admin/       menu, list table, form, analytics, settings
  demo/, cli/  demo seeder and the WP-CLI command
templates/     storefront templates; a theme can override them in {theme}/promo-engine/
```

Promotions live in their own table rather than a custom post type. They are typed rows queried by status and date, and one indexed query beats a `meta_query` over `postmeta`.

The list of enabled, not yet expired promotions is cached in a transient. Start dates and usage limits are checked in memory on each request, so the cache never goes stale with time and is only cleared on writes. The cart adds no queries of its own beyond product terms, which WordPress already caches.

Catalog prices are left alone: filtering the price of every product on every listing would be expensive, and the spec asks to keep the catalog light. Promotions are visible on `/deals/`, in the popup, the mini-cart, the cart and the checkout.

Dates are stored in UTC and entered and shown in the site timezone.

## Storefront

`/deals/` is a virtual page served from a rewrite rule, so no WordPress page is needed. Products are rendered with `wc_get_template_part( 'content', 'product' )`, i.e. the theme's product card. Timed promotions get a countdown, the cart promotion lists its tiers.

The popup belongs to the highest priority running promotion that has one enabled. It opens once per browser session (`sessionStorage`), never on the checkout, and only while the tab is visible. It is a modal dialog: focus moves into it and stays there, Esc, the backdrop and the close button dismiss it, focus goes back where it was, and animations are off under `prefers-reduced-motion`. It closes by itself when the countdown runs out.

The mini-cart block is printed inside the widget markup (`woocommerce_widget_shopping_cart_before_buttons`), so WooCommerce's cart fragments refresh it with no extra requests. It lists the applied promotions, the total saving and the progress towards the next tier ("Add $22.00 more to get −15%").

The cart and the checkout show a savings breakdown. The checkout uses a standard totals table row; `multibrand-theme` builds its order review from div rows, so for that theme a matching template is used instead (filterable via `promo_engine_checkout_savings_template`). The breakdown is also saved on the order and shown in the order totals (thank you page, emails, My Account). These blocks are rendered through classic template hooks; with the Cart and Checkout blocks the discounts still apply and orders are recorded, but the breakdown isn't shown.

Styles follow the theme: monochrome, square corners, uppercase headings. Colours are CSS custom properties (`--pe-*`) that a child theme can override.

## Analytics

Events go to `wp_promo_engine_events`: `impression`, `click`, `add_to_cart` and `order`, indexed by `(promotion_id, event_type, created_at)`, `created_at` and `order_id`.

- Popup impressions and clicks are sent by the browser (`sendBeacon`) to `admin-ajax` with a nonce. The endpoint only accepts those two event types and only for a running promotion.
- `add_to_cart` is written on the server for every running promotion the product falls under.
- `order` is written when an order reaches a paid status (`wc_get_is_paid_statuses()`), one row per promotion and order line: quantity, line revenue after all discounts, and the discount given. Cancelling, refunding or failing the order removes its events and gives the usage back; paying again counts it again. Unpaid orders never show up in the report or count towards usage limits.

Reports are aggregated in SQL with `GROUP BY`: impressions, clicks, CTR, add to carts, orders, conversion, revenue, discount given, top products and a daily chart with a date filter. The chart is plain SVG, no library or CDN.

CTR is clicks / impressions, conversion is orders / add to carts, orders are distinct order IDs. One order line can carry several promotions (say a flash sale and the cart discount), and its revenue is counted for each of them, so revenue shouldn't be summed across promotions.

## Popup A/B test

A promotion with a popup can run an A/B test with a different title, text and button label for variant B; empty B fields fall back to A. The "Flash −30%" demo has it switched on.

The browser picks a variant once, 50/50, and keeps it in the `promo_engine_ab_{id}` cookie for 30 days. The page always contains variant A plus variant B in a `<template>`, and the script swaps B in when needed — so the HTML is the same for everyone and can be cached. The server reads the same cookie, which lets it attribute add to carts and orders to the variant as well. Since payment often completes in a gateway callback without the customer's cookies, the variant is saved on the order at checkout.

The promotion's analytics screen compares A and B and runs a two-proportion z-test on CTR and on conversion, flagging |z| ≥ 1.96 as significant at 95%. With fewer than 30 trials per variant no verdict is shown. The split is fixed at 50/50, and visitors who block cookies get a new variant on every visit and are reported without one.

## Security

Nonces on every write (form, row and bulk actions, demo, settings, tracking endpoint), `manage_woocommerce` checks in the admin, input sanitized field by field, output escaped, every query through `$wpdb->prepare()` with `%i` for table names and an allowlist for `ORDER BY`.

## Known limitations

- Daily buckets use the current timezone offset, so around a DST change a day boundary can be off by an hour.
- With full-page caching the tracking nonce in cached HTML can expire (12–24 h). Exclude it from the cache or fetch it separately.
- The usage limit is checked when the cart is calculated and counted when the order is paid, so two orders paid at the same moment can both get in over the limit.
- `multibrand-theme` has its own ACF-based "1+1+1=2" promotion that also changes prices in `before_calculate_totals`. ACF isn't installed on the test site, so it's inactive there; with both enabled the discounts stack.
