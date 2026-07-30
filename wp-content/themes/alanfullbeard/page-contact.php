<?php

/*
Template Name: Contact
*/

declare(strict_types=1);

$smsUrl = alanfullbeard_lcars_contact_sms_url();
$smsLabel = alanfullbeard_lcars_contact_sms_setting('label');

get_header();
?>
<article class="entry entry--contact">
  <header class="page-intro">
    <p class="eyebrow"><?php esc_html_e('Start a conversation', 'alanfullbeard-lcars'); ?></p>
    <h1 class="entry-title"><?php esc_html_e('Contact Alan', 'alanfullbeard-lcars'); ?></h1>
    <p><?php esc_html_e('Have a project in mind, need help with an existing site, or want to discuss a plugin? Send a message below.', 'alanfullbeard-lcars'); ?></p>
    <?php if ('' !== $smsUrl) : ?>
      <p class="contact-page__sms">
        <span><?php esc_html_e('Prefer to text?', 'alanfullbeard-lcars'); ?></span>
        <a class="button contact-page__sms-link" href="<?php echo esc_url($smsUrl, ['sms']); ?>"><?php echo esc_html($smsLabel); ?></a>
      </p>
    <?php endif; ?>
    <p><?php esc_html_e('Prefer email?', 'alanfullbeard-lcars'); ?> <a href="mailto:alan@alanfullbeard.com">alan@alanfullbeard.com</a></p>
  </header>

  <?php get_template_part('template-parts/contact-form'); ?>
</article>
<?php
get_footer();
