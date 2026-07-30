<?php

declare(strict_types=1);

get_header();

if (have_posts()) :
    while (have_posts()) :
        the_post();
        ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class('entry'); ?>>
          <h1 class="entry-title">
            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
          </h1>

          <div class="entry-meta">
            <?php echo esc_html(get_the_date()); ?>
          </div>

          <div class="entry-content">
            <?php the_content(); ?>
          </div>
        </article>
        <?php
    endwhile;

    the_posts_navigation();
else :
    ?>
    <section class="not-found">
      <h1 class="entry-title"><?php esc_html_e('No transmission found', 'alanfullbeard-lcars'); ?></h1>
      <div class="entry-content">
        <p><?php esc_html_e('No content matched this request.', 'alanfullbeard-lcars'); ?></p>
      </div>
    </section>
    <?php
endif;

get_footer();
