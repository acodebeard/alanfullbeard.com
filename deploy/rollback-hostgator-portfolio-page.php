<?php

require __DIR__ . '/hostgator-portfolio-page-lib.php';

$page = get_page_by_path('portfolio', OBJECT, 'page');
if (! $page instanceof WP_Post) {
    printf("Portfolio rollback: no matching page exists.\n");
    return;
}

$candidate = file_get_contents(AFB_PORTFOLIO_CONTENT_FILE);
if (
    ! is_string($candidate)
    || $page->post_author !== '1'
    || $page->post_title !== 'Portfolio'
    || $page->post_name !== 'portfolio'
    || $page->post_type !== 'page'
    || ! hash_equals(AFB_PORTFOLIO_CONTENT_SHA256, hash('sha256', $page->post_content))
    || ! hash_equals(AFB_PORTFOLIO_CONTENT_SHA256, hash('sha256', $candidate))
    || get_post_meta($page->ID, '_wp_page_template', true) !== 'page-web-portfolio.php'
) {
    throw new RuntimeException('Refusing to delete an unexpected Portfolio page.');
}

$deleted = wp_delete_post($page->ID, true);
if (! $deleted instanceof WP_Post) {
    throw new RuntimeException('WordPress could not remove the created Portfolio page.');
}

if (get_post($page->ID) instanceof WP_Post) {
    throw new RuntimeException('The created Portfolio page remains after rollback.');
}

printf("Portfolio rollback removed page_id=%d.\n", $page->ID);
