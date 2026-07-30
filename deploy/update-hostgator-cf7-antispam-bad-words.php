<?php

if (PHP_SAPI !== 'cli' || ! defined('WP_CLI')) {
    throw new RuntimeException('Run this update through WP-CLI.');
}

if (
    ! class_exists('AlanFullbeard_Contact_Vault')
    || ! AlanFullbeard_Contact_Vault::isReady()
) {
    throw new RuntimeException('The encrypted contact vault must be active.');
}

$optionName = 'cf7a_options';
$candidateFile = __DIR__ . '/cf7-antispam-bad-words.txt';
$candidateHash = '8d3eb4d4e707300e6c25bfa7e3922605033ba9457a7eafc5b3d9191044810019';
$backupRoot = '/home2/afullbeard/security-hardening-backups/cf7-antispam-bad-words-20260729';
$backupFile = $backupRoot . '/cf7a-options-before.afbvault';
$expectedCurrent = [
    'viagra',
    'Earn extra cash',
    'MEET SINGLES',
];

if (! is_readable($candidateFile)) {
    throw new RuntimeException('The bad-word candidate is unavailable.');
}

if (hash_file('sha256', $candidateFile) !== $candidateHash) {
    throw new RuntimeException('The bad-word candidate hash failed.');
}

$candidate = file(
    $candidateFile,
    FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
);

if (! is_array($candidate)) {
    throw new RuntimeException('The bad-word candidate could not be read.');
}

$candidate = array_values(array_map('trim', $candidate));
$normalized = [];

foreach ($candidate as $phrase) {
    if ($phrase === '') {
        throw new RuntimeException('The bad-word candidate contains an empty phrase.');
    }

    $key = str_replace(' ', '', strtolower($phrase));

    if (isset($normalized[$key])) {
        throw new RuntimeException(
            sprintf('Duplicate normalized bad-word phrase: %s', $phrase)
        );
    }

    $normalized[$key] = true;
}

if (count($candidate) !== 48) {
    throw new RuntimeException(
        sprintf('Expected 48 candidate phrases; found %d.', count($candidate))
    );
}

$options = get_option($optionName);

if (! is_array($options)) {
    throw new RuntimeException('The AntiSpam options are unavailable.');
}

$current = isset($options['bad_words_list'])
    && is_array($options['bad_words_list'])
        ? array_values($options['bad_words_list'])
        : [];

if ($current !== $expectedCurrent) {
    throw new RuntimeException(
        sprintf(
            'The live bad-word list changed: expected=%d actual=%d.',
            count($expectedCurrent),
            count($current)
        )
    );
}

if ((int) ($options['check_bad_words'] ?? 0) !== 1) {
    throw new RuntimeException('The AntiSpam bad-word filter is not enabled.');
}

if (file_exists($backupRoot)) {
    throw new RuntimeException('The AntiSpam backup directory already exists.');
}

$serializedOptions = serialize($options);
$optionHash = hash('sha256', $serializedOptions);
$envelope = AlanFullbeard_Contact_Vault::encryptPayload([
    'backup_type' => 'cf7-antispam-options',
    'option_name' => $optionName,
    'option_sha256' => $optionHash,
    'serialized_option' => base64_encode($serializedOptions),
]);

if (! mkdir($backupRoot, 0700) || ! chmod($backupRoot, 0700)) {
    throw new RuntimeException('Could not create the protected AntiSpam backup directory.');
}

$handle = @fopen($backupFile, 'x');

if (! is_resource($handle)) {
    throw new RuntimeException('Could not exclusively create the AntiSpam backup.');
}

$written = fwrite($handle, $envelope . "\n");
$flushed = fflush($handle);
$protected = chmod($backupFile, 0600);
$closed = fclose($handle);

if (
    $written !== strlen($envelope) + 1
    || ! $flushed
    || ! $protected
    || ! $closed
) {
    throw new RuntimeException('Could not safely write the encrypted AntiSpam backup.');
}

$storedEnvelope = trim((string) file_get_contents($backupFile));
$verified = AlanFullbeard_Contact_Vault::decryptPayload($storedEnvelope);

if (
    ($verified['backup_type'] ?? null) !== 'cf7-antispam-options'
    || ($verified['option_name'] ?? null) !== $optionName
    || ($verified['option_sha256'] ?? null) !== $optionHash
    || ($verified['serialized_option'] ?? null)
        !== base64_encode($serializedOptions)
) {
    throw new RuntimeException('The encrypted AntiSpam backup failed verification.');
}

$updatedOptions = $options;
$updatedOptions['bad_words_list'] = $candidate;

if (! update_option($optionName, $updatedOptions)) {
    throw new RuntimeException('WordPress did not update the AntiSpam options.');
}

$saved = get_option($optionName);

if (! is_array($saved) || $saved !== $updatedOptions) {
    throw new RuntimeException('The updated AntiSpam options failed verification.');
}

printf(
    "CF7 AntiSpam bad words updated: before=%d after=%d backup=%s\n",
    count($current),
    count($candidate),
    $backupFile
);
