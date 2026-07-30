<?php
/**
 * Plugin Name: Alan Fullbeard Public Security Headers
 * Description: Applies a nonce-based CSP, origin isolation, clickjacking protection, and Trusted Types to public HTML pages.
 * Version: 1.0.0
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

const ALANFULLBEARD_SECURITY_HEADERS_VERSION = '1.0.0';
const ALANFULLBEARD_DOMPURIFY_VERSION = '3.4.12';

/**
 * Returns whether this request is rendering a public HTML document.
 */
function alanfullbeard_security_is_public_html_request(): bool
{
    if (is_admin()) {
        return false;
    }

    if (
        (function_exists('wp_doing_ajax') && wp_doing_ajax())
        || (defined('REST_REQUEST') && REST_REQUEST)
        || (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST)
    ) {
        return false;
    }

    if (
        (function_exists('is_feed') && is_feed())
        || (function_exists('is_robots') && is_robots())
        || (function_exists('is_trackback') && is_trackback())
    ) {
        return false;
    }

    return true;
}

/**
 * Marks the current response as one protected by this plugin.
 */
function alanfullbeard_security_activate_public_response(): void
{
    $GLOBALS['alanfullbeard_security_public_response'] = true;
}

function alanfullbeard_security_public_response_is_active(): bool
{
    return true === ($GLOBALS['alanfullbeard_security_public_response'] ?? false);
}

/**
 * Returns one random nonce for the lifetime of the request.
 */
function alanfullbeard_security_csp_nonce(): string
{
    static $nonce = null;

    if (! is_string($nonce)) {
        $nonce = base64_encode(random_bytes(18));
    }

    return $nonce;
}

/**
 * @return array<string, string>
 */
function alanfullbeard_security_csp_directives(string $nonce): array
{
    return [
        'default-src' => "'none'",
        'base-uri' => "'self'",
        'object-src' => "'none'",
        'frame-ancestors' => "'self'",
        'form-action' => "'self'",
        'script-src' => sprintf(
            "'nonce-%s' 'strict-dynamic' 'self' https://challenges.cloudflare.com",
            $nonce
        ),
        'script-src-attr' => "'none'",
        'style-src' => "'self' 'unsafe-inline'",
        'style-src-attr' => "'unsafe-inline'",
        'img-src' => "'self' data: blob: https://secure.gravatar.com",
        'font-src' => "'self' data:",
        'connect-src' => "'self' https://challenges.cloudflare.com",
        'frame-src' => 'https://challenges.cloudflare.com https://www.google.com',
        'media-src' => "'self'",
        'manifest-src' => "'self'",
        'worker-src' => "'self' blob:",
        'trusted-types' => 'default dompurify',
        'require-trusted-types-for' => "'script'",
        'upgrade-insecure-requests' => '',
        'block-all-mixed-content' => '',
    ];
}

function alanfullbeard_security_csp_value(string $nonce): string
{
    $parts = [];

    foreach (alanfullbeard_security_csp_directives($nonce) as $name => $value) {
        $parts[] = '' === $value ? $name : $name . ' ' . $value;
    }

    return implode('; ', $parts) . ';';
}

function alanfullbeard_security_csp_is_report_only(): bool
{
    $isCustomizerPreview = function_exists('is_customize_preview')
        && is_customize_preview();

    return (bool) apply_filters(
        'alanfullbeard_security_csp_report_only',
        $isCustomizerPreview
    );
}

/**
 * Sends public-document headers after WordPress has resolved the request.
 */
function alanfullbeard_security_send_public_headers(): void
{
    if (! alanfullbeard_security_is_public_html_request() || headers_sent()) {
        return;
    }

    alanfullbeard_security_activate_public_response();

    header('Cross-Origin-Opener-Policy: same-origin', true);
    header('X-Frame-Options: SAMEORIGIN', true);
    header('X-Content-Type-Options: nosniff', true);
    header('Referrer-Policy: strict-origin-when-cross-origin', true);

    $cspHeader = alanfullbeard_security_csp_is_report_only()
        ? 'Content-Security-Policy-Report-Only'
        : 'Content-Security-Policy';

    header(
        $cspHeader . ': ' . alanfullbeard_security_csp_value(
            alanfullbeard_security_csp_nonce()
        ),
        true
    );
}
add_action(
    'template_redirect',
    'alanfullbeard_security_send_public_headers',
    -1000
);

/**
 * Adds the request nonce to external and inline scripts emitted through the
 * WordPress script-tag APIs.
 *
 * @param array<string, string|bool> $attributes
 * @return array<string, string|bool>
 */
function alanfullbeard_security_add_script_nonce(array $attributes): array
{
    if (! alanfullbeard_security_public_response_is_active()) {
        return $attributes;
    }

    $attributes['nonce'] = alanfullbeard_security_csp_nonce();

    return $attributes;
}
add_filter(
    'wp_script_attributes',
    'alanfullbeard_security_add_script_nonce',
    PHP_INT_MAX
);
add_filter(
    'wp_inline_script_attributes',
    'alanfullbeard_security_add_script_nonce',
    PHP_INT_MAX
);

/**
 * Adds a nonce to script tags produced by older loader filters that bypass
 * wp_get_script_tag().
 */
function alanfullbeard_security_add_loader_tag_nonce(
    string $tag,
    string $handle,
    string $src
): string {
    unset($handle, $src);

    if (
        ! alanfullbeard_security_public_response_is_active()
        || str_contains($tag, ' nonce=')
        || ! class_exists('WP_HTML_Tag_Processor')
    ) {
        return $tag;
    }

    $processor = new WP_HTML_Tag_Processor($tag);

    if (! $processor->next_tag('script')) {
        return $tag;
    }

    $processor->set_attribute(
        'nonce',
        alanfullbeard_security_csp_nonce()
    );

    return $processor->get_updated_html();
}
add_filter(
    'script_loader_tag',
    'alanfullbeard_security_add_loader_tag_nonce',
    PHP_INT_MAX,
    3
);

/**
 * Loads the sanitizer and Trusted Types policy before other public scripts.
 */
function alanfullbeard_security_enqueue_trusted_types(): void
{
    if (! alanfullbeard_security_public_response_is_active()) {
        return;
    }

    $assetBaseUrl = content_url('/mu-plugins/alanfullbeard-security-assets/');

    wp_enqueue_script(
        'alanfullbeard-dompurify',
        $assetBaseUrl . 'purify.min.js',
        [],
        ALANFULLBEARD_DOMPURIFY_VERSION,
        false
    );

    wp_enqueue_script(
        'alanfullbeard-trusted-types-policy',
        $assetBaseUrl . 'trusted-types-policy.js',
        ['alanfullbeard-dompurify'],
        ALANFULLBEARD_SECURITY_HEADERS_VERSION,
        false
    );
}
add_action(
    'wp_enqueue_scripts',
    'alanfullbeard_security_enqueue_trusted_types',
    -1000
);
