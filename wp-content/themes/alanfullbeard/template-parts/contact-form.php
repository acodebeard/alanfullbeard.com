<?php

declare(strict_types=1);
?>
<section class="contact-form" id="contact-form" aria-labelledby="contact-form-heading">
  <div class="contact-form__heading">
    <p class="eyebrow"><?php esc_html_e('Secure channel', 'alanfullbeard-lcars'); ?></p>
    <h2 id="contact-form-heading"><?php esc_html_e('Send a message', 'alanfullbeard-lcars'); ?></h2>
  </div>

  <div class="contact-form__body">
    <?php alanfullbeard_lcars_render_contact_form(); ?>
  </div>
</section>
