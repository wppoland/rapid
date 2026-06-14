<?php
/**
 * Default settings, merged under the option key `rapid_settings`.
 *
 * @package Rapid
 *
 * @return array<string, mixed>
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

return [
    'enabled' => true,

    // Product scope: 'all' or 'categories' (limit to selected categories).
    'scope'      => 'all',
    // Term IDs of product categories the form is limited to (scope = categories).
    'categories' => [],

    // Columns shown in the order table.
    'show_image' => true,
    'show_sku'   => true,
    'show_price' => true,
    'show_stock' => true,

    // Search results per page.
    'per_page' => 12,
];
