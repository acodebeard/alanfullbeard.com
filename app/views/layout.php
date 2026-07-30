<?php

declare(strict_types=1);

use function AlanFullbeard\e;

/** @var string $content */
/** @var array<string, mixed> $page */
/** @var array<string, mixed> $site */
/** @var string $root */

$title = (string) ($page['title'] ?? $site['name']);
$description = (string) ($page['description'] ?? $site['tagline']);
$path = (string) ($page['path'] ?? '/');
$styles = $page['styles'] ?? [];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?></title>
  <meta name="description" content="<?= e($description) ?>">
  <meta name="author" content="Alan Fullmer">
  <meta name="robots" content="index, follow">
  <meta name="theme-color" content="#365875">
  <link rel="canonical" href="https://alanfullbeard.com<?= e($path === '/' ? '' : $path) ?>">
  <link rel="stylesheet" href="/assets/css/site.css">
  <link rel="stylesheet" href="/assets/css/utilities.css">
  <?php if (is_array($styles)) : ?>
    <?php foreach ($styles as $style) : ?>
      <link rel="stylesheet" href="<?= e((string) $style) ?>">
    <?php endforeach; ?>
  <?php endif; ?>
</head>
<body id="page-<?= e((string) ($page['slug'] ?? 'page')) ?>">
  <?php require $root . '/app/views/partials/header.php'; ?>
  <main id="site-content" tabindex="-1">
    <?= $content ?>
  </main>
  <?php require $root . '/app/views/partials/footer.php'; ?>
  <script src="/assets/js/site.js" defer></script>
</body>
</html>
