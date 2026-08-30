# Ship-To Rules for WooCommerce

**Prevent orders that cannot be fulfilled.** WooCommerce has no built-in way to say “this product cannot ship to country X”. Ship-To Rules adds per-product shipping countries and enforces them at cart and checkout — based on the **real shipping address**, not geo-blocking by IP.

## Requirements

- WordPress 5.8+
- WooCommerce 7.0+ (tested with 11.0)
- PHP 7.4+

## Installation

1. Copy the plugin folder to `wp-content/plugins/ship-to-rules/`
2. Activate **Ship-To Rules for WooCommerce** in WordPress → Plugins

## Quick start

1. Go to **WooCommerce → Ship-To Rules**
2. Click **Seed from shipping zones** to create destination countries from your existing zones (recommended)
3. Review **Products → Ship-To Countries** — each term needs a valid **ISO2** code
4. Edit products → tab **Ship-To** → select countries and choose allow/deny mode
5. Shoppers pick a country via the context strip; rules enforce at add-to-cart and checkout

### Destinations workflow

| Action | What it does |
|--------|----------------|
| **Seed from shipping zones** | Creates or updates terms for countries covered by zones with enabled shipping methods |
| **Seed all WooCommerce countries** | Creates or updates terms for every country WooCommerce knows |
| **Clear all destinations** | Removes all ship-to terms and clears product country assignments |

Seeding only **adds or updates** countries. If you seeded all countries by mistake, use **Clear all destinations** first, then seed again from zones.

## Settings

| Setting | Default | Description |
|---------|---------|-------------|
| Narrow checkout country list | Off | Limits the checkout country dropdown to destinations compatible with the cart (UX helper; server validation always runs) |
| Show ship-to context strip | On | Country selector bar for shoppers |
| Show shipping notice on product pages | On | Availability message on single product |
| Show availability badge in loops | On | Badge when a destination is selected |
| Enable catalog filter | Off | Hide or badge products by destination (can affect full-page cache) |
| Catalog mode | Badge | **Filter** hides non-shippable products; **Badge** keeps full catalog |
| Empty filter message | — | Shown when filter mode hides everything; `{country}` placeholder supported |

Category rules (same settings page) apply allow/deny lists per product category when a product has no product-level rule.

## Shortcodes & PHP helpers

| Shortcode | PHP helper | Purpose |
|-----------|------------|---------|
| `[ship_to_context]` | `str_get_ship_to_context()` | Ship-to context strip with country selector |
| `[ship_to_picker]` | `str_get_ship_to_picker()` | Compact country picker |
| `[ship_to_notice]` | `str_get_ship_to_notice( $product_id )` | Product shipping availability notice |

## How enforcement works

1. **Add to cart** — blocks items that cannot ship to the selected destination (when known)
2. **Cart & checkout** — re-validates before payment; local pickup is respected when applicable
3. **Optional catalog filter** — off by default; enable only if you accept cache implications

The audit panel on the settings page flags mismatches between declared product countries and WooCommerce shipping zones.

## Architecture

```
ship-to-rules.php
includes/
  class-str-countries.php     Country data, ISO map, cookie
  class-str-rules.php         Rule engine (product × country)
  class-str-enforcement.php   Cart/checkout validation
  class-str-audit.php         Admin shipping audit
  class-str-seeder.php        Seed and reset destinations
  class-str-frontend.php      Shopper UI
  class-str-query.php         Optional catalog filter
templates/
  ship-to-context.php
  ship-to-picker.php
  country-combobox.php
  product-shipping-notice.php
  cart-blocked-items.php
tests/
  RulesTest.php
```

## Development

```bash
composer install
composer test
```

## License

GPL-2.0-or-later
