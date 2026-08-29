# Ship-To Rules for WooCommerce

**Prevent orders that cannot be fulfilled.** WooCommerce core has no way to express “this product cannot ship to country X”. Ship-To Rules adds product-level shipping countries and enforces them at cart and checkout — based on the **real shipping address**, not geo-blocking by IP.

## Requirements

- WordPress 5.8+
- WooCommerce 7.0+ (tested with 11.0)
- PHP 7.4+

## Install / reinstall

1. **Deactivate** the old plugin if active (`wp-country-search` or any previous copy).
2. **Delete** the old folder: `wp-content/plugins/wp-country-search/` (if it still exists).
3. Place this plugin at: `wp-content/plugins/ship-to-rules/`
4. Main file must be: `ship-to-rules/ship-to-rules.php`
5. **Activate** “Ship-To Rules for WooCommerce” in WordPress → Plugins.
6. On first activation, migration runs automatically and imports data from a previous install (taxonomy, settings, product rules, widgets).

If you had shortcodes or theme code from the old plugin, update them:

| Old | New |
|-----|-----|
| `[destination_context]` | `[ship_to_context]` |
| `[destination_shop_picker]` | `[ship_to_picker]` |
| `[destination_passport]` | `[ship_to_notice]` |
| `ds_get_destination_*()` | `str_get_ship_to_*()` |

## Setup

1. Go to **WooCommerce → Ship-To Rules** and click **Seed from shipping zones**.
2. Review **Products → Ship-To Countries** — every country needs a valid **ISO2** code.
3. Edit products → tab **Ship-To** → select countries and choose rule mode.
4. Shoppers select a country via the context strip; rules enforce at checkout.

## Shortcodes & PHP helpers

| Shortcode | PHP helper | Purpose |
|-----------|------------|---------|
| `[ship_to_context]` | `str_get_ship_to_context()` | Ship-to context strip with country selector |
| `[ship_to_picker]` | `str_get_ship_to_picker()` | Compact country picker |
| `[ship_to_notice]` | `str_get_ship_to_notice( $product_id )` | Product shipping availability notice |

## Naming convention

Everything uses the `str_` / `STR_` prefix (Ship-To Rules):

| Item | Value |
|------|-------|
| Plugin folder | `ship-to-rules` |
| Bootstrap file | `ship-to-rules.php` |
| Text domain | `ship-to-rules` |
| Taxonomy | `str_ship_to` |
| Cookie / query var | `str_ship_to` |
| Settings option | `str_settings` |
| Widget ID | `str_ship_to_picker` |

## Architecture

```
ship-to-rules.php
includes/
  class-str-countries.php     Country data + ISO map + cookie
  class-str-rules.php         Rule engine (product × country)
  class-str-enforcement.php   Cart/checkout validation
  class-str-audit.php         Admin shipping audit
  class-str-seeder.php        Country seeding
  class-str-frontend.php      Shopper UI
  class-str-query.php         Optional catalog filter
  class-str-migration.php     Upgrade from legacy installs
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

GPL2+
