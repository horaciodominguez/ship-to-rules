# Destination Shop for WooCommerce

Browse your catalog by **shipping destination** — clear availability, not geo-blocking.

Customers pick where they want products delivered. You mark which products ship where. The shop filters (or badges) accordingly, and each product page shows an **Availability Passport** with destination chips and live status.

## Who it's for

- Stores that ship different SKUs to different countries
- Gift / international shoppers who choose a destination first
- Merchants who want transparency instead of hiding the catalog by IP

## Features

- **Destinations** taxonomy under Products (ISO2 codes + flags + active flag)
- Assign destinations per product (searchable checklist)
- Products list column + bulk assign / clear
- **Destination Bar** shortcode with searchable combobox, flags, loading state
- Catalog modes: **Filter** or **Badge**
- **Availability Passport** on product pages
- Loop badges when a destination is selected
- One-time migration from older `product_country` CPT + `csb_product_countries` meta (upgrade only)
- Lightweight: `tax_query` (no serialized meta LIKE), conditional assets, destination list transient

## Requirements

- WordPress 5.8+
- WooCommerce 7.0+
- PHP 7.4+

## Setup

1. Activate the plugin (WooCommerce must be active).
2. Go to **Products → Destinations** and add countries (set ISO2 for flags, e.g. `AR`, `DE`, `JP`).
3. Edit products → tab **Destinations** → check where each product ships.  
   Leave empty = available everywhere.
4. Place the bar:

```
[destination_shop_bar]
```

PHP: `echo ds_get_destination_bar();`

5. Configure **WooCommerce → Destination Shop** (catalog mode, results URL, passport/badges, empty message).

## Shortcodes

| Shortcode | Purpose |
|-----------|---------|
| `[destination_shop_bar]` | Destination + product search bar |
| `[destination_passport]` | Passport block (usually auto on product pages) |

## How filtering works

Query var / cookie: `ds_destination={slug}`

In **Filter** mode, the product query includes:

- products assigned to that destination, **or**
- products with no destinations (treated as ship-everywhere)

In **Badge** mode the catalog stays complete; cards and the passport show availability.

## Architecture

```
wp-country-search.php          Bootstrap
includes/class-ds-*.php        Taxonomy, migration, admin, query, frontend, settings
templates/                     Destination bar + passport
assets/css|js                  Front & admin (enqueued only when needed)
```

## Security & performance notes

- Nonces + capability checks on product/term saves
- Escaping on all front output; ISO sanitized to 2 letters
- No per-page geolocation API calls
- Destination list cached in a transient

## License

GPL2+
