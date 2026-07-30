<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This test may only run from the command line.\n");
    exit(1);
}

define(
    'ALANFULLBEARD_CONTACT_VAULT_KEY',
    base64_encode(random_bytes(32))
);

require dirname(__DIR__) . '/wp-load.php';

function contact_vault_wp_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

contact_vault_wp_assert(
    class_exists('AlanFullbeard_Contact_Vault'),
    'The contact-vault mu-plugin did not load.'
);
contact_vault_wp_assert(
    class_exists('Flamingo_Inbound_Message'),
    'Flamingo is not available.'
);
contact_vault_wp_assert(
    AlanFullbeard_Contact_Vault::isReady(),
    'Sodium or the test key is unavailable.'
);

$marker = 'vault-test-' . bin2hex(random_bytes(8));
$email = $marker . '@example.com';
$postId = 0;
$legacyPostId = 0;

try {
    AlanFullbeard_Contact_Vault::beginSubmission();

    $contact = Flamingo_Contact::add([
        'email' => $email,
        'name' => $marker,
    ]);

    contact_vault_wp_assert(
        empty($contact),
        'The Flamingo address-book plaintext copy was not suppressed.'
    );

    $scrubbed = AlanFullbeard_Contact_Vault::encryptInboundParameters([
        'channel' => 'contact-form-7',
        'status' => 'mail_sent',
        'subject' => $marker . ' subject',
        'from' => $marker . ' <' . $email . '>',
        'from_name' => $marker,
        'from_email' => $email,
        'fields' => [
            'your-name' => $marker,
            'your-email' => $email,
            'your-phone' => '+1 555 010 9999',
            'your-text-permission' => ['No'],
            'your-message' => $marker . ' private message',
        ],
        'meta' => [
            'remote_ip' => '192.0.2.20',
            'user_agent' => $marker . ' browser',
        ],
        'akismet' => [],
        'recaptcha' => ['score' => 0.95],
        'spam' => false,
        'spam_log' => [],
        'consent' => [],
        'timestamp' => time(),
        'posted_data_hash' => hash('sha256', $marker),
    ]);

    $inbound = Flamingo_Inbound_Message::add($scrubbed);
    contact_vault_wp_assert(
        is_object($inbound) && $inbound->id() > 0,
        'Flamingo did not create the scrubbed test record.'
    );

    $postId = (int) $inbound->id();

    AlanFullbeard_Contact_Vault::persistEncryptedRecord([
        'flamingo_inbound_id' => $postId,
    ]);

    $rawPost = get_post($postId, ARRAY_A);
    $rawMeta = get_post_meta($postId, '', false);
    $databaseImage = serialize([$rawPost, $rawMeta]);

    contact_vault_wp_assert(
        ! str_contains($databaseImage, $marker),
        'A visitor value remained in the raw WordPress record.'
    );
    contact_vault_wp_assert(
        ! str_contains($databaseImage, $email),
        'The visitor email remained in the raw WordPress record.'
    );
    contact_vault_wp_assert(
        ! str_contains($databaseImage, '+1 555 010 9999'),
        'The visitor phone remained in the raw WordPress record.'
    );
    contact_vault_wp_assert(
        ! str_contains($databaseImage, '192.0.2.20'),
        'The visitor IP address remained in the raw WordPress record.'
    );

    $envelope = get_post_meta(
        $postId,
        '_afb_contact_vault_payload',
        true
    );
    contact_vault_wp_assert(
        is_string($envelope) && $envelope !== '',
        'The encrypted payload was not stored.'
    );

    $payload = AlanFullbeard_Contact_Vault::decryptPayload($envelope);
    contact_vault_wp_assert(
        $payload['fields']['your-name'] === $marker
            && $payload['fields']['your-email'] === $email
            && $payload['fields']['your-phone'] === '+1 555 010 9999'
            && $payload['technical']['remote_ip'] === '192.0.2.20',
        'The encrypted WordPress payload did not round-trip every field.'
    );

    $legacyMarker = $marker . '-legacy';
    $legacyEmail = $legacyMarker . '@example.com';
    $legacy = Flamingo_Inbound_Message::add([
        'channel' => 'contact-form-7',
        'status' => 'mail_sent',
        'subject' => $legacyMarker . ' subject',
        'from' => $legacyMarker . ' <' . $legacyEmail . '>',
        'from_name' => $legacyMarker,
        'from_email' => $legacyEmail,
        'fields' => [
            'your-name' => $legacyMarker,
            'your-email' => $legacyEmail,
            'your-phone' => '+1 555 010 8888',
            'your-text-permission' => ['Yes'],
            'your-message' => $legacyMarker . ' private message',
        ],
        'meta' => [
            'remote_ip' => '192.0.2.30',
            'form_id' => 61,
            'message_field' => '[your-message]',
        ],
        'akismet' => ['comment' => $legacyMarker],
        'recaptcha' => ['score' => 0.8],
        'spam' => false,
        'spam_log' => [],
        'consent' => [],
        'timestamp' => time(),
        'posted_data_hash' => hash('sha256', $legacyMarker),
    ]);
    contact_vault_wp_assert(
        is_object($legacy) && $legacy->id() > 0,
        'Flamingo did not create the legacy migration fixture.'
    );

    $legacyPostId = (int) $legacy->id();

    contact_vault_wp_assert(
        str_contains(
            serialize([
                get_post($legacyPostId, ARRAY_A),
                get_post_meta($legacyPostId, '', false),
            ]),
            $legacyEmail
        ),
        'The migration fixture should begin as a plaintext Flamingo message.'
    );
    contact_vault_wp_assert(
        AlanFullbeard_Contact_Vault::migrateLegacyMessage($legacyPostId),
        'The legacy Flamingo message migration failed.'
    );

    $migratedPost = get_post($legacyPostId, ARRAY_A);
    $migratedMeta = get_post_meta($legacyPostId, '', false);
    $migratedImage = serialize([$migratedPost, $migratedMeta]);
    $remainingPlaintext = [];

    foreach ([
        'marker' => $legacyMarker,
        'email' => $legacyEmail,
        'phone' => '+1 555 010 8888',
        'IP address' => '192.0.2.30',
    ] as $label => $needle) {
        if (str_contains($migratedImage, $needle)) {
            $remainingPlaintext[] = $label;
        }
    }

    if (in_array('marker', $remainingPlaintext, true)) {
        foreach ((array) $migratedPost as $key => $value) {
            if (str_contains(serialize($value), $legacyMarker)) {
                $remainingPlaintext[] = 'post.' . (string) $key;
            }
        }

        foreach ((array) $migratedMeta as $key => $value) {
            if (str_contains(serialize($value), $legacyMarker)) {
                $remainingPlaintext[] = 'meta.' . (string) $key;
            }
        }
    }

    contact_vault_wp_assert(
        $remainingPlaintext === [],
        'Legacy plaintext remained after migration: '
            . implode(', ', $remainingPlaintext)
    );

    $legacyPayload = AlanFullbeard_Contact_Vault::decryptPayload(
        (string) get_post_meta(
            $legacyPostId,
            '_afb_contact_vault_payload',
            true
        )
    );
    contact_vault_wp_assert(
        $legacyPayload['sender']['email'] === $legacyEmail
            && $legacyPayload['fields']['your-phone'] === '+1 555 010 8888'
            && $legacyPayload['technical']['remote_ip'] === '192.0.2.30',
        'The migrated legacy payload did not retain every encrypted field.'
    );

    echo "Encrypted contact-vault WordPress integration passed.\n";
} finally {
    AlanFullbeard_Contact_Vault::endSubmission();

    if ($postId > 0) {
        wp_delete_post($postId, true);
    }

    if ($legacyPostId > 0) {
        wp_delete_post($legacyPostId, true);
    }
}
