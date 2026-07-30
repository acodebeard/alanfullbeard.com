<?php
/**
 * HostGator runtime configuration for Alan Fullbeard File Hub.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('ALANFULLBEARD_FILEHUB_ROOT')) {
    define('ALANFULLBEARD_FILEHUB_ROOT', '/home2/afullbeard/filehub_data');
}

if (! defined('ALANFULLBEARD_FILEHUB_CAPABILITY')) {
    define('ALANFULLBEARD_FILEHUB_CAPABILITY', 'manage_options');
}

if (! defined('ALANFULLBEARD_FILEHUB_MAX_UPLOAD_BYTES')) {
    define('ALANFULLBEARD_FILEHUB_MAX_UPLOAD_BYTES', 10 * 1024 * 1024);
}
