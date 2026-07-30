<?php

declare(strict_types=1);

function alanfullbeard_lcars_meta_description(): string
{
    if (is_front_page()) {
        $description = function_exists('alanfullbeard_lcars_front_page_text')
            ? alanfullbeard_lcars_front_page_text('hero_summary')
            : get_bloginfo('description');
    } elseif (alanfullbeard_lcars_is_contact_page()) {
        $description = __(
            'Contact Alan Fullmer about accessible websites, front-end repairs, performance improvements, or custom WordPress plugin development.',
            'alanfullbeard-lcars'
        );
    } elseif (is_page('privacy-policy')) {
        $description = __(
            'Read how alanfullbeard.com handles information submitted through its contact form and other website data.',
            'alanfullbeard-lcars'
        );
    } else {
        return '';
    }

    $description = wp_strip_all_tags((string) $description);
    $description = preg_replace('/\s+/', ' ', $description);

    return is_string($description) ? trim($description) : '';
}

function alanfullbeard_lcars_filter_meta_description(string $description): string
{
    if ('' !== trim($description)) {
        return $description;
    }

    return alanfullbeard_lcars_meta_description();
}

if (defined('BASIC_SEO_META_VERSION')) {
    add_filter('basic_seo_meta_description', 'alanfullbeard_lcars_filter_meta_description');
} else {
    function alanfullbeard_lcars_print_meta_description(): void
    {
        $description = alanfullbeard_lcars_meta_description();

        if ('' === $description) {
            return;
        }

        echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
    }
    add_action('wp_head', 'alanfullbeard_lcars_print_meta_description', 2);
}
