<?php

if (PHP_SAPI !== 'cli' || ! defined('WP_CLI')) {
    throw new RuntimeException('Run this Portfolio migration through WP-CLI.');
}

const AFB_PORTFOLIO_CONTENT_FILE = __DIR__ . '/hostgator-portfolio-page-content.html';
const AFB_PORTFOLIO_CONTENT_SHA256 = '660716cd0c61c0348c12bfc24e198264d9694df0622cd76721825e0d5f8d1a74';

/**
 * Verify the exact live prerequisites and return the reviewed page payload.
 *
 * @return array{
 *     content: string,
 *     content_sha256: string,
 *     user_id: int,
 *     card_count: int
 * }
 */
function afb_portfolio_page_preflight(): array
{
    if (! is_readable(AFB_PORTFOLIO_CONTENT_FILE)) {
        throw new RuntimeException('The Portfolio page content candidate is unavailable.');
    }

    $contentHash = hash_file('sha256', AFB_PORTFOLIO_CONTENT_FILE);
    if (! is_string($contentHash) || ! hash_equals(AFB_PORTFOLIO_CONTENT_SHA256, $contentHash)) {
        throw new RuntimeException('The Portfolio page content hash failed.');
    }

    $content = file_get_contents(AFB_PORTFOLIO_CONTENT_FILE);
    if (! is_string($content) || $content === '') {
        throw new RuntimeException('The Portfolio page content could not be read.');
    }

    if (get_page_by_path('portfolio', OBJECT, 'page') instanceof WP_Post) {
        throw new RuntimeException('A Portfolio page already exists.');
    }

    $owner = get_user_by('login', 'alan');
    if (! $owner instanceof WP_User || (int) $owner->ID !== 1) {
        throw new RuntimeException('The expected production content owner is unavailable.');
    }

    $template = get_theme_file_path('page-web-portfolio.php');
    if (! is_readable($template)) {
        throw new RuntimeException('The reviewed Portfolio page template is unavailable.');
    }

    $expectedAttachments = [
        85 => [
            'relative' => '2026/07/redmeat-screen.webp',
            'sha256' => '24a6a597f868a4253ffbc8fa1b0ca0404ce495c4ebf6859615560a2a3bbac180',
            'url' => 'https://alanfullbeard.com/wp-content/uploads/2026/07/redmeat-screen.webp',
        ],
        86 => [
            'relative' => '2026/07/newtimes-screen.webp',
            'sha256' => '21f5022c7bdaaaeadfc7b46a94e69f0546dbedb4a225d0cede87ea6096399376',
            'url' => 'https://alanfullbeard.com/wp-content/uploads/2026/07/newtimes-screen.webp',
        ],
        87 => [
            'relative' => '2026/07/laffs-screen.webp',
            'sha256' => 'c249363862a3bd9d1d41307e22d73ffe2f9d97cc56a567e992e25c8a161d0b97',
            'url' => 'https://alanfullbeard.com/wp-content/uploads/2026/07/laffs-screen.webp',
        ],
        88 => [
            'relative' => '2026/07/dkc-screen.webp',
            'sha256' => 'febae764ce80f00a7c47acd78d401f670baf6c7f7af402f632458defffdbbb67',
            'url' => 'https://alanfullbeard.com/wp-content/uploads/2026/07/dkc-screen.webp',
        ],
        89 => [
            'relative' => '2026/07/aaa-screen.webp',
            'sha256' => 'b8376e509c75a4576318fb0049b710203b1dd2b11fba9b9995bfa9e33700c152',
            'url' => 'https://alanfullbeard.com/wp-content/uploads/2026/07/aaa-screen.webp',
        ],
    ];

    foreach ($expectedAttachments as $attachmentId => $expected) {
        $attachment = get_post($attachmentId);
        if (
            ! $attachment instanceof WP_Post
            || $attachment->post_type !== 'attachment'
            || get_post_mime_type($attachment) !== 'image/webp'
        ) {
            throw new RuntimeException(
                sprintf('Expected WebP attachment %d is unavailable.', $attachmentId)
            );
        }

        $relative = get_post_meta($attachmentId, '_wp_attached_file', true);
        if (! is_string($relative) || $relative !== $expected['relative']) {
            throw new RuntimeException(
                sprintf('Attachment %d has an unexpected media path.', $attachmentId)
            );
        }

        $attachedFile = get_attached_file($attachmentId, true);
        if (! is_string($attachedFile) || ! is_readable($attachedFile)) {
            throw new RuntimeException(
                sprintf('Attachment %d file is unavailable.', $attachmentId)
            );
        }

        $attachedHash = hash_file('sha256', $attachedFile);
        if (
            ! is_string($attachedHash)
            || ! hash_equals($expected['sha256'], $attachedHash)
        ) {
            throw new RuntimeException(
                sprintf('Attachment %d file hash failed.', $attachmentId)
            );
        }

        $url = wp_get_attachment_url($attachmentId);
        if (! is_string($url) || $url !== $expected['url']) {
            throw new RuntimeException(
                sprintf('Attachment %d URL is unexpected.', $attachmentId)
            );
        }

    }

    $contentBlocks = array_values(
        array_filter(
            parse_blocks($content),
            static fn (array $block): bool => is_string($block['blockName'])
        )
    );

    if (count($contentBlocks) !== 6 || $contentBlocks[0]['blockName'] !== 'core/paragraph') {
        throw new RuntimeException('The Portfolio page block structure is unexpected.');
    }

    $expectedCards = [
        [
            'heading' => 'Phoenix New Times',
            'imageId' => 86,
            'imageAlt' => 'Screenshot of the Phoenix New Times website',
        ],
        [
            'heading' => 'Destination Kona Coast',
            'imageId' => 88,
            'imageAlt' => 'Screenshot of Destination Kona Coast',
        ],
        [
            'heading' => 'Red Meat',
            'imageId' => 85,
            'imageAlt' => 'Screenshot of Redmeat',
        ],
        [
            'heading' => 'AAA Gas & Plumbing',
            'imageId' => 89,
            'imageAlt' => 'Screenshot of AAA Gas & Plumbing',
        ],
        [
            'heading' => 'Laffs Comedy Caffé',
            'imageId' => 87,
            'imageAlt' => 'Screenshot of Laffs Comedy Cafe',
        ],
    ];

    foreach ($expectedCards as $index => $expectedCard) {
        $block = $contentBlocks[$index + 1] ?? null;
        if (
            ! is_array($block)
            || $block['blockName'] !== 'alanfullbeard/portfolio-card'
            || ($block['attrs']['heading'] ?? null) !== $expectedCard['heading']
            || (int) ($block['attrs']['imageId'] ?? 0) !== $expectedCard['imageId']
            || ($block['attrs']['imageAlt'] ?? null) !== $expectedCard['imageAlt']
            || ! filter_var($block['attrs']['linkUrl'] ?? '', FILTER_VALIDATE_URL)
        ) {
            throw new RuntimeException(
                sprintf('Portfolio card %d is unexpected.', $index + 1)
            );
        }
    }

    return [
        'content' => $content,
        'content_sha256' => $contentHash,
        'user_id' => (int) $owner->ID,
        'card_count' => count($expectedCards),
    ];
}
