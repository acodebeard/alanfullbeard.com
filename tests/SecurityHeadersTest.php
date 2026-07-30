<?php

declare(strict_types=1);

const ABSPATH = __DIR__ . '/';

/** @var array<string, list<callable|string>> */
$securityHeaderFilters = [];

/** @var array<string, list<array{callback: mixed, priority: int, accepted_args: int}>> */
$securityHeaderActions = [];

/** @var list<string> */
$securityHeaderEnqueuedScripts = [];

function add_action(
    string $hook,
    mixed $callback,
    int $priority = 10,
    int $acceptedArgs = 1
): void
{
    $GLOBALS['securityHeaderActions'][$hook][] = [
        'callback' => $callback,
        'priority' => $priority,
        'accepted_args' => $acceptedArgs,
    ];
}

function add_filter(
    string $tag,
    callable|string $callback,
    int $priority = 10,
    int $acceptedArgs = 1
): void {
    unset($priority, $acceptedArgs);
    $GLOBALS['securityHeaderFilters'][$tag][] = $callback;
}

function apply_filters(string $tag, mixed $value, mixed ...$arguments): mixed
{
    unset($arguments);

    foreach ($GLOBALS['securityHeaderFilters'][$tag] ?? [] as $callback) {
        $value = $callback($value);
    }

    return $value;
}

function is_admin(): bool
{
    return false;
}

function wp_doing_ajax(): bool
{
    return false;
}

function is_feed(): bool
{
    return false;
}

function is_robots(): bool
{
    return false;
}

function is_trackback(): bool
{
    return false;
}

function is_customize_preview(): bool
{
    return true === ($GLOBALS['securityHeaderCustomizerPreview'] ?? false);
}

function content_url(string $path = ''): string
{
    return 'https://example.test/wp-content' . $path;
}

function wp_enqueue_script(
    string $handle,
    string $src = '',
    array $dependencies = [],
    string|bool|null $version = false,
    array|bool $arguments = []
): void {
    $GLOBALS['securityHeaderEnqueuedScripts'][] = $handle;
    unset($src, $dependencies, $version, $arguments);
}

function security_headers_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

require dirname(__DIR__)
    . '/wp-content/mu-plugins/alanfullbeard-security-headers.php';

$nonce = alanfullbeard_security_csp_nonce();
$policy = alanfullbeard_security_csp_value($nonce);

security_headers_assert(
    1 === preg_match('/\A[A-Za-z0-9+\/]{24}\z/', $nonce),
    'The CSP nonce should be an unpredictable 144-bit base64 value.'
);
security_headers_assert(
    str_contains($policy, "script-src 'nonce-{$nonce}' 'strict-dynamic'"),
    'The CSP should use the request nonce and strict-dynamic.'
);
security_headers_assert(
    str_contains($policy, "script-src-attr 'none'"),
    'The CSP should block inline event-handler attributes.'
);
security_headers_assert(
    str_contains($policy, "frame-ancestors 'self'"),
    'The CSP should prevent cross-origin framing.'
);
security_headers_assert(
    str_contains($policy, "frame-src https://challenges.cloudflare.com https://www.google.com"),
    'The CSP should allow only the audited Turnstile and map frame origins.'
);
security_headers_assert(
    str_contains($policy, "trusted-types default dompurify"),
    'The CSP should allow only the sanitizer-backed default and DOMPurify policies.'
);
security_headers_assert(
    str_contains($policy, "require-trusted-types-for 'script'"),
    'The CSP should enforce Trusted Types for DOM XSS sinks.'
);
security_headers_assert(
    ! str_contains($policy, "'unsafe-eval'"),
    'The CSP must not permit string-to-code evaluation.'
);
security_headers_assert(
    ! str_contains($policy, "script-src 'self' 'unsafe-inline'"),
    'The CSP must not broadly allow inline scripts.'
);
security_headers_assert(
    false === alanfullbeard_security_csp_is_report_only(),
    'The production default should enforce rather than only report CSP violations.'
);

$GLOBALS['securityHeaderCustomizerPreview'] = true;
security_headers_assert(
    true === alanfullbeard_security_csp_is_report_only(),
    'Customizer previews should stay in report-only mode for editing compatibility.'
);
$GLOBALS['securityHeaderCustomizerPreview'] = false;

alanfullbeard_security_activate_public_response();
$attributes = alanfullbeard_security_add_script_nonce(['src' => '/app.js']);

security_headers_assert(
    $nonce === ($attributes['nonce'] ?? null),
    'WordPress-generated scripts should receive the response nonce.'
);
security_headers_assert(
    is_file(
        dirname(__DIR__)
        . '/wp-content/mu-plugins/alanfullbeard-security-assets/trusted-types-policy.js'
    ),
    'The Trusted Types policy asset should be present.'
);
security_headers_assert(
    is_file(
        dirname(__DIR__)
        . '/wp-content/mu-plugins/alanfullbeard-security-assets/purify.min.js'
    ),
    'The pinned DOMPurify production asset should be present.'
);

echo "All public security-header tests passed.\n";
