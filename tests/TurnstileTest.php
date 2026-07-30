<?php

declare(strict_types=1);

final class TurnstileTestState
{
    /** @var array<string, string> */
    public static array $filters = [
        'viazen_mailersend_smtp_turnstile_site_key' => '',
        'viazen_mailersend_smtp_turnstile_secret_key' => '',
    ];
}

function add_action(): void
{
}

function add_filter(): void
{
}

function apply_filters(string $tag, mixed $value, mixed ...$arguments): mixed
{
    unset($arguments);

    return TurnstileTestState::$filters[$tag] ?? $value;
}

function sanitize_text_field(mixed $value): string
{
    return trim((string) $value);
}

function __(string $text): string
{
    return $text;
}

function turnstile_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

final class TurnstileValidationResult
{
    public string $field = '';
    public string $message = '';

    public function invalidate(object $tag, string $message): void
    {
        $this->field = (string) ($tag->name ?? '');
        $this->message = $message;
    }
}

require dirname(__DIR__) . '/wp-content/themes/alanfullbeard/inc/contact-form.php';

turnstile_assert(
    'native-site-key' === alanfullbeard_lcars_cf7_turnstile_site_key('native-site-key'),
    'The bridge should preserve a site key configured directly in Contact Form 7.'
);
turnstile_assert(
    'native-secret' === alanfullbeard_lcars_cf7_turnstile_secret('native-secret'),
    'The bridge should preserve a secret configured directly in Contact Form 7.'
);

TurnstileTestState::$filters = [
    'viazen_mailersend_smtp_turnstile_site_key' => 'mailersend-site-key',
    'viazen_mailersend_smtp_turnstile_secret_key' => 'mailersend-secret',
];

turnstile_assert(
    'mailersend-site-key' === alanfullbeard_lcars_cf7_turnstile_site_key('native-site-key'),
    'Contact Form 7 should use the site key stored in the MailerSend plugin.'
);
turnstile_assert(
    'mailersend-secret' === alanfullbeard_lcars_cf7_turnstile_secret('native-secret'),
    'Contact Form 7 should use the secret stored in the MailerSend plugin.'
);

$_POST = [
    'your-text-permission' => ['Yes, you can text me'],
    'your-phone' => '',
];
$validation = new TurnstileValidationResult();
$phoneTag = (object) ['name' => 'your-phone'];

alanfullbeard_lcars_cf7_validate_text_permission($validation, [$phoneTag]);

turnstile_assert(
    'your-phone' === $validation->field,
    'Permission to text should require a phone number.'
);
turnstile_assert(
    'Enter a phone number if Alan may text you.' === $validation->message,
    'The missing-phone validation should explain how to fix the field.'
);

$_POST['your-text-permission'] = ['No'];
$validation = new TurnstileValidationResult();

alanfullbeard_lcars_cf7_validate_text_permission($validation, [$phoneTag]);

turnstile_assert(
    '' === $validation->field,
    'A phone number should remain optional when permission to text is No.'
);

echo "All Contact Form 7 integration tests passed.\n";
