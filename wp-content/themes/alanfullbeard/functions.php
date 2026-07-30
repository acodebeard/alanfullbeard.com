<?php

declare(strict_types=1);

require_once get_theme_file_path('inc/portfolio-blocks.php');
require_once get_theme_file_path('inc/contact-form.php');
require_once get_theme_file_path('inc/seo.php');

if (! function_exists('alanfullbeard_lcars_setup')) {
    function alanfullbeard_lcars_setup(): void
    {
        add_theme_support('automatic-feed-links');
        add_theme_support('title-tag');
        add_theme_support('post-thumbnails');
        add_theme_support('responsive-embeds');
        add_theme_support('wp-block-styles');
        add_theme_support('editor-styles');
        add_theme_support('align-wide');
        add_theme_support('html5', [
            'caption',
            'comment-form',
            'comment-list',
            'gallery',
            'navigation-widgets',
            'script',
            'search-form',
            'style',
        ]);

        register_nav_menus([
            'primary' => __('Primary Navigation', 'alanfullbeard-lcars'),
        ]);
    }
}
add_action('after_setup_theme', 'alanfullbeard_lcars_setup');

function alanfullbeard_lcars_preload_fonts(): void
{
    $local_font_files = [
        'assets/fonts/jost-400.woff2',
        'assets/fonts/jost-700.woff2',
        'assets/fonts/antonio-400.woff2',
        'assets/fonts/antonio-700.woff2',
    ];

    echo "\n";

    foreach ($local_font_files as $local_font_file) {
        echo '<link rel="preload" href="' . esc_url(get_theme_file_uri($local_font_file)) . '" as="font" type="font/woff2" crossorigin>' . "\n";
    }
}
add_action('wp_head', 'alanfullbeard_lcars_preload_fonts', 1);

function alanfullbeard_lcars_is_local_site(): bool
{
    $site_host = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
    $local_hosts = ['localhost', '127.0.0.1', '::1'];

    return in_array($site_host, $local_hosts, true);
}

function alanfullbeard_lcars_stylesheet_file(): string
{
    if (alanfullbeard_lcars_is_local_site() || 'production' !== wp_get_environment_type()) {
        return 'style.css';
    }

    return 'style.min.css';
}

function alanfullbeard_lcars_disable_frontend_emoji_assets(): void
{
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('wp_enqueue_scripts', 'wp_enqueue_emoji_styles');
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('embed_head', 'print_emoji_detection_script');
    remove_action('enqueue_embed_scripts', 'wp_enqueue_emoji_styles');
}
add_action('init', 'alanfullbeard_lcars_disable_frontend_emoji_assets', 0);

function alanfullbeard_lcars_assets(): void
{
    $stylesheet_file = alanfullbeard_lcars_stylesheet_file();
    $stylesheet_path = get_theme_file_path($stylesheet_file);
    $stylesheet_version = filemtime($stylesheet_path);
    $sticky_header_path = get_theme_file_path('assets/js/sticky-header.js');
    $odometer_path = get_theme_file_path('assets/js/odometer.js');
    $theme_version = wp_get_theme()->get('Version');

    wp_enqueue_style(
        'alanfullbeard-lcars-style',
        get_theme_file_uri($stylesheet_file),
        [],
        false === $stylesheet_version ? $theme_version : (string) $stylesheet_version
    );

    wp_enqueue_script(
        'alanfullbeard-lcars-sticky-header',
        get_theme_file_uri('assets/js/sticky-header.js'),
        [],
        is_file($sticky_header_path) ? (string) filemtime($sticky_header_path) : $theme_version,
        true
    );

    if (is_front_page()) {
        wp_enqueue_script(
            'alanfullbeard-lcars-odometer',
            get_theme_file_uri('assets/js/odometer.js'),
            [],
            is_file($odometer_path) ? (string) filemtime($odometer_path) : $theme_version,
            true
        );
    }

}
add_action('wp_enqueue_scripts', 'alanfullbeard_lcars_assets');

function alanfullbeard_lcars_portfolio_page_url(): string
{
    $page = get_page_by_path('portfolio');

    if ($page) {
        $permalink = get_permalink($page);

        if (is_string($permalink) && '' !== $permalink) {
            return $permalink;
        }
    }

    return home_url('/portfolio/');
}

function alanfullbeard_lcars_front_page_defaults(): array
{
    $contact_links = wp_json_encode([
        [
            'label' => __('Send a message', 'alanfullbeard-lcars'),
            'url' => alanfullbeard_lcars_contact_page_url(),
        ],
    ]);

    return [
        'hero_eyebrow' => __('Resume site', 'alanfullbeard-lcars'),
        'hero_years' => '14',
        'hero_experience_label' => __('years in web development and design.', 'alanfullbeard-lcars'),
        'hero_heading' => __('Alan Fullmer builds accessible, fast, practical websites.', 'alanfullbeard-lcars'),
        'hero_summary' => __('Tucson-based web developer focused on accessibility, UX, performance, interface repair, plugins, and clear front-end systems that are easy to maintain.', 'alanfullbeard-lcars'),
        'hero_image_id' => 0,
        'hero_image_alt' => '',
        'bio_eyebrow' => __('Profile', 'alanfullbeard-lcars'),
        'bio_heading' => __('Brief Bio', 'alanfullbeard-lcars'),
        'bio_paragraph_1' => __('Alan Fullmer is a Tucson-based web developer with more than 14 years of experience designing, building, repairing, and optimizing websites. His work centers on accessible interfaces, clear user experience, fast pages, and code that stays understandable after launch.', 'alanfullbeard-lcars'),
        'bio_paragraph_2' => __('He builds modern, responsive sites; improves existing interfaces; fixes accessibility issues; tunes performance; and works with PHP, WordPress, front-end architecture, and custom plugins. The through line is practical web work: make the site easier to use, easier to edit, and better aligned with the people who depend on it.', 'alanfullbeard-lcars'),
        'bio_paragraph_3' => __('Photography has been part of his life for more than 20 years. That attention to composition, small details, and timing carries into how he approaches layout, content hierarchy, and interface polish.', 'alanfullbeard-lcars'),
        'portfolio_eyebrow' => __('Selected work', 'alanfullbeard-lcars'),
        'portfolio_heading' => __('Portfolio', 'alanfullbeard-lcars'),
        'portfolio_heading_url' => alanfullbeard_lcars_portfolio_page_url(),
        'portfolio_body' => __('Selected site, accessibility, performance, interface, and photography work. Starter areas include Voice Media Group, Laffs, photography, and accessible interface work.', 'alanfullbeard-lcars'),
        'portfolio_links' => '[]',
        'plugins_eyebrow' => __('Tools', 'alanfullbeard-lcars'),
        'plugins_heading' => __('Plugins', 'alanfullbeard-lcars'),
        'plugins_heading_url' => '',
        'plugins_body' => __('Standalone WordPress plugin demos will live as regular pages. Each plugin page can show the problem, the interface, screenshots, code notes, and practical outcomes without adding custom content types.', 'alanfullbeard-lcars'),
        'plugins_links' => '[]',
        'contact_eyebrow' => __('Availability', 'alanfullbeard-lcars'),
        'contact_heading' => __('Contact', 'alanfullbeard-lcars'),
        'contact_heading_url' => '',
        'contact_body' => __('Alan can help with builds, repairs, audits, plugin demonstrations, and maintainable front-end systems. Send a message to start a conversation.', 'alanfullbeard-lcars'),
        'contact_links' => false === $contact_links ? '[]' : $contact_links,
    ];
}

function alanfullbeard_lcars_front_page_theme_mod_name(string $key): string
{
    return 'alanfullbeard_front_page_' . $key;
}

function alanfullbeard_lcars_front_page_text(string $key): string
{
    $defaults = alanfullbeard_lcars_front_page_defaults();

    if (! array_key_exists($key, $defaults)) {
        return '';
    }

    $value = get_theme_mod(
        alanfullbeard_lcars_front_page_theme_mod_name($key),
        $defaults[$key]
    );

    return is_scalar($value) ? (string) $value : '';
}

function alanfullbeard_lcars_front_page_url(string $key): string
{
    return esc_url_raw(alanfullbeard_lcars_front_page_text($key));
}

/**
 * @return array<int, array{label: string, url: string}>
 */
function alanfullbeard_lcars_normalize_front_page_links(mixed $value): array
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        $value = is_array($decoded) ? $decoded : [];
    }

    if (! is_array($value)) {
        return [];
    }

    $links = [];

    foreach (array_slice($value, 0, 20) as $link) {
        if (! is_array($link)) {
            continue;
        }

        $label = sanitize_text_field((string) ($link['label'] ?? ''));
        $url = esc_url_raw((string) ($link['url'] ?? ''));

        if ('' === $label || '' === $url) {
            continue;
        }

        $links[] = [
            'label' => $label,
            'url' => $url,
        ];
    }

    return $links;
}

function alanfullbeard_lcars_sanitize_front_page_links(mixed $value): string
{
    $encoded_links = wp_json_encode(alanfullbeard_lcars_normalize_front_page_links($value));

    return false === $encoded_links ? '[]' : $encoded_links;
}

/**
 * @return array<int, array{label: string, url: string}>
 */
function alanfullbeard_lcars_front_page_links(string $key): array
{
    $defaults = alanfullbeard_lcars_front_page_defaults();

    if (! array_key_exists($key, $defaults)) {
        return [];
    }

    return alanfullbeard_lcars_normalize_front_page_links(
        get_theme_mod(
            alanfullbeard_lcars_front_page_theme_mod_name($key),
            $defaults[$key]
        )
    );
}

function alanfullbeard_lcars_sanitize_front_page_years(mixed $value): int
{
    $years = absint($value);

    if ($years < 1) {
        return 1;
    }

    return min($years, 99);
}

function alanfullbeard_lcars_front_page_years(): int
{
    $defaults = alanfullbeard_lcars_front_page_defaults();

    return alanfullbeard_lcars_sanitize_front_page_years(
        get_theme_mod(
            alanfullbeard_lcars_front_page_theme_mod_name('hero_years'),
            $defaults['hero_years']
        )
    );
}

function alanfullbeard_lcars_front_page_hero_image_id(): int
{
    $defaults = alanfullbeard_lcars_front_page_defaults();

    return absint(
        get_theme_mod(
            alanfullbeard_lcars_front_page_theme_mod_name('hero_image_id'),
            $defaults['hero_image_id']
        )
    );
}

function alanfullbeard_lcars_front_page_hero_image_alt(): string
{
    $defaults = alanfullbeard_lcars_front_page_defaults();

    return (string) get_theme_mod(
        alanfullbeard_lcars_front_page_theme_mod_name('hero_image_alt'),
        $defaults['hero_image_alt']
    );
}

function alanfullbeard_lcars_front_page_years_label(int $years): string
{
    return sprintf(
        _n(
            '%d year experience in web development and design.',
            '%d years experience in web development and design.',
            $years,
            'alanfullbeard-lcars'
        ),
        $years
    );
}

function alanfullbeard_lcars_front_page_customizer_fields(): array
{
    return [
        'hero_eyebrow' => [
            'section' => 'alanfullbeard_front_page_hero',
            'label' => __('Hero eyebrow', 'alanfullbeard-lcars'),
            'type' => 'text',
            'sanitize_callback' => 'sanitize_text_field',
        ],
        'hero_years' => [
            'section' => 'alanfullbeard_front_page_hero',
            'label' => __('Years number', 'alanfullbeard-lcars'),
            'type' => 'number',
            'sanitize_callback' => 'alanfullbeard_lcars_sanitize_front_page_years',
            'input_attrs' => [
                'min' => 1,
                'max' => 99,
                'step' => 1,
            ],
        ],
        'hero_experience_label' => [
            'section' => 'alanfullbeard_front_page_hero',
            'label' => __('Years label', 'alanfullbeard-lcars'),
            'type' => 'text',
            'sanitize_callback' => 'sanitize_text_field',
        ],
        'hero_heading' => [
            'section' => 'alanfullbeard_front_page_hero',
            'label' => __('Hero heading', 'alanfullbeard-lcars'),
            'type' => 'textarea',
            'sanitize_callback' => 'sanitize_textarea_field',
        ],
        'hero_summary' => [
            'section' => 'alanfullbeard_front_page_hero',
            'label' => __('Hero summary', 'alanfullbeard-lcars'),
            'type' => 'textarea',
            'sanitize_callback' => 'sanitize_textarea_field',
        ],
        'hero_image_id' => [
            'section' => 'alanfullbeard_front_page_hero',
            'label' => __('Hero image', 'alanfullbeard-lcars'),
            'type' => 'media',
            'sanitize_callback' => 'absint',
            'mime_type' => 'image',
        ],
        'hero_image_alt' => [
            'section' => 'alanfullbeard_front_page_hero',
            'label' => __('Hero image alt text', 'alanfullbeard-lcars'),
            'type' => 'text',
            'sanitize_callback' => 'sanitize_text_field',
        ],
        'bio_eyebrow' => [
            'section' => 'alanfullbeard_front_page_bio',
            'label' => __('Bio eyebrow', 'alanfullbeard-lcars'),
            'type' => 'text',
            'sanitize_callback' => 'sanitize_text_field',
        ],
        'bio_heading' => [
            'section' => 'alanfullbeard_front_page_bio',
            'label' => __('Bio heading', 'alanfullbeard-lcars'),
            'type' => 'text',
            'sanitize_callback' => 'sanitize_text_field',
        ],
        'bio_paragraph_1' => [
            'section' => 'alanfullbeard_front_page_bio',
            'label' => __('Bio paragraph 1', 'alanfullbeard-lcars'),
            'type' => 'textarea',
            'sanitize_callback' => 'sanitize_textarea_field',
        ],
        'bio_paragraph_2' => [
            'section' => 'alanfullbeard_front_page_bio',
            'label' => __('Bio paragraph 2', 'alanfullbeard-lcars'),
            'type' => 'textarea',
            'sanitize_callback' => 'sanitize_textarea_field',
        ],
        'bio_paragraph_3' => [
            'section' => 'alanfullbeard_front_page_bio',
            'label' => __('Bio paragraph 3', 'alanfullbeard-lcars'),
            'type' => 'textarea',
            'sanitize_callback' => 'sanitize_textarea_field',
        ],
        'portfolio_eyebrow' => [
            'section' => 'alanfullbeard_front_page_summary_cards',
            'label' => __('Portfolio eyebrow', 'alanfullbeard-lcars'),
            'type' => 'text',
            'sanitize_callback' => 'sanitize_text_field',
        ],
        'portfolio_heading' => [
            'section' => 'alanfullbeard_front_page_summary_cards',
            'label' => __('Portfolio heading', 'alanfullbeard-lcars'),
            'type' => 'text',
            'sanitize_callback' => 'sanitize_text_field',
        ],
        'portfolio_heading_url' => [
            'section' => 'alanfullbeard_front_page_summary_cards',
            'label' => __('Portfolio heading URL (optional)', 'alanfullbeard-lcars'),
            'type' => 'url',
            'sanitize_callback' => 'esc_url_raw',
        ],
        'portfolio_body' => [
            'section' => 'alanfullbeard_front_page_summary_cards',
            'label' => __('Portfolio body', 'alanfullbeard-lcars'),
            'type' => 'textarea',
            'sanitize_callback' => 'sanitize_textarea_field',
        ],
        'portfolio_links' => [
            'section' => 'alanfullbeard_front_page_summary_cards',
            'label' => __('Portfolio links', 'alanfullbeard-lcars'),
            'type' => 'link_repeater',
            'sanitize_callback' => 'alanfullbeard_lcars_sanitize_front_page_links',
        ],
        'plugins_eyebrow' => [
            'section' => 'alanfullbeard_front_page_summary_cards',
            'label' => __('Plugins eyebrow', 'alanfullbeard-lcars'),
            'type' => 'text',
            'sanitize_callback' => 'sanitize_text_field',
        ],
        'plugins_heading' => [
            'section' => 'alanfullbeard_front_page_summary_cards',
            'label' => __('Plugins heading', 'alanfullbeard-lcars'),
            'type' => 'text',
            'sanitize_callback' => 'sanitize_text_field',
        ],
        'plugins_heading_url' => [
            'section' => 'alanfullbeard_front_page_summary_cards',
            'label' => __('Plugins heading URL (optional)', 'alanfullbeard-lcars'),
            'type' => 'url',
            'sanitize_callback' => 'esc_url_raw',
        ],
        'plugins_body' => [
            'section' => 'alanfullbeard_front_page_summary_cards',
            'label' => __('Plugins body', 'alanfullbeard-lcars'),
            'type' => 'textarea',
            'sanitize_callback' => 'sanitize_textarea_field',
        ],
        'plugins_links' => [
            'section' => 'alanfullbeard_front_page_summary_cards',
            'label' => __('Plugin links', 'alanfullbeard-lcars'),
            'type' => 'link_repeater',
            'sanitize_callback' => 'alanfullbeard_lcars_sanitize_front_page_links',
        ],
        'contact_eyebrow' => [
            'section' => 'alanfullbeard_front_page_summary_cards',
            'label' => __('Contact eyebrow', 'alanfullbeard-lcars'),
            'type' => 'text',
            'sanitize_callback' => 'sanitize_text_field',
        ],
        'contact_heading' => [
            'section' => 'alanfullbeard_front_page_summary_cards',
            'label' => __('Contact heading', 'alanfullbeard-lcars'),
            'type' => 'text',
            'sanitize_callback' => 'sanitize_text_field',
        ],
        'contact_heading_url' => [
            'section' => 'alanfullbeard_front_page_summary_cards',
            'label' => __('Contact heading URL (optional)', 'alanfullbeard-lcars'),
            'type' => 'url',
            'sanitize_callback' => 'esc_url_raw',
        ],
        'contact_body' => [
            'section' => 'alanfullbeard_front_page_summary_cards',
            'label' => __('Contact body', 'alanfullbeard-lcars'),
            'type' => 'textarea',
            'sanitize_callback' => 'sanitize_textarea_field',
        ],
        'contact_links' => [
            'section' => 'alanfullbeard_front_page_summary_cards',
            'label' => __('Contact links', 'alanfullbeard-lcars'),
            'type' => 'link_repeater',
            'sanitize_callback' => 'alanfullbeard_lcars_sanitize_front_page_links',
        ],
    ];
}

function alanfullbeard_lcars_customizer_controls_assets(): void
{
    $script_path = get_theme_file_path('assets/js/customizer-link-repeater.js');
    $style_path = get_theme_file_path('assets/css/customizer-controls.css');
    $theme_version = wp_get_theme()->get('Version');

    wp_enqueue_script(
        'alanfullbeard-lcars-customizer-links',
        get_theme_file_uri('assets/js/customizer-link-repeater.js'),
        ['customize-controls'],
        is_file($script_path) ? (string) filemtime($script_path) : $theme_version,
        true
    );

    wp_enqueue_style(
        'alanfullbeard-lcars-customizer-controls',
        get_theme_file_uri('assets/css/customizer-controls.css'),
        [],
        is_file($style_path) ? (string) filemtime($style_path) : $theme_version
    );
}
add_action('customize_controls_enqueue_scripts', 'alanfullbeard_lcars_customizer_controls_assets');

function alanfullbeard_lcars_customize_front_page_sections(WP_Customize_Manager $wp_customize): void
{
    require_once get_theme_file_path('inc/class-front-page-link-repeater-control.php');

    $wp_customize->add_panel('alanfullbeard_front_page_sections', [
        'title' => __('Front Page Sections', 'alanfullbeard-lcars'),
        'priority' => 30,
    ]);

    $wp_customize->add_section('alanfullbeard_front_page_hero', [
        'title' => __('Hero', 'alanfullbeard-lcars'),
        'panel' => 'alanfullbeard_front_page_sections',
    ]);

    $wp_customize->add_section('alanfullbeard_front_page_bio', [
        'title' => __('Bio', 'alanfullbeard-lcars'),
        'panel' => 'alanfullbeard_front_page_sections',
    ]);

    $wp_customize->add_section('alanfullbeard_front_page_summary_cards', [
        'title' => __('Summary Cards', 'alanfullbeard-lcars'),
        'panel' => 'alanfullbeard_front_page_sections',
    ]);

    $defaults = alanfullbeard_lcars_front_page_defaults();

    foreach (alanfullbeard_lcars_front_page_customizer_fields() as $key => $field) {
        $settingId = alanfullbeard_lcars_front_page_theme_mod_name((string) $key);

        $wp_customize->add_setting($settingId, [
            'default' => $defaults[$key] ?? '',
            'sanitize_callback' => $field['sanitize_callback'],
            'transport' => 'refresh',
            'type' => 'theme_mod',
        ]);

        $control = [
            'label' => $field['label'],
            'section' => $field['section'],
            'settings' => $settingId,
            'type' => $field['type'],
        ];

        if (isset($field['input_attrs']) && is_array($field['input_attrs'])) {
            $control['input_attrs'] = $field['input_attrs'];
        }

        if ('media' === $field['type']) {
            $control['mime_type'] = $field['mime_type'] ?? '';
            $wp_customize->add_control(new WP_Customize_Media_Control($wp_customize, $settingId, $control));
        } elseif ('link_repeater' === $field['type']) {
            $wp_customize->add_control(new Alanfullbeard_Lcars_Link_Repeater_Control($wp_customize, $settingId, $control));
        } else {
            $wp_customize->add_control($settingId, $control);
        }
    }
}
add_action('customize_register', 'alanfullbeard_lcars_customize_front_page_sections');
