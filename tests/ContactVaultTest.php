<?php

declare(strict_types=1);

const ALANFULLBEARD_CONTACT_VAULT_KEY = 'ERERERERERERERERERERERERERERERERERERERERERE=';

/** @var array<string, array<int, array{callback: mixed, priority: int}>> */
$contactVaultHooks = [];
/** @var array<int, array<string, mixed>> */
$contactVaultMeta = [];
/** @var array<int, bool> */
$contactVaultDeleted = [];

function add_action(string $hook, mixed $callback, int $priority = 10): void
{
    global $contactVaultHooks;
    $contactVaultHooks[$hook][] = [
        'callback' => $callback,
        'priority' => $priority,
    ];
}

function add_filter(string $hook, mixed $callback, int $priority = 10): void
{
    add_action($hook, $callback, $priority);
}

function wp_json_encode(mixed $value, int $flags = 0): string|false
{
    return json_encode($value, $flags);
}

function __(string $value, string $domain = ''): string
{
    return $value;
}

function update_post_meta(int $postId, string $key, mixed $value): int|bool
{
    global $contactVaultMeta;
    $contactVaultMeta[$postId][$key] = $value;

    return 1;
}

function get_post_meta(int $postId, string $key, bool $single = false): mixed
{
    global $contactVaultMeta;
    $value = $contactVaultMeta[$postId][$key] ?? ($single ? '' : []);

    return $single ? $value : [$value];
}

function wp_delete_post(int $postId, bool $forceDelete = false): object
{
    global $contactVaultDeleted;
    $contactVaultDeleted[$postId] = $forceDelete;

    return (object) ['ID' => $postId];
}

function get_posts(array $args): array
{
    return [];
}

function get_post_type(int $postId): string
{
    return 'flamingo_inbound';
}

function current_user_can(string $capability, int $postId = 0): bool
{
    return true;
}

function sanitize_key(string $value): string
{
    return strtolower((string) preg_replace('/[^a-z0-9_\\-]/', '', $value));
}

function wp_unslash(mixed $value): mixed
{
    return $value;
}

function absint(mixed $value): int
{
    return abs((int) $value);
}

function add_meta_box(mixed ...$args): void
{
}

function esc_html__(string $value, string $domain = ''): string
{
    return $value;
}

function esc_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function esc_attr__(string $value, string $domain = ''): string
{
    return $value;
}

function contact_vault_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

require dirname(__DIR__) . '/wp-content/mu-plugins/alanfullbeard-contact-vault.php';

$payload = [
    'schema' => 1,
    'form_id' => 61,
    'sender' => [
        'name' => 'A Test Person',
        'email' => 'person@example.com',
    ],
    'fields' => [
        'your-name' => 'A Test Person',
        'your-email' => 'person@example.com',
        'your-phone' => '+1 555 555 1212',
        'your-text-permission' => ['No'],
        'your-message' => "A private message.\nSecond line.",
    ],
];

contact_vault_assert(
    AlanFullbeard_Contact_Vault::isReady(),
    'The vault should be ready with Sodium and a valid key.'
);

$firstEnvelope = AlanFullbeard_Contact_Vault::encryptPayload($payload);
$secondEnvelope = AlanFullbeard_Contact_Vault::encryptPayload($payload);

contact_vault_assert(
    str_starts_with($firstEnvelope, 'afb-contact-vault:v1:'),
    'Ciphertext should include a versioned envelope prefix.'
);
contact_vault_assert(
    $firstEnvelope !== $secondEnvelope,
    'A fresh nonce should produce different ciphertext for identical fields.'
);
contact_vault_assert(
    ! str_contains($firstEnvelope, 'person@example.com')
        && ! str_contains($firstEnvelope, 'A private message'),
    'The envelope must not expose visitor fields.'
);
contact_vault_assert(
    $payload === AlanFullbeard_Contact_Vault::decryptPayload($firstEnvelope),
    'The authenticated payload should round-trip without data loss.'
);

$tamperedEnvelope = substr($firstEnvelope, 0, -1)
    . (str_ends_with($firstEnvelope, 'A') ? 'B' : 'A');
$tamperingRejected = false;

try {
    AlanFullbeard_Contact_Vault::decryptPayload($tamperedEnvelope);
} catch (RuntimeException) {
    $tamperingRejected = true;
}

contact_vault_assert(
    $tamperingRejected,
    'Authentication must reject modified ciphertext.'
);

$firstIndex = AlanFullbeard_Contact_Vault::emailIndex('Person@Example.com ');
$secondIndex = AlanFullbeard_Contact_Vault::emailIndex('person@example.com');

contact_vault_assert(
    $firstIndex === $secondIndex && strlen($firstIndex) === 64,
    'Email lookup indexes should be normalized, stable, and non-empty.'
);
contact_vault_assert(
    ! str_contains($firstIndex, 'person@example.com'),
    'The privacy lookup index must not expose the email address.'
);

AlanFullbeard_Contact_Vault::beginSubmission();

$contact = AlanFullbeard_Contact_Vault::suppressPlaintextContact([
    'email' => 'person@example.com',
    'name' => 'A Test Person',
    'props' => ['source' => 'contact form'],
]);

contact_vault_assert(
    $contact['email'] === ''
        && $contact['name'] === ''
        && $contact['props'] === [],
    'Flamingo address-book duplicates should be suppressed.'
);

$inbound = AlanFullbeard_Contact_Vault::encryptInboundParameters([
    'subject' => 'Sensitive subject',
    'from' => 'A Test Person <person@example.com>',
    'from_name' => 'A Test Person',
    'from_email' => 'person@example.com',
    'fields' => $payload['fields'],
    'meta' => ['remote_ip' => '192.0.2.10'],
    'akismet' => ['comment' => 'private'],
    'recaptcha' => ['score' => 0.9],
    'spam_log' => ['reason' => 'test'],
    'consent' => ['privacy' => true],
    'posted_data_hash' => 'sensitive-hash',
]);

foreach ([
    'from_name',
    'from_email',
    'fields',
    'meta',
    'akismet',
    'recaptcha',
    'spam_log',
    'consent',
    'posted_data_hash',
] as $key) {
    contact_vault_assert(
        empty($inbound[$key]),
        "Flamingo parameter {$key} should be scrubbed before storage."
    );
}

contact_vault_assert(
    $inbound['subject'] === 'Encrypted contact message'
        && $inbound['from'] === 'Encrypted sender',
    'Flamingo title fields should contain generic labels only.'
);

AlanFullbeard_Contact_Vault::persistEncryptedRecord([
    'flamingo_inbound_id' => 91,
]);

update_post_meta(91, '_fields', [
    'your-name' => null,
    'your-email' => null,
]);
update_post_meta(91, '_meta', [
    'form_id' => 61,
    'message_field' => '[your-message]',
    'remote_ip' => '192.0.2.10',
]);
AlanFullbeard_Contact_Vault::scrubPostPluginCopies([
    'flamingo_inbound_id' => 91,
]);

contact_vault_assert(
    isset($contactVaultMeta[91]['_afb_contact_vault_payload']),
    'The encrypted payload should be stored on the Flamingo message.'
);
contact_vault_assert(
    ! str_contains(
        serialize($contactVaultMeta[91]),
        'person@example.com'
    )
        && ! str_contains(
            serialize($contactVaultMeta[91]),
            'A private message'
        ),
    'Persisted metadata must contain no visitor plaintext.'
);
contact_vault_assert(
    $contactVaultMeta[91]['_fields'] === []
        && $contactVaultMeta[91]['_meta'] === [
            'form_id' => 61,
            'message_field' => '[your-message]',
        ],
    'Later plugin hooks should retain only non-sensitive operational metadata.'
);

$storedPayload = AlanFullbeard_Contact_Vault::decryptPayload(
    $contactVaultMeta[91]['_afb_contact_vault_payload']
);

contact_vault_assert(
    $storedPayload['fields'] === $payload['fields'],
    'Every original form field should be recoverable after decryption.'
);
contact_vault_assert(
    $storedPayload['technical']['remote_ip'] === '192.0.2.10',
    'Sensitive technical metadata should be encrypted with the form fields.'
);

$vaultReflection = new ReflectionClass(AlanFullbeard_Contact_Vault::class);
$learningFlag = $vaultReflection->getProperty('exposeForSpamLearning');
$learningFlag->setValue(null, true);
$learningFields =
    AlanFullbeard_Contact_Vault::exposeMetadataForSpamLearning(
        null,
        91,
        '_fields',
        true,
        'post'
    );
$learningEmail =
    AlanFullbeard_Contact_Vault::exposeMetadataForSpamLearning(
        null,
        91,
        '_from_email',
        true,
        'post'
    );

contact_vault_assert(
    $learningFields === $payload['fields']
        && $learningEmail === 'person@example.com',
    'Spam learning should receive decrypted values in memory.'
);

AlanFullbeard_Contact_Vault::endSubmission();
AlanFullbeard_Contact_Vault::endSpamLearning();

echo "All encrypted contact-vault tests passed.\n";
