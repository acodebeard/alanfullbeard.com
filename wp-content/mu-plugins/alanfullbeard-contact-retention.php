<?php
/**
 * Plugin Name: Alan Fullbeard Contact Retention
 * Description: Applies the published retention schedule to Flamingo contact data.
 * Author: @acodebeard
 * Version: 1.0.0
 */

declare(strict_types=1);

if (! defined('ALANFULLBEARD_CONTACT_INBOX_RETENTION_DAYS')) {
    define('ALANFULLBEARD_CONTACT_INBOX_RETENTION_DAYS', 180);
}

if (! defined('ALANFULLBEARD_CONTACT_SPAM_RETENTION_DAYS')) {
    define('ALANFULLBEARD_CONTACT_SPAM_RETENTION_DAYS', 30);
}

if (! function_exists('alanfullbeard_contact_retention_schedule')) {
    /**
     * Ensures the retention task is scheduled for sites using this mu-plugin.
     */
    function alanfullbeard_contact_retention_schedule(): void
    {
        if (! wp_next_scheduled('alanfullbeard_contact_retention_daily')) {
            wp_schedule_event(
                time() + HOUR_IN_SECONDS,
                'daily',
                'alanfullbeard_contact_retention_daily'
            );
        }
    }
}
add_action('init', 'alanfullbeard_contact_retention_schedule');

if (! function_exists('alanfullbeard_contact_retention_cutoff')) {
    /**
     * Returns a site-timezone cutoff suitable for WordPress date queries.
     */
    function alanfullbeard_contact_retention_cutoff(int $days): string
    {
        return wp_date(
            'Y-m-d H:i:s',
            time() - (max(1, $days) * DAY_IN_SECONDS)
        );
    }
}

if (! function_exists('alanfullbeard_contact_retention_delete_ids')) {
    /**
     * Permanently deletes a bounded list of expired private records.
     *
     * @param array<int, int|string> $postIds
     */
    function alanfullbeard_contact_retention_delete_ids(array $postIds): void
    {
        foreach ($postIds as $postId) {
            wp_delete_post((int) $postId, true);
        }
    }
}

if (! function_exists('alanfullbeard_contact_retention_cleanup_messages')) {
    /**
     * Deletes expired inbox, spam, and trash records in bounded batches.
     */
    function alanfullbeard_contact_retention_cleanup_messages(): void
    {
        $policies = [
            'publish' => (int) ALANFULLBEARD_CONTACT_INBOX_RETENTION_DAYS,
            'flamingo-spam' => (int) ALANFULLBEARD_CONTACT_SPAM_RETENTION_DAYS,
            'trash' => (int) ALANFULLBEARD_CONTACT_SPAM_RETENTION_DAYS,
        ];

        foreach ($policies as $status => $days) {
            $postIds = get_posts([
                'post_type' => 'flamingo_inbound',
                'post_status' => $status,
                'posts_per_page' => 100,
                'fields' => 'ids',
                'orderby' => 'ID',
                'order' => 'ASC',
                'no_found_rows' => true,
                'date_query' => [[
                    'before' => alanfullbeard_contact_retention_cutoff($days),
                    'inclusive' => false,
                ]],
            ]);

            alanfullbeard_contact_retention_delete_ids(
                is_array($postIds) ? $postIds : []
            );
        }
    }
}

if (! function_exists('alanfullbeard_contact_retention_cleanup_contacts')) {
    /**
     * Deletes expired Flamingo address-book records in bounded batches.
     */
    function alanfullbeard_contact_retention_cleanup_contacts(): void
    {
        $postIds = get_posts([
            'post_type' => 'flamingo_contact',
            'post_status' => 'publish',
            'posts_per_page' => 100,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
            'no_found_rows' => true,
            'meta_key' => '_last_contacted',
            'meta_value' => alanfullbeard_contact_retention_cutoff(
                (int) ALANFULLBEARD_CONTACT_INBOX_RETENTION_DAYS
            ),
            'meta_compare' => '<',
            'meta_type' => 'DATETIME',
        ]);

        alanfullbeard_contact_retention_delete_ids(
            is_array($postIds) ? $postIds : []
        );
    }
}

if (! function_exists('alanfullbeard_contact_retention_run')) {
    /**
     * Runs the complete contact-data retention schedule.
     */
    function alanfullbeard_contact_retention_run(): void
    {
        alanfullbeard_contact_retention_cleanup_messages();
        alanfullbeard_contact_retention_cleanup_contacts();
    }
}
add_action(
    'alanfullbeard_contact_retention_daily',
    'alanfullbeard_contact_retention_run'
);
