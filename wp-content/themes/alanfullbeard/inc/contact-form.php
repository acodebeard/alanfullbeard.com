<?php

declare(strict_types=1);

function alanfullbeard_lcars_is_contact_page(): bool
{
    return is_page('contact') || is_page_template('page-contact.php');
}

function alanfullbeard_lcars_scope_turnstile_assets(): void
{
    if (is_admin() || alanfullbeard_lcars_is_contact_page()) {
        return;
    }

    remove_action('wp_enqueue_scripts', 'wpcf7_turnstile_enqueue_scripts', 10);
}
add_action('wp', 'alanfullbeard_lcars_scope_turnstile_assets');

function alanfullbeard_lcars_contact_page_url(): string
{
    $page = get_page_by_path('contact');

    if ($page) {
        $permalink = get_permalink($page);

        if (is_string($permalink) && '' !== $permalink) {
            return $permalink;
        }
    }

    return home_url('/contact/');
}

function alanfullbeard_lcars_redirect_legacy_contact_page(): void
{
    if (! is_404()) {
        return;
    }

    $requestUri = isset($_SERVER['REQUEST_URI'])
        ? sanitize_text_field((string) wp_unslash($_SERVER['REQUEST_URI']))
        : '';
    $requestPath = wp_parse_url($requestUri, PHP_URL_PATH);
    $legacyPath = wp_parse_url(home_url('/accessible-form/'), PHP_URL_PATH);

    if (
        ! is_string($requestPath)
        || ! is_string($legacyPath)
        || untrailingslashit($requestPath) !== untrailingslashit($legacyPath)
    ) {
        return;
    }

    wp_safe_redirect(alanfullbeard_lcars_contact_page_url(), 301, 'Alan Fullbeard');
    exit;
}
add_action('template_redirect', 'alanfullbeard_lcars_redirect_legacy_contact_page');

function alanfullbeard_lcars_contact_sms_defaults(): array
{
    return [
        'number' => '',
        'label' => __('Text Alan', 'alanfullbeard-lcars'),
        'message' => __('Hi Alan, I found your website and would like to get in touch.', 'alanfullbeard-lcars'),
    ];
}

function alanfullbeard_lcars_sanitize_sms_number(mixed $value): string
{
    $raw_number = trim(sanitize_text_field((string) $value));
    $digits = preg_replace('/\D+/', '', $raw_number);

    if (! is_string($digits) || strlen($digits) < 7 || strlen($digits) > 15) {
        return '';
    }

    return str_starts_with($raw_number, '+') ? '+' . $digits : $digits;
}

function alanfullbeard_lcars_contact_sms_setting(string $key): string
{
    $defaults = alanfullbeard_lcars_contact_sms_defaults();

    if (! array_key_exists($key, $defaults)) {
        return '';
    }

    $value = (string) get_theme_mod('alanfullbeard_contact_sms_' . $key, $defaults[$key]);

    if ('label' === $key && '' === sanitize_text_field($value)) {
        return $defaults['label'];
    }

    return $value;
}

function alanfullbeard_lcars_contact_sms_url(): string
{
    $number = alanfullbeard_lcars_sanitize_sms_number(
        alanfullbeard_lcars_contact_sms_setting('number')
    );

    if ('' === $number) {
        return '';
    }

    $message = sanitize_textarea_field(alanfullbeard_lcars_contact_sms_setting('message'));
    $url = 'sms:' . $number;

    if ('' !== $message) {
        $url .= '?body=' . rawurlencode($message);
    }

    return $url;
}

function alanfullbeard_lcars_customize_contact_options(WP_Customize_Manager $wp_customize): void
{
    $defaults = alanfullbeard_lcars_contact_sms_defaults();

    $wp_customize->add_section('alanfullbeard_contact_options', [
        'title' => __('Contact Options', 'alanfullbeard-lcars'),
        'priority' => 31,
    ]);

    $wp_customize->add_setting('alanfullbeard_contact_sms_number', [
        'default' => $defaults['number'],
        'sanitize_callback' => 'alanfullbeard_lcars_sanitize_sms_number',
        'transport' => 'refresh',
        'type' => 'theme_mod',
    ]);

    $wp_customize->add_control('alanfullbeard_contact_sms_number', [
        'label' => __('Google Voice business number', 'alanfullbeard-lcars'),
        'description' => __('Leave blank to hide the text-message option. The number is public when enabled.', 'alanfullbeard-lcars'),
        'section' => 'alanfullbeard_contact_options',
        'type' => 'tel',
        'input_attrs' => [
            'autocomplete' => 'tel',
            'inputmode' => 'tel',
            'placeholder' => '+1 520 555 0123',
        ],
    ]);

    $wp_customize->add_setting('alanfullbeard_contact_sms_label', [
        'default' => $defaults['label'],
        'sanitize_callback' => 'sanitize_text_field',
        'transport' => 'refresh',
        'type' => 'theme_mod',
    ]);

    $wp_customize->add_control('alanfullbeard_contact_sms_label', [
        'label' => __('Text button label', 'alanfullbeard-lcars'),
        'section' => 'alanfullbeard_contact_options',
        'type' => 'text',
    ]);

    $wp_customize->add_setting('alanfullbeard_contact_sms_message', [
        'default' => $defaults['message'],
        'sanitize_callback' => 'sanitize_textarea_field',
        'transport' => 'refresh',
        'type' => 'theme_mod',
    ]);

    $wp_customize->add_control('alanfullbeard_contact_sms_message', [
        'label' => __('Prefilled text message', 'alanfullbeard-lcars'),
        'section' => 'alanfullbeard_contact_options',
        'type' => 'textarea',
    ]);
}
add_action('customize_register', 'alanfullbeard_lcars_customize_contact_options');

function alanfullbeard_lcars_turnstile_site_key(): string
{
    $siteKey = apply_filters('viazen_mailersend_smtp_turnstile_site_key', '');

    return is_string($siteKey) ? trim($siteKey) : '';
}

function alanfullbeard_lcars_turnstile_secret_key(): string
{
    $secretKey = apply_filters('viazen_mailersend_smtp_turnstile_secret_key', '');

    return is_string($secretKey) ? trim($secretKey) : '';
}

function alanfullbeard_lcars_turnstile_is_configured(): bool
{
    return '' !== alanfullbeard_lcars_turnstile_site_key()
        && '' !== alanfullbeard_lcars_turnstile_secret_key();
}

function alanfullbeard_lcars_cf7_turnstile_site_key(mixed $siteKey): string
{
    $configuredSiteKey = alanfullbeard_lcars_turnstile_site_key();

    return '' !== $configuredSiteKey
        ? $configuredSiteKey
        : (is_string($siteKey) ? trim($siteKey) : '');
}
add_filter('wpcf7_turnstile_sitekey', 'alanfullbeard_lcars_cf7_turnstile_site_key');

function alanfullbeard_lcars_cf7_turnstile_secret(mixed $secret): string
{
    $configuredSecret = alanfullbeard_lcars_turnstile_secret_key();

    return '' !== $configuredSecret
        ? $configuredSecret
        : (is_string($secret) ? trim($secret) : '');
}
add_filter('wpcf7_turnstile_secret', 'alanfullbeard_lcars_cf7_turnstile_secret');

function alanfullbeard_lcars_cf7_validate_text_permission(mixed $result, mixed $tags): mixed
{
    if (! is_object($result) || ! method_exists($result, 'invalidate')) {
        return $result;
    }

    $permission = function_exists('wpcf7_superglobal_post')
        ? wpcf7_superglobal_post('your-text-permission', [])
        : ($_POST['your-text-permission'] ?? []);
    $phone = function_exists('wpcf7_superglobal_post')
        ? wpcf7_superglobal_post('your-phone', '')
        : ($_POST['your-phone'] ?? '');

    $permission = is_array($permission) ? reset($permission) : $permission;
    $permission = is_string($permission) ? sanitize_text_field($permission) : '';
    $phone = is_string($phone) ? trim(sanitize_text_field($phone)) : '';

    $allowsText = in_array($permission, ['Yes', 'Yes, you can text me'], true);

    if (! $allowsText || '' !== $phone || ! is_array($tags)) {
        return $result;
    }

    foreach ($tags as $tag) {
        if (is_object($tag) && 'your-phone' === ($tag->name ?? '')) {
            $result->invalidate(
                $tag,
                __('Enter a phone number if Alan may text you.', 'alanfullbeard-lcars')
            );
            break;
        }
    }

    return $result;
}
add_filter('wpcf7_validate', 'alanfullbeard_lcars_cf7_validate_text_permission', 20, 2);

function alanfullbeard_lcars_render_contact_form(): void
{
    if (! shortcode_exists('contact-form-7')) {
        echo '<p class="contact-form__notice contact-form__notice--error" role="alert">'
            . esc_html__('The contact form is temporarily unavailable. Email alan@alanfullbeard.com instead.', 'alanfullbeard-lcars')
            . '</p>';
        return;
    }

    echo do_shortcode(
        '[contact-form-7 title="Website contact" html_class="contact-form__form" html_title="Send a message"]'
    );
}
