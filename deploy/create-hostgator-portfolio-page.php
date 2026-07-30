<?php

require __DIR__ . '/hostgator-portfolio-page-lib.php';

$candidate = afb_portfolio_page_preflight();

global $wpdb;

if ($wpdb->query('START TRANSACTION') === false) {
    throw new RuntimeException('WordPress could not start the Portfolio transaction.');
}

try {
    $pageId = wp_insert_post(
        [
            'post_author' => $candidate['user_id'],
            'post_title' => 'Portfolio',
            'post_name' => 'portfolio',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_content' => wp_slash($candidate['content']),
            'comment_status' => 'closed',
            'ping_status' => 'closed',
        ],
        true
    );

    if (is_wp_error($pageId)) {
        throw new RuntimeException(
            sprintf(
                'WordPress could not create the Portfolio page: %s',
                $pageId->get_error_message()
            )
        );
    }

    $pageId = (int) $pageId;
    if ($pageId <= 0) {
        throw new RuntimeException('WordPress returned an invalid Portfolio page ID.');
    }

    if (update_post_meta($pageId, '_wp_page_template', 'page-web-portfolio.php') === false) {
        throw new RuntimeException('WordPress could not assign the Portfolio page template.');
    }

    clean_post_cache($pageId);
    $savedPage = get_post($pageId);
    if (
        ! $savedPage instanceof WP_Post
        || $savedPage->post_type !== 'page'
        || $savedPage->post_status !== 'publish'
        || $savedPage->post_name !== 'portfolio'
        || $savedPage->post_title !== 'Portfolio'
        || ! hash_equals(
            $candidate['content_sha256'],
            hash('sha256', $savedPage->post_content)
        )
    ) {
        throw new RuntimeException('The saved Portfolio page failed verification.');
    }

    $template = get_post_meta($pageId, '_wp_page_template', true);
    if ($template !== 'page-web-portfolio.php') {
        throw new RuntimeException('The saved Portfolio template failed verification.');
    }

    $permalink = get_permalink($pageId);
    if ($permalink !== 'https://alanfullbeard.com/portfolio/') {
        throw new RuntimeException(
            sprintf('The saved Portfolio permalink is unexpected: %s', (string) $permalink)
        );
    }

    if ($wpdb->query('COMMIT') === false) {
        throw new RuntimeException('WordPress could not commit the Portfolio transaction.');
    }
} catch (Throwable $error) {
    $wpdb->query('ROLLBACK');
    throw $error;
}

printf(
    "Portfolio page created: page_id=%d cards=%d content_sha256=%s permalink=%s\n",
    $pageId,
    $candidate['card_count'],
    $candidate['content_sha256'],
    $permalink
);
