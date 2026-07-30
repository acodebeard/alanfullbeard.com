<?php

declare(strict_types=1);

if (! class_exists('WP_Customize_Control')) {
    return;
}

class Alanfullbeard_Lcars_Link_Repeater_Control extends WP_Customize_Control
{
    public $type = 'alanfullbeard_link_repeater';

    public function render_content(): void
    {
        $links = alanfullbeard_lcars_normalize_front_page_links($this->value());
        $encoded_links = wp_json_encode($links);

        if (false === $encoded_links) {
            $encoded_links = '[]';
        }
        ?>
        <?php if ('' !== $this->label) : ?>
            <span class="customize-control-title"><?php echo esc_html($this->label); ?></span>
        <?php endif; ?>

        <?php if ('' !== $this->description) : ?>
            <span class="description customize-control-description"><?php echo esc_html($this->description); ?></span>
        <?php endif; ?>

        <div class="alanfullbeard-link-repeater" data-control-id="<?php echo esc_attr($this->id); ?>">
            <div class="alanfullbeard-link-repeater__rows"></div>

            <template class="alanfullbeard-link-repeater__template">
                <div class="alanfullbeard-link-repeater__row">
                    <label class="alanfullbeard-link-repeater__field">
                        <span><?php esc_html_e('Link text', 'alanfullbeard-lcars'); ?></span>
                        <input type="text" data-link-field="label">
                    </label>

                    <label class="alanfullbeard-link-repeater__field">
                        <span><?php esc_html_e('URL', 'alanfullbeard-lcars'); ?></span>
                        <input type="url" data-link-field="url" placeholder="https://example.com">
                    </label>

                    <button type="button" class="button-link-delete alanfullbeard-link-repeater__remove">
                        <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                        <span class="screen-reader-text"><?php esc_html_e('Remove link', 'alanfullbeard-lcars'); ?></span>
                    </button>
                </div>
            </template>

            <button type="button" class="button alanfullbeard-link-repeater__add">
                <?php esc_html_e('Add link', 'alanfullbeard-lcars'); ?>
            </button>

            <input
                type="hidden"
                class="alanfullbeard-link-repeater__value"
                value="<?php echo esc_attr($encoded_links); ?>"
                <?php $this->link(); ?>
            >
        </div>
        <?php
    }
}
