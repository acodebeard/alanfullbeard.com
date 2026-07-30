<?php

declare(strict_types=1);

get_header();

while (have_posts()) :
    the_post();
    ?>
    <article id="post-<?php the_ID(); ?>" <?php post_class('entry'); ?>>
      <h1 class="entry-title"><?php the_title(); ?></h1>
      <div class="entry-content">
        <?php the_content(); ?>
      </div>
    </article>
    <?php
endwhile;

get_footer();
