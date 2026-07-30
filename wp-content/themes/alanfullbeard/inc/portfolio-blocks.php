<?php

declare(strict_types=1);

function alanfullbeard_lcars_register_block_category(array $categories): array
{
    foreach ($categories as $category) {
        if (isset($category['slug']) && 'alanfullbeard-lcars' === $category['slug']) {
            return $categories;
        }
    }

    return array_merge(
        [
            [
                'slug' => 'alanfullbeard-lcars',
                'title' => __('Alan Fullbeard LCARS', 'alanfullbeard-lcars'),
                'icon' => null,
            ],
        ],
        $categories
    );
}
add_filter('block_categories_all', 'alanfullbeard_lcars_register_block_category');

function alanfullbeard_lcars_block_asset_version(string $relative_path): string
{
    $asset_path = get_theme_file_path($relative_path);

    if (is_file($asset_path)) {
        return (string) filemtime($asset_path);
    }

    return (string) wp_get_theme()->get('Version');
}

function alanfullbeard_lcars_portfolio_external_url(mixed $value): string
{
    if (! is_string($value)) {
        return '';
    }

    $url = trim($value);
    $scheme = strtolower((string) wp_parse_url($url, PHP_URL_SCHEME));

    if (! in_array($scheme, ['http', 'https'], true)) {
        return '';
    }

    return esc_url($url);
}

function alanfullbeard_lcars_portfolio_card_choice(
    mixed $value,
    array $allowed,
    string $fallback
): string {
    return is_string($value) && in_array($value, $allowed, true)
        ? $value
        : $fallback;
}

/**
 * Make links authored in a portfolio description open in an isolated new tab.
 *
 * @return array{html: string, has_links: bool}
 */
function alanfullbeard_lcars_prepare_portfolio_summary_links(
    string $html,
    string $notice_id
): array {
    $processor = new WP_HTML_Tag_Processor($html);
    $has_links = false;

    while ($processor->next_tag('A')) {
        $has_links = true;
        $processor->set_attribute('target', '_blank');

        $rel_values = preg_split(
            '/\s+/',
            trim((string) $processor->get_attribute('rel'))
        );
        $rel_values = is_array($rel_values) ? array_filter($rel_values) : [];
        $rel_values[] = 'noopener';
        $rel_values[] = 'noreferrer';
        $processor->set_attribute(
            'rel',
            implode(' ', array_values(array_unique($rel_values)))
        );

        $description_ids = preg_split(
            '/\s+/',
            trim((string) $processor->get_attribute('aria-describedby'))
        );
        $description_ids = is_array($description_ids)
            ? array_filter($description_ids)
            : [];
        $description_ids[] = $notice_id;
        $processor->set_attribute(
            'aria-describedby',
            implode(' ', array_values(array_unique($description_ids)))
        );
    }

    return [
        'html' => $processor->get_updated_html(),
        'has_links' => $has_links,
    ];
}

function alanfullbeard_lcars_render_portfolio_card(array $attributes): string
{
    $eyebrow = trim(wp_strip_all_tags((string) ($attributes['eyebrow'] ?? '')));
    $heading = trim(wp_strip_all_tags((string) ($attributes['heading'] ?? '')));
    $meta = trim(wp_kses(
        (string) ($attributes['meta'] ?? ''),
        [
            'strong' => [],
            'em' => [],
        ]
    ));
    $summary = trim(wp_kses_post((string) ($attributes['summary'] ?? '')));
    $image_id = absint($attributes['imageId'] ?? 0);
    $image_url = esc_url((string) ($attributes['imageUrl'] ?? ''));
    $image_alt = trim(wp_strip_all_tags((string) ($attributes['imageAlt'] ?? '')));
    $image_position = alanfullbeard_lcars_portfolio_card_choice(
        $attributes['imagePosition'] ?? '',
        ['left', 'right'],
        'right'
    );
    $accent = alanfullbeard_lcars_portfolio_card_choice(
        $attributes['accent'] ?? '',
        ['orange', 'gold', 'cyan', 'violet'],
        'orange'
    );
    $link_url = alanfullbeard_lcars_portfolio_external_url($attributes['linkUrl'] ?? '');
    $link_label = trim(wp_strip_all_tags((string) ($attributes['linkLabel'] ?? '')));
    $summary_link_notice_id = wp_unique_id('portfolio-card-new-tab-');
    $summary_data = alanfullbeard_lcars_prepare_portfolio_summary_links(
        $summary,
        $summary_link_notice_id
    );
    $summary = $summary_data['html'];

    $image_html = '';
    if (0 < $image_id) {
        $image_html = (string) wp_get_attachment_image(
            $image_id,
            'large',
            false,
            [
                'class' => 'portfolio-card__image',
                'alt' => $image_alt,
                'loading' => 'lazy',
                'decoding' => 'async',
            ]
        );
    }

    if ('' === $image_html && '' !== $image_url) {
        $image_html = sprintf(
            '<img class="portfolio-card__image" src="%1$s" alt="%2$s" loading="lazy" decoding="async">',
            $image_url,
            esc_attr($image_alt)
        );
    }

    if (
        '' === $heading
        && '' === $summary
        && '' === $image_html
        && ('' === $link_url || '' === $link_label)
    ) {
        return '';
    }

    $uses_placeholder = '' === $image_html;
    if ($uses_placeholder) {
        $image_html = sprintf(
            '<img class="portfolio-card__image portfolio-card__image--placeholder" src="%1$s" alt="" width="1600" height="1000" loading="lazy" decoding="async">',
            esc_url(get_theme_file_uri('assets/images/portfolio-screenshot-placeholder.svg'))
        );
    }

    $classes = [
        'portfolio-card',
        'portfolio-card--image-' . $image_position,
        'portfolio-card--accent-' . $accent,
        'portfolio-card--has-image',
    ];
    if ($uses_placeholder) {
        $classes[] = 'portfolio-card--placeholder';
    }
    $wrapper_attributes = get_block_wrapper_attributes([
        'class' => implode(' ', $classes),
    ]);

    ob_start();
    ?>
    <article <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
      <?php if ('left' === $image_position && '' !== $image_html) : ?>
        <figure class="portfolio-card__media">
          <?php echo $image_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </figure>
      <?php endif; ?>

      <div class="portfolio-card__body">
        <?php if ('' !== $eyebrow) : ?>
          <p class="eyebrow portfolio-card__eyebrow"><?php echo esc_html($eyebrow); ?></p>
        <?php endif; ?>

        <?php if ('' !== $heading) : ?>
          <h2 class="portfolio-card__title"><?php echo esc_html($heading); ?></h2>
        <?php endif; ?>

        <?php if ('' !== $meta) : ?>
          <p class="portfolio-card__meta"><?php echo wp_kses($meta, ['strong' => [], 'em' => []]); ?></p>
        <?php endif; ?>

        <?php if ('' !== $summary) : ?>
          <div class="portfolio-card__summary">
            <?php echo wp_kses_post($summary); ?>
            <?php if ($summary_data['has_links']) : ?>
              <span
                id="<?php echo esc_attr($summary_link_notice_id); ?>"
                class="screen-reader-text"
              >
                <?php esc_html_e('(opens in a new tab)', 'alanfullbeard-lcars'); ?>
              </span>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if ('' !== $link_url && '' !== $link_label) : ?>
          <p class="portfolio-card__action">
            <a
              class="portfolio-card__link"
              href="<?php echo esc_url($link_url); ?>"
              target="_blank"
              rel="noopener noreferrer"
            >
              <span><?php echo esc_html($link_label); ?></span>
              <span class="portfolio-card__link-icon" aria-hidden="true">↗</span>
              <span class="screen-reader-text">
                <?php esc_html_e('(opens in a new tab)', 'alanfullbeard-lcars'); ?>
              </span>
            </a>
          </p>
        <?php endif; ?>
      </div>

      <?php if ('right' === $image_position && '' !== $image_html) : ?>
        <figure class="portfolio-card__media">
          <?php echo $image_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </figure>
      <?php endif; ?>
    </article>
    <?php

    return trim((string) ob_get_clean());
}

function alanfullbeard_lcars_register_portfolio_blocks(): void
{
    $block_dir = get_theme_file_path('blocks/portfolio-card');

    wp_register_script(
        'alanfullbeard-lcars-portfolio-card-editor',
        get_theme_file_uri('blocks/portfolio-card/index.js'),
        ['wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n'],
        alanfullbeard_lcars_block_asset_version('blocks/portfolio-card/index.js'),
        true
    );

    wp_register_style(
        'alanfullbeard-lcars-portfolio-card',
        get_theme_file_uri('blocks/portfolio-card/style.css'),
        [],
        alanfullbeard_lcars_block_asset_version('blocks/portfolio-card/style.css')
    );

    wp_register_style(
        'alanfullbeard-lcars-portfolio-card-editor',
        get_theme_file_uri('blocks/portfolio-card/editor.css'),
        ['alanfullbeard-lcars-portfolio-card'],
        alanfullbeard_lcars_block_asset_version('blocks/portfolio-card/editor.css')
    );

    register_block_type(
        $block_dir,
        [
            'editor_script' => 'alanfullbeard-lcars-portfolio-card-editor',
            'style' => 'alanfullbeard-lcars-portfolio-card',
            'editor_style' => 'alanfullbeard-lcars-portfolio-card-editor',
            'render_callback' => 'alanfullbeard_lcars_render_portfolio_card',
        ]
    );
}
add_action('init', 'alanfullbeard_lcars_register_portfolio_blocks');
