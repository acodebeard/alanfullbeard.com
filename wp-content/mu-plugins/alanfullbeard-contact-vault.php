<?php
/**
 * Plugin Name: Alan Fullbeard Encrypted Contact Vault
 * Description: Encrypts Contact Form 7 submissions before Flamingo stores them.
 * Author: @acodebeard
 * Version: 1.0.0
 */

declare(strict_types=1);

if (! function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')) {
    $alanfullbeardSodiumAutoloader = dirname(__DIR__, 2) . '/vendor/autoload.php';

    if (is_readable($alanfullbeardSodiumAutoloader)) {
        require_once $alanfullbeardSodiumAutoloader;
    }
}

if (! class_exists('AlanFullbeard_Contact_Vault')) {
    final class AlanFullbeard_Contact_Vault
    {
        private const PREFIX = 'afb-contact-vault:v1:';
        private const AAD = 'alanfullbeard-contact-vault:v1';
        private const KEY_BYTES = 32;
        private const PAYLOAD_META_KEY = '_afb_contact_vault_payload';
        private const EMAIL_INDEX_META_KEY = '_afb_contact_email_index';
        private const VERSION_META_KEY = '_afb_contact_vault_version';
        private const FORM_ID_META_KEY = '_afb_contact_form_id';

        /** @var array{ciphertext: string, email_index: string, form_id: int}|null */
        private static ?array $pendingRecord = null;

        private static bool $capturingSubmission = false;

        private static bool $exposeForSpamLearning = false;

        /** @var array<int, array<string, mixed>> */
        private static array $decryptedRecordCache = [];

        /**
         * Registers the integration without modifying Flamingo or Contact Form 7.
         */
        public static function boot(): void
        {
            add_action('wpcf7_submit', [self::class, 'beginSubmission'], 9, 2);
            add_action('wpcf7_submit', [self::class, 'endSubmission'], 11, 2);

            add_filter(
                'wpcf7_flamingo_submit_if',
                [self::class, 'requireEncryptionBeforeStorage'],
                PHP_INT_MAX
            );
            add_filter(
                'flamingo_add_contact',
                [self::class, 'suppressPlaintextContact'],
                PHP_INT_MAX
            );
            add_filter(
                'wpcf7_flamingo_inbound_message_parameters',
                [self::class, 'encryptInboundParameters'],
                PHP_INT_MAX
            );
            add_action(
                'wpcf7_after_flamingo',
                [self::class, 'persistEncryptedRecord'],
                1
            );
            add_action(
                'wpcf7_after_flamingo',
                [self::class, 'scrubPostPluginCopies'],
                PHP_INT_MAX
            );

            add_action(
                'load-flamingo_page_flamingo_inbound',
                [self::class, 'registerAdminMessageBox'],
                20
            );
            add_action(
                'load-flamingo_page_flamingo_inbound',
                [self::class, 'beginSpamLearning'],
                8
            );
            add_action(
                'load-flamingo_page_flamingo_inbound',
                [self::class, 'endSpamLearning'],
                10
            );
            add_filter(
                'get_post_metadata',
                [self::class, 'exposeMetadataForSpamLearning'],
                10,
                5
            );
            add_filter(
                'manage_flamingo_inbound_posts_columns',
                [self::class, 'filterInboundColumns'],
                20
            );

            add_filter(
                'wp_privacy_personal_data_exporters',
                [self::class, 'registerPrivacyExporter']
            );
            add_filter(
                'wp_privacy_personal_data_erasers',
                [self::class, 'registerPrivacyEraser']
            );

            add_action('admin_notices', [self::class, 'renderConfigurationNotice']);
        }

        /**
         * Marks the narrow portion of a CF7 request in which Flamingo may run.
         *
         * @param mixed $contactForm
         * @param mixed $result
         */
        public static function beginSubmission($contactForm = null, $result = null): void
        {
            self::$capturingSubmission = true;
            self::$pendingRecord = null;
        }

        /**
         * Clears request-only references after storage.
         *
         * @param mixed $contactForm
         * @param mixed $result
         */
        public static function endSubmission($contactForm = null, $result = null): void
        {
            self::$capturingSubmission = false;
            self::$pendingRecord = null;
        }

        /**
         * Prevents Flamingo storage if authenticated encryption is unavailable.
         *
         * @param mixed $cases
         * @return array<int, string>
         */
        public static function requireEncryptionBeforeStorage($cases): array
        {
            if (! self::isReady()) {
                return [];
            }

            return is_array($cases) ? array_values($cases) : [];
        }

        /**
         * Stops CF7 from creating a Flamingo address-book copy in plaintext.
         *
         * @param mixed $args
         * @return mixed
         */
        public static function suppressPlaintextContact($args)
        {
            if (! self::$capturingSubmission || ! is_array($args)) {
                return $args;
            }

            $args['email'] = '';
            $args['name'] = '';
            $args['props'] = [];

            return $args;
        }

        /**
         * Replaces every visitor value with generic data before Flamingo writes.
         *
         * @param mixed $args
         * @return mixed
         */
        public static function encryptInboundParameters($args)
        {
            if (! self::$capturingSubmission || ! is_array($args)) {
                return $args;
            }

            $formId = self::currentFormId();
            $payload = [
                'schema' => 1,
                'form_id' => $formId,
                'sender' => [
                    'subject' => $args['subject'] ?? '',
                    'from' => $args['from'] ?? '',
                    'name' => $args['from_name'] ?? '',
                    'email' => $args['from_email'] ?? '',
                ],
                'fields' => $args['fields'] ?? [],
                'technical' => $args['meta'] ?? [],
                'spam_context' => [
                    'akismet' => $args['akismet'] ?? [],
                    'recaptcha' => $args['recaptcha'] ?? [],
                    'spam_log' => $args['spam_log'] ?? [],
                ],
                'consent' => $args['consent'] ?? [],
                'posted_data_hash' => $args['posted_data_hash'] ?? '',
            ];

            try {
                $email = is_scalar($payload['sender']['email'])
                    ? (string) $payload['sender']['email']
                    : '';

                self::$pendingRecord = [
                    'ciphertext' => self::encryptPayload($payload),
                    'email_index' => self::emailIndex($email),
                    'form_id' => $formId,
                ];
            } catch (Throwable $error) {
                self::$pendingRecord = null;
                self::logFailure('Unable to encrypt a contact submission.', $error);
            }

            return self::scrubInboundParameters($args);
        }

        /**
         * Persists only ciphertext and non-sensitive lookup metadata.
         *
         * @param mixed $result
         */
        public static function persistEncryptedRecord($result): void
        {
            if (! is_array($result) || self::$pendingRecord === null) {
                return;
            }

            $postId = isset($result['flamingo_inbound_id'])
                ? (int) $result['flamingo_inbound_id']
                : 0;

            if ($postId < 1) {
                self::$pendingRecord = null;
                return;
            }

            $payloadSaved = update_post_meta(
                $postId,
                self::PAYLOAD_META_KEY,
                self::$pendingRecord['ciphertext']
            );

            if (false === $payloadSaved) {
                wp_delete_post($postId, true);
                self::$pendingRecord = null;
                self::logFailure('Unable to persist an encrypted contact submission.');
                return;
            }

            update_post_meta($postId, self::VERSION_META_KEY, 1);
            update_post_meta(
                $postId,
                self::FORM_ID_META_KEY,
                self::$pendingRecord['form_id']
            );

            if (self::$pendingRecord['email_index'] !== '') {
                update_post_meta(
                    $postId,
                    self::EMAIL_INDEX_META_KEY,
                    self::$pendingRecord['email_index']
                );
            }

            self::$pendingRecord = null;
        }

        /**
         * Removes empty field maps added by later spam-plugin hooks.
         *
         * @param mixed $result
         */
        public static function scrubPostPluginCopies($result): void
        {
            if (! is_array($result)) {
                return;
            }

            $postId = isset($result['flamingo_inbound_id'])
                ? (int) $result['flamingo_inbound_id']
                : 0;
            $envelope = $postId > 0
                ? get_post_meta($postId, self::PAYLOAD_META_KEY, true)
                : '';

            if (! is_string($envelope) || $envelope === '') {
                return;
            }

            $meta = get_post_meta($postId, '_meta', true);
            update_post_meta($postId, '_fields', []);
            update_post_meta(
                $postId,
                '_meta',
                self::legacyOperationalMeta($meta)
            );
            update_post_meta($postId, '_hash', '');
        }

        /**
         * Encrypts a complete submission with XChaCha20-Poly1305.
         *
         * @param array<string, mixed> $payload
         */
        public static function encryptPayload(array $payload): string
        {
            if (! self::isReady()) {
                throw new RuntimeException('Contact-vault encryption is not configured.');
            }

            $plaintext = wp_json_encode(
                $payload,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );

            if (! is_string($plaintext)) {
                throw new RuntimeException('The contact payload could not be encoded.');
            }

            $nonce = random_bytes(
                SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES
            );
            $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
                $plaintext,
                self::AAD,
                $nonce,
                self::derivedKey('encryption')
            );

            return self::PREFIX . sodium_bin2base64(
                $nonce . $ciphertext,
                SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING
            );
        }

        /**
         * Decrypts and authenticates a contact payload.
         *
         * @return array<string, mixed>
         */
        public static function decryptPayload(string $envelope): array
        {
            if (! str_starts_with($envelope, self::PREFIX)) {
                throw new RuntimeException('Unknown contact-vault payload version.');
            }

            $encoded = substr($envelope, strlen(self::PREFIX));

            try {
                $packed = sodium_base642bin(
                    $encoded,
                    SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING,
                    ''
                );
            } catch (Throwable $error) {
                throw new RuntimeException(
                    'The encrypted contact payload is malformed.',
                    0,
                    $error
                );
            }

            $nonceBytes = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
            $minimumBytes = $nonceBytes
                + SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES;

            if (strlen($packed) < $minimumBytes) {
                throw new RuntimeException('The encrypted contact payload is truncated.');
            }

            $nonce = substr($packed, 0, $nonceBytes);
            $ciphertext = substr($packed, $nonceBytes);
            $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
                $ciphertext,
                self::AAD,
                $nonce,
                self::derivedKey('encryption')
            );

            if (false === $plaintext) {
                throw new RuntimeException(
                    'The contact payload failed authentication.'
                );
            }

            $payload = json_decode($plaintext, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($payload)) {
                throw new RuntimeException('The decrypted contact payload is invalid.');
            }

            return $payload;
        }

        /**
         * Returns a stable keyed index for exact privacy lookups.
         */
        public static function emailIndex(string $email): string
        {
            $normalized = strtolower(trim($email));

            if ($normalized === '' || ! self::isReady()) {
                return '';
            }

            return hash_hmac(
                'sha256',
                $normalized,
                self::derivedKey('email-index')
            );
        }

        /**
         * Reports whether both Sodium and a dedicated 256-bit key are available.
         */
        public static function isReady(): bool
        {
            return function_exists(
                'sodium_crypto_aead_xchacha20poly1305_ietf_encrypt'
            ) && self::masterKey() !== null;
        }

        /**
         * Adds the decrypted record to Flamingo's existing admin detail screen.
         */
        public static function registerAdminMessageBox(): void
        {
            $action = isset($_REQUEST['action'])
                ? sanitize_key((string) wp_unslash($_REQUEST['action']))
                : '';
            $postId = isset($_REQUEST['post'])
                ? absint($_REQUEST['post'])
                : 0;

            if (
                $action !== 'edit'
                || $postId < 1
                || ! current_user_can(
                    'flamingo_edit_inbound_message',
                    $postId
                )
            ) {
                return;
            }

            add_meta_box(
                'alanfullbeard-contact-vault',
                __('Encrypted submission', 'alanfullbeard'),
                [self::class, 'renderAdminMessageBox'],
                null,
                'normal',
                'high'
            );
        }

        /**
         * Decrypts a single message for an authorized Flamingo administrator.
         *
         * @param mixed $post
         */
        public static function renderAdminMessageBox($post): void
        {
            $postId = is_object($post) && method_exists($post, 'id')
                ? (int) $post->id()
                : 0;

            if (
                $postId < 1
                || ! current_user_can(
                    'flamingo_edit_inbound_message',
                    $postId
                )
            ) {
                echo '<p>' . esc_html__(
                    'You are not allowed to decrypt this submission.',
                    'alanfullbeard'
                ) . '</p>';
                return;
            }

            $envelope = get_post_meta(
                $postId,
                self::PAYLOAD_META_KEY,
                true
            );

            if (! is_string($envelope) || $envelope === '') {
                echo '<p>' . esc_html__(
                    'This is a legacy message that has not been encrypted yet.',
                    'alanfullbeard'
                ) . '</p>';
                return;
            }

            try {
                $payload = self::decryptPayload($envelope);
            } catch (Throwable $error) {
                echo '<p>' . esc_html__(
                    'This submission could not be decrypted. Check the vault key before making any changes.',
                    'alanfullbeard'
                ) . '</p>';
                self::logFailure('Unable to decrypt an admin contact record.', $error);
                return;
            }

            echo '<p><strong>' . esc_html__(
                'Encrypted at rest with Sodium XChaCha20-Poly1305.',
                'alanfullbeard'
            ) . '</strong></p>';

            self::renderValueTable(
                __('Sender', 'alanfullbeard'),
                isset($payload['sender']) && is_array($payload['sender'])
                    ? $payload['sender']
                    : []
            );
            self::renderValueTable(
                __('Form fields', 'alanfullbeard'),
                isset($payload['fields']) && is_array($payload['fields'])
                    ? $payload['fields']
                    : []
            );

            $privateContext = [
                'technical' => $payload['technical'] ?? [],
                'spam_context' => $payload['spam_context'] ?? [],
                'consent' => $payload['consent'] ?? [],
            ];

            echo '<details><summary>' . esc_html__(
                'Technical and spam-review data',
                'alanfullbeard'
            ) . '</summary>';
            self::renderValueTable('', $privateContext);
            echo '</details>';
        }

        /**
         * Removes the misleading plaintext "From" column.
         *
         * @param mixed $columns
         * @return mixed
         */
        public static function filterInboundColumns($columns)
        {
            if (! is_array($columns)) {
                return $columns;
            }

            if (isset($columns['subject'])) {
                $columns['subject'] = __('Message', 'alanfullbeard');
            }

            unset($columns['from']);

            return $columns;
        }

        /**
         * Enables in-memory decryption for CF7 AntiSpam's verified learning action.
         */
        public static function beginSpamLearning(): void
        {
            self::$exposeForSpamLearning = false;
            self::$decryptedRecordCache = [];

            $action = isset($_REQUEST['action'])
                ? sanitize_key((string) wp_unslash($_REQUEST['action']))
                : '';

            if (! in_array($action, ['spam', 'unspam', 'save'], true)) {
                return;
            }

            $requestedPosts = isset($_REQUEST['post'])
                ? wp_unslash($_REQUEST['post'])
                : [];
            $postIds = is_array($requestedPosts)
                ? array_map('absint', $requestedPosts)
                : [absint($requestedPosts)];
            $postIds = array_values(array_filter($postIds));

            if ($postIds === []) {
                return;
            }

            foreach ($postIds as $postId) {
                if (
                    get_post_type($postId) !== 'flamingo_inbound'
                    || ! current_user_can(
                        'flamingo_edit_inbound_message',
                        $postId
                    )
                ) {
                    return;
                }
            }

            $nonce = isset($_REQUEST['_wpnonce'])
                ? sanitize_text_field(
                    (string) wp_unslash($_REQUEST['_wpnonce'])
                )
                : '';

            if (is_array($requestedPosts)) {
                $nonceAction = 'bulk-posts';
            } elseif ($action === 'save') {
                $nonceAction = 'flamingo-update-inbound_' . $postIds[0];
            } else {
                $nonceAction = sprintf(
                    'flamingo-%s-inbound-message_%d',
                    $action,
                    $postIds[0]
                );
            }

            if (! wp_verify_nonce($nonce, $nonceAction)) {
                return;
            }

            self::$exposeForSpamLearning = true;
        }

        /**
         * Clears decrypted values before Flamingo's status-saving callback runs.
         */
        public static function endSpamLearning(): void
        {
            self::$exposeForSpamLearning = false;
            self::$decryptedRecordCache = [];
        }

        /**
         * Supplies authenticated plaintext only to the spam-learning request.
         *
         * @param mixed $check
         * @param mixed $objectId
         * @param mixed $metaKey
         * @param mixed $single
         * @param mixed $metaType
         * @return mixed
         */
        public static function exposeMetadataForSpamLearning(
            $check,
            $objectId,
            $metaKey,
            $single,
            $metaType = ''
        ) {
            $postId = (int) $objectId;
            $key = is_string($metaKey) ? $metaKey : '';
            $sensitiveKeys = [
                '_subject',
                '_from',
                '_from_name',
                '_from_email',
                '_fields',
                '_meta',
                '_akismet',
                '_recaptcha',
                '_spam_log',
                '_consent',
                '_hash',
            ];

            if (
                ! self::$exposeForSpamLearning
                || $postId < 1
                || $metaType !== 'post'
                || ! in_array($key, $sensitiveKeys, true)
                || get_post_type($postId) !== 'flamingo_inbound'
                || ! current_user_can(
                    'flamingo_edit_inbound_message',
                    $postId
                )
            ) {
                return $check;
            }

            $payload = self::decryptedRecord($postId);

            if ($payload === null) {
                return $check;
            }

            $sender = isset($payload['sender']) && is_array($payload['sender'])
                ? $payload['sender']
                : [];
            $spamContext = isset($payload['spam_context'])
                && is_array($payload['spam_context'])
                    ? $payload['spam_context']
                    : [];
            $values = [
                '_subject' => $sender['subject'] ?? '',
                '_from' => $sender['from'] ?? '',
                '_from_name' => $sender['name'] ?? '',
                '_from_email' => $sender['email'] ?? '',
                '_fields' => isset($payload['fields'])
                    && is_array($payload['fields'])
                        ? $payload['fields']
                        : [],
                '_meta' => isset($payload['technical'])
                    && is_array($payload['technical'])
                        ? $payload['technical']
                        : [],
                '_akismet' => $spamContext['akismet'] ?? [],
                '_recaptcha' => $spamContext['recaptcha'] ?? [],
                '_spam_log' => $spamContext['spam_log'] ?? [],
                '_consent' => $payload['consent'] ?? [],
                '_hash' => $payload['posted_data_hash'] ?? '',
            ];
            $value = $values[$key];

            return $single ? $value : [$value];
        }

        /**
         * Registers an exact-match privacy exporter backed by the keyed index.
         *
         * @param mixed $exporters
         * @return mixed
         */
        public static function registerPrivacyExporter($exporters)
        {
            if (! is_array($exporters)) {
                return $exporters;
            }

            $exporters['alanfullbeard-contact-vault'] = [
                'exporter_friendly_name' => __(
                    'Encrypted contact messages',
                    'alanfullbeard'
                ),
                'callback' => [self::class, 'exportPersonalData'],
            ];

            return $exporters;
        }

        /**
         * Registers deletion of complete encrypted submissions.
         *
         * @param mixed $erasers
         * @return mixed
         */
        public static function registerPrivacyEraser($erasers)
        {
            if (! is_array($erasers)) {
                return $erasers;
            }

            $erasers['alanfullbeard-contact-vault'] = [
                'eraser_friendly_name' => __(
                    'Encrypted contact messages',
                    'alanfullbeard'
                ),
                'callback' => [self::class, 'erasePersonalData'],
            ];

            return $erasers;
        }

        /**
         * Exports decrypted fields through WordPress's protected privacy workflow.
         *
         * @return array{data: array<int, array<string, mixed>>, done: bool}
         */
        public static function exportPersonalData(
            string $emailAddress,
            int $page = 1
        ): array {
            $data = [];

            foreach (self::privacyQuery($emailAddress, $page) as $postId) {
                $envelope = get_post_meta(
                    $postId,
                    self::PAYLOAD_META_KEY,
                    true
                );

                if (! is_string($envelope) || $envelope === '') {
                    continue;
                }

                try {
                    $payload = self::decryptPayload($envelope);
                } catch (Throwable $error) {
                    self::logFailure(
                        'Unable to decrypt a privacy export record.',
                        $error
                    );
                    continue;
                }

                $exportValues = [];
                self::flattenExportValues(
                    [
                        'sender' => $payload['sender'] ?? [],
                        'fields' => $payload['fields'] ?? [],
                    ],
                    '',
                    $exportValues
                );

                $data[] = [
                    'group_id' => 'alanfullbeard-contact-messages',
                    'group_label' => __(
                        'Contact messages',
                        'alanfullbeard'
                    ),
                    'item_id' => 'contact-message-' . $postId,
                    'data' => $exportValues,
                ];
            }

            return [
                'data' => $data,
                'done' => count($data) < 50,
            ];
        }

        /**
         * Deletes whole encrypted records instead of partially redacting them.
         *
         * @return array{
         *   items_removed: bool,
         *   items_retained: bool,
         *   messages: array<int, string>,
         *   done: bool
         * }
         */
        public static function erasePersonalData(
            string $emailAddress,
            int $page = 1
        ): array {
            $removed = false;
            $postIds = self::privacyQuery($emailAddress, 1);

            foreach ($postIds as $postId) {
                if (wp_delete_post($postId, true)) {
                    $removed = true;
                }
            }

            return [
                'items_removed' => $removed,
                'items_retained' => false,
                'messages' => [],
                'done' => count($postIds) < 50,
            ];
        }

        /**
         * Converts one existing Flamingo message without ever deleting first.
         *
         * The encrypted payload and privacy index are written before plaintext
         * fields are scrubbed. Callers must still create a database backup and
         * verify raw storage after a batch migration.
         */
        public static function migrateLegacyMessage(int $postId): bool
        {
            if (
                $postId < 1
                || get_post_type($postId) !== 'flamingo_inbound'
                || ! self::isReady()
            ) {
                return false;
            }

            $existingEnvelope = get_post_meta(
                $postId,
                self::PAYLOAD_META_KEY,
                true
            );

            if (is_string($existingEnvelope) && $existingEnvelope !== '') {
                try {
                    self::decryptPayload($existingEnvelope);
                    return true;
                } catch (Throwable $error) {
                    self::logFailure(
                        'An existing encrypted message failed authentication.',
                        $error
                    );
                    return false;
                }
            }

            $legacyMeta = get_post_meta($postId, '_meta', true);
            $formId = (int) get_post_meta(
                $postId,
                '_wpcf7_form_id',
                true
            );

            if (
                $formId < 1
                && is_array($legacyMeta)
                && isset($legacyMeta['form_id'])
            ) {
                $formId = (int) $legacyMeta['form_id'];
            }

            $email = (string) get_post_meta(
                $postId,
                '_from_email',
                true
            );
            $payload = [
                'schema' => 1,
                'form_id' => $formId,
                'sender' => [
                    'subject' => get_post_meta($postId, '_subject', true),
                    'from' => get_post_meta($postId, '_from', true),
                    'name' => get_post_meta($postId, '_from_name', true),
                    'email' => $email,
                ],
                'fields' => self::readLegacyFields($postId),
                'technical' => is_array($legacyMeta) ? $legacyMeta : [],
                'spam_context' => [
                    'akismet' => get_post_meta($postId, '_akismet', true),
                    'recaptcha' => get_post_meta($postId, '_recaptcha', true),
                    'spam_log' => get_post_meta($postId, '_spam_log', true),
                ],
                'consent' => get_post_meta($postId, '_consent', true),
                'posted_data_hash' => get_post_meta($postId, '_hash', true),
            ];

            try {
                $envelope = self::encryptPayload($payload);
            } catch (Throwable $error) {
                self::logFailure(
                    'Unable to encrypt a legacy contact message.',
                    $error
                );
                return false;
            }

            if (
                false === update_post_meta(
                    $postId,
                    self::PAYLOAD_META_KEY,
                    $envelope
                )
            ) {
                self::logFailure(
                    'Unable to store an encrypted legacy contact message.'
                );
                return false;
            }

            update_post_meta($postId, self::VERSION_META_KEY, 1);
            update_post_meta($postId, self::FORM_ID_META_KEY, $formId);

            $emailIndex = self::emailIndex($email);

            if ($emailIndex !== '') {
                update_post_meta(
                    $postId,
                    self::EMAIL_INDEX_META_KEY,
                    $emailIndex
                );
            }

            $postUpdate = wp_update_post([
                'ID' => $postId,
                'post_title' => __('Encrypted contact message', 'alanfullbeard'),
                'post_name' => 'encrypted-contact-message-' . $postId,
                'post_content' => '',
                'post_excerpt' => '',
            ], true);

            if (is_wp_error($postUpdate)) {
                self::logFailure(
                    'Unable to scrub a legacy contact-message post.',
                    new RuntimeException($postUpdate->get_error_message())
                );
                return false;
            }

            update_post_meta(
                $postId,
                '_subject',
                __('Encrypted contact message', 'alanfullbeard')
            );
            update_post_meta(
                $postId,
                '_from',
                __('Encrypted sender', 'alanfullbeard')
            );
            update_post_meta($postId, '_from_name', '');
            update_post_meta($postId, '_from_email', '');

            foreach (array_keys(self::readLegacyFieldMap($postId)) as $fieldKey) {
                delete_post_meta(
                    $postId,
                    sanitize_key('_field_' . $fieldKey)
                );
            }

            update_post_meta($postId, '_fields', []);
            update_post_meta(
                $postId,
                '_meta',
                self::legacyOperationalMeta($legacyMeta)
            );
            update_post_meta($postId, '_akismet', []);
            update_post_meta($postId, '_recaptcha', []);
            update_post_meta($postId, '_spam_log', []);
            update_post_meta($postId, '_consent', []);
            update_post_meta($postId, '_hash', '');
            delete_post_meta($postId, '_wp_old_slug');

            clean_post_cache($postId);

            return self::legacyMessageIsScrubbed($postId);
        }

        /**
         * Warns administrators before a misconfiguration can create plaintext.
         */
        public static function renderConfigurationNotice(): void
        {
            if (self::isReady() || ! current_user_can('manage_options')) {
                return;
            }

            echo '<div class="notice notice-error"><p>';
            echo esc_html__(
                'Encrypted Contact Vault is not storing submissions because Sodium or ALANFULLBEARD_CONTACT_VAULT_KEY is unavailable.',
                'alanfullbeard'
            );
            echo '</p></div>';
        }

        /**
         * @param array<string, mixed> $args
         * @return array<string, mixed>
         */
        private static function scrubInboundParameters(array $args): array
        {
            $args['subject'] = __('Encrypted contact message', 'alanfullbeard');
            $args['from'] = __('Encrypted sender', 'alanfullbeard');
            $args['from_name'] = '';
            $args['from_email'] = '';
            $args['fields'] = [];
            $args['meta'] = [];
            $args['akismet'] = [];
            $args['recaptcha'] = [];
            $args['spam_log'] = [];
            $args['consent'] = [];
            $args['posted_data_hash'] = '';

            return $args;
        }

        private static function currentFormId(): int
        {
            if (
                ! class_exists('WPCF7_Submission')
                || ! method_exists('WPCF7_Submission', 'get_instance')
            ) {
                return 0;
            }

            $submission = WPCF7_Submission::get_instance();

            if (
                ! is_object($submission)
                || ! method_exists($submission, 'get_contact_form')
            ) {
                return 0;
            }

            $form = $submission->get_contact_form();

            if (! is_object($form) || ! method_exists($form, 'id')) {
                return 0;
            }

            return (int) $form->id();
        }

        /**
         * @return array<string, mixed>|null
         */
        private static function decryptedRecord(int $postId): ?array
        {
            if (isset(self::$decryptedRecordCache[$postId])) {
                return self::$decryptedRecordCache[$postId];
            }

            $envelope = get_post_meta(
                $postId,
                self::PAYLOAD_META_KEY,
                true
            );

            if (! is_string($envelope) || $envelope === '') {
                return null;
            }

            try {
                self::$decryptedRecordCache[$postId] =
                    self::decryptPayload($envelope);
            } catch (Throwable $error) {
                self::logFailure(
                    'Unable to decrypt a message for spam learning.',
                    $error
                );
                return null;
            }

            return self::$decryptedRecordCache[$postId];
        }

        /**
         * @return array<string, mixed>
         */
        private static function readLegacyFields(int $postId): array
        {
            $fieldMap = self::readLegacyFieldMap($postId);
            $fields = [];

            foreach (array_keys($fieldMap) as $fieldKey) {
                $fields[$fieldKey] = get_post_meta(
                    $postId,
                    sanitize_key('_field_' . $fieldKey),
                    true
                );
            }

            return $fields;
        }

        /**
         * @return array<string, mixed>
         */
        private static function readLegacyFieldMap(int $postId): array
        {
            $fieldMap = get_post_meta($postId, '_fields', true);

            return is_array($fieldMap) ? $fieldMap : [];
        }

        /**
         * Retains only schema information needed by the spam plugin.
         *
         * @param mixed $legacyMeta
         * @return array<string, int|string>
         */
        private static function legacyOperationalMeta($legacyMeta): array
        {
            if (! is_array($legacyMeta)) {
                return [];
            }

            $operational = [];

            if (isset($legacyMeta['form_id'])) {
                $operational['form_id'] = (int) $legacyMeta['form_id'];
            }

            if (
                isset($legacyMeta['message_field'])
                && is_scalar($legacyMeta['message_field'])
            ) {
                $operational['message_field'] = (string) $legacyMeta['message_field'];
            }

            return $operational;
        }

        private static function legacyMessageIsScrubbed(int $postId): bool
        {
            $post = get_post($postId);

            if (
                ! is_object($post)
                || $post->post_title !== 'Encrypted contact message'
                || $post->post_content !== ''
                || get_post_meta($postId, '_from_email', true) !== ''
                || get_post_meta($postId, '_from_name', true) !== ''
                || get_post_meta($postId, '_hash', true) !== ''
            ) {
                return false;
            }

            foreach (array_keys(get_post_meta($postId, '', false)) as $metaKey) {
                if (str_starts_with((string) $metaKey, '_field_')) {
                    return false;
                }
            }

            return true;
        }

        private static function masterKey(): ?string
        {
            if (! defined('ALANFULLBEARD_CONTACT_VAULT_KEY')) {
                return null;
            }

            $encoded = constant('ALANFULLBEARD_CONTACT_VAULT_KEY');

            if (! is_string($encoded)) {
                return null;
            }

            $decoded = base64_decode($encoded, true);

            if (! is_string($decoded) || strlen($decoded) !== self::KEY_BYTES) {
                return null;
            }

            return $decoded;
        }

        private static function derivedKey(string $purpose): string
        {
            $masterKey = self::masterKey();

            if ($masterKey === null) {
                throw new RuntimeException('The contact-vault key is unavailable.');
            }

            return sodium_crypto_generichash(
                self::AAD . ':' . $purpose,
                $masterKey,
                self::KEY_BYTES
            );
        }

        /**
         * @return array<int, int>
         */
        private static function privacyQuery(
            string $emailAddress,
            int $page
        ): array {
            $index = self::emailIndex($emailAddress);

            if ($index === '') {
                return [];
            }

            $postIds = get_posts([
                'post_type' => 'flamingo_inbound',
                'post_status' => ['publish', 'flamingo-spam', 'trash'],
                'posts_per_page' => 50,
                'paged' => max(1, $page),
                'fields' => 'ids',
                'orderby' => 'ID',
                'order' => 'ASC',
                'no_found_rows' => true,
                'meta_key' => self::EMAIL_INDEX_META_KEY,
                'meta_value' => $index,
                'meta_compare' => '=',
            ]);

            if (! is_array($postIds)) {
                return [];
            }

            return array_map('intval', $postIds);
        }

        /**
         * @param array<string, mixed> $values
         */
        private static function renderValueTable(
            string $heading,
            array $values
        ): void {
            if ($heading !== '') {
                echo '<h3>' . esc_html($heading) . '</h3>';
            }

            if ($values === []) {
                echo '<p>' . esc_html__('No data recorded.', 'alanfullbeard') . '</p>';
                return;
            }

            echo '<table class="widefat striped"><tbody>';

            foreach ($values as $key => $value) {
                $label = ucwords(str_replace(
                    ['-', '_'],
                    ' ',
                    (string) $key
                ));

                echo '<tr><th scope="row">' . esc_html($label) . '</th><td>';

                if (is_array($value) || is_object($value)) {
                    echo '<pre>' . esc_html(
                        (string) wp_json_encode(
                            $value,
                            JSON_PRETTY_PRINT
                            | JSON_UNESCAPED_SLASHES
                            | JSON_UNESCAPED_UNICODE
                        )
                    ) . '</pre>';
                } elseif (is_bool($value)) {
                    echo esc_html(
                        $value
                            ? __('Yes', 'alanfullbeard')
                            : __('No', 'alanfullbeard')
                    );
                } elseif ($value === null || $value === '') {
                    echo '<span aria-label="' . esc_attr__(
                        'No value',
                        'alanfullbeard'
                    ) . '">&mdash;</span>';
                } else {
                    echo nl2br(esc_html((string) $value));
                }

                echo '</td></tr>';
            }

            echo '</tbody></table>';
        }

        /**
         * @param mixed $value
         * @param array<int, array{name: string, value: string}> $output
         */
        private static function flattenExportValues(
            $value,
            string $path,
            array &$output
        ): void {
            if (is_array($value)) {
                foreach ($value as $key => $child) {
                    $childPath = $path === ''
                        ? (string) $key
                        : $path . '.' . (string) $key;
                    self::flattenExportValues($child, $childPath, $output);
                }

                return;
            }

            $output[] = [
                'name' => $path === '' ? __('Value', 'alanfullbeard') : $path,
                'value' => is_scalar($value) ? (string) $value : '',
            ];
        }

        private static function logFailure(
            string $message,
            ?Throwable $error = null
        ): void {
            if ($error !== null) {
                $message .= ' ' . $error->getMessage();
            }

            error_log('[AlanFullbeard Contact Vault] ' . $message);
        }
    }
}

AlanFullbeard_Contact_Vault::boot();
