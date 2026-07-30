<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$page = AlanFullbeard\resolve_route($_SERVER['REQUEST_URI'] ?? '/');

AlanFullbeard\send_security_headers();
http_response_code((int) $page['status']);

echo AlanFullbeard\render_page($page, dirname(__DIR__));
