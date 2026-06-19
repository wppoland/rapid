<?php
/**
 * Plugin Name:       Rapid - Quick Order Form for WooCommerce
 * Plugin URI:        https://plogins.com/rapid/
 * Description:        A fast bulk order form so B2B and wholesale buyers can add many products at once.
 * Version:           0.1.1
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * Author:            WPPoland.com
 * Author URI:        https://wppoland.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       rapid
 * Domain Path:       /languages
 * WC requires at least: 8.0
 *
 * @package Rapid
 */

declare(strict_types=1);

namespace Rapid;

defined('ABSPATH') || exit;

const VERSION     = '0.1.1';
const PLUGIN_FILE = __FILE__;

define('RAPID_DIR', plugin_dir_path(__FILE__));
define('RAPID_URL', plugin_dir_url(__FILE__));

require_once __DIR__ . '/autoload.php';

// HPOS + cart/checkout blocks compatibility.
add_action('before_woocommerce_init', static function (): void {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

add_action('plugins_loaded', static function (): void {
    if (! class_exists('WooCommerce')) {
        add_action('admin_notices', static function (): void {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('Rapid - Quick Order Form for WooCommerce requires WooCommerce to be active.', 'rapid');
            echo '</p></div>';
        });
        return;
    }

    // Translations load automatically on WordPress.org-hosted plugins (WP 4.6+)
    // via the slug + Domain Path header, so no manual load_plugin_textdomain()
    // call is needed (and Plugin Check discourages it).
    add_action('init', static function (): void {
        Plugin::instance()->boot();
    }, 0);
}, 10);
