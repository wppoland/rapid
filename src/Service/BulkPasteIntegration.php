<?php

declare(strict_types=1);

namespace Rapid\Service;

defined('ABSPATH') || exit;

/**
 * Integration points for the PRO bulk-paste shortcode and add-ons.
 *
 * The paste UI lives in Rapid Pro, but these hooks are owned by the free plugin
 * so third parties can extend the same surface.
 */
final class BulkPasteIntegration
{
    /**
     * Lines to pre-fill in the bulk-paste textarea (SKU, quantity per line).
     */
    public static function prefillLines(int $userId): string
    {
        $lines = apply_filters('rapid/bulk_paste_prefill', '', $userId);

        return is_string($lines) ? $lines : '';
    }

    /**
     * Render extra markup inside the bulk-paste form (saved lists, etc.).
     */
    public static function renderFormExtras(): void
    {
        do_action('rapid/bulk_paste_form_extra');
    }

    /**
     * Render markup after the bulk-paste form (delete actions, etc.).
     */
    public static function renderAfterForm(): void
    {
        do_action('rapid/bulk_paste_after_form');
    }

    public static function currentUserId(): int
    {
        $userId = get_current_user_id();

        return $userId > 0 ? $userId : 0;
    }
}
