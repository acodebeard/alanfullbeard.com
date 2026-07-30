<?php
/**
 * Plugin Name:       Basic SEO Meta
 * Description:       Adds essential search and social metadata with simple controls in Settings > General.
 * Version:           1.1.0
 * Requires at least: 6.6
 * Requires PHP:      8.2
 * Author:            @acodebeard
 * License:           GPL-2.0-or-later
 * Text Domain:       basic-seo-meta
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('BASIC_SEO_META_VERSION', '1.1.0');

final class Basic_Seo_Meta
{
    private const OPTION_HOME_TITLE = 'basic_seo_meta_home_title';
    private const OPTION_DESCRIPTION = 'basic_seo_meta_description';
    private const OPTION_SOCIAL_IMAGE = 'basic_seo_meta_social_image_id';

    public static function init(): void
    {
        add_action('admin_init', [self::class, 'register_settings']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin_assets']);
        add_filter('pre_get_document_title', [self::class, 'filter_home_title']);
        add_action('wp_head', [self::class, 'render_metadata'], 2);
    }

    public static function register_settings(): void
    {
        register_setting('general', self::OPTION_HOME_TITLE, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);

        register_setting('general', self::OPTION_DESCRIPTION, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default' => '',
        ]);

        register_setting('general', self::OPTION_SOCIAL_IMAGE, [
            'type' => 'integer',
            'sanitize_callback' => [self::class, 'sanitize_social_image'],
            'default' => 0,
        ]);

        add_settings_section(
            'basic-seo-meta',
            __('Basic SEO', 'basic-seo-meta'),
            [self::class, 'render_section'],
            'general'
        );

        add_settings_field(
            self::OPTION_HOME_TITLE,
            __('Homepage SEO title', 'basic-seo-meta'),
            [self::class, 'render_home_title_field'],
            'general',
            'basic-seo-meta'
        );

        add_settings_field(
            self::OPTION_DESCRIPTION,
            __('Default meta description', 'basic-seo-meta'),
            [self::class, 'render_description_field'],
            'general',
            'basic-seo-meta'
        );

        add_settings_field(
            self::OPTION_SOCIAL_IMAGE,
            __('Default social image', 'basic-seo-meta'),
            [self::class, 'render_social_image_field'],
            'general',
            'basic-seo-meta'
        );
    }

    public static function render_section(): void
    {
        echo '<p>'
            . esc_html__(
                'Set the homepage search title, a default description, and the image used when links are shared. Page titles continue to come from WordPress.',
                'basic-seo-meta'
            )
            . '</p>';
    }

    public static function render_home_title_field(): void
    {
        $value = (string) get_option(self::OPTION_HOME_TITLE, '');

        printf(
            '<input type="text" class="regular-text" id="%1$s" name="%1$s" value="%2$s" maxlength="200" aria-describedby="%1$s-description"><p class="description" id="%1$s-description">%3$s</p>',
            esc_attr(self::OPTION_HOME_TITLE),
            esc_attr($value),
            esc_html__('Leave blank to use the normal WordPress site title and tagline.', 'basic-seo-meta')
        );
    }

    public static function render_description_field(): void
    {
        $value = (string) get_option(self::OPTION_DESCRIPTION, '');

        printf(
            '<textarea class="large-text" rows="3" id="%1$s" name="%1$s" maxlength="320" aria-describedby="%1$s-description">%2$s</textarea><p class="description" id="%1$s-description">%3$s</p>',
            esc_attr(self::OPTION_DESCRIPTION),
            esc_textarea($value),
            esc_html__(
                'Used on the homepage and as a fallback when a page has no specific description. Aim for a concise summary of roughly 150 characters.',
                'basic-seo-meta'
            )
        );
    }

    public static function render_social_image_field(): void
    {
        $image_id = absint(get_option(self::OPTION_SOCIAL_IMAGE, 0));
        $image_url = $image_id > 0 ? wp_get_attachment_image_url($image_id, 'medium') : false;
        $has_image = is_string($image_url) && '' !== $image_url;

        ?>
        <div class="basic-seo-meta-image" data-basic-seo-meta-image>
            <input
                type="hidden"
                id="<?php echo esc_attr(self::OPTION_SOCIAL_IMAGE); ?>"
                name="<?php echo esc_attr(self::OPTION_SOCIAL_IMAGE); ?>"
                value="<?php echo esc_attr((string) $image_id); ?>"
                data-basic-seo-meta-image-id
            >
            <div class="basic-seo-meta-image__preview" data-basic-seo-meta-image-preview aria-live="polite">
                <?php if ($has_image) : ?>
                    <img src="<?php echo esc_url($image_url); ?>" alt="">
                <?php else : ?>
                    <span><?php esc_html_e('No image selected.', 'basic-seo-meta'); ?></span>
                <?php endif; ?>
            </div>
            <p>
                <button
                    type="button"
                    class="button"
                    data-basic-seo-meta-image-select
                    data-media-title="<?php esc_attr_e('Choose a social sharing image', 'basic-seo-meta'); ?>"
                    data-media-button="<?php esc_attr_e('Use this image', 'basic-seo-meta'); ?>"
                ><?php esc_html_e('Choose image', 'basic-seo-meta'); ?></button>
                <button
                    type="button"
                    class="button button-link-delete"
                    data-basic-seo-meta-image-remove
                    <?php echo $has_image ? '' : 'hidden'; ?>
                ><?php esc_html_e('Remove image', 'basic-seo-meta'); ?></button>
            </p>
            <p class="description">
                <?php esc_html_e('Use a high-quality landscape image; 1200 × 630 pixels is a practical default.', 'basic-seo-meta'); ?>
            </p>
        </div>
        <?php
    }

    public static function sanitize_social_image(mixed $value): int
    {
        $image_id = absint($value);

        return $image_id > 0 && wp_attachment_is_image($image_id) ? $image_id : 0;
    }

    public static function enqueue_admin_assets(string $hook_suffix): void
    {
        if ('options-general.php' !== $hook_suffix) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style(
            'basic-seo-meta-admin',
            plugin_dir_url(__FILE__) . 'assets/admin.css',
            [],
            BASIC_SEO_META_VERSION
        );
        wp_enqueue_script(
            'basic-seo-meta-admin',
            plugin_dir_url(__FILE__) . 'assets/admin.js',
            ['jquery'],
            BASIC_SEO_META_VERSION,
            true
        );
    }

    public static function filter_home_title(string $title): string
    {
        if (! is_front_page()) {
            return $title;
        }

        $custom_title = trim((string) get_option(self::OPTION_HOME_TITLE, ''));

        return '' !== $custom_title ? $custom_title : $title;
    }

    public static function render_metadata(): void
    {
        if (! is_front_page() && ! is_singular()) {
            return;
        }

        $title = wp_get_document_title();
        $description = self::get_description();
        $url = self::get_current_url();
        $image = self::get_social_image();

        if ('' !== $description) {
            self::print_meta('name', 'description', $description);
        }

        self::print_meta('property', 'og:type', is_singular('post') ? 'article' : 'website');
        self::print_meta('property', 'og:site_name', (string) get_bloginfo('name'));
        self::print_meta('property', 'og:locale', (string) get_locale());
        self::print_meta('property', 'og:title', $title);
        self::print_meta('property', 'og:url', $url);

        if ('' !== $description) {
            self::print_meta('property', 'og:description', $description);
        }

        self::print_meta('name', 'twitter:card', null !== $image ? 'summary_large_image' : 'summary');
        self::print_meta('name', 'twitter:title', $title);

        if ('' !== $description) {
            self::print_meta('name', 'twitter:description', $description);
        }

        if (null !== $image) {
            self::print_meta('property', 'og:image', $image['url']);
            self::print_meta('property', 'og:image:width', (string) $image['width']);
            self::print_meta('property', 'og:image:height', (string) $image['height']);
            self::print_meta('name', 'twitter:image', $image['url']);

            if ('' !== $image['alt']) {
                self::print_meta('property', 'og:image:alt', $image['alt']);
                self::print_meta('name', 'twitter:image:alt', $image['alt']);
            }
        }

        self::render_json_ld($title, $description, $url, $image);
    }

    private static function get_description(): string
    {
        $description = '';

        if (is_front_page()) {
            $description = (string) get_option(self::OPTION_DESCRIPTION, '');
        } elseif (is_singular()) {
            $post = get_queried_object();

            if ($post instanceof WP_Post && has_excerpt($post)) {
                $description = (string) get_the_excerpt($post);
            }
        }

        $description = (string) apply_filters('basic_seo_meta_description', $description);

        if ('' === trim($description)) {
            $description = (string) get_option(self::OPTION_DESCRIPTION, '');
        }

        return self::normalize_text($description);
    }

    private static function get_current_url(): string
    {
        if (is_front_page()) {
            return home_url('/');
        }

        $post = get_queried_object();
        $permalink = $post instanceof WP_Post ? get_permalink($post) : false;

        return is_string($permalink) && '' !== $permalink ? $permalink : home_url('/');
    }

    /**
     * @return array{url: string, width: int, height: int, alt: string}|null
     */
    private static function get_social_image(): ?array
    {
        $image_id = 0;

        if (is_singular()) {
            $post = get_queried_object();

            if ($post instanceof WP_Post) {
                $image_id = get_post_thumbnail_id($post);
            }
        }

        if ($image_id <= 0) {
            $image_id = absint(get_option(self::OPTION_SOCIAL_IMAGE, 0));
        }

        if ($image_id <= 0) {
            return null;
        }

        $image = wp_get_attachment_image_src($image_id, 'full');

        if (! is_array($image) || ! isset($image[0], $image[1], $image[2])) {
            return null;
        }

        $alt = trim((string) get_post_meta($image_id, '_wp_attachment_image_alt', true));

        if ('' === $alt) {
            $alt = trim((string) get_the_title($image_id));
        }

        return [
            'url' => (string) $image[0],
            'width' => (int) $image[1],
            'height' => (int) $image[2],
            'alt' => $alt,
        ];
    }

    private static function normalize_text(string $text): string
    {
        $text = wp_strip_all_tags($text);
        $text = preg_replace('/\s+/', ' ', $text);

        return is_string($text) ? trim($text) : '';
    }

    private static function print_meta(string $attribute, string $key, string $content): void
    {
        printf(
            '<meta %1$s="%2$s" content="%3$s">' . "\n",
            esc_attr($attribute),
            esc_attr($key),
            esc_attr($content)
        );
    }

    /**
     * @param array{url: string, width: int, height: int, alt: string}|null $image
     */
    private static function render_json_ld(
        string $title,
        string $description,
        string $url,
        ?array $image
    ): void {
        $home_url = home_url('/');
        $website_id = $home_url . '#website';
        $person_id = $home_url . '#person';
        $webpage_id = $url . '#webpage';
        $language = str_replace('_', '-', (string) get_locale());
        $site_name = (string) get_bloginfo('name');
        $tagline = trim((string) get_bloginfo('description'));
        $page_type = is_front_page()
            ? 'ProfilePage'
            : (is_page('contact') || is_page_template('page-contact.php') ? 'ContactPage' : 'WebPage');

        $website = [
            '@type' => 'WebSite',
            '@id' => $website_id,
            'url' => $home_url,
            'name' => $site_name,
            'inLanguage' => $language,
            'publisher' => ['@id' => $person_id],
        ];

        $person = [
            '@type' => 'Person',
            '@id' => $person_id,
            'name' => $site_name,
            'url' => $home_url,
        ];

        if ('' !== $tagline) {
            $person['jobTitle'] = $tagline;
        }

        if (is_front_page() && '' !== $description) {
            $person['description'] = $description;
        }

        $webpage = [
            '@type' => $page_type,
            '@id' => $webpage_id,
            'url' => $url,
            'name' => $title,
            'isPartOf' => ['@id' => $website_id],
            'about' => ['@id' => $person_id],
            'inLanguage' => $language,
        ];

        if ('' !== $description) {
            $webpage['description'] = $description;
        }

        if (is_front_page()) {
            $webpage['mainEntity'] = ['@id' => $person_id];
        }

        $graph = [$website, $person, $webpage];

        if (null !== $image) {
            $image_id = $url . '#primaryimage';
            $image_object = [
                '@type' => 'ImageObject',
                '@id' => $image_id,
                'url' => $image['url'],
                'contentUrl' => $image['url'],
                'width' => $image['width'],
                'height' => $image['height'],
            ];

            if ('' !== $image['alt']) {
                $image_object['caption'] = $image['alt'];
            }

            $graph[] = $image_object;
            $webpage_index = array_key_last($graph) - 1;
            $graph[$webpage_index]['primaryImageOfPage'] = ['@id' => $image_id];
            $graph[$webpage_index]['image'] = ['@id' => $image_id];

            if (is_front_page()) {
                $person['image'] = ['@id' => $image_id];
                $graph[1] = $person;
            }
        }

        $graph = apply_filters('basic_seo_meta_json_ld_graph', $graph, [
            'title' => $title,
            'description' => $description,
            'url' => $url,
            'image' => $image,
        ]);

        if (! is_array($graph) || [] === $graph) {
            return;
        }

        $json = wp_json_encode(
            [
                '@context' => 'https://schema.org',
                '@graph' => array_values($graph),
            ],
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
        );

        if (! is_string($json) || '' === $json) {
            return;
        }

        echo '<script type="application/ld+json">' . $json . '</script>' . "\n";
    }
}

Basic_Seo_Meta::init();
