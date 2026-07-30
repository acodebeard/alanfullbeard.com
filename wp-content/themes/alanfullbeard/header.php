<?php

declare(strict_types=1);
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div class="site-shell">
  <a class="skip-link" href="#site-content"><?php esc_html_e('Skip to content', 'alanfullbeard-lcars'); ?></a>

  <span class="site-header-sentinel" aria-hidden="true"></span>

  <header class="site-header">
    <div class="lcars-topline" aria-hidden="true" hidden>
      <span class="lcars-pill lcars-pill--elbow"></span>
      <span class="lcars-pill lcars-pill--gold"></span>
      <span class="lcars-pill lcars-pill--short lcars-pill--violet"></span>
      <span class="lcars-pill lcars-pill--cyan"></span>
    </div>


    <div class="site-header__content">
      <a class="brand" href="<?php echo esc_url(home_url('/')); ?>" rel="home">
        <span class="brand__name"><?php bloginfo('name'); ?></span>
        <span class="brand__tagline"><?php bloginfo('description'); ?></span>
      </a>

      <button
        class="site-nav__toggle"
        type="button"
        aria-controls="primary-navigation"
        aria-expanded="false"
        hidden
      >
        <span class="site-nav__toggle-text sr-only"><?php esc_html_e('Menu', 'alanfullbeard-lcars'); ?></span>
        <span class="site-nav__toggle-icon" aria-hidden="true">
          <span></span>
          <span></span>
          <span></span>
        </span>
      </button>

      <nav id="primary-navigation" class="site-nav" aria-label="<?php esc_attr_e('Primary navigation', 'alanfullbeard-lcars'); ?>">
        <?php
        wp_nav_menu([
            'theme_location' => 'primary',
            'container' => false,
            'fallback_cb' => 'wp_page_menu',
            'depth' => 2,
            'link_before' => '<span>',
            'link_after' => '</span>',
        ]);
        ?>
      </nav>
    </div>
  </header>

  <main id="site-content" class="site-main">
