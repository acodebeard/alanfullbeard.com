<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = 0;

function wp_setup_check(string $name, callable $test): void
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

function wp_setup_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function wp_setup_file_contains(string $path, string $needle, string $message): void
{
    $content = file_get_contents($path);
    wp_setup_assert(is_string($content), "Could not read {$path}");
    wp_setup_assert(str_contains($content, $needle), $message);
}

wp_setup_check('WordPress source policy tracks wp-content but not core', function () use ($root): void {
    wp_setup_file_contains($root . '/.gitignore', '/wp-admin/', 'WordPress admin core should be ignored.');
    wp_setup_file_contains($root . '/.gitignore', '/wp-includes/', 'WordPress includes core should be ignored.');
    wp_setup_file_contains($root . '/.gitignore', '/index.php', 'Root WordPress index.php should be ignored.');
    wp_setup_file_contains($root . '/.gitignore', '/wp-*.php', 'Root WordPress PHP entry points should be ignored.');
    wp_setup_file_contains($root . '/.gitignore', '/xmlrpc.php', 'Root xmlrpc.php should be ignored.');
    wp_setup_file_contains($root . '/.gitignore', '/license.txt', 'WordPress license.txt should be ignored.');
    wp_setup_file_contains($root . '/.gitignore', '/readme.html', 'WordPress readme.html should be ignored.');
    wp_setup_assert(! str_contains((string) file_get_contents($root . '/.gitignore'), "\nwp-content/\n"), 'wp-content itself should remain trackable.');
});

wp_setup_check('local WordPress config and generated content are excluded from source control', function () use ($root): void {
    wp_setup_file_contains($root . '/.gitignore', 'wp-config.php', 'Local wp-config.php should be ignored.');
    wp_setup_file_contains($root . '/.gitignore', 'wp-content/uploads/', 'Uploaded media should be ignored.');
    wp_setup_file_contains($root . '/.gitignore', 'wp-content/cache/', 'Runtime cache files should be ignored.');
    wp_setup_file_contains($root . '/.gitignore', 'wp-content/upgrade/', 'WordPress upgrade scratch files should be ignored.');
});

wp_setup_check('New Tab Dammit improves admin new-tab controls without forcing frontend menus', function () use ($root): void {
    $plugin = $root . '/wp-content/mu-plugins/new-tab-dammit.php';

    wp_setup_assert(is_file($plugin), 'Missing New Tab Dammit mu-plugin.');

    $source = file_get_contents($plugin);
    wp_setup_assert(is_string($source), 'Could not read New Tab Dammit mu-plugin.');

    $projectNamespace = strtolower(basename($root));

    wp_setup_assert(str_contains($source, 'Plugin Name: New Tab Dammit'), 'Mu-plugin should use the requested plugin name.');
    wp_setup_assert(! str_contains(strtolower($source), $projectNamespace), 'Mu-plugin source should not reference the local project namespace.');
    wp_setup_assert(str_contains($source, 'Author: @acodebeard'), 'Mu-plugin author should use the reusable @acodebeard handle.');
    wp_setup_assert(str_contains($source, "function_exists('new_tab_dammit')"), 'Mu-plugin function guard should use the requested function name.');
    wp_setup_assert(str_contains($source, 'function new_tab_dammit(WP_Admin_Bar $wp_admin_bar): void'), 'Mu-plugin callback should use the requested function name.');
    wp_setup_assert(str_contains($source, "add_action('admin_bar_menu', 'new_tab_dammit', 100);"), 'Mu-plugin should update the admin bar after WordPress adds its default nodes.');
    wp_setup_assert(str_contains($source, 'is_admin()'), 'Mu-plugin should only alter admin-screen admin-bar links.');
    wp_setup_assert(str_contains($source, "'site-name'"), 'Mu-plugin should update the top-level site-name admin-bar link.');
    wp_setup_assert(str_contains($source, "'view-site'"), 'Mu-plugin should update the Visit Site admin-bar submenu link.');
    wp_setup_assert(str_contains($source, "'target' => '_blank'"), 'Mu-plugin should open the links in a new tab.');
    wp_setup_assert(str_contains($source, "'rel' => 'noopener noreferrer'"), 'Mu-plugin should protect new-tab links with noopener noreferrer.');
    wp_setup_assert(str_contains($source, '$wp_admin_bar->get_node($node_id)'), 'Mu-plugin should preserve existing node data before updating metadata.');
    wp_setup_assert(str_contains($source, '$wp_admin_bar->add_node('), 'Mu-plugin should update the existing admin-bar nodes through WordPress APIs.');
    wp_setup_assert(str_contains($source, 'function new_tab_dammit_enable_menu_link_target('), 'Mu-plugin should expose the classic Menus Link Target control by default.');
    wp_setup_assert(str_contains($source, "'hidden_columns'"), 'Mu-plugin should use the WordPress hidden-columns API.');
    wp_setup_assert(str_contains($source, "'nav-menus'"), 'Mu-plugin should limit the Link Target default to the classic Menus screen.');
    wp_setup_assert(str_contains($source, "array_diff(\$hidden, ['link-target'])"), 'Mu-plugin should reveal the Link Target control.');
    wp_setup_assert(str_contains($source, "'_new_tab_dammit_link_target_defaulted'"), 'Mu-plugin should apply the Link Target default only once per user.');
    wp_setup_assert(str_contains($source, "'managenav-menuscolumnshidden'"), 'Mu-plugin should persist the initial Screen Options state through WordPress user metadata.');
});

wp_setup_check('default bundled themes are removed', function () use ($root): void {
    $themeRoot = $root . '/wp-content/themes';
    wp_setup_assert(is_dir($themeRoot), 'Missing wp-content/themes.');

    $defaultThemes = glob($themeRoot . '/twenty*', GLOB_ONLYDIR);
    wp_setup_assert(is_array($defaultThemes), 'Could not scan theme directory.');
    wp_setup_assert($defaultThemes === [], 'Default Twenty* themes should be removed.');
});

wp_setup_check('default bundled plugins are removed', function () use ($root): void {
    wp_setup_assert(! is_file($root . '/wp-content/plugins/hello.php'), 'Hello Dolly should be removed.');
    wp_setup_assert(! is_dir($root . '/wp-content/plugins/akismet'), 'Akismet should be removed.');
});

wp_setup_check('Alan Fullbeard custom theme skeleton is present', function () use ($root): void {
    $theme = $root . '/wp-content/themes/alanfullbeard';

    foreach ([
        'style.css',
        'style.min.css',
        'functions.php',
        'index.php',
        'header.php',
        'footer.php',
        'front-page.php',
        'inc/contact-form.php',
        'page-contact.php',
        'page-web-portfolio.php',
        'page.php',
        'template-parts/contact-form.php',
    ] as $file) {
        wp_setup_assert(is_file($theme . '/' . $file), "Missing theme file {$file}");
    }

    wp_setup_file_contains($theme . '/style.css', 'Theme Name: Alan Fullbeard LCARS', 'Theme metadata should name the custom theme.');
    wp_setup_file_contains($theme . '/style.css', '--font-interface: "Antonio"', 'Theme CSS should use Antonio as the interface font.');
    wp_setup_file_contains($theme . '/functions.php', 'alanfullbeard_lcars_setup', 'Theme setup function should be registered.');
    wp_setup_file_contains($theme . '/functions.php', 'alanfullbeard-lcars-style', 'Theme stylesheet should be enqueued.');
});

wp_setup_check('theme keeps the current LCARS MVP visual system', function () use ($root): void {
    $theme = $root . '/wp-content/themes/alanfullbeard';
    $styles = (string) file_get_contents($theme . '/style.css');

    wp_setup_file_contains($theme . '/header.php', 'site-header__content', 'Header should keep the current brand and navigation frame.');
    wp_setup_file_contains($theme . '/front-page.php', 'lcars-gridline', 'Front page should keep the LCARS divider below the hero.');
    wp_setup_file_contains($theme . '/footer.php', 'lcars-footerline', 'Footer should keep the LCARS closing rail.');

    wp_setup_file_contains($theme . '/style.css', 'body::before', 'Theme should add a site-wide LCARS background grid layer.');
    wp_setup_file_contains($theme . '/style.css', '.home-hero::before', 'Home hero should receive LCARS panel rails.');
    wp_setup_file_contains($theme . '/style.css', '.home-hero::after', 'Home hero should carve an independent inner elbow.');
    wp_setup_file_contains($theme . '/style.css', '--hero-outer-radius', 'Hero elbow should expose an outer-radius control.');
    wp_setup_file_contains($theme . '/style.css', '--hero-inner-radius', 'Hero elbow should expose an inner-radius control.');
    wp_setup_file_contains($theme . '/style.css', 'repeating-linear-gradient', 'LCARS theme should use a structured background grid.');
    wp_setup_file_contains($theme . '/style.css', '--lcars-panel-radius', 'LCARS panel geometry should be centralized in CSS variables.');
    wp_setup_file_contains($theme . '/style.css', '--lcars-grid-size: 80px;', 'LCARS grid should use one shared square-grid size.');
    wp_setup_file_contains($theme . '/style.css', 'background-size: var(--lcars-grid-size) var(--lcars-grid-size);', 'Body grid should render equal-size squares.');
    wp_setup_assert(! str_contains($styles, 'rgba(255, 159, 69, 0.16) 0 18px'), 'Body background should not draw a viewport-edge orange strip.');
    wp_setup_assert(! preg_match('/body::before\s*\{[^}]*border-inline/s', $styles), 'Body background pseudo-element should not draw content-edge borders.');
});

wp_setup_check('theme serves source CSS locally and minified CSS in production', function () use ($root): void {
    $theme = $root . '/wp-content/themes/alanfullbeard';
    $functions = (string) file_get_contents($theme . '/functions.php');

    wp_setup_assert(is_file($theme . '/style.min.css'), 'Production stylesheet should exist.');
    wp_setup_assert(str_contains($functions, "return 'style.css';"), 'Local environments should use source CSS.');
    wp_setup_assert(str_contains($functions, "return 'style.min.css';"), 'Production should use minified CSS.');
    wp_setup_assert(str_contains($functions, "'localhost', '127.0.0.1', '::1'"), 'Localhost detection should include standard loopback hosts.');
});

wp_setup_check('theme removes WordPress emoji assets from the front end', function () use ($root): void {
    $functions = $root . '/wp-content/themes/alanfullbeard/functions.php';

    wp_setup_file_contains($functions, "remove_action('wp_head', 'print_emoji_detection_script', 7);", 'Theme should remove the front-end emoji detection script.');
    wp_setup_file_contains($functions, "remove_action('wp_enqueue_scripts', 'wp_enqueue_emoji_styles');", 'Theme should remove the front-end emoji styles.');
    wp_setup_file_contains($functions, "remove_action('embed_head', 'print_emoji_detection_script');", 'Theme should remove the emoji detection script from embeds.');
    wp_setup_file_contains($functions, "remove_action('enqueue_embed_scripts', 'wp_enqueue_emoji_styles');", 'Theme should remove emoji styles from embeds.');
    wp_setup_file_contains($functions, "add_action('init', 'alanfullbeard_lcars_disable_frontend_emoji_assets', 0);", 'Theme should disable emoji assets before front-end rendering begins.');
});

wp_setup_check('theme loads local Jost and Antonio fonts with head preloads', function () use ($root): void {
    $theme = $root . '/wp-content/themes/alanfullbeard';
    $styles = (string) file_get_contents($theme . '/style.css');
    $functions = (string) file_get_contents($theme . '/functions.php');

    wp_setup_assert(is_file($theme . '/assets/fonts/jost-400.woff2'), 'Missing Jost regular font file.');
    wp_setup_assert(is_file($theme . '/assets/fonts/jost-700.woff2'), 'Missing Jost bold font file.');
    wp_setup_assert(is_file($theme . '/assets/fonts/antonio-400.woff2'), 'Missing Antonio regular font file.');
    wp_setup_assert(is_file($theme . '/assets/fonts/antonio-700.woff2'), 'Missing Antonio bold font file.');
    wp_setup_file_contains($theme . '/style.css', 'font-family: "Jost";', 'Theme CSS should define local Jost font faces.');
    wp_setup_file_contains($theme . '/style.css', 'assets/fonts/jost-400.woff2', 'Theme CSS should load the regular Jost font file.');
    wp_setup_file_contains($theme . '/style.css', 'assets/fonts/jost-700.woff2', 'Theme CSS should load the bold Jost font file.');
    wp_setup_file_contains($theme . '/style.css', '--font-body: "Jost", system-ui, sans-serif;', 'Body font stack should prefer local Jost.');
    wp_setup_assert(! str_contains($styles, "--font-body: 'Lexend'"), 'Body font stack should not use the old Lexend setting.');
    wp_setup_assert(! str_contains($styles, '--font-body: "Jost", Arial'), 'Body font stack should not fall back to Arial.');

    wp_setup_file_contains($theme . '/functions.php', 'alanfullbeard_lcars_preload_fonts', 'Theme should expose a dedicated font preload helper.');
    wp_setup_file_contains($theme . '/functions.php', "add_action('wp_head', 'alanfullbeard_lcars_preload_fonts', 1);", 'Font preloads should be printed early in wp_head.');
    wp_setup_file_contains($theme . '/functions.php', 'jost-400.woff2', 'Theme head preloads should include Jost regular.');
    wp_setup_file_contains($theme . '/functions.php', 'jost-700.woff2', 'Theme head preloads should include Jost bold.');
    wp_setup_file_contains($theme . '/functions.php', 'antonio-400.woff2', 'Theme head preloads should include Antonio regular.');
    wp_setup_file_contains($theme . '/functions.php', 'antonio-700.woff2', 'Theme head preloads should include Antonio bold.');
    wp_setup_file_contains($theme . '/functions.php', 'as="font"', 'Local font preloads should use the font destination.');
    wp_setup_file_contains($theme . '/functions.php', 'type="font/woff2"', 'Local font preloads should declare the WOFF2 MIME type.');
    wp_setup_file_contains($theme . '/functions.php', 'crossorigin', 'Font preload links should include crossorigin.');
    wp_setup_assert(! str_contains($functions, 'fonts.googleapis.com'), 'Theme should not load Google Fonts when Antonio is local.');
    wp_setup_assert(! str_contains($functions, 'fonts.gstatic.com'), 'Theme should not preconnect to Google font assets when Antonio is local.');
    wp_setup_assert(! str_contains($functions, 'alanfullbeard-lcars-fonts'), 'Theme should not enqueue a remote font stylesheet handle.');
});

wp_setup_check('primary navigation keeps semantic list markup', function () use ($root): void {
    $theme = $root . '/wp-content/themes/alanfullbeard';
    $stickyHeaderScript = $theme . '/assets/js/sticky-header.js';
    $style = (string) file_get_contents($theme . '/style.css');

    wp_setup_assert(is_file($stickyHeaderScript), 'Header should include the sticky-state script.');
    wp_setup_file_contains($theme . '/header.php', "class=\"site-nav\"", 'Header should include the primary navigation wrapper.');
    wp_setup_file_contains($theme . '/header.php', 'class="site-nav__toggle"', 'Header should include a dedicated mobile navigation control.');
    wp_setup_file_contains($theme . '/header.php', 'aria-controls="primary-navigation"', 'Mobile navigation control should identify the controlled region.');
    wp_setup_file_contains($theme . '/header.php', 'id="primary-navigation"', 'Primary navigation should expose the controlled region ID.');
    wp_setup_file_contains($theme . '/functions.php', 'alanfullbeard-lcars-sticky-header', 'Theme should enqueue the sticky-header script.');
    wp_setup_file_contains($theme . '/header.php', "'container' => false", 'Primary navigation should not add an extra WordPress menu container.');
    wp_setup_file_contains($theme . '/header.php', "'depth' => 2", 'Primary navigation should allow one dropdown level.');
    wp_setup_file_contains($theme . '/header.php', "'link_before' => '<span>'", 'Primary navigation should open a span around each link label.');
    wp_setup_file_contains($theme . '/header.php', "'link_after' => '</span>'", 'Primary navigation should close the span around each link label.');
    wp_setup_assert(! str_contains((string) file_get_contents($theme . '/functions.php'), 'nav_menu_link_attributes'), 'Primary navigation should respect each menu item target instead of forcing frontend link attributes.');
    wp_setup_assert(! str_contains((string) file_get_contents($theme . '/header.php'), "'items_wrap' => '%3\$s'"), 'Primary navigation should not strip WordPress list markup.');
    wp_setup_file_contains($theme . '/header.php', 'class="site-header-sentinel"', 'Header should include a dedicated sticky-state sentinel.');
    wp_setup_file_contains($stickyHeaderScript, "classList.toggle('site-header--compact'", 'Sticky-header script should toggle the compact state after scrolling.');
    wp_setup_file_contains($stickyHeaderScript, 'new IntersectionObserver', 'Sticky-header script should observe the sentinel.');
    wp_setup_file_contains($stickyHeaderScript, 'compactObserver.observe(sentinel)', 'Sticky-header observer should use the dedicated sentinel.');
    wp_setup_assert(! str_contains((string) file_get_contents($stickyHeaderScript), "addEventListener('scroll'"), 'Sticky-header state should not use a scroll listener.');
    wp_setup_assert(! str_contains((string) file_get_contents($stickyHeaderScript), 'getBoundingClientRect'), 'Sticky-header state should not measure its changing height.');
    wp_setup_assert(! str_contains((string) file_get_contents($stickyHeaderScript), 'requestAnimationFrame'), 'Sticky-header state should not schedule per-scroll animation frames.');
    wp_setup_file_contains($stickyHeaderScript, "window.matchMedia('(max-width: 800px)')", 'Header script should limit the disclosure menu to phone layouts.');
    wp_setup_file_contains($stickyHeaderScript, "navToggle.setAttribute('aria-expanded', String(isOpen))", 'Mobile navigation control should expose its open state.');
    wp_setup_file_contains($stickyHeaderScript, "event.key === 'Escape'", 'Mobile navigation should close with Escape.');
    wp_setup_file_contains($theme . '/style.css', '.site-header-sentinel', 'CSS should position the sticky-state sentinel.');
    wp_setup_file_contains($theme . '/style.css', 'top: calc(var(--header-expanded-height) + var(--header-compact-offset));', 'CSS should own the sticky-header threshold.');
    wp_setup_file_contains($theme . '/style.css', 'grid-template-rows: var(--header-expanded-height) 1fr auto;', 'Header grid row should remain stable while the visible header compacts.');
    wp_setup_file_contains($theme . '/style.css', 'position: sticky;', 'Site header should remain visible while scrolling.');
    wp_setup_assert(
        1 === preg_match('/\\.site-header--compact\\.site-header\\s*\\{\\s*--header-height:\\s*44px;\\s*\\}/', $style),
        'Compact header should use the accessible minimum height.'
    );
    wp_setup_file_contains($theme . '/style.css', 'transition: height 180ms cubic-bezier(0.4, 0, 0.2, 1);', 'Only the header height should animate when sticky mode changes.');
    wp_setup_file_contains($theme . '/style.css', '.site-header--compact .brand__name', 'Compact header should reduce the brand name.');
    wp_setup_file_contains($theme . '/style.css', '.site-header--compact .site-nav a', 'Compact header should reduce navigation controls.');
    wp_setup_file_contains($theme . '/style.css', 'min-height: 44px;', 'Navigation links should retain an accessible minimum height.');
    wp_setup_file_contains($theme . '/style.css', 'font-size: clamp(1.25rem, calc(15vw - 6.25rem), 5rem);', 'Expanded header title should shrink fluidly before navigation labels.');
    wp_setup_file_contains($theme . '/style.css', '.site-header--nav-ready .site-nav--open', 'Mobile navigation should expose an explicit open state.');
    wp_setup_file_contains($theme . '/style.css', '--header-height: 60px;', 'Phone layouts should use a stable compact header height.');
    wp_setup_file_contains($theme . '/style.css', 'border-radius: 1000px;', 'Header should retain its rounded outer pill edge.');
    wp_setup_file_contains($theme . '/style.css', 'border-radius: 500px;', 'Header content should retain its rounded inner pill edge.');
    wp_setup_file_contains($theme . '/style.min.css', '--header-height:44px', 'Production CSS should include the compact sticky-header height.');
    wp_setup_file_contains($theme . '/style.min.css', '.site-header-sentinel', 'Production CSS should include the sticky-state sentinel.');
    wp_setup_file_contains($theme . '/style.min.css', 'font-size:clamp(1.25rem,calc(15vw - 6.25rem),5rem)', 'Production CSS should include fluid header-title sizing.');
    wp_setup_file_contains($theme . '/style.min.css', '.site-header--nav-ready .site-nav--open', 'Production CSS should include the open mobile navigation state.');
    wp_setup_file_contains($theme . '/style.css', '.site-nav > ul', 'Primary navigation top-level list should own the flex layout.');
    wp_setup_assert(
        1 === preg_match('/\\.brand__name,[^{]+\\{[^}]*white-space:\\s*nowrap;/s', $style),
        'Header title should stay on one line.'
    );
    wp_setup_assert(
        1 === preg_match('/\\.site-nav > ul\\s*\\{[^}]*flex-wrap:\\s*nowrap;/s', $style),
        'Primary navigation row should stay on one line.'
    );
    wp_setup_assert(
        1 === preg_match('/\\.site-header--compact \\.site-nav > ul > li\\s*\\{[^}]*flex:\\s*1 1 0;[^}]*min-width:\\s*96px;/s', $style),
        'Compact navigation items should share space from a balanced minimum width.'
    );
    wp_setup_assert(
        1 === preg_match('/\\.site-nav a span\\s*\\{[^}]*white-space:\\s*nowrap;/s', $style),
        'Primary navigation labels should stay on one line.'
    );
    wp_setup_file_contains($theme . '/style.min.css', '.site-nav a span{transform-origin:bottom right;transition:transform 133ms ease-out;outline-offset:2px;white-space:nowrap;}', 'Production CSS should prevent navigation labels from wrapping.');
    wp_setup_file_contains($theme . '/style.min.css', '.site-header--compact .site-nav>ul>li{flex:1 1 0;min-width:96px', 'Production CSS should preserve balanced compact navigation widths.');
    wp_setup_file_contains($theme . '/style.css', '.site-nav > ul > li:nth-child(2n) > a', 'Alternating nav colors should target top-level list items.');
    wp_setup_assert(
        1 === preg_match('/\\.site-nav a span\\s*\\{[^}]*transform-origin:\\s*bottom right;/s', $style),
        'Primary navigation labels should scale from their lower-right corner.'
    );
    wp_setup_assert(
        1 === preg_match('/\\.site-header--compact\\s+\\.site-nav a span\\s*\\{[^}]*transform-origin:\\s*center;/s', $style),
        'Compact navigation labels should scale from their center.'
    );
    wp_setup_file_contains($theme . '/style.min.css', '.site-nav a span{transform-origin:bottom right', 'Production CSS should preserve the lower-right navigation-label transform origin.');
    wp_setup_file_contains($theme . '/style.min.css', '.site-header--compact .site-nav a span{transform-origin:center;}', 'Production compact navigation should center its transform origin.');
    wp_setup_file_contains($theme . '/style.css', '.site-nav li:hover > .sub-menu', 'Dropdowns should stay open while hovering the parent list item.');
    wp_setup_file_contains($theme . '/style.css', '.site-nav li:focus-within > .sub-menu', 'Dropdowns should stay open for keyboard focus inside the submenu.');
});

wp_setup_check('front page template preserves the original site sections', function () use ($root): void {
    $template = $root . '/wp-content/themes/alanfullbeard/front-page.php';
    $functions = $root . '/wp-content/themes/alanfullbeard/functions.php';
    $frontPageSource = (string) file_get_contents($template) . "\n" . (string) file_get_contents($functions);

    wp_setup_assert(str_contains($frontPageSource, 'class="odometer"'), 'Front page should keep the years odometer.');
    wp_setup_assert(str_contains($frontPageSource, 'Brief Bio'), 'Front page should include the brief bio section.');
    wp_setup_assert(str_contains($frontPageSource, 'Portfolio'), 'Front page should include the portfolio summary section.');
    wp_setup_assert(str_contains($frontPageSource, 'Plugins'), 'Front page should include the plugins summary section.');
    wp_setup_assert(str_contains($frontPageSource, 'Contact'), 'Front page should include the contact summary section.');
    wp_setup_assert(str_contains($frontPageSource, 'years experience in web development and design.'), 'Front page should include the static screen-reader odometer equivalent.');
});

wp_setup_check('front page copy is editable through Customizer settings', function () use ($root): void {
    $template = $root . '/wp-content/themes/alanfullbeard/front-page.php';
    $functions = $root . '/wp-content/themes/alanfullbeard/functions.php';

    wp_setup_file_contains($functions, "add_action('customize_register', 'alanfullbeard_lcars_customize_front_page_sections');", 'Theme should register front-page Customizer controls.');
    wp_setup_file_contains($functions, 'alanfullbeard_lcars_front_page_defaults', 'Theme should define front-page default copy in one helper.');
    wp_setup_file_contains($functions, 'alanfullbeard_lcars_front_page_text', 'Theme should expose a helper for editable front-page text.');
    wp_setup_file_contains($functions, 'alanfullbeard_lcars_front_page_years', 'Theme should expose a helper for editable front-page years.');
    wp_setup_file_contains($functions, "add_panel('alanfullbeard_front_page_sections'", 'Customizer should group controls in a Front Page Sections panel.');
    wp_setup_file_contains($functions, "add_section('alanfullbeard_front_page_hero'", 'Customizer should include a Hero section.');
    wp_setup_file_contains($functions, "add_section('alanfullbeard_front_page_bio'", 'Customizer should include a Bio section.');
    wp_setup_file_contains($functions, "add_section('alanfullbeard_front_page_summary_cards'", 'Customizer should include a Summary Cards section.');
    wp_setup_file_contains($functions, 'alanfullbeard_lcars_sanitize_front_page_years', 'Theme should sanitize the editable years value.');
    wp_setup_file_contains($functions, 'sanitize_textarea_field', 'Theme should sanitize paragraph fields as textareas.');

    wp_setup_file_contains($template, "alanfullbeard_lcars_front_page_text('hero_heading')", 'Hero heading should come from editable front-page copy.');
    wp_setup_file_contains($template, 'alanfullbeard_lcars_front_page_years()', 'Odometer years should come from editable front-page copy.');
    wp_setup_file_contains($template, "alanfullbeard_lcars_front_page_text('bio_paragraph_1')", 'Bio paragraphs should come from editable front-page copy.');
    wp_setup_file_contains($template, "alanfullbeard_lcars_front_page_text('portfolio_body')", 'Summary card copy should come from editable front-page copy.');
});

wp_setup_check('front page summary cards support repeatable Customizer links', function () use ($root): void {
    $theme = $root . '/wp-content/themes/alanfullbeard';
    $functions = $theme . '/functions.php';
    $template = $theme . '/front-page.php';

    foreach ([
        'assets/css/customizer-controls.css',
        'assets/js/customizer-link-repeater.js',
        'inc/class-front-page-link-repeater-control.php',
    ] as $file) {
        wp_setup_assert(is_file($theme . '/' . $file), "Missing front-page link control file {$file}");
    }

    wp_setup_file_contains($functions, "'portfolio_links'", 'Portfolio card should have an editable link list.');
    wp_setup_file_contains($functions, "'plugins_links'", 'Plugins card should have an editable link list.');
    wp_setup_file_contains($functions, "'contact_links'", 'Contact card should have an editable link list.');
    wp_setup_file_contains($functions, 'alanfullbeard_lcars_sanitize_front_page_links', 'Front-page links should use a dedicated sanitizer.');
    wp_setup_file_contains($functions, 'esc_url_raw', 'Front-page link URLs should be sanitized through WordPress.');
    wp_setup_file_contains($functions, 'Alanfullbeard_Lcars_Link_Repeater_Control', 'Link settings should use the repeatable Customizer control.');
    wp_setup_file_contains($functions, "add_action('customize_controls_enqueue_scripts'", 'Link-control assets should load only in the Customizer.');

    wp_setup_file_contains($template, "alanfullbeard_lcars_front_page_links('portfolio_links')", 'Portfolio links should render below the portfolio copy.');
    wp_setup_file_contains($template, "alanfullbeard_lcars_front_page_links('plugins_links')", 'Plugin links should render below the plugin copy.');
    wp_setup_file_contains($template, "alanfullbeard_lcars_front_page_links('contact_links')", 'Contact links should render below the contact copy.');
    wp_setup_file_contains($template, 'summary-card__links', 'Summary card links should use a dedicated wrapping container.');
    wp_setup_file_contains($theme . '/style.css', 'flex-wrap: wrap;', 'Summary card links should wrap on narrow screens.');
    wp_setup_file_contains($theme . '/style.min.css', '.summary-card__links', 'Production CSS should include summary card link styling.');
});

wp_setup_check('front page summary-card headings support optional links', function () use ($root): void {
    $theme = $root . '/wp-content/themes/alanfullbeard';
    $functions = $theme . '/functions.php';
    $template = $theme . '/front-page.php';
    $styles = $theme . '/style.css';

    foreach (['portfolio', 'plugins', 'contact'] as $card) {
        wp_setup_file_contains($functions, "'{$card}_heading_url'", ucfirst($card) . ' card should have an optional heading URL.');
        wp_setup_file_contains($functions, "__('" . ucfirst($card) . " heading URL (optional)'", ucfirst($card) . ' heading URL should be exposed in the Customizer.');
        wp_setup_file_contains($template, "alanfullbeard_lcars_front_page_url('{$card}_heading_url')", ucfirst($card) . ' heading should read its optional URL.');
    }

    wp_setup_file_contains($functions, "'portfolio_heading_url' => alanfullbeard_lcars_portfolio_page_url()", 'Portfolio heading should default to the Portfolio page.');
    wp_setup_file_contains($functions, "'type' => 'url'", 'Heading-link Customizer controls should use URL inputs.');
    wp_setup_file_contains($functions, "'sanitize_callback' => 'esc_url_raw'", 'Heading-link URLs should be sanitized.');
    wp_setup_file_contains($template, 'class="summary-card__heading-link"', 'Linked headings should use a dedicated class.');
    wp_setup_file_contains($template, "<?php else : ?>", 'Headings should retain unlinked markup when no URL is configured.');
    wp_setup_file_contains($styles, '.summary-card__heading-link {', 'Linked headings should have a neutral resting style.');
    wp_setup_file_contains($styles, 'color: inherit;', 'Linked headings should inherit the existing heading color.');
    wp_setup_file_contains($styles, '.summary-card__heading-link:hover', 'Linked headings should have a hover state.');
    wp_setup_file_contains($styles, '.summary-card__heading-link:focus-visible', 'Linked headings should have a keyboard focus state.');
});

wp_setup_check('front page hero has a separate editable illustrative image', function () use ($root): void {
    $template = $root . '/wp-content/themes/alanfullbeard/front-page.php';
    $functions = $root . '/wp-content/themes/alanfullbeard/functions.php';
    $styles = $root . '/wp-content/themes/alanfullbeard/style.css';

    wp_setup_file_contains($functions, "'hero_image_id'", 'Hero image should use a dedicated theme mod separate from the featured image.');
    wp_setup_file_contains($functions, "'hero_image_alt'", 'Hero image alt text should be editable.');
    wp_setup_file_contains($functions, 'alanfullbeard_lcars_front_page_hero_image_id', 'Theme should expose a helper for the illustrative hero image ID.');
    wp_setup_file_contains($functions, 'WP_Customize_Media_Control', 'Customizer should use a media control for the illustrative hero image.');
    wp_setup_file_contains($functions, "'mime_type' => 'image'", 'Hero media control should be limited to images.');

    wp_setup_file_contains($template, 'home-hero__content', 'Hero text should be wrapped for the two-column layout.');
    wp_setup_file_contains($template, 'home-hero__media', 'Hero should include a media column.');
    wp_setup_file_contains($template, 'wp_get_attachment_image', 'Hero should render the selected image through WordPress attachment markup.');
    wp_setup_file_contains($template, 'alanfullbeard_lcars_front_page_hero_image_id()', 'Hero image should come from the dedicated Customizer setting.');
    wp_setup_assert(! str_contains($template, 'get_the_post_thumbnail'), 'Hero image should not depend on the page featured image.');

    wp_setup_file_contains($styles, '.home-hero--has-image', 'Hero should have a state class when an image is selected.');
    wp_setup_file_contains($styles, '.home-hero__media', 'Hero image column should have dedicated CSS.');
    wp_setup_file_contains($styles, 'grid-template-columns', 'Hero image layout should use explicit responsive columns.');
});

wp_setup_check('web portfolio page uses a dedicated LCARS template and custom block', function () use ($root): void {
    $theme = $root . '/wp-content/themes/alanfullbeard';
    $template = $theme . '/page-web-portfolio.php';
    $blockRoot = $theme . '/blocks/portfolio-card';

    foreach ([
        'inc/portfolio-blocks.php',
        'blocks/portfolio-card/block.json',
        'blocks/portfolio-card/index.js',
        'blocks/portfolio-card/style.css',
        'blocks/portfolio-card/editor.css',
        'assets/images/portfolio-screenshot-placeholder.svg',
    ] as $file) {
        wp_setup_assert(is_file($theme . '/' . $file), "Missing portfolio block file {$file}");
    }

    wp_setup_file_contains($theme . '/functions.php', "require_once get_theme_file_path('inc/portfolio-blocks.php');", 'Theme should load portfolio block registration separately.');
    wp_setup_file_contains($theme . '/functions.php', "add_theme_support('editor-styles')", 'Theme should opt into editor styles for block previews.');

    wp_setup_file_contains($theme . '/inc/portfolio-blocks.php', 'block_categories_all', 'Portfolio block should add a theme-local inserter category.');
    wp_setup_file_contains($theme . '/inc/portfolio-blocks.php', 'register_block_type', 'Portfolio block should be registered with WordPress.');
    wp_setup_file_contains($theme . '/inc/portfolio-blocks.php', 'blocks/portfolio-card/index.js', 'Portfolio block editor script should be registered from the block folder.');
    wp_setup_file_contains($theme . '/inc/portfolio-blocks.php', 'blocks/portfolio-card/style.css', 'Portfolio block frontend style should be registered from the block folder.');
    wp_setup_file_contains($theme . '/inc/portfolio-blocks.php', "'render_callback' => 'alanfullbeard_lcars_render_portfolio_card'", 'Portfolio card output should remain owned by the LCARS theme.');
    wp_setup_file_contains($theme . '/inc/portfolio-blocks.php', 'wp_get_attachment_image', 'Portfolio screenshots should use responsive WordPress attachment markup.');
    wp_setup_file_contains($theme . '/inc/portfolio-blocks.php', "get_theme_file_uri('assets/images/portfolio-screenshot-placeholder.svg')", 'Portfolio cards should share one theme-owned screenshot placeholder.');
    wp_setup_file_contains($theme . '/inc/portfolio-blocks.php', 'portfolio-card--placeholder', 'Portfolio cards should expose their placeholder state for styling.');
    wp_setup_file_contains($theme . '/inc/portfolio-blocks.php', 'target="_blank"', 'Portfolio project links should open in a new tab.');
    wp_setup_file_contains($theme . '/inc/portfolio-blocks.php', 'rel="noopener noreferrer"', 'Portfolio project links should isolate the new tab.');
    wp_setup_file_contains($theme . '/inc/portfolio-blocks.php', '(opens in a new tab)', 'Portfolio project links should announce their new-tab behavior.');
    wp_setup_file_contains($theme . '/inc/portfolio-blocks.php', 'WP_HTML_Tag_Processor', 'Portfolio description links should be updated with the WordPress HTML API.');
    wp_setup_file_contains($theme . '/inc/portfolio-blocks.php', "set_attribute('target', '_blank')", 'Portfolio description links should open in a new tab.');
    wp_setup_file_contains($theme . '/inc/portfolio-blocks.php', "'noopener'", 'Portfolio description links should isolate the new tab.');
    wp_setup_file_contains($theme . '/inc/portfolio-blocks.php', "'noreferrer'", 'Portfolio description links should omit referrer data.');
    wp_setup_file_contains($theme . '/inc/portfolio-blocks.php', "'aria-describedby'", 'Portfolio description links should announce their new-tab behavior.');
    wp_setup_file_contains($theme . '/inc/portfolio-blocks.php', "wp_unique_id('portfolio-card-new-tab-')", 'Portfolio description link notices should have unique IDs.');

    wp_setup_file_contains($blockRoot . '/block.json', '"name": "alanfullbeard/portfolio-card"', 'Portfolio card block should use a project namespace.');
    wp_setup_file_contains($blockRoot . '/block.json', '"title": "LCARS Portfolio Card"', 'Portfolio card block should have an obvious editor title.');
    wp_setup_file_contains($blockRoot . '/block.json', '"html": false', 'Portfolio card block should prevent raw HTML edits.');
    wp_setup_file_contains($blockRoot . '/block.json', '"default": "Visit project"', 'Portfolio card should provide a useful default link label.');
    wp_setup_file_contains($blockRoot . '/index.js', "registerBlockType('alanfullbeard/portfolio-card'", 'Portfolio card script should register the expected block.');
    wp_setup_file_contains($blockRoot . '/index.js', 'MediaUpload', 'Portfolio card should support an optional image.');
    wp_setup_file_contains($blockRoot . '/index.js', "__('Screenshot pending'", 'Portfolio card previews should show the shared screenshot placeholder state.');
    wp_setup_file_contains($blockRoot . '/index.js', 'imagePosition', 'Portfolio card should support adjustable image alignment.');
    wp_setup_file_contains($blockRoot . '/index.js', 'linkUrl', 'Portfolio card should support a project link.');
    wp_setup_file_contains($blockRoot . '/index.js', 'function getExternalUrl', 'Portfolio card should validate external URLs in the editor.');
    wp_setup_file_contains($blockRoot . '/index.js', "target: '_blank'", 'Portfolio link previews should show the external-link behavior.');
    wp_setup_file_contains($blockRoot . '/index.js', 'return null;', 'Dynamic portfolio cards should save attributes rather than duplicate theme markup.');
    wp_setup_file_contains($blockRoot . '/style.css', '.portfolio-card', 'Portfolio card frontend CSS should style the saved block.');
    wp_setup_file_contains($blockRoot . '/style.css', '.portfolio-card .portfolio-card__title', 'Portfolio card title styling should outrank generic entry-content headings.');
    wp_setup_file_contains($blockRoot . '/style.css', 'color: #fff;', 'Portfolio card headlines should be white.');
    wp_setup_file_contains($blockRoot . '/style.css', 'min-width: 0;', 'Portfolio media should be allowed to shrink within its grid column.');
    wp_setup_file_contains($blockRoot . '/style.css', 'max-width: 100%;', 'Portfolio images should remain within their media column.');
    wp_setup_file_contains($blockRoot . '/style.css', '.portfolio-card__link:focus-visible', 'Portfolio links should have a clear keyboard focus state.');
    wp_setup_file_contains($theme . '/style.css', '.portfolio-page .entry-title', 'Portfolio page title should use a page-specific white headline rule.');

    wp_setup_file_contains($template, 'Template Name: Web Portfolio', 'Web Portfolio template should be assignable in WordPress.');
    wp_setup_file_contains($template, 'entry--web-portfolio', 'Web Portfolio template should expose a dedicated page class.');
    wp_setup_file_contains($template, 'portfolio-page__content', 'Web Portfolio template should wrap block content for portfolio layout.');
});

wp_setup_check('front page odometer animates once per years value', function () use ($root): void {
    $template = $root . '/wp-content/themes/alanfullbeard/front-page.php';
    $functions = $root . '/wp-content/themes/alanfullbeard/functions.php';
    $styles = $root . '/wp-content/themes/alanfullbeard/style.css';
    $script = $root . '/wp-content/themes/alanfullbeard/assets/js/odometer.js';

    wp_setup_file_contains($functions, 'alanfullbeard-lcars-odometer', 'Theme should enqueue a dedicated odometer script.');
    wp_setup_file_contains($functions, 'is_front_page()', 'Odometer script should only be enqueued for the front page.');
    wp_setup_file_contains($template, 'data-odometer-years', 'Odometer markup should expose the current years value to JavaScript.');
    wp_setup_file_contains($styles, '.odometer--animate > div', 'Odometer animation should only run when the animation class is present.');
    wp_setup_file_contains($styles, 'animation: odometer 8000ms', 'Odometer should animate at a readable pace.');
    wp_setup_file_contains($styles, '.odometer--final > div', 'Odometer should have an explicit final state class.');
    wp_setup_assert(is_file($script), 'Missing odometer JavaScript asset.');
    wp_setup_file_contains($script, "const KEY_PREFIX = 'alanfullbeard_odometer_years_v1_';", 'Odometer storage key should be namespaced and versioned.');
    wp_setup_file_contains($script, 'odometer.dataset.odometerYears', 'Odometer storage key should include the current years value.');
    wp_setup_file_contains($script, 'localStorage.getItem', 'Odometer script should check whether the animation has already run.');
    wp_setup_file_contains($script, 'localStorage.setItem', 'Odometer script should remember completed animations.');
    wp_setup_file_contains($script, 'animationend', 'Odometer script should persist state after the animation finishes.');
    wp_setup_file_contains($script, 'const ANIMATION_FALLBACK_MS = 9000;', 'Odometer fallback should not interrupt the animation.');
    wp_setup_file_contains($script, 'odometer--animate', 'Odometer script should add the animation class for first-time visitors.');
    wp_setup_file_contains($script, 'odometer--final', 'Odometer script should use the final state when animation is skipped.');
});

wp_setup_check('basic SEO plugin owns metadata while the theme supplies page defaults', function () use ($root): void {
    $functions = $root . '/wp-content/themes/alanfullbeard/functions.php';
    $seo = $root . '/wp-content/themes/alanfullbeard/inc/seo.php';
    $plugin = $root . '/wp-content/plugins/basic-seo-meta/basic-seo-meta.php';
    $adminScript = $root . '/wp-content/plugins/basic-seo-meta/assets/admin.js';

    wp_setup_file_contains($functions, "require_once get_theme_file_path('inc/seo.php');", 'Theme should load its SEO metadata helpers.');
    wp_setup_assert(is_file($seo), 'Missing SEO metadata helper.');
    wp_setup_assert(is_file($plugin), 'Missing Basic SEO Meta plugin.');
    wp_setup_assert(is_file($adminScript), 'Missing Basic SEO Meta media-picker script.');
    wp_setup_file_contains($seo, 'alanfullbeard_lcars_front_page_text', 'Homepage description should follow the editable hero summary.');
    wp_setup_file_contains($seo, 'alanfullbeard_lcars_is_contact_page', 'Contact should have a focused description.');
    wp_setup_file_contains($seo, "is_page('privacy-policy')", 'Privacy Policy should have a focused description.');
    wp_setup_file_contains($seo, "add_filter('basic_seo_meta_description'", 'Theme should pass its page-specific defaults to the plugin.');
    wp_setup_file_contains($plugin, "add_settings_section(", 'Plugin should add its controls to General Settings.');
    wp_setup_file_contains($plugin, "'general'", 'Plugin settings should live on the General Settings screen.');
    wp_setup_file_contains($plugin, 'wp_enqueue_media();', 'Social image control should use the WordPress Media Library.');
    wp_setup_file_contains($plugin, "'og:image'", 'Plugin should output Open Graph image metadata.');
    wp_setup_file_contains($plugin, "'twitter:card'", 'Plugin should output X/Twitter card metadata.');
    wp_setup_file_contains($plugin, "'@context' => 'https://schema.org'", 'Plugin should output schema.org JSON-LD.');
    wp_setup_file_contains($plugin, "'@type' => 'WebSite'", 'Plugin should describe the website.');
    wp_setup_file_contains($plugin, "'@type' => 'Person'", 'Plugin should describe the site owner.');
    wp_setup_file_contains($plugin, "'ProfilePage'", 'Plugin should identify the personal homepage.');
    wp_setup_file_contains($plugin, "'ContactPage'", 'Plugin should identify the contact page.');
    wp_setup_file_contains($plugin, "'ImageObject'", 'Plugin should connect the selected social image to JSON-LD.');
    wp_setup_file_contains($adminScript, 'wp.media', 'Social image control should open the Media Library.');
});

wp_setup_check('theme keeps the WordPress content model simple', function () use ($root): void {
    $theme = $root . '/wp-content/themes/alanfullbeard';
    $functions = file_get_contents($theme . '/functions.php');

    wp_setup_assert(is_string($functions), 'Could not read theme functions.php.');
    wp_setup_assert(!str_contains($functions, 'register_post_type'), 'Theme should not add custom post types for plugin pages.');
    wp_setup_assert(!is_file($theme . '/theme.json'), 'Theme should not opt into advanced block theme configuration.');
});

wp_setup_check('contact page uses Contact Form 7 with theme-local presentation', function () use ($root): void {
    $theme = $root . '/wp-content/themes/alanfullbeard';
    $functions = (string) file_get_contents($theme . '/functions.php');
    $handler = (string) file_get_contents($theme . '/inc/contact-form.php');
    $template = (string) file_get_contents($theme . '/template-parts/contact-form.php');
    $page = (string) file_get_contents($theme . '/page-contact.php');
    $frontPage = (string) file_get_contents($theme . '/front-page.php');

    wp_setup_assert(str_contains($functions, "require_once get_theme_file_path('inc/contact-form.php');"), 'Theme should load contact integration separately.');
    wp_setup_assert(! str_contains($functions, 'public/assets/css/forms.css'), 'WordPress contact form should not depend on legacy site assets.');
    wp_setup_assert(str_contains($page, 'Template Name: Contact'), 'Contact page template should remain assignable if its slug changes.');
    wp_setup_assert(str_contains($page, "get_template_part('template-parts/contact-form')"), 'Contact page should render the contact form wrapper.');
    wp_setup_assert(str_contains($template, 'alanfullbeard_lcars_render_contact_form'), 'Contact wrapper should render the configured Contact Form 7 form.');
    wp_setup_assert(str_contains($handler, "shortcode_exists('contact-form-7')"), 'Contact integration should fail safely if Contact Form 7 is unavailable.');
    wp_setup_assert(str_contains($handler, '[contact-form-7 title="Website contact"'), 'Contact integration should render the portable titled form.');
    wp_setup_assert(str_contains($handler, 'html_class="contact-form__form"'), 'Contact Form 7 should receive the existing theme form class.');
    wp_setup_assert(str_contains($handler, "home_url('/accessible-form/')"), 'The former contact URL should retain an explicit redirect target.');
    wp_setup_assert(str_contains($handler, 'wp_safe_redirect(alanfullbeard_lcars_contact_page_url(), 301'), 'The former contact URL should redirect permanently.');
    wp_setup_assert(str_contains($handler, "add_filter('wpcf7_turnstile_sitekey'"), 'Contact Form 7 should receive the stored Turnstile site key.');
    wp_setup_assert(str_contains($handler, "add_filter('wpcf7_turnstile_secret'"), 'Contact Form 7 should receive the stored Turnstile secret.');
    wp_setup_assert(str_contains($handler, "remove_action('wp_enqueue_scripts', 'wpcf7_turnstile_enqueue_scripts', 10)"), 'Turnstile assets should be removed outside the Contact page.');
    wp_setup_assert(str_contains($handler, "add_filter('wpcf7_validate'"), 'Contact Form 7 should preserve the cross-field phone validation.');
    wp_setup_assert(str_contains($handler, "\$allowsText = in_array(\$permission"), 'Permission to text should require a phone only when the visitor selects Yes.');
    wp_setup_assert(str_contains($handler, 'Enter a phone number if Alan may text you.'), 'Phone validation should provide a useful error.');
    wp_setup_assert(! str_contains($template, 'username'), 'Contact form should not include signup fields.');
    wp_setup_assert(! str_contains($template, 'password'), 'Contact form should not include password fields.');
    wp_setup_assert(! str_contains($template, 'Create Account'), 'Contact form should not use account-creation copy.');
    wp_setup_assert(str_contains($handler, "add_section('alanfullbeard_contact_options'"), 'Customizer should include dedicated Contact Options.');
    wp_setup_assert(str_contains($handler, 'alanfullbeard_contact_sms_number'), 'Contact Options should include an editable Google Voice number.');
    wp_setup_assert(str_contains($handler, 'alanfullbeard_lcars_sanitize_sms_number'), 'Google Voice number should use a dedicated sanitizer.');
    wp_setup_assert(str_contains($handler, "'sms:' . \$number"), 'Contact helper should build a standard SMS link.');
    wp_setup_assert(str_contains($handler, 'rawurlencode($message)'), 'Optional SMS body text should be URL encoded.');
    wp_setup_assert(str_contains($page, "if ('' !== \$smsUrl)"), 'Text-message option should remain hidden until a business number is configured.');
    wp_setup_assert(str_contains($page, "esc_url(\$smsUrl, ['sms'])"), 'Contact page should explicitly allow the SMS URL protocol.');
    wp_setup_assert(str_contains($page, 'contact-page__sms-link'), 'Contact page should render a dedicated text-message action.');
    wp_setup_assert(str_contains($frontPage, 'alanfullbeard_lcars_contact_sms_url()'), 'Homepage Contact card should use the configured Google Voice SMS URL.');
    wp_setup_assert(str_contains($frontPage, "esc_url(\$contactSmsUrl, ['sms'])"), 'Homepage Contact card should render the configured text-message action.');
    wp_setup_file_contains($theme . '/style.css', '.contact-form__body', 'Contact Form 7 wrapper should participate in the existing grid.');
    wp_setup_file_contains($theme . '/style.css', '.contact-form__form', 'Contact form should have theme-local responsive styling.');
    wp_setup_file_contains($theme . '/style.css', '.contact-form__choices', 'Contact form should style the permission-to-text choices.');
    wp_setup_file_contains($theme . '/style.css', '.contact-form__actions', 'Contact form should place Turnstile to the left of submit.');
    wp_setup_file_contains($theme . '/style.css', '.wpcf7-response-output', 'Contact Form 7 status messages should match the theme.');
    wp_setup_file_contains($theme . '/style.css', '.wpcf7-not-valid-tip', 'Contact Form 7 validation messages should match the theme.');
    wp_setup_file_contains($theme . '/style.css', '.contact-page__sms', 'Text-message option should have responsive theme styling.');
    wp_setup_file_contains($theme . '/style.min.css', '.contact-form__body', 'Production CSS should include the Contact Form 7 grid wrapper.');
    wp_setup_file_contains($theme . '/style.min.css', '.contact-form__form', 'Production CSS should include the contact form styles.');
    wp_setup_file_contains($theme . '/style.min.css', '.contact-form__choices', 'Production CSS should include the permission-to-text choices.');
    wp_setup_file_contains($theme . '/style.min.css', '.contact-form__actions', 'Production CSS should include the Turnstile action row.');
    wp_setup_file_contains($theme . '/style.min.css', '.wpcf7-response-output', 'Production CSS should include Contact Form 7 status styling.');
    wp_setup_file_contains($theme . '/style.min.css', '.contact-page__sms', 'Production CSS should include the text-message option.');
    wp_setup_assert(str_contains($functions, 'alanfullbeard_lcars_contact_page_url'), 'Default Contact link should resolve the contact form page.');
    wp_setup_assert(str_contains($frontPage, "alanfullbeard_lcars_front_page_links('contact_links')"), 'Front page should render the editable Contact links.');
});

wp_setup_check('automatic contact storage and retention match the privacy policy', function () use ($root): void {
    $formDocumentation = $root . '/docs/contact-form-7-configuration.md';
    $formSource = $root . '/docs/contact-form-7-form.txt';
    $mailSource = $root . '/docs/contact-form-7-mail.json';
    $additionalSettingsSource = $root . '/docs/contact-form-7-additional-settings.txt';
    $privacyPolicy = $root . '/docs/privacy-policy.html';
    $retentionPlugin = $root . '/wp-content/mu-plugins/alanfullbeard-contact-retention.php';
    $vaultPlugin = $root . '/wp-content/mu-plugins/alanfullbeard-contact-vault.php';

    foreach ([
        $formDocumentation,
        $formSource,
        $mailSource,
        $additionalSettingsSource,
        $privacyPolicy,
        $retentionPlugin,
        $vaultPlugin,
    ] as $file) {
        wp_setup_assert(is_file($file), "Missing contact privacy file {$file}");
    }

    wp_setup_file_contains($formDocumentation, 'privately stores an encrypted copy in WordPress', 'The form should disclose automatic encrypted WordPress storage.');
    wp_setup_file_contains($formSource, 'privately stores an encrypted copy in WordPress', 'Deployable form source should disclose automatic encrypted WordPress storage.');
    wp_setup_file_contains($formSource, 'Submitting this form privately stores an encrypted copy', 'Deployable form source should explain that submission triggers encrypted storage.');
    wp_setup_assert(! str_contains((string) file_get_contents($formDocumentation), 'message-storage'), 'Form documentation should not include a storage checkbox.');
    wp_setup_assert(! str_contains((string) file_get_contents($formSource), 'message-storage'), 'Deployable form source should not include a storage checkbox.');
    wp_setup_assert(! str_contains((string) file_get_contents($mailSource), 'message-storage'), 'Contact email should not reference a removed storage field.');
    wp_setup_file_contains($additionalSettingsSource, 'flamingo_message: "[your-message]"', 'Deployable form settings should preserve spam scoring.');

    wp_setup_file_contains($retentionPlugin, "define('ALANFULLBEARD_CONTACT_INBOX_RETENTION_DAYS', 180);", 'Inbox and contact retention should be 180 days.');
    wp_setup_file_contains($retentionPlugin, "define('ALANFULLBEARD_CONTACT_SPAM_RETENTION_DAYS', 30);", 'Spam and trash retention should be 30 days.');
    wp_setup_file_contains($retentionPlugin, "'post_type' => 'flamingo_inbound'", 'Retention should cover Flamingo inbound messages.');
    wp_setup_file_contains($retentionPlugin, "'post_type' => 'flamingo_contact'", 'Retention should cover Flamingo address-book records.');
    wp_setup_file_contains($retentionPlugin, "add_action(\n    'alanfullbeard_contact_retention_daily'", 'Retention cleanup should run on its scheduled hook.');

    wp_setup_file_contains($vaultPlugin, 'sodium_crypto_aead_xchacha20poly1305_ietf_encrypt', 'Contact fields should use Sodium authenticated encryption.');
    wp_setup_file_contains($vaultPlugin, "'wpcf7_flamingo_inbound_message_parameters'", 'The vault should scrub CF7 data before Flamingo writes it.');
    wp_setup_file_contains($vaultPlugin, "'flamingo_add_contact'", 'The vault should suppress Flamingo address-book plaintext copies.');
    wp_setup_file_contains($vaultPlugin, "'wp_privacy_personal_data_exporters'", 'Encrypted records should support WordPress privacy exports.');
    wp_setup_file_contains($vaultPlugin, "'wp_privacy_personal_data_erasers'", 'Encrypted records should support WordPress privacy deletion.');
    wp_setup_file_contains($privacyPolicy, 'encrypted at rest in WordPress with Sodium XChaCha20-Poly1305', 'The policy should accurately describe database encryption.');
    wp_setup_file_contains($privacyPolicy, 'Storage does not depend on a separate checkbox.', 'The policy should disclose unconditional contact-form storage.');
    wp_setup_file_contains($privacyPolicy, 'email delivery is not guaranteed', 'The policy should describe email as a best-effort notification.');
    wp_setup_file_contains($privacyPolicy, 'automatically deleted after 180 days', 'The policy should publish the inbox retention period.');
    wp_setup_file_contains($privacyPolicy, 'automatically deleted after 30 days', 'The policy should publish the spam retention period.');
    wp_setup_file_contains($privacyPolicy, 'alan@alanfullbeard.com', 'The policy should publish the privacy and deletion request address.');
    wp_setup_file_contains($privacyPolicy, 'https://www.mailersend.com/legal/privacy-policy', 'The policy should link to MailerSend privacy terms.');
    wp_setup_file_contains($privacyPolicy, 'https://www.cloudflare.com/turnstile-privacy-policy/', 'The policy should link to the Turnstile privacy addendum.');
    wp_setup_file_contains($privacyPolicy, 'https://www.newfold.com/privacy-center/privacy', 'The policy should link to the hosting provider privacy notice.');
});

wp_setup_check('File Hub is administrator-only and stores files outside the webroot', function () use ($root): void {
    $plugin = $root . '/wp-content/mu-plugins/alanfullbeard-filehub.php';
    $targetFilter = $root . '/deploy/digitalocean-shared/alanfullbeard-target-rsync-filter.txt';
    $targetHtaccess = $root . '/deploy/digitalocean-shared/alanfullbeard-htaccess.conf';
    $fpmPool = $root . '/deploy/digitalocean-shared/alanfullbeard-fpm.conf';
    $httpVhost = $root . '/deploy/digitalocean-shared/alanfullbeard.com-http.conf';
    $sslVhost = $root . '/deploy/digitalocean-shared/alanfullbeard.com-ssl.conf';
    $standaloneCandidate = $root . '/deploy/digitalocean-shared/filehub.php';

    foreach ([$plugin, $targetFilter, $targetHtaccess, $fpmPool, $httpVhost, $sslVhost] as $file) {
        wp_setup_assert(is_file($file), "Missing File Hub security file {$file}");
    }

    $pluginSource = (string) file_get_contents($plugin);

    wp_setup_assert(! is_file($standaloneCandidate), 'Standalone FileHub candidate should be retired.');
    wp_setup_assert(! str_contains($pluginSource, 'admin_post_nopriv_'), 'File Hub must not register logged-out handlers.');
    wp_setup_assert(! str_contains($pluginSource, 'WWW-Authenticate'), 'File Hub should use WordPress authentication instead of standalone Basic Auth.');
    wp_setup_assert(! str_contains($pluginSource, 'PHP_AUTH_'), 'File Hub should not inspect standalone Basic Auth credentials.');
    wp_setup_file_contains($plugin, "define('ALANFULLBEARD_FILEHUB_ROOT', '/home/afullbeard/filehub_data');", 'Private files should remain outside the webroot.');
    wp_setup_file_contains($plugin, "define('ALANFULLBEARD_FILEHUB_CAPABILITY', 'manage_options');", 'Only administrators should receive File Hub access by default.');
    wp_setup_file_contains($plugin, "define('ALANFULLBEARD_FILEHUB_MAX_UPLOAD_BYTES', 10 * 1024 * 1024);", 'File Hub should enforce the 10 MB file ceiling.');
    wp_setup_file_contains($plugin, 'is_user_logged_in()', 'Every File Hub operation should require a logged-in WordPress user.');
    wp_setup_file_contains($plugin, 'current_user_can(alanfullbeard_filehub_capability())', 'Every File Hub operation should enforce the administrator capability.');
    wp_setup_file_contains($plugin, 'add_management_page(', 'File Hub should live under WordPress Tools.');
    wp_setup_file_contains($plugin, "add_action('admin_post_alanfullbeard_filehub_upload'", 'Uploads should use an authenticated admin-post handler.');
    wp_setup_file_contains($plugin, "add_action('admin_post_alanfullbeard_filehub_download'", 'Downloads should use an authenticated admin-post handler.');
    wp_setup_file_contains($plugin, "add_action('admin_post_alanfullbeard_filehub_rename'", 'Renames should use an authenticated admin-post handler.');
    wp_setup_file_contains($plugin, "add_action('admin_post_alanfullbeard_filehub_move'", 'Moves should use an authenticated admin-post handler.');
    wp_setup_file_contains($plugin, "add_action('admin_post_alanfullbeard_filehub_delete'", 'Deletes should use an authenticated admin-post handler.');
    wp_setup_file_contains($plugin, "check_admin_referer('alanfullbeard_filehub_upload')", 'Uploads should require a WordPress nonce.');
    wp_setup_file_contains($plugin, "check_admin_referer('alanfullbeard_filehub_download_'", 'Downloads should require a file-specific WordPress nonce.');
    wp_setup_file_contains($plugin, "check_admin_referer('alanfullbeard_filehub_delete_'", 'Deletes should require a file-specific WordPress nonce.');
    wp_setup_file_contains($plugin, 'realpath($directory . \'/\' . $name)', 'File actions should resolve and validate the selected path.');
    wp_setup_file_contains($plugin, 'is_link($filePath)', 'File actions should reject symbolic links.');
    wp_setup_file_contains($plugin, 'if (file_exists($destination))', 'Uploads and moves should refuse silent replacement.');
    wp_setup_file_contains($plugin, "header('X-Robots-Tag: noindex, nofollow')", 'Private downloads should send a noindex header.');
    wp_setup_file_contains($targetFilter, '- /filehub/', 'The standalone FileHub directory should be excluded from the target payload.');
    wp_setup_file_contains($targetHtaccess, 'RewriteRule ^filehub(?:/|$) - [R=404,L]', 'The retired public FileHub path should return 404.');
    wp_setup_file_contains($fpmPool, 'php_admin_value[post_max_size] = 12M', 'Alan Fullbeard POST bodies should allow form overhead above the file limit.');
    wp_setup_file_contains($fpmPool, 'php_admin_value[upload_max_filesize] = 10M', 'Alan Fullbeard PHP uploads should be limited to 10 MB.');
    wp_setup_file_contains($httpVhost, 'php8.3-fpm-alanfullbeard.sock', 'The HTTP vhost should use the site-specific PHP-FPM pool.');
    wp_setup_file_contains($sslVhost, 'php8.3-fpm-alanfullbeard.sock', 'The HTTPS vhost should use the site-specific PHP-FPM pool.');
    wp_setup_file_contains($httpVhost, 'LimitRequestBody 12582912', 'The HTTP vhost should reject request bodies larger than the PHP POST limit.');
    wp_setup_file_contains($sslVhost, 'LimitRequestBody 12582912', 'The HTTPS vhost should reject request bodies larger than the PHP POST limit.');
});

wp_setup_check('HostGator deployment configuration preserves protected roots and blocks executable uploads', function () use ($root): void {
    $hostConfig = $root . '/deploy/hostgator-root.htaccess';
    $robotsConfig = $root . '/deploy/robots.txt';
    $uploadConfig = $root . '/deploy/hostgator-uploads.htaccess';

    wp_setup_assert(is_file($hostConfig), 'Missing reviewed HostGator root configuration.');
    wp_setup_assert(is_file($robotsConfig), 'Missing reviewed production robots.txt.');
    wp_setup_assert(is_file($uploadConfig), 'Missing reviewed uploads execution guard.');

    wp_setup_file_contains($hostConfig, 'RewriteCond %{REQUEST_FILENAME} -d', 'Existing protected directories should bypass WordPress routing.');
    wp_setup_file_contains($hostConfig, 'RewriteRule . /index.php [L]', 'Unknown main-domain routes should reach WordPress.');
    wp_setup_file_contains($hostConfig, '^wp-config\\.php$', 'Web access to wp-config.php should be denied.');
    wp_setup_file_contains($hostConfig, '^xmlrpc\\.php$', 'XML-RPC should be disabled for this site.');
    wp_setup_file_contains($hostConfig, 'Options -Indexes', 'Directory listings should be disabled.');
    wp_setup_file_contains($hostConfig, 'php_flag display_errors Off', 'Production PHP errors should remain hidden.');
    wp_setup_file_contains($hostConfig, 'application/x-httpd-ea-php82___lsphp', 'The account PHP 8.2 handler should remain intact.');

    wp_setup_file_contains($robotsConfig, 'User-agent: *', 'Robots policy should apply to all crawlers.');
    wp_setup_file_contains($robotsConfig, 'Sitemap: https://alanfullbeard.com/wp-sitemap.xml', 'Robots policy should advertise the production WordPress sitemap.');

    wp_setup_file_contains($uploadConfig, 'Options -Indexes', 'Uploads should not expose directory listings.');
    wp_setup_file_contains($uploadConfig, 'php[0-9]?', 'Uploads should block executable PHP variants.');
    wp_setup_file_contains($uploadConfig, 'Require all denied', 'Uploads should deny executable files under Apache 2.4.');

    $uploadSource = file_get_contents($uploadConfig);
    wp_setup_assert(is_string($uploadSource), 'Could not read the uploads execution guard.');

    $uploadPatternMatch = [];
    $patternFound = preg_match(
        '/<FilesMatch "([^"]+)">/',
        $uploadSource,
        $uploadPatternMatch
    );

    wp_setup_assert(
        $patternFound === 1
            && isset($uploadPatternMatch[1])
            && is_string($uploadPatternMatch[1]),
        'Could not read the executable upload pattern.'
    );

    $uploadPattern = '~' . $uploadPatternMatch[1] . '~i';

    foreach ([
        'payload.php',
        'payload.php.jpg',
        'payload.php8.webp',
        'payload.phtml.txt',
        'payload.phar.gif',
    ] as $filename) {
        wp_setup_assert(
            preg_match($uploadPattern, $filename) === 1,
            "Uploads should block executable filename {$filename}."
        );
    }

    foreach (['portrait.jpg', 'archive.tar.gz', 'document.pdf'] as $filename) {
        wp_setup_assert(
            preg_match($uploadPattern, $filename) === 0,
            "Uploads should allow non-executable filename {$filename}."
        );
    }
});

if ($failures > 0) {
    echo "\n{$failures} WordPress setup test(s) failed.\n";
    exit(1);
}

echo "\nAll WordPress setup tests passed.\n";
