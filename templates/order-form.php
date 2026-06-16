<?php
/**
 * Storefront template for the [rapid_order] quick-order form.
 *
 * Provided variables (escaped on output here):
 *
 * @var array<string, mixed>                 $settings    Merged plugin settings.
 * @var array<int, array<string, mixed>>     $products    Initial product rows.
 * @var int                                  $columns     Table column count.
 * @var string                               $notice      Pre-rendered WC notices HTML.
 *
 * @package Rapid
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$rapid_show_image = ! empty($settings['show_image']);
$rapid_show_sku   = ! empty($settings['show_sku']);
$rapid_show_price = ! empty($settings['show_price']);
$rapid_show_stock = ! empty($settings['show_stock']);
?>
<div
    class="rapid"
    data-show-image="<?php echo $rapid_show_image ? '1' : '0'; ?>"
    data-show-sku="<?php echo $rapid_show_sku ? '1' : '0'; ?>"
    data-show-price="<?php echo $rapid_show_price ? '1' : '0'; ?>"
    data-show-stock="<?php echo $rapid_show_stock ? '1' : '0'; ?>"
>
    <?php
    if ('' !== $notice) {
        echo '<div class="rapid__notices">' . wp_kses_post($notice) . '</div>';
    }
    ?>

    <div class="rapid__controls">
        <p class="rapid__search-field">
            <label for="rapid-search"><?php esc_html_e('Search products', 'rapid'); ?></label>
            <input
                type="search"
                id="rapid-search"
                class="rapid__search"
                placeholder="<?php esc_attr_e('Search by name or SKU…', 'rapid'); ?>"
                autocomplete="off"
            />
        </p>
    </div>

    <form method="post" class="rapid__form">
        <?php wp_nonce_field('rapid_order', 'rapid_nonce'); ?>

        <div class="rapid__status" aria-live="polite" role="status"></div>

        <table class="rapid__table">
            <thead>
                <tr>
                    <?php if ($rapid_show_image) : ?>
                        <th scope="col" class="rapid__col-image"><span class="screen-reader-text"><?php esc_html_e('Image', 'rapid'); ?></span></th>
                    <?php endif; ?>
                    <th scope="col" class="rapid__col-name"><?php esc_html_e('Product', 'rapid'); ?></th>
                    <?php if ($rapid_show_sku) : ?>
                        <th scope="col" class="rapid__col-sku"><?php esc_html_e('SKU', 'rapid'); ?></th>
                    <?php endif; ?>
                    <?php if ($rapid_show_price) : ?>
                        <th scope="col" class="rapid__col-price"><?php esc_html_e('Price', 'rapid'); ?></th>
                    <?php endif; ?>
                    <?php if ($rapid_show_stock) : ?>
                        <th scope="col" class="rapid__col-stock"><?php esc_html_e('Stock', 'rapid'); ?></th>
                    <?php endif; ?>
                    <th scope="col" class="rapid__col-qty"><?php esc_html_e('Quantity', 'rapid'); ?></th>
                </tr>
            </thead>
            <tbody class="rapid__body">
                <?php if ([] === $products) : ?>
                    <tr class="rapid__empty-row">
                        <td colspan="<?php echo esc_attr((string) $columns); ?>">
                            <?php esc_html_e('No products are available to order yet.', 'rapid'); ?>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($products as $rapid_product) : ?>
                        <tr class="rapid__row">
                            <?php if ($rapid_show_image) : ?>
                                <td class="rapid__col-image" data-label="<?php esc_attr_e('Image', 'rapid'); ?>">
                                    <img
                                        src="<?php echo esc_url((string) $rapid_product['imageUrl']); ?>"
                                        alt=""
                                        width="48"
                                        height="48"
                                        loading="lazy"
                                        decoding="async"
                                    />
                                </td>
                            <?php endif; ?>
                            <td class="rapid__col-name" data-label="<?php esc_attr_e('Product', 'rapid'); ?>">
                                <?php if ('' !== (string) $rapid_product['permalink']) : ?>
                                    <a href="<?php echo esc_url((string) $rapid_product['permalink']); ?>"><?php echo esc_html((string) $rapid_product['name']); ?></a>
                                <?php else : ?>
                                    <?php echo esc_html((string) $rapid_product['name']); ?>
                                <?php endif; ?>
                            </td>
                            <?php if ($rapid_show_sku) : ?>
                                <td class="rapid__col-sku" data-label="<?php esc_attr_e('SKU', 'rapid'); ?>"><?php echo esc_html((string) $rapid_product['sku']); ?></td>
                            <?php endif; ?>
                            <?php if ($rapid_show_price) : ?>
                                <td class="rapid__col-price" data-label="<?php esc_attr_e('Price', 'rapid'); ?>"><?php echo wp_kses_post((string) $rapid_product['priceHtml']); ?></td>
                            <?php endif; ?>
                            <?php if ($rapid_show_stock) : ?>
                                <td class="rapid__col-stock" data-label="<?php esc_attr_e('Stock', 'rapid'); ?>"><?php echo esc_html((string) $rapid_product['stockHtml']); ?></td>
                            <?php endif; ?>
                            <td class="rapid__col-qty" data-label="<?php esc_attr_e('Quantity', 'rapid'); ?>">
                                <label class="screen-reader-text" for="rapid-qty-<?php echo esc_attr((string) $rapid_product['id']); ?>">
                                    <?php
                                    /* translators: %s: product name */
                                    echo esc_html(sprintf(__('Quantity for %s', 'rapid'), (string) $rapid_product['name']));
                                    ?>
                                </label>
                                <input
                                    type="number"
                                    min="0"
                                    step="1"
                                    inputmode="numeric"
                                    id="rapid-qty-<?php echo esc_attr((string) $rapid_product['id']); ?>"
                                    name="rapid_qty[<?php echo esc_attr((string) $rapid_product['id']); ?>]"
                                    value="0"
                                    class="rapid__qty"
                                    <?php disabled((bool) $rapid_product['inStock'], false); ?>
                                />
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="rapid__actions">
            <button type="submit" name="rapid_submit" value="1" class="button alt rapid__submit">
                <?php esc_html_e('Add selected to cart', 'rapid'); ?>
            </button>
            <span class="rapid__tally" aria-live="polite">
                <span class="rapid__tally-count">0</span>
                <?php esc_html_e('lines queued', 'rapid'); ?>
            </span>
        </div>
    </form>
</div>
