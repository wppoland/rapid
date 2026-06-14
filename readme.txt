=== Rapid - Quick Order Form for WooCommerce ===
Contributors: wppoland
Tags: woocommerce, quick order, bulk order, b2b, wholesale
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Requires Plugins: woocommerce
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A fast bulk order form so B2B and wholesale buyers can add many products at once.

== Description ==

Rapid adds a searchable quick-order form to your WooCommerce store. Customers
find products by **name or SKU**, set quantities in a compact table and add many
products to the cart in a **single submit** — no clicking through product pages.

It is built for B2B, wholesale, trade and reorder workflows, where buyers know
what they want and value speed over browsing.

= Features =

* A `[rapid_order]` shortcode that renders a searchable product table/form.
* Live AJAX product search by name or SKU (debounced, no page reload).
* Configurable product scope: **all products** or **selected categories only**.
* Batched add-to-cart: set quantities on many products, add them all at once with a single combined notice.
* Choose which columns to show: image, SKU, price, stock.
* Configurable search results per page.
* Works without JavaScript: the first page of products renders as a plain table and the submit still batches into the cart.
* Accessible, mobile-friendly markup (the table collapses to cards on small screens), visible focus states and screen-reader labels.
* Translation ready (POT included) and clean uninstall.
* HPOS and cart/checkout blocks compatible.

= The [rapid_order] shortcode =

Create a page (e.g. "Quick Order") and add the shortcode:

`[rapid_order]`

== Installation ==

1. Upload the plugin to `/wp-content/plugins/rapid`, or install via Plugins → Add New.
2. Activate it. WooCommerce must be installed and active.
3. Go to **WooCommerce → Rapid** to choose the product scope and which columns to show.
4. Create a page with the `[rapid_order]` shortcode to host the form.

== Frequently Asked Questions ==

= Does it require WooCommerce? =

Yes. WooCommerce must be installed and active.

= Can I limit the form to certain categories? =

Yes. Set the product scope to "Selected categories only" and tick the categories
you want to offer. Choose "All products" to cover the whole catalogue.

= Does it work without JavaScript? =

Yes. Without JavaScript the form shows the first page of in-scope products and the
submit button still adds the selected quantities to the cart server-side. The
live search and category filter are progressive enhancements.

= How does adding to the cart work? =

Enter a quantity for each product you want, then click "Add selected to cart".
All products with a quantity are added in one go and you get a single notice.

== Screenshots ==

1. The quick order form with live search and quantity inputs.
2. The Rapid settings screen under WooCommerce.

== Changelog ==

= 0.1.0 =
* Initial release: `[rapid_order]` shortcode with AJAX product search by name or SKU, configurable product scope (all / selected categories), batched add-to-cart with a single notice, selectable columns (image / SKU / price / stock) and results-per-page.
