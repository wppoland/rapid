=== Rapid - Quick Order Form for WooCommerce ===
Contributors: motylanogha
Tags: woocommerce, quick order, bulk order, b2b, wholesale
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Requires Plugins: woocommerce
Stable tag: 0.1.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A fast bulk order form so B2B and wholesale buyers can add many products at once.

== Description ==

Rapid adds a searchable quick-order form to your WooCommerce store. Customers
find products by **name or SKU**, set quantities in a compact table and add many
products to the cart in a **single submit**, no clicking through product pages.

It is built for B2B, wholesale, trade and reorder workflows, where buyers know
what they want and value speed over browsing.

The code lives on GitHub at https://github.com/wppoland/rapid; that is the
place to read the source, file a bug or send a patch.

= Documentation and links =

* **Documentation** - https://plogins.com/rapid/docs/
* **Plugin page** - https://plogins.com/rapid/
* **Source code** - https://github.com/wppoland/rapid
* **Bug reports and feature requests** - https://github.com/wppoland/rapid/issues
* **Discussions and questions** - https://github.com/wppoland/rapid/discussions


= Features =

* A `[rapid_order]` shortcode that renders a searchable product table/form.
* Live AJAX product search by name or SKU (debounced, no page reload).
* Configurable product scope: **all products** or **selected categories only**.
* Batched add-to-cart: set quantities on many products and add them all in one submit, with a notice summarising how many went into the cart.
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
Every product with a quantity is added in one submit, and you get a notice
saying how many were added (and how many, if any, could not be).

= Do shoppers need an account? =

No. The form works for guests and logged-in customers; cart behaviour follows your normal WooCommerce guest-checkout settings.

== Screenshots ==

1. The quick order form with live search and quantity inputs.
2. The Rapid settings screen under WooCommerce.

== External Services ==

Rapid does not connect to any external services. The live product search runs against your own store: the form posts to your site's `admin-ajax.php` and queries your existing WooCommerce products by name or SKU, and the batched add-to-cart uses WooCommerce's own cart. Rapid stores only two options in your WordPress database (`rapid_settings` and `rapid_db_version`); it creates no custom tables and sends no email.

== Changelog ==

= 0.1.3 =
* `rapid/order_settings` filter and `rapid/form_fields` action for PRO per-role quick-order forms.
* `OrderContext` helper exposes visitor role context to templates and filters.

= 0.1.2 =
* Add `rapid/product_price_html` filter so PRO and custom code can override prices in the quick-order table.

= 0.1.1 =
* Add `rapid/bulk_paste_prefill` filter and `rapid/bulk_paste_form_extra` / `rapid/bulk_paste_after_form` actions for PRO bulk-paste integrations (saved SKU lists).

= 0.1.0 =
* Initial release: `[rapid_order]` shortcode with AJAX product search by name or SKU, configurable product scope (all / selected categories), batched add-to-cart with a single notice, selectable columns (image / SKU / price / stock) and results-per-page.
