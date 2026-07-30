<?php

declare(strict_types=1);

namespace AlanFullbeard;

use RuntimeException;

function e(null|bool|int|float|string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * @return array<string, mixed>
 */
function site_config(): array
{
    return [
        'name' => 'Alan Fullmer',
        'domain' => 'alanfullbeard.com',
        'tagline' => 'Accessibility, UX, performance, and practical web work.',
        'email' => 'alan@alanfullbeard.com',
        'resume' => [
            'label' => 'Download Resume',
            'href' => '/assets/downloads/alan-fullmer-resume.txt',
            'filename' => 'alan-fullmer-resume.txt',
        ],
        'nav' => [
            ['href' => '/portfolio', 'label' => 'Portfolio'],
            ['href' => '/plugins', 'label' => 'Plugins'],
            ['href' => '/accessible-form', 'label' => 'Form Showcase'],
            ['href' => '/contact', 'label' => 'Contact'],
        ],
    ];
}

/**
 * @return array<string, array<string, mixed>>
 */
function site_routes(): array
{
    $routes = require __DIR__ . '/content/pages.php';

    if (!is_array($routes)) {
        throw new RuntimeException('Route config must return an array.');
    }

    return $routes;
}

function normalize_path(string $uri): string
{
    $path = parse_url($uri, PHP_URL_PATH);

    if (!is_string($path) || $path === '') {
        return '/';
    }

    $path = rawurldecode($path);
    $path = '/' . trim($path, '/');

    return $path === '/' ? '/' : rtrim($path, '/');
}

/**
 * @param array<string, array<string, mixed>>|null $routes
 * @return array<string, mixed>
 */
function resolve_route(string $uri, ?array $routes = null): array
{
    $routes ??= site_routes();
    $path = normalize_path($uri);

    if (array_key_exists($path, $routes)) {
        $page = $routes[$path];
        $page['path'] = $path;
        $page['status'] = 200;

        return $page;
    }

    return [
        'path' => $path,
        'status' => 404,
        'slug' => 'not-found',
        'title' => 'Page Not Found | Alan Fullmer',
        'description' => 'The requested page could not be found.',
        'template' => 'not-found.php',
    ];
}

/**
 * @param array<string, mixed> $data
 */
function render_view(string $file, array $data = []): string
{
    if (!is_file($file)) {
        throw new RuntimeException("Missing view: {$file}");
    }

    ob_start();
    extract($data, EXTR_SKIP);
    require $file;

    return (string) ob_get_clean();
}

/**
 * @param array<string, mixed> $page
 */
function render_page(array $page, ?string $root = null): string
{
    $root ??= dirname(__DIR__);
    $site = site_config();
    $template = $root . '/app/views/pages/' . (string) $page['template'];
    $content = render_view($template, [
        'page' => $page,
        'site' => $site,
        'root' => $root,
    ]);

    return render_view($root . '/app/views/layout.php', [
        'content' => $content,
        'page' => $page,
        'site' => $site,
        'root' => $root,
    ]);
}

function render_path(string $uri, ?string $root = null): string
{
    return render_page(resolve_route($uri), $root);
}

/**
 * @return list<string>
 */
function security_headers(): array
{
    return [
        'X-Frame-Options: DENY',
        'X-Content-Type-Options: nosniff',
        'Referrer-Policy: strict-origin-when-cross-origin',
        'Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()',
        "Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; img-src 'self' data:; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; object-src 'none'",
    ];
}

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header_remove('X-Powered-By');

    foreach (security_headers() as $header) {
        header($header);
    }
}
