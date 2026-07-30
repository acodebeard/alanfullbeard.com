<?php

declare(strict_types=1);

/** @var string $root */
?>
<header class="page-header">
  <p class="eyebrow">Accessibility showcase</p>
  <h1>Accessible Form Showcase</h1>
  <p>This is a demo form imported from the previous site. It is intentionally non-functional: nothing is sent to a server, though draft restoration may use this browser's local storage.</p>
</header>

<div class="form form-1 showcase-form">
  <?php require $root . '/app/views/partials/accessible-form.php'; ?>
</div>
