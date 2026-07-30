<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/fixtures/');
define('ALANFULLBEARD_FILEHUB_ROOT', '/private/filehub_data');

$registeredHooks = [];

/**
 * @param callable|string|array<mixed> $callback
 */
function add_action(string $hook, callable|string|array $callback): void
{
    global $registeredHooks;
    $registeredHooks[$hook] = $callback;
}

function sanitize_file_name(string $filename): string
{
    $filename = str_replace(['\\', '/'], '-', $filename);
    $filename = preg_replace('/[^A-Za-z0-9._ -]/', '', $filename);
    return is_string($filename) ? trim($filename) : '';
}

function wp_unslash(string $value): string
{
    return stripslashes($value);
}

require dirname(__DIR__) . '/wp-content/mu-plugins/alanfullbeard-filehub.php';

$failures = 0;

function filehub_assert(bool $condition, string $message): void
{
    global $failures;

    if ($condition) {
        echo "PASS: {$message}\n";
        return;
    }

    $failures++;
    echo "FAIL: {$message}\n";
}

filehub_assert(
    '/private/filehub_data' === alanfullbeard_filehub_base_path(),
    'File Hub honors its private, externally configured storage root.'
);
filehub_assert(
    'storage' === alanfullbeard_filehub_root_key('anything-else')
    && 'favorites' === alanfullbeard_filehub_root_key('favorites'),
    'Only the two fixed private storage roots can be selected.'
);
filehub_assert(
    'report.pdf' === alanfullbeard_filehub_valid_name('report.pdf'),
    'An allowed personal-file name remains valid.'
);
filehub_assert(
    '' === alanfullbeard_filehub_valid_name('payload.php')
    && '' === alanfullbeard_filehub_valid_name('.htaccess')
    && '' === alanfullbeard_filehub_valid_name('no-extension'),
    'Executable, hidden, and extensionless upload names are rejected.'
);
filehub_assert(
    'manage_options' === alanfullbeard_filehub_capability(),
    'The default File Hub capability is administrator-only.'
);

$expectedAuthenticatedHooks = [
    'admin_post_alanfullbeard_filehub_upload',
    'admin_post_alanfullbeard_filehub_download',
    'admin_post_alanfullbeard_filehub_rename',
    'admin_post_alanfullbeard_filehub_move',
    'admin_post_alanfullbeard_filehub_delete',
];

foreach ($expectedAuthenticatedHooks as $hook) {
    filehub_assert(isset($registeredHooks[$hook]), "Authenticated handler is registered: {$hook}");
}

$loggedOutHooks = array_filter(
    array_keys($registeredHooks),
    static fn (string $hook): bool => str_starts_with($hook, 'admin_post_nopriv_')
);
filehub_assert([] === $loggedOutHooks, 'No logged-out File Hub handler is registered.');

if ($failures > 0) {
    echo "\n{$failures} File Hub test(s) failed.\n";
    exit(1);
}

echo "\nAll File Hub tests passed.\n";
