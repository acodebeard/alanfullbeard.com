<?php

if (PHP_SAPI !== 'cli' || ! defined('WP_CLI')) {
    throw new RuntimeException('Run this cleanup through WP-CLI.');
}

global $wpdb;

$contactIds = $wpdb->get_col(
    $wpdb->prepare(
        "SELECT ID
        FROM {$wpdb->posts}
        WHERE post_type = %s
        ORDER BY ID ASC",
        'flamingo_contact'
    )
);

if (! is_array($contactIds)) {
    throw new RuntimeException('Could not enumerate Flamingo contacts.');
}

foreach ($contactIds as $contactId) {
    $postId = (int) $contactId;

    if (! wp_delete_post($postId, true)) {
        throw new RuntimeException(
            sprintf('Could not remove Flamingo contact ID %d.', $postId)
        );
    }
}

$remaining = (int) $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*)
        FROM {$wpdb->posts}
        WHERE post_type = %s",
        'flamingo_contact'
    )
);

if ($remaining !== 0) {
    throw new RuntimeException(
        sprintf('%d plaintext Flamingo contacts remain.', $remaining)
    );
}

printf(
    "flamingo_contacts_removed=%d remaining=%d\n",
    count($contactIds),
    $remaining
);
