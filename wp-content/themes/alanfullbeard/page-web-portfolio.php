<?php
/**
 * Template Name: Web Portfolio
 */

declare(strict_types=1);

get_header();

while (have_posts()) :
    the_post();
    ?>
    <article id="post-<?php the_ID(); ?>" <?php post_class('entry entry--web-portfolio portfolio-page'); ?>>
      <header class="page-intro portfolio-page__intro">
        <p class="eyebrow"><?php esc_html_e('Selected work', 'alanfullbeard-lcars'); ?></p>
        <h1 class="entry-title"><?php the_title(); ?></h1>
      </header>

      <div class="entry-content portfolio-page__content">
        <?php the_content(); ?>
      </div>
    </article>
    <?php
endwhile;

get_footer();
