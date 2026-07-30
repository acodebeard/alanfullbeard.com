(function ($) {
  'use strict';

  $(function () {
    const container = $('[data-basic-seo-meta-image]');

    if (!container.length || !window.wp || !wp.media) {
      return;
    }

    const imageId = container.find('[data-basic-seo-meta-image-id]');
    const preview = container.find('[data-basic-seo-meta-image-preview]');
    const selectButton = container.find('[data-basic-seo-meta-image-select]');
    const removeButton = container.find('[data-basic-seo-meta-image-remove]');
    let frame;

    selectButton.on('click', function () {
      if (frame) {
        frame.open();
        return;
      }

      frame = wp.media({
        title: selectButton.data('media-title'),
        button: {
          text: selectButton.data('media-button'),
        },
        library: {
          type: 'image',
        },
        multiple: false,
      });

      frame.on('select', function () {
        const attachment = frame.state().get('selection').first().toJSON();
        const imageUrl = attachment.sizes?.medium?.url || attachment.url;

        imageId.val(attachment.id);
        preview.empty().append($('<img>', {
          src: imageUrl,
          alt: '',
        }));
        removeButton.prop('hidden', false);
      });

      frame.open();
    });

    removeButton.on('click', function () {
      imageId.val('0');
      preview.empty().append($('<span>').text('No image selected.'));
      removeButton.prop('hidden', true);
    });
  });
}(jQuery));
