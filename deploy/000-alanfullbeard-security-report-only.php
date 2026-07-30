<?php
/**
 * Temporary rollout control for Alan Fullbeard public security headers.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

add_filter(
    'alanfullbeard_security_csp_report_only',
    '__return_true',
    PHP_INT_MIN
);
