<?php
/**
 * Plugin Name: New Tab Dammit
 * Description: Opens admin-bar Visit Site links in a new tab and shows the classic Menus Link Target control by default.
 * Author: @acodebeard
 * Version: 1.1.0
 * License: Unlicense
 * License URI: https://unlicense.org/
 */

declare(strict_types=1);

if (! function_exists('new_tab_dammit')) {
    /**
     * Opens the admin-bar site links in a new tab while preserving core node data.
     *
     * @param WP_Admin_Bar $wp_admin_bar WordPress admin-bar instance.
     */
    function new_tab_dammit(WP_Admin_Bar $wp_admin_bar): void
    {
        if (! is_admin()) {
            return;
        }

        foreach (['site-name', 'view-site'] as $node_id) {
            $node = $wp_admin_bar->get_node($node_id);

            if (! $node) {
                continue;
            }

            $meta = array_merge(is_array($node->meta) ? $node->meta : [], [
                'target' => '_blank',
                'rel' => 'noopener noreferrer',
            ]);

            $wp_admin_bar->add_node([
                'id' => $node_id,
                'meta' => $meta,
            ]);
        }
    }
}
add_action('admin_bar_menu', 'new_tab_dammit', 100);

if (! function_exists('new_tab_dammit_enable_menu_link_target')) {
    /**
     * Shows the classic Menus screen's Link Target control once per user.
     *
     * The marker makes this a default rather than a permanent override: after
     * the first visit, WordPress continues to respect the user's Screen Options
     * choice.
     *
     * @param string[] $hidden       Hidden column IDs for the current screen.
     * @param WP_Screen $screen      Current WordPress admin screen.
     * @param bool      $use_defaults Whether WordPress started with defaults.
     *
     * @return string[]
     */
    function new_tab_dammit_enable_menu_link_target(
        array $hidden,
        WP_Screen $screen,
        bool $use_defaults
    ): array {
        unset($use_defaults);

        if ('nav-menus' !== $screen->id || ! current_user_can('edit_theme_options')) {
            return $hidden;
        }

        $user_id = get_current_user_id();
        $marker_key = '_new_tab_dammit_link_target_defaulted';

        if (
            $user_id <= 0
            || '1' === get_user_meta($user_id, $marker_key, true)
        ) {
            return $hidden;
        }

        $hidden = array_values(array_diff($hidden, ['link-target']));

        update_user_meta($user_id, 'managenav-menuscolumnshidden', $hidden);
        update_user_meta($user_id, $marker_key, '1');

        return $hidden;
    }
}
add_filter(
    'hidden_columns',
    'new_tab_dammit_enable_menu_link_target',
    10,
    3
);
