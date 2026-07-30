<?php

require __DIR__ . '/hostgator-portfolio-page-lib.php';

$candidate = afb_portfolio_page_preflight();

printf(
    "Portfolio page preflight passed: slug=portfolio owner=%d cards=%d content_sha256=%s\n",
    $candidate['user_id'],
    $candidate['card_count'],
    $candidate['content_sha256']
);
printf("Database writes=0 media writes=0\n");
