<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = 0;

function check(string $name, callable $test): void
{
    global $failures;

    try {
        $test();
        echo "PASS: {$name}\n";
    } catch (Throwable $error) {
        $failures++;
        echo "FAIL: {$name}\n";
        echo "  {$error->getMessage()}\n";
    }
}

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assert_contains(string $needle, string $haystack, string $message): void
{
    assert_true(str_contains($haystack, $needle), $message);
}

function load_app(string $root): void
{
    $bootstrap = $root . '/app/bootstrap.php';

    assert_true(is_file($bootstrap), 'Missing app/bootstrap.php');
    require_once $bootstrap;
}

check('required project files exist', function () use ($root): void {
    $files = [
        'app/Site.php',
        'app/bootstrap.php',
        'app/content/pages.php',
        'app/views/layout.php',
        'app/views/partials/header.php',
        'app/views/partials/footer.php',
        'app/views/pages/home.php',
        'app/views/pages/accessible-form.php',
        'public/index.php',
        'public/.htaccess',
        'public/assets/css/site.css',
        'public/assets/css/utilities.css',
        'public/assets/css/forms.css',
        'public/assets/js/site.js',
        'public/assets/images/.gitkeep',
        'public/assets/images/README.md',
        'public/assets/images/icons/.gitkeep',
    ];

    foreach ($files as $file) {
        assert_true(is_file($root . '/' . $file), "Missing {$file}");
    }
});

check('source-only media ignore policy is present', function () use ($root): void {
    $gitignore = file_get_contents($root . '/.gitignore');
    assert_true(is_string($gitignore), 'Could not read .gitignore');
    assert_contains('public/assets/images/*', $gitignore, 'Image files should be ignored.');
    assert_contains('public/assets/images/icons/*', $gitignore, 'Icon files should be ignored.');
    assert_contains('public/assets/css/*.min.css', $gitignore, 'Generated minified CSS should be ignored.');
    assert_contains('!public/assets/images/.gitkeep', $gitignore, 'Images directory placeholder should be trackable.');
});

check('utility-only stylesheet was extracted', function () use ($root): void {
    $css = file_get_contents($root . '/public/assets/css/utilities.css');
    assert_true(is_string($css), 'Could not read utilities.css');
    assert_contains('@layer reset, utilities;', $css, 'Cascade layers should define reset and utilities.');
    assert_contains('--global-gap-small: var(--gap-small, 8px);', $css, 'Gap token should have a portable fallback.');
    assert_contains('@media (prefers-reduced-motion: reduce)', $css, 'Reduced motion reset should be present.');
    assert_contains('img,', $css, 'Media reset should include images.');
    assert_contains('button,', $css, 'Form control reset should include buttons.');
    assert_contains('table {', $css, 'Table reset should be present.');
    assert_contains('.container', $css, 'Container utility should be present.');
    assert_contains('.flow', $css, 'Flow utility should be present.');
    assert_contains('.stack', $css, 'Stack utility should be present.');
    assert_contains('.cluster', $css, 'Cluster utility should be present.');
    assert_contains('.grid-auto-fit', $css, 'Auto-fit grid utility should be present.');
    assert_contains('.aspect-16-9', $css, 'Aspect-ratio utility should be present.');
    assert_contains('.object-cover', $css, 'Object-fit utility should be present.');
    assert_contains('.min-width-0', $css, 'Flex overflow utility should be present.');
    assert_contains('.visually-hidden-focusable', $css, 'Focusable visually-hidden helper should be present.');
    assert_contains('.focus-ring', $css, 'Reusable focus ring utility should be present.');
    assert_contains('.skip-link', $css, 'Skip link utility should be present.');
    assert_true(substr_count($css, '.flex {') === 1, 'Flex utility should only be defined once.');
    assert_true(substr_count($css, '.flex-center {') === 1, 'Flex center utility should only be defined once.');
});

check('semgrep workflow uses supported scanner invocation', function () use ($root): void {
    $workflow = file_get_contents($root . '/.github/workflows/semgrep.yml');
    assert_true(is_string($workflow), 'Could not read Semgrep workflow.');
    assert_contains('semgrep scan --config p/php --sarif --output semgrep.sarif', $workflow, 'Semgrep should run through its supported console command.');
    assert_true(!str_contains($workflow, 'python3 -m semgrep'), 'Semgrep workflow should not use deprecated python module invocation.');
});

check('routes resolve expected pages and 404s', function () use ($root): void {
    load_app($root);

    $routes = AlanFullbeard\site_routes();
    $home = AlanFullbeard\resolve_route('/', $routes);
    $portfolio = AlanFullbeard\resolve_route('/portfolio', $routes);
    $missing = AlanFullbeard\resolve_route('/missing-page', $routes);

    assert_true($home['status'] === 200, 'Home route should resolve with 200.');
    assert_true($portfolio['status'] === 200, 'Portfolio route should resolve with 200.');
    assert_true($missing['status'] === 404, 'Missing route should resolve with 404.');
});

check('homepage renders resume header and starter sections', function () use ($root): void {
    load_app($root);

    $html = AlanFullbeard\render_path('/', $root);

    assert_contains('Download Resume', $html, 'Header should include resume download button.');
    assert_contains('Brief Bio', $html, 'Homepage should include brief bio section.');
    assert_contains('Portfolio', $html, 'Homepage should include portfolio section.');
    assert_contains('Plugins', $html, 'Homepage should include plugins section.');
    assert_contains('Contact', $html, 'Homepage should include contact section.');
});

check('homepage renders animated years odometer', function () use ($root): void {
    load_app($root);

    $html = AlanFullbeard\render_path('/', $root);
    $css = file_get_contents($root . '/public/assets/css/site.css');
    $js = file_get_contents($root . '/public/assets/js/site.js');

    assert_true(is_string($css), 'Could not read site.css');
    assert_true(is_string($js), 'Could not read site.js');
    assert_contains('class="odometer"', $html, 'Homepage should render the years odometer.');
    assert_contains('<span>1</span>', $html, 'Odometer should start at 1.');
    assert_contains('<span>14</span>', $html, 'Odometer should include the final 14 value.');
    assert_contains('id="yearsSr"', $html, 'Odometer should include static screen-reader text.');
    assert_contains('14 years experience in web development and design.', $html, 'Screen-reader text should provide the static equivalent.');
    assert_contains('@keyframes odometer', $css, 'Odometer animation keyframes should be present.');
    assert_contains('.odometer--final > div', $css, 'Odometer final state should be present.');
    assert_contains('@media (prefers-reduced-motion: reduce)', $css, 'Odometer should respect reduced motion.');
    assert_contains("const KEY = 'odometer_years_v1';", $js, 'Odometer should keep the old one-time localStorage key.');
    assert_contains("localStorage.getItem(KEY)", $js, 'Odometer should skip animation after it has run once.');
    assert_contains("localStorage.setItem(KEY, '1')", $js, 'Odometer should remember that the animation completed.');
});

check('accessible form showcase renders imported accessibility markers', function () use ($root): void {
    load_app($root);

    $html = AlanFullbeard\render_path('/accessible-form', $root);

    assert_contains('id="theGreatSampleForm"', $html, 'Imported form section should render.');
    assert_contains('aria-live="polite"', $html, 'Form should preserve live-region status messaging.');
    assert_contains('This is a demo form. Nothing is sent to a server.', $html, 'Demo disclaimer should render.');
    assert_contains('/assets/images/google-sign-in.webp', $html, 'Google sign-in asset path should be updated.');
    assert_contains('/assets/images/pineapple.webp', $html, 'Pineapple asset path should be updated.');
});

check('security headers are defined for runtime emission', function () use ($root): void {
    load_app($root);

    $headers = AlanFullbeard\security_headers();
    $joined = implode("\n", $headers);

    assert_contains('X-Frame-Options: DENY', $joined, 'Frame blocking header should be present.');
    assert_contains('X-Content-Type-Options: nosniff', $joined, 'Nosniff header should be present.');
    assert_contains('Referrer-Policy: strict-origin-when-cross-origin', $joined, 'Referrer policy should be present.');
    assert_contains('Content-Security-Policy:', $joined, 'CSP should be present.');
});

if ($failures > 0) {
    echo "\n{$failures} test(s) failed.\n";
    exit(1);
}

echo "\nAll tests passed.\n";
