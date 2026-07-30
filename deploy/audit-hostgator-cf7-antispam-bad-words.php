<?php

if (PHP_SAPI !== 'cli' || ! defined('WP_CLI')) {
    throw new RuntimeException('Run this audit through WP-CLI.');
}

$optionName = 'cf7a_options';
$candidateFile = __DIR__ . '/cf7-antispam-bad-words.txt';
$candidateHash = '8d3eb4d4e707300e6c25bfa7e3922605033ba9457a7eafc5b3d9191044810019';
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

printf(
    "CF7 AntiSpam bad-word preflight passed: current=%d candidate=%d enabled=1\n",
    count($current),
    count($candidate)
);
