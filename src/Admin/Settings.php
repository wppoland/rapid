<?php

declare(strict_types=1);

namespace Rapid\Admin;

defined('ABSPATH') || exit;

use Rapid\Contract\HasHooks;

/**
 * Admin settings page registered as a WooCommerce submenu.
 *
 * Stores everything in the `rapid_settings` option (array): the master toggle,
 * product scope (all / selected categories), the selected category IDs, which
 * columns are shown (image / SKU / price / stock) and the search
 * results-per-page. All output is escaped; all input is sanitised and clamped on
 * save.
 */
final class Settings implements HasHooks
{
    private const OPTION = 'rapid_settings';
    private const PAGE   = 'rapid-settings';
    private const GROUP  = 'rapid_settings_group';

    private const SCOPES = ['all', 'categories'];

    /** Search results-per-page bounds for the admin number field. */
    private const MIN_PER_PAGE = 1;
    private const MAX_PER_PAGE = 50;

    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function enqueueAssets(string $hook): void
    {
        if ('woocommerce_page_' . self::PAGE !== $hook) {
            return;
        }

        wp_enqueue_style(
            'rapid-admin',
            RAPID_URL . 'assets/css/admin.css',
            [],
            \Rapid\VERSION,
        );

        wp_enqueue_script(
            'rapid-admin',
            RAPID_URL . 'assets/js/admin.js',
            [],
            \Rapid\VERSION,
            ['in_footer' => true, 'strategy' => 'defer'],
        );
    }

    public function addMenuPage(): void
    {
        add_submenu_page(
            'woocommerce',
            __('Rapid — Quick Order Form', 'rapid'),
            __('Rapid', 'rapid'),
            'manage_woocommerce',
            self::PAGE,
            [$this, 'renderPage'],
        );
    }

    public function registerSettings(): void
    {
        register_setting(
            self::GROUP,
            self::OPTION,
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize'],
            ],
        );

        // The menu uses manage_woocommerce; align the options.php save capability
        // so shop managers (not just admins with manage_options) can save.
        add_filter(
            'option_page_capability_' . self::GROUP,
            static fn (): string => 'manage_woocommerce',
        );
    }

    public function renderPage(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }

        $settings   = $this->settings();
        $scope      = (string) ($settings['scope'] ?? 'all');
        $selected   = array_map('absint', (array) ($settings['categories'] ?? []));
        $categories = $this->productCategories();
        ?>
        <div class="wrap rapid-admin">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="rapid-intro">
                <h2><?php esc_html_e('A fast bulk order form for your shop', 'rapid'); ?></h2>
                <p>
                    <?php
                    printf(
                        /* translators: %s: shortcode wrapped in <code>. */
                        esc_html__('Drop %s into any page to let customers search products by name or SKU, set quantities and add many to the cart in one click — perfect for B2B, wholesale and reorders.', 'rapid'),
                        '<code>[rapid_order]</code>',
                    );
                    ?>
                </p>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields(self::GROUP); ?>

                <div class="rapid-card">
                    <h2><?php esc_html_e('General', 'rapid'); ?></h2>
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <?php esc_html_e('Enable quick order', 'rapid'); ?>
                                </th>
                                <td>
                                    <label for="rapid_enabled">
                                        <input
                                            type="checkbox"
                                            id="rapid_enabled"
                                            name="<?php echo esc_attr(self::OPTION); ?>[enabled]"
                                            value="1"
                                            <?php checked((bool) ($settings['enabled'] ?? false), true); ?>
                                        />
                                        <?php esc_html_e('Show the quick order form on the storefront.', 'rapid'); ?>
                                    </label>
                                    <p class="description">
                                        <?php esc_html_e('When off, the shortcode renders nothing — handy while you set things up. Turn it on once you are ready for customers to use it.', 'rapid'); ?>
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="rapid-card">
                    <h2><?php esc_html_e('Product scope', 'rapid'); ?></h2>
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="rapid_scope"><?php esc_html_e('Which products?', 'rapid'); ?></label>
                                </th>
                                <td>
                                    <select
                                        id="rapid_scope"
                                        class="rapid-scope-select"
                                        name="<?php echo esc_attr(self::OPTION); ?>[scope]"
                                    >
                                        <option value="all" <?php selected($scope, 'all'); ?>>
                                            <?php esc_html_e('All products', 'rapid'); ?>
                                        </option>
                                        <option value="categories" <?php selected($scope, 'categories'); ?>>
                                            <?php esc_html_e('Selected categories only', 'rapid'); ?>
                                        </option>
                                    </select>
                                    <p class="description">
                                        <?php esc_html_e('Limits what customers can search and add. Leave on "All products" for a general reorder form, or pick categories to scope it to one range (for example wholesale lines only).', 'rapid'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr
                                class="rapid-categories-row"
                                <?php echo 'categories' === $scope ? '' : 'data-hidden="1"'; ?>
                            >
                                <th scope="row">
                                    <?php esc_html_e('Categories', 'rapid'); ?>
                                </th>
                                <td>
                                    <?php if ([] === $categories) : ?>
                                        <p class="description"><?php esc_html_e('No product categories found yet.', 'rapid'); ?></p>
                                    <?php else : ?>
                                        <fieldset class="rapid-categories">
                                            <legend class="screen-reader-text"><?php esc_html_e('Product categories', 'rapid'); ?></legend>
                                            <?php foreach ($categories as $rapid_term) : ?>
                                                <label class="rapid-category">
                                                    <input
                                                        type="checkbox"
                                                        name="<?php echo esc_attr(self::OPTION); ?>[categories][]"
                                                        value="<?php echo esc_attr((string) $rapid_term->term_id); ?>"
                                                        <?php checked(in_array((int) $rapid_term->term_id, $selected, true), true); ?>
                                                    />
                                                    <?php echo esc_html($rapid_term->name); ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </fieldset>
                                        <p class="description">
                                            <?php esc_html_e('Only products in the ticked categories appear in the form. Tick none and the form falls back to showing all products.', 'rapid'); ?>
                                        </p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="rapid-card">
                    <h2><?php esc_html_e('Columns', 'rapid'); ?></h2>
                    <p class="description"><?php esc_html_e('Choose which columns appear in the order table. Product name and quantity are always shown — for example:', 'rapid'); ?></p>
                    <div class="rapid-preview" aria-hidden="true">
                        <div class="rapid-preview-head">
                            <span><?php esc_html_e('Product', 'rapid'); ?></span>
                            <span><?php esc_html_e('Qty', 'rapid'); ?></span>
                        </div>
                        <div class="rapid-preview-row">
                            <span><?php esc_html_e('Espresso beans, 1 kg', 'rapid'); ?></span>
                            <span>2</span>
                        </div>
                    </div>
                    <table class="form-table" role="presentation">
                        <tbody>
                            <?php
                            $this->checkboxRow('show_image', __('Image', 'rapid'), __('Show a product thumbnail.', 'rapid'), $settings);
                            $this->checkboxRow('show_sku', __('SKU', 'rapid'), __('Show the product SKU.', 'rapid'), $settings);
                            $this->checkboxRow('show_price', __('Price', 'rapid'), __('Show the product price.', 'rapid'), $settings);
                            $this->checkboxRow('show_stock', __('Stock', 'rapid'), __('Show stock availability.', 'rapid'), $settings);
                            ?>
                        </tbody>
                    </table>
                </div>

                <div class="rapid-card">
                    <h2><?php esc_html_e('Search', 'rapid'); ?></h2>
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="rapid_per_page"><?php esc_html_e('Results per page', 'rapid'); ?></label>
                                </th>
                                <td>
                                    <input
                                        type="number"
                                        min="<?php echo esc_attr((string) self::MIN_PER_PAGE); ?>"
                                        max="<?php echo esc_attr((string) self::MAX_PER_PAGE); ?>"
                                        step="1"
                                        id="rapid_per_page"
                                        name="<?php echo esc_attr(self::OPTION); ?>[per_page]"
                                        value="<?php echo esc_attr((string) (int) ($settings['per_page'] ?? 12)); ?>"
                                        class="small-text"
                                    />
                                    <p class="description">
                                        <?php
                                        printf(
                                            /* translators: 1: minimum, 2: maximum */
                                            esc_html__('How many matches to show before customers load more. Lower keeps the form compact and quick; higher shows more at once. Between %1$d and %2$d.', 'rapid'),
                                            (int) self::MIN_PER_PAGE,
                                            (int) self::MAX_PER_PAGE,
                                        );
                                        ?>
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render a single checkbox row in the form-table.
     *
     * @param array<string, mixed> $settings
     */
    private function checkboxRow(string $key, string $label, string $help, array $settings): void
    {
        $id = 'rapid_' . $key;
        ?>
        <tr>
            <th scope="row">
                <?php echo esc_html($label); ?>
            </th>
            <td>
                <label for="<?php echo esc_attr($id); ?>">
                    <input
                        type="checkbox"
                        id="<?php echo esc_attr($id); ?>"
                        name="<?php echo esc_attr(self::OPTION); ?>[<?php echo esc_attr($key); ?>]"
                        value="1"
                        <?php checked((bool) ($settings[$key] ?? false), true); ?>
                    />
                    <?php echo esc_html($help); ?>
                </label>
            </td>
        </tr>
        <?php
    }

    /**
     * Sanitises, validates and clamps the submitted settings before save.
     *
     * @param mixed $raw
     * @return array<string, mixed>
     */
    public function sanitize(mixed $raw): array
    {
        if (! is_array($raw)) {
            $raw = [];
        }

        $scope = isset($raw['scope']) ? sanitize_key((string) $raw['scope']) : 'all';

        if (! in_array($scope, self::SCOPES, true)) {
            $scope = 'all';
        }

        $categories = [];

        if (isset($raw['categories']) && is_array($raw['categories'])) {
            foreach ($raw['categories'] as $id) {
                $id = absint($id);
                if ($id > 0) {
                    $categories[] = $id;
                }
            }
            $categories = array_values(array_unique($categories));
        }

        $perPage = isset($raw['per_page']) ? (int) $raw['per_page'] : 12;
        $perPage = max(self::MIN_PER_PAGE, min(self::MAX_PER_PAGE, $perPage));

        return [
            'enabled'    => ! empty($raw['enabled']),
            'scope'      => $scope,
            'categories' => $categories,
            'show_image' => ! empty($raw['show_image']),
            'show_sku'   => ! empty($raw['show_sku']),
            'show_price' => ! empty($raw['show_price']),
            'show_stock' => ! empty($raw['show_stock']),
            'per_page'   => $perPage,
        ];
    }

    /**
     * Product categories for the scope picker.
     *
     * @return array<int, \WP_Term>
     */
    private function productCategories(): array
    {
        $terms = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'orderby'    => 'name',
        ]);

        return is_array($terms)
            ? array_values(array_filter($terms, static fn ($t): bool => $t instanceof \WP_Term))
            : [];
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
}
