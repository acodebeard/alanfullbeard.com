<?php

if (PHP_SAPI !== 'cli' || ! defined('WP_CLI')) {
    throw new RuntimeException('Run this migration through WP-CLI.');
}

if (
    ! class_exists('AlanFullbeard_Contact_Vault')
    || ! AlanFullbeard_Contact_Vault::isReady()
) {
    throw new RuntimeException(
        'The encrypted contact vault and its key must be active first.'
    );
}

global $wpdb;

$inboundIds = $wpdb->get_col(
    $wpdb->prepare(
        "SELECT ID
        FROM {$wpdb->posts}
        WHERE post_type = %s
        ORDER BY ID ASC",
        'flamingo_inbound'
    )
);

if (! is_array($inboundIds)) {
    throw new RuntimeException('Could not enumerate legacy Flamingo messages.');
}

$migrated = 0;

foreach ($inboundIds as $inboundId) {
    $postId = (int) $inboundId;

    if (! AlanFullbeard_Contact_Vault::migrateLegacyMessage($postId)) {
        throw new RuntimeException(
            sprintf('Migration failed for Flamingo message ID %d.', $postId)
        );
    }

    $envelope = get_post_meta(
        $postId,
        '_afb_contact_vault_payload',
        true
    );

    if (! is_string($envelope) || $envelope === '') {
        throw new RuntimeException(
            sprintf('Encrypted payload missing for message ID %d.', $postId)
        );
    }

    AlanFullbeard_Contact_Vault::decryptPayload($envelope);
    $migrated++;
}

$fieldMetaPattern = $wpdb->esc_like('_field_') . '%';
$remainingFieldMeta = (int) $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*)
        FROM {$wpdb->postmeta} AS pm
        INNER JOIN {$wpdb->posts} AS p ON p.ID = pm.post_id
        WHERE p.post_type = %s
          AND pm.meta_key LIKE %s",
        'flamingo_inbound',
        $fieldMetaPattern
    )
);
$remainingSenderMeta = (int) $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*)
        FROM {$wpdb->postmeta} AS pm
        INNER JOIN {$wpdb->posts} AS p ON p.ID = pm.post_id
        WHERE p.post_type = %s
          AND pm.meta_key IN ('_from_email', '_from_name')
          AND pm.meta_value <> ''",
        'flamingo_inbound'
    )
);
$unscrubbedPosts = (int) $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*)
        FROM {$wpdb->posts}
        WHERE post_type = %s
          AND (
            post_title <> %s
            OR post_content <> ''
            OR post_excerpt <> ''
          )",
        'flamingo_inbound',
        'Encrypted contact message'
    )
);
$encryptedRows = (int) $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(DISTINCT pm.post_id)
        FROM {$wpdb->postmeta} AS pm
        INNER JOIN {$wpdb->posts} AS p ON p.ID = pm.post_id
        WHERE p.post_type = %s
          AND pm.meta_key = %s",
        'flamingo_inbound',
        '_afb_contact_vault_payload'
    )
);

if (
    $remainingFieldMeta !== 0
    || $remainingSenderMeta !== 0
    || $unscrubbedPosts !== 0
    || $encryptedRows !== count($inboundIds)
) {
    throw new RuntimeException(
        sprintf(
            'Migration verification failed: field_meta=%d sender_meta=%d unscrubbed_posts=%d encrypted=%d expected=%d.',
            $remainingFieldMeta,
            $remainingSenderMeta,
            $unscrubbedPosts,
            $encryptedRows,
            count($inboundIds)
        )
    );
}

printf(
    "contact_vault_migrated=%d encrypted=%d field_meta=%d sender_meta=%d unscrubbed_posts=%d\n",
    $migrated,
    $encryptedRows,
    $remainingFieldMeta,
    $remainingSenderMeta,
    $unscrubbedPosts
);
