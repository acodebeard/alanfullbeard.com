<?php

declare(strict_types=1);

use function AlanFullbeard\e;

/** @var array<string, mixed> $page */
/** @var array<string, mixed> $site */

$currentPath = (string) ($page['path'] ?? '/');
$resume = is_array($site['resume'] ?? null) ? $site['resume'] : [];
?>
<header class="site-header">
  <a class="skip-link" href="#site-content">Skip to content</a>
  <div class="site-header__inner">
    <a class="brand" href="/" aria-label="Alan Fullmer home">
      <span class="brand__name"><?= e((string) $site['name']) ?></span>
      <span class="brand__tagline"><?= e((string) $site['tagline']) ?></span>
    </a>

    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="primary-navigation">
      <span class="nav-toggle__bar"></span>
      <span class="nav-toggle__bar"></span>
      <span class="nav-toggle__bar"></span>
      <span class="sr-only">Menu</span>
    </button>

    <nav id="primary-navigation" class="site-nav" aria-label="Primary">
      <?php foreach ($site['nav'] as $item) : ?>
        <?php
        $href = (string) $item['href'];
        $isCurrent = $href === $currentPath;
        ?>
        <a href="<?= e($href) ?>"<?= $isCurrent ? ' aria-current="page"' : '' ?>><?= e((string) $item['label']) ?></a>
      <?php endforeach; ?>
    </nav>

    <a class="resume-button" href="<?= e((string) ($resume['href'] ?? '#')) ?>" download="<?= e((string) ($resume['filename'] ?? 'alan-fullmer-resume.txt')) ?>">
      <?= e((string) ($resume['label'] ?? 'Download Resume')) ?>
    </a>
  </div>
</header>
