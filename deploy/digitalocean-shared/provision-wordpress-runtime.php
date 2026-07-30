<?php
/**
 * Provision the isolated WordPress database account and private secret file.
 *
 * Run only as root on the selected DigitalOcean target. This script is
 * intentionally idempotent so an interrupted first run can be resumed without
 * generating a second database password.
 */

declare(strict_types=1);

const EXPECTED_HOSTNAME = 'ubuntu-s-2vcpu-4gb-nyc1';
const ACCOUNT_HOME = '/home/afullbeard';
const PRIVATE_DIRECTORY = ACCOUNT_HOME . '/private';
const SECRETS_FILE = PRIVATE_DIRECTORY . '/wordpress-secrets.json';
const DATABASE_NAME = 'afullbeard_wp';
const DATABASE_USER = 'afullbeard_wp';
const DATABASE_HOST = 'localhost';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (gethostname() !== EXPECTED_HOSTNAME) {
    fwrite(STDERR, "Unexpected target hostname.\n");
    exit(70);
}

if (!function_exists('posix_geteuid') || posix_geteuid() !== 0) {
    fwrite(STDERR, "This provisioner must run as root.\n");
    exit(71);
}

if (!is_dir(ACCOUNT_HOME)) {
    fwrite(STDERR, "The isolated account home is missing.\n");
    exit(72);
}

$accountUid = fileowner(ACCOUNT_HOME);
$accountGid = filegroup(ACCOUNT_HOME);

if (!is_int($accountUid) || !is_int($accountGid)) {
    fwrite(STDERR, "Unable to resolve the isolated account owner.\n");
    exit(73);
}

if (!is_dir(PRIVATE_DIRECTORY)) {
    if (!mkdir(PRIVATE_DIRECTORY, 0700)) {
        fwrite(STDERR, "Unable to create the private configuration directory.\n");
        exit(74);
    }
}

if (!chmod(PRIVATE_DIRECTORY, 0700) || !chown(PRIVATE_DIRECTORY, $accountUid) || !chgrp(PRIVATE_DIRECTORY, $accountGid)) {
    fwrite(STDERR, "Unable to secure the private configuration directory.\n");
    exit(75);
}

$secrets = null;

if (is_file(SECRETS_FILE)) {
    if (!is_readable(SECRETS_FILE)) {
        fwrite(STDERR, "The existing WordPress secret file is not readable.\n");
        exit(76);
    }

    $loadedJson = file_get_contents(SECRETS_FILE);
    if (is_string($loadedJson)) {
        $loadedSecrets = json_decode($loadedJson, true);
        if (is_array($loadedSecrets)) {
            $secrets = $loadedSecrets;
        }
    }
}

$saltNames = [
    'AUTH_KEY',
    'SECURE_AUTH_KEY',
    'LOGGED_IN_KEY',
    'NONCE_KEY',
    'AUTH_SALT',
    'SECURE_AUTH_SALT',
    'LOGGED_IN_SALT',
    'NONCE_SALT',
    'WP_CACHE_KEY_SALT',
];

if ($secrets === null) {
    $salts = [];
    foreach ($saltNames as $saltName) {
        $salts[$saltName] = bin2hex(random_bytes(48));
    }

    $secrets = [
        'db_password' => bin2hex(random_bytes(32)),
        'salts'       => $salts,
    ];

    $contents = json_encode($secrets, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (!is_string($contents)) {
        fwrite(STDERR, "Unable to encode the private WordPress secret file.\n");
        exit(77);
    }
    $contents .= "\n";
    $temporaryFile = tempnam(PRIVATE_DIRECTORY, '.wordpress-secrets-');

    if ($temporaryFile === false) {
        fwrite(STDERR, "Unable to allocate a private temporary file.\n");
        exit(78);
    }

    $written = file_put_contents($temporaryFile, $contents, LOCK_EX);
    if ($written !== strlen($contents)) {
        fwrite(STDERR, "Unable to write the private WordPress secret file.\n");
        exit(79);
    }

    if (!chmod($temporaryFile, 0600) || !chown($temporaryFile, $accountUid) || !chgrp($temporaryFile, $accountGid)) {
        fwrite(STDERR, "Unable to secure the private WordPress secret file.\n");
        exit(80);
    }

    if (!rename($temporaryFile, SECRETS_FILE)) {
        fwrite(STDERR, "Unable to install the private WordPress secret file.\n");
        exit(81);
    }
}

if (
    !isset($secrets['db_password'], $secrets['salts'])
    || !is_string($secrets['db_password'])
    || $secrets['db_password'] === ''
    || !is_array($secrets['salts'])
) {
    fwrite(STDERR, "The WordPress secret file has an invalid structure.\n");
    exit(82);
}

foreach ($saltNames as $saltName) {
    if (!isset($secrets['salts'][$saltName]) || !is_string($secrets['salts'][$saltName]) || $secrets['salts'][$saltName] === '') {
        fwrite(STDERR, "The WordPress secret file is missing a required salt.\n");
        exit(83);
    }
}

if (!chmod(SECRETS_FILE, 0600) || !chown(SECRETS_FILE, $accountUid) || !chgrp(SECRETS_FILE, $accountGid)) {
    fwrite(STDERR, "Unable to verify the WordPress secret file permissions.\n");
    exit(84);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $database = new mysqli(DATABASE_HOST, 'root', '');
    $database->set_charset('utf8mb4');

    $escapedPassword = $database->real_escape_string($secrets['db_password']);

    $database->query(
        'CREATE DATABASE IF NOT EXISTS `' . DATABASE_NAME . '`'
        . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci'
    );
    $database->query(
        "CREATE USER IF NOT EXISTS '" . DATABASE_USER . "'@'" . DATABASE_HOST
        . "' IDENTIFIED BY '" . $escapedPassword . "'"
    );
    $database->query(
        "ALTER USER '" . DATABASE_USER . "'@'" . DATABASE_HOST
        . "' IDENTIFIED BY '" . $escapedPassword . "'"
    );
    $database->query(
        'GRANT ALL PRIVILEGES ON `' . DATABASE_NAME . "`.* TO '"
        . DATABASE_USER . "'@'" . DATABASE_HOST . "'"
    );
} catch (Throwable $exception) {
    fwrite(STDERR, "Unable to provision the isolated WordPress database namespace.\n");
    exit(85);
} finally {
    if (isset($database) && $database instanceof mysqli) {
        $database->close();
    }
}

unset($escapedPassword, $secrets);

fwrite(STDOUT, "Isolated WordPress database account and private secrets are ready.\n");
