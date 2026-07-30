<?php

declare(strict_types=1);

use function AlanFullbeard\e;

/** @var array<string, mixed> $site */
$email = (string) $site['email'];
?>
<header class="page-header">
  <p class="eyebrow">Contact</p>
  <h1>Get in touch</h1>
  <p>Have a project in mind? Alan is quick to reply.</p>
</header>

<section class="content-section">
  <p><a class="large-link" href="mailto:<?= e($email) ?>"><?= e($email) ?></a></p>
</section>
