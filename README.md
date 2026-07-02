# Product Filters — WooCommerce Plugin

Advanced product filtering for WooCommerce shop and taxonomy pages.
Filters are driven by URL parameters and rendered as a standard HTML form,
making them compatible with full-page caching (no AJAX required to display).

## Features

- Filters by product category, tags, and all WooCommerce attributes (taxonomies)
- On sale filter — shows only when sale products exist in the current view
- Multi-select within a taxonomy uses OR logic by default
- Category filter works on top-level shop page (native WooCommerce limitation bypass)
- Filter state reflected in URL (`filter_*` params) — shareable and cacheable
- On taxonomy pages, shows only child terms of the current category
- Admin UI to exclude specific taxonomies or individual terms from filters
- Filter results cached per active combination via `wp_cache`, flushed on product changes
- WPML-aware — cache keys include current language
- Available as a shortcode

## Scripts

```bash
npm start          # development build (watch)
npm run build      # production build
npm run make       # build + package as chocante-product-filters.zip
npm run translate  # generate .pot translation file
```
