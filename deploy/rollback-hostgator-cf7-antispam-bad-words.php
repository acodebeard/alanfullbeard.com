<?php

if (PHP_SAPI !== 'cli' || ! defined('WP_CLI')) {
    throw new RuntimeException('Run this rollback through WP-CLI.');
}

if (
    ! class_exists('AlanFullbeard_Contact_Vault')
    || ! AlanFullbeard_Contact_Vault::isReady()
) {
    throw new RuntimeException('The encrypted contact vault must be active.');
}

$optionName = 'cf7a_options';
$candidateFile = __DIR__ . '/cf7-antispam-bad-words.txt';
$backupFile = '/home2/afullbeard/security-hardening-backups/cf7-antispam-bad-words-20260729/cf7a-options-before.afbvault';

$candidate = file(
    $candidateFile,
    FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
);
$current = get_option($optionName);

if (
    ! is_array($candidate)
    || ! is_array($current)
    || ! isset($current['bad_words_list'])
    || ! is_array($current['bad_words_list'])
    || array_values($current['bad_words_list']) !== array_values($candidate)
) {
    throw new RuntimeException('The current AntiSpam list is not the reviewed candidate.');
}

if (! is_readable($backupFile)) {
    throw new RuntimeException('The encrypted AntiSpam backup is unavailable.');
}

$envelope = trim((string) file_get_contents($backupFile));
$payload = AlanFullbeard_Contact_Vault::decryptPayload($envelope);

if (
    ($payload['backup_type'] ?? null) !== 'cf7-antispam-options'
    || ($payload['option_name'] ?? null) !== $optionName
    || ! isset($payload['serialized_option'])
    || ! is_string($payload['serialized_option'])
    || ! isset($payload['option_sha256'])
    || ! is_string($payload['option_sha256'])
) {
    throw new RuntimeException('The encrypted AntiSpam backup payload is invalid.');
}

$serialized = base64_decode($payload['serialized_option'], true);

if (
    ! is_string($serialized)
    || hash('sha256', $serialized) !== $payload['option_sha256']
) {
    throw new RuntimeException('The AntiSpam backup failed its plaintext hash check.');
}

$restored = unserialize($serialized, ['allowed_classes' => false]);

if (! is_array($restored)) {
    throw new RuntimeException('The AntiSpam backup does not contain valid options.');
}

if (! update_option($optionName, $restored)) {
    throw new RuntimeException('WordPress did not restore the AntiSpam options.');
}

$saved = get_option($optionName);

if (! is_array($saved) || $saved !== $restored) {
    throw new RuntimeException('The restored AntiSpam options failed verification.');
}

printf(
    "CF7 AntiSpam bad-word rollback completed: restored=%d\n",
    count(
        isset($restored['bad_words_list'])
        && is_array($restored['bad_words_list'])
            ? $restored['bad_words_list']
            : []
    )
);
