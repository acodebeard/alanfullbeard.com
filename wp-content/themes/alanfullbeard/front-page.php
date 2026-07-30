<?php

declare(strict_types=1);

get_header();

$experienceYears = alanfullbeard_lcars_front_page_years();
$heroImageId = alanfullbeard_lcars_front_page_hero_image_id();
$heroImage = '';

if ($heroImageId > 0) {
  $heroImage = wp_get_attachment_image(
    $heroImageId,
    'large',
    false,
    [
      'class' => 'home-hero__image',
      'alt' => alanfullbeard_lcars_front_page_hero_image_alt(),
      'loading' => 'eager',
      'decoding' => 'async',
    ]
  );
}

$heroClasses = ['home-hero'];
$portfolioLinks = alanfullbeard_lcars_front_page_links('portfolio_links');
$pluginLinks = alanfullbeard_lcars_front_page_links('plugins_links');
$contactLinks = alanfullbeard_lcars_front_page_links('contact_links');
$portfolioHeading = alanfullbeard_lcars_front_page_text('portfolio_heading');
$portfolioHeadingUrl = alanfullbeard_lcars_front_page_url('portfolio_heading_url');
$pluginsHeading = alanfullbeard_lcars_front_page_text('plugins_heading');
$pluginsHeadingUrl = alanfullbeard_lcars_front_page_url('plugins_heading_url');
$contactHeading = alanfullbeard_lcars_front_page_text('contact_heading');
$contactHeadingUrl = alanfullbeard_lcars_front_page_url('contact_heading_url');
$contactSmsUrl = alanfullbeard_lcars_contact_sms_url();
$contactSmsLabel = alanfullbeard_lcars_contact_sms_setting('label');

if ('' !== $heroImage) {
  $heroClasses[] = 'home-hero--has-image';
}
?>
<section class="<?php echo esc_attr(implode(' ', $heroClasses)); ?>" aria-labelledby="home-heading">
  <div class="home-experience">
      <div class="odometer" data-odometer-years="<?php echo esc_attr((string) $experienceYears); ?>" aria-hidden="true">
        <div>
          <?php for ($year = 1; $year <= $experienceYears; $year++) : ?>
            <span><?php echo esc_html((string) $year); ?></span>
          <?php endfor; ?>
        </div>
      </div>
      <span class="home-experience__label"><?php echo esc_html(alanfullbeard_lcars_front_page_text('hero_experience_label')); ?></span>
      <p class="screen-reader-text" id="yearsSr"><?php echo esc_html(alanfullbeard_lcars_front_page_years_label($experienceYears)); ?></p>
    </div>
  <div class="home-hero__content-wrap">
    <div class="home-hero__content">
      <p class="eyebrow" hidden><?php echo esc_html(alanfullbeard_lcars_front_page_text('hero_eyebrow')); ?></p>
      <h1 id="home-heading" class="entry-title"><?php echo esc_html(alanfullbeard_lcars_front_page_text('hero_heading')); ?></h1>
      <p class="home-hero__summary"><?php echo esc_html(alanfullbeard_lcars_front_page_text('hero_summary')); ?></p>
    </div>

  <?php if ('' !== $heroImage) : ?>
    <figure class="home-hero__media">
      <?php echo $heroImage; ?>
    </figure>
  <?php endif; ?>
  </div>
</section>
    <div class="lcars-gridline" aria-hidden="true">
      <span></span>
      <span></span>
      <span></span>
      <span></span>
    </div>
<section class="summary-grid" aria-label="<?php esc_attr_e('Primary resume sections', 'alanfullbeard-lcars'); ?>">
  <article class="summary-card">
    <p class="eyebrow"><?php echo esc_html(alanfullbeard_lcars_front_page_text('portfolio_eyebrow')); ?></p>
    <h2>
      <?php if ('' !== $portfolioHeadingUrl) : ?>
        <a class="summary-card__heading-link" href="<?php echo esc_url($portfolioHeadingUrl); ?>"><?php echo esc_html($portfolioHeading); ?></a>
      <?php else : ?>
        <?php echo esc_html($portfolioHeading); ?>
      <?php endif; ?>
    </h2>
    <p><?php echo esc_html(alanfullbeard_lcars_front_page_text('portfolio_body')); ?></p>
    <?php if ([] !== $portfolioLinks) : ?>
      <div class="summary-card__links">
        <?php foreach ($portfolioLinks as $link) : ?>
          <a target="_blank" class="button summary-card__action" href="<?php echo esc_url($link['url']); ?>"><?php echo esc_html($link['label']); ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </article>
  <article class="summary-card" id="plugins">
    <p class="eyebrow"><?php echo esc_html(alanfullbeard_lcars_front_page_text('plugins_eyebrow')); ?></p>
    <h2>
      <?php if ('' !== $pluginsHeadingUrl) : ?>
        <a class="summary-card__heading-link" href="<?php echo esc_url($pluginsHeadingUrl); ?>"><?php echo esc_html($pluginsHeading); ?></a>
      <?php else : ?>
        <?php echo esc_html($pluginsHeading); ?>
      <?php endif; ?>
    </h2>
    <p><?php echo esc_html(alanfullbeard_lcars_front_page_text('plugins_body')); ?></p>
    <?php if ([] !== $pluginLinks) : ?>
      <div class="summary-card__links">
        <?php foreach ($pluginLinks as $link) : ?>
          <a target="_blank" class="button summary-card__action" href="<?php echo esc_url($link['url']); ?>"><?php echo esc_html($link['label']); ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </article>
  <article class="summary-card">
    <p class="eyebrow"><?php echo esc_html(alanfullbeard_lcars_front_page_text('contact_eyebrow')); ?></p>
    <h2>
      <?php if ('' !== $contactHeadingUrl) : ?>
        <a class="summary-card__heading-link" href="<?php echo esc_url($contactHeadingUrl); ?>"><?php echo esc_html($contactHeading); ?></a>
      <?php else : ?>
        <?php echo esc_html($contactHeading); ?>
      <?php endif; ?>
    </h2>
    <p><?php echo esc_html(alanfullbeard_lcars_front_page_text('contact_body')); ?></p>
    <?php if ([] !== $contactLinks || '' !== $contactSmsUrl) : ?>
      <div class="summary-card__links">
        <?php foreach ($contactLinks as $link) : ?>
          <a class="button summary-card__action" href="<?php echo esc_url($link['url']); ?>"><?php echo esc_html($link['label']); ?></a>
        <?php endforeach; ?>
        <?php if ('' !== $contactSmsUrl) : ?>
          <a class="button summary-card__action" href="<?php echo esc_url($contactSmsUrl, ['sms']); ?>"><?php echo esc_html($contactSmsLabel); ?></a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </article>
</section>
<?php
// Hide bio for now
/*
<section class="content-panel" aria-labelledby="bio-heading">
  <p class="eyebrow"><?php echo esc_html(alanfullbeard_lcars_front_page_text('bio_eyebrow')); ?></p>
  <h2 id="bio-heading"><?php echo esc_html(alanfullbeard_lcars_front_page_text('bio_heading')); ?></h2>
  <div class="entry-content">
    <p><?php echo esc_html(alanfullbeard_lcars_front_page_text('bio_paragraph_1')); ?></p>
    <p><?php echo esc_html(alanfullbeard_lcars_front_page_text('bio_paragraph_2')); ?></p>
    <p><?php echo esc_html(alanfullbeard_lcars_front_page_text('bio_paragraph_3')); ?></p>
  </div>
</section>
*/ ?>

<?php

get_footer();
