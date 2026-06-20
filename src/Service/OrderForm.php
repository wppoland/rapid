<?php

declare(strict_types=1);

namespace Rapid\Service;

use Rapid\Contract\HasHooks;

defined('ABSPATH') || exit;

/**
 * The [rapid_order] quick-order form.
 *
 * Renders a searchable product table where customers set quantities and add many
 * products to the cart in a single submit. Provides an AJAX product-search
 * endpoint (scoped to the configured product scope) and a batched add-to-cart
 * handler that emits one combined notice.
 *
 * The form degrades gracefully without JavaScript: it renders the first page of
 * in-scope products as a plain table and the submit button still batches them
 * into the cart server-side. All output is escaped; all input is sanitised and
 * nonce-verified; nothing is ever fatal when WooCommerce data is missing.
 */
final class OrderForm implements HasHooks
{
    private const OPTION       = 'rapid_settings';
    private const NONCE        = 'rapid_order';
    private const SEARCH_NONCE = 'rapid_search';

    /** Hard cap on AJAX results per page, regardless of the configured value. */
    private const MAX_PER_PAGE = 50;

    /** Hard cap on distinct line items accepted in one submit. */
    private const MAX_LINES = 200;

    public function registerHooks(): void
    {
        add_shortcode('rapid_order', [$this, 'render']);
        add_action('wp_enqueue_scripts', [$this, 'registerAssets']);
        add_action('template_redirect', [$this, 'maybeHandleSubmit']);
        add_action('wp_ajax_rapid_search', [$this, 'ajaxSearch']);
        add_action('wp_ajax_nopriv_rapid_search', [$this, 'ajaxSearch']);
    }

    /**
     * Register (but do not enqueue) front-end assets. They are enqueued on demand
     * by the shortcode so unrelated pages stay clean.
     */
    public function registerAssets(): void
    {
        wp_register_style(
            'rapid',
            RAPID_URL . 'assets/css/rapid.css',
            [],
            \Rapid\VERSION,
        );

        wp_register_script(
            'rapid',
            RAPID_URL . 'assets/js/rapid.js',
            [],
            \Rapid\VERSION,
            ['in_footer' => true, 'strategy' => 'defer'],
        );
    }

    /**
     * Render the [rapid_order] shortcode. Returns escaped HTML, or an empty
     * string when disabled.
     */
    public function render(): string
    {
        if (! $this->isEnabled()) {
            return '';
        }

        $settings = $this->settings();

        wp_enqueue_style('rapid');
        wp_enqueue_script('rapid');
        wp_localize_script('rapid', 'rapidData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce(self::SEARCH_NONCE),
            'action'  => 'rapid_search',
            'i18n'    => [
                'searching' => __('Searching…', 'rapid'),
                'noResults' => __('No products found.', 'rapid'),
                'error'     => __('Something went wrong. Please try again.', 'rapid'),
            ],
        ]);

        ob_start();
        $this->renderTemplate('order-form', [
            'settings' => $settings,
            'products' => $this->initialProducts($settings),
            'columns'  => $this->columnCount($settings),
            'notice'   => $this->consumeNotice(),
        ]);

        return (string) ob_get_clean();
    }

    /**
     * Handle a no-JS (or JS) batched add-to-cart submission early, before output,
     * so cart cookies can be written and we can redirect cleanly.
     */
    public function maybeHandleSubmit(): void
    {
        if (! isset($_POST['rapid_submit'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified below.
            return;
        }

        if (! $this->isEnabled() || ! function_exists('WC') || null === WC()->cart) {
            return;
        }

        $nonce = isset($_POST['rapid_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['rapid_nonce']))
            : '';

        if (! wp_verify_nonce($nonce, self::NONCE)) {
            return;
        }

        $quantities = isset($_POST['rapid_qty']) && is_array($_POST['rapid_qty'])
            ? wp_unslash($_POST['rapid_qty']) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- keys/values cast to int below.
            : [];

        $added   = 0;
        $skipped = 0;
        $count   = 0;

        foreach ((array) $quantities as $productId => $qty) {
            if (++$count > self::MAX_LINES) {
                break;
            }

            $productId = absint($productId);
            $qty       = absint($qty);

            if ($productId <= 0 || $qty <= 0) {
                continue;
            }

            if (! $this->productInScope($productId)) {
                ++$skipped;
                continue;
            }

            $result = WC()->cart->add_to_cart($productId, $qty);

            if (false === $result) {
                ++$skipped;
                continue;
            }

            ++$added;
        }

        $this->storeNotice($added, $skipped);

        wp_safe_redirect($this->currentUrl());
        exit;
    }

    /**
     * AJAX product search. Returns a small JSON payload of in-scope products that
     * match the term (by name or SKU).
     */
    public function ajaxSearch(): void
    {
        if (! $this->isEnabled()) {
            wp_send_json_error(['message' => __('Quick order is disabled.', 'rapid')], 403);
        }

        $nonce = isset($_GET['nonce']) ? sanitize_text_field(wp_unslash($_GET['nonce'])) : '';

        if (! wp_verify_nonce($nonce, self::SEARCH_NONCE)) {
            wp_send_json_error(['message' => __('Your session expired. Please reload the page.', 'rapid')], 403);
        }

        $term = isset($_GET['term']) ? sanitize_text_field(wp_unslash($_GET['term'])) : '';

        $settings = $this->settings();
        $products = $this->queryProducts($settings, $term);

        $items = [];

        foreach ($products as $product) {
            $items[] = $this->presentProduct($product, $settings);
        }

        wp_send_json_success(['products' => $items]);
    }

    /**
     * The first page of in-scope products, used to seed the no-JS table and the
     * initial JS view.
     *
     * @param array<string, mixed> $settings
     * @return array<int, array<string, mixed>>
     */
    private function initialProducts(array $settings): array
    {
        $products = $this->queryProducts($settings, '');
        $items    = [];

        foreach ($products as $product) {
            $items[] = $this->presentProduct($product, $settings);
        }

        return $items;
    }

    /**
     * Query purchasable products in scope, optionally filtered by search term.
     * Variations are excluded for simplicity.
     *
     * @param array<string, mixed> $settings
     * @return array<int, \WC_Product>
     */
    private function queryProducts(array $settings, string $term): array
    {
        $perPage = $this->perPage($settings);

        $args = [
            'status'   => 'publish',
            'limit'    => $perPage,
            'orderby'  => 'title',
            'order'    => 'ASC',
            'return'   => 'objects',
            'paginate' => false,
        ];

        if ('' !== $term) {
            // wc_get_products matches the search term against title, SKU and more.
            $args['s'] = $term;
        }

        $categorySlugs = $this->scopeCategorySlugs($settings);

        if (null === $categorySlugs) {
            // Scope is "categories" but none are valid/selected — nothing to show.
            return [];
        }

        if ([] !== $categorySlugs) {
            $args['category'] = $categorySlugs;
        }

        /** @var array<int, \WC_Product> $products */
        $products = wc_get_products($args);

        // Keep only purchasable, visible products so the form never offers
        // something that cannot be ordered.
        return array_values(array_filter(
            $products,
            static fn (\WC_Product $product): bool => $product->is_purchasable() && 'variable' !== $product->get_type(),
        ));
    }

    /**
     * Resolve the configured scope into category slugs to query.
     *
     * Returns:
     *  - a list of slugs to filter by, or
     *  - [] for "no category restriction" (scope = all), or
     *  - null when scope is "categories" but there is nothing valid to show.
     *
     * @param array<string, mixed> $settings
     * @return array<int, string>|null
     */
    private function scopeCategorySlugs(array $settings): ?array
    {
        if ('categories' !== ($settings['scope'] ?? 'all')) {
            return [];
        }

        $scopeSlugs = $this->idsToSlugs(array_map('absint', (array) ($settings['categories'] ?? [])));

        if ([] === $scopeSlugs) {
            return null;
        }

        return $scopeSlugs;
    }

    /**
     * @param array<int, int> $ids
     * @return array<int, string>
     */
    private function idsToSlugs(array $ids): array
    {
        $slugs = [];

        foreach ($ids as $id) {
            $term = get_term($id, 'product_cat');

            if ($term instanceof \WP_Term) {
                $slugs[] = $term->slug;
            }
        }

        return $slugs;
    }

    /**
     * Build the safe, presentational payload for one product.
     *
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function presentProduct(\WC_Product $product, array $settings): array
    {
        $imageId  = $product->get_image_id();
        $imageUrl = '';

        if ($imageId) {
            $src = wp_get_attachment_image_url((int) $imageId, 'thumbnail');
            if (is_string($src)) {
                $imageUrl = $src;
            }
        }

        if ('' === $imageUrl) {
            $imageUrl = wc_placeholder_img_src('thumbnail');
        }

        $priceHtml = '';

        if ($this->showPrice($settings)) {
            $priceHtml = $product->get_price_html();
            /**
             * Filter the price HTML shown for a product in the quick-order table.
             *
             * @param string               $priceHtml WooCommerce price markup.
             * @param \WC_Product          $product   Product being presented.
             * @param array<string, mixed> $settings  Rapid settings.
             */
            $priceHtml = (string) apply_filters('rapid/product_price_html', $priceHtml, $product, $settings);
        }

        return [
            'id'          => $product->get_id(),
            'name'        => $product->get_name(),
            'sku'         => (string) $product->get_sku(),
            'priceHtml'   => $priceHtml,
            'stockHtml'   => $this->showStock($settings) ? $this->stockLabel($product) : '',
            'imageUrl'    => $imageUrl,
            'permalink'   => get_permalink($product->get_id()) ?: '',
            'inStock'     => $product->is_in_stock(),
        ];
    }

    /**
     * A short, human-friendly stock label.
     */
    private function stockLabel(\WC_Product $product): string
    {
        if (! $product->is_in_stock()) {
            return __('Out of stock', 'rapid');
        }

        if ($product->managing_stock()) {
            $qty = $product->get_stock_quantity();

            if (null !== $qty) {
                return sprintf(
                    /* translators: %d: number of items in stock */
                    _n('%d in stock', '%d in stock', (int) $qty, 'rapid'),
                    (int) $qty,
                );
            }
        }

        return __('In stock', 'rapid');
    }

    /**
     * Verify a product is purchasable and within the configured scope, used to
     * defend the server-side batch handler against tampered input (no IDOR).
     */
    private function productInScope(int $productId): bool
    {
        $product = wc_get_product($productId);

        if (! $product instanceof \WC_Product || ! $product->is_purchasable()) {
            return false;
        }

        $settings = $this->settings();

        if ('categories' !== ($settings['scope'] ?? 'all')) {
            return true;
        }

        $scopeIds = array_map('absint', (array) ($settings['categories'] ?? []));

        if ([] === $scopeIds) {
            return false;
        }

        $productCats = $product->get_category_ids();

        // A variation inherits its parent's categories.
        if ([] === $productCats && $product->get_parent_id() > 0) {
            $productCats = (array) wc_get_product_term_ids($product->get_parent_id(), 'product_cat');
        }

        return [] !== array_intersect($scopeIds, array_map('absint', $productCats));
    }

    /**
     * Store a one-shot success/info notice in the session for display after the
     * post-redirect-get.
     */
    private function storeNotice(int $added, int $skipped): void
    {
        if (! function_exists('wc_add_notice')) {
            return;
        }

        if ($added > 0) {
            wc_add_notice(
                sprintf(
                    /* translators: %d: number of products added to the cart */
                    _n('%d product added to your cart.', '%d products added to your cart.', $added, 'rapid'),
                    $added,
                ),
                'success',
            );
        }

        if ($skipped > 0) {
            wc_add_notice(
                sprintf(
                    /* translators: %d: number of products that could not be added */
                    _n('%d product could not be added.', '%d products could not be added.', $skipped, 'rapid'),
                    $skipped,
                ),
                'error',
            );
        }

        if (0 === $added && 0 === $skipped) {
            wc_add_notice(__('No quantities were entered.', 'rapid'), 'notice');
        }
    }

    /**
     * Render any queued WooCommerce notices once (the form sits on a normal page,
     * which may not print them itself).
     */
    private function consumeNotice(): string
    {
        if (! function_exists('wc_print_notices') || ! function_exists('wc_notice_count') || wc_notice_count() < 1) {
            return '';
        }

        ob_start();
        wc_print_notices();

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function columnCount(array $settings): int
    {
        // Name + quantity + add are always present.
        $columns = 3;

        $columns += $this->showImage($settings) ? 1 : 0;
        $columns += $this->showSku($settings) ? 1 : 0;
        $columns += $this->showPrice($settings) ? 1 : 0;
        $columns += $this->showStock($settings) ? 1 : 0;

        return $columns;
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function perPage(array $settings): int
    {
        $perPage = isset($settings['per_page']) ? (int) $settings['per_page'] : 12;

        return max(1, min(self::MAX_PER_PAGE, $perPage));
    }

    /** @param array<string, mixed> $settings */
    private function showImage(array $settings): bool
    {
        return ! empty($settings['show_image']);
    }

    /** @param array<string, mixed> $settings */
    private function showSku(array $settings): bool
    {
        return ! empty($settings['show_sku']);
    }

    /** @param array<string, mixed> $settings */
    private function showPrice(array $settings): bool
    {
        return ! empty($settings['show_price']);
    }

    /** @param array<string, mixed> $settings */
    private function showStock(array $settings): bool
    {
        return ! empty($settings['show_stock']);
    }

    private function currentUrl(): string
    {
        $pageId = get_queried_object_id();

        if ($pageId > 0) {
            $permalink = get_permalink($pageId);
            if (is_string($permalink)) {
                return $permalink;
            }
        }

        return home_url(add_query_arg([], ''));
    }

    private function isEnabled(): bool
    {
        return (bool) ($this->settings()['enabled'] ?? false);
    }

    /**
     * Stored settings merged over packaged defaults.
     *
     * @return array<string, mixed>
     */
    private function settings(): array
    {
        $stored = get_option(self::OPTION, []);

        if (! is_array($stored)) {
            $stored = [];
        }

        /** @var array<string, mixed> $defaults */
        $defaults = require RAPID_DIR . 'config/defaults.php';

        return array_merge($defaults, $stored);
    }

    /**
     * Render a packaged template with an escaped context.
     *
     * @param array<string, mixed> $context
     */
    private function renderTemplate(string $template, array $context): void
    {
        $file = RAPID_DIR . 'templates/' . $template . '.php';

        if (! is_readable($file)) {
            return;
        }

        extract($context, EXTR_SKIP);
        require $file;
    }
}
