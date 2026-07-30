<?php

declare(strict_types=1);
?>
  </main>

  <footer class="site-footer">
    <div class="lcars-footerline" aria-hidden="true">
      <span class="lcars-pill lcars-pill--violet"></span>
      <span class="lcars-pill"></span>
      <span class="lcars-pill lcars-pill--cyan lcars-pill--end"></span>
    </div>

    <div class="site-footer__content">
      <p>&copy; <?php echo esc_html(gmdate('Y')); ?> <?php bloginfo('name'); ?></p>
      <a href="<?php echo esc_url(home_url('/privacy-policy/')); ?>"><?php esc_html_e('Privacy Policy', 'alanfullbeard-lcars'); ?></a>
    </div>
  </footer>
</div>
<?php wp_footer(); ?>
</body>
</html>
