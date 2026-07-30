(function (blocks, blockEditor, components, element, i18n) {
  const { registerBlockType } = blocks;
  const {
    InspectorControls,
    MediaUpload,
    MediaUploadCheck,
    RichText,
    useBlockProps,
  } = blockEditor;
  const {
    Button,
    ButtonGroup,
    Notice,
    PanelBody,
    SelectControl,
    TextControl,
  } = components;
  const { createElement: el, Fragment } = element;
  const { __ } = i18n;

  const ACCENT_OPTIONS = [
    { label: __('Orange', 'alanfullbeard-lcars'), value: 'orange' },
    { label: __('Gold', 'alanfullbeard-lcars'), value: 'gold' },
    { label: __('Cyan', 'alanfullbeard-lcars'), value: 'cyan' },
    { label: __('Violet', 'alanfullbeard-lcars'), value: 'violet' },
  ];

  const BODY_ALLOWED_FORMATS = ['core/bold', 'core/italic', 'core/link'];

  const icon = el(
    'svg',
    {
      viewBox: '0 0 24 24',
      width: '24',
      height: '24',
      'aria-hidden': true,
      focusable: false,
    },
    el('path', {
      d: 'M4 6h7v12H4zM13 6h7v4h-7zM13 12h7v2h-7zM13 16h5v2h-5z',
      fill: 'currentColor',
    })
  );

  function getAccent(value) {
    return ACCENT_OPTIONS.some((option) => option.value === value) ? value : 'orange';
  }

  function getImagePosition(value) {
    return value === 'left' ? 'left' : 'right';
  }

  function getExternalUrl(value) {
    if (typeof value !== 'string' || value.trim() === '') {
      return '';
    }

    try {
      const url = new URL(value.trim());

      return url.protocol === 'http:' || url.protocol === 'https:' ? url.href : '';
    } catch (error) {
      return '';
    }
  }

  function getImageUrl(media) {
    if (!media) {
      return '';
    }

    if (media.sizes && media.sizes.large && media.sizes.large.url) {
      return media.sizes.large.url;
    }

    return media.url || '';
  }

  function getImageAlt(media) {
    if (!media) {
      return '';
    }

    return media.alt || media.title || '';
  }

  function getCardClassName(attributes) {
    const imagePosition = getImagePosition(attributes.imagePosition);
    const accent = getAccent(attributes.accent);
    const mediaState = attributes.imageUrl
      ? 'portfolio-card--has-image'
      : 'portfolio-card--has-image portfolio-card--placeholder';

    return [
      'portfolio-card',
      `portfolio-card--image-${imagePosition}`,
      `portfolio-card--accent-${accent}`,
      mediaState,
    ].join(' ');
  }

  function renderMedia(attributes, setAttributes) {
    const image = attributes.imageUrl
      ? el(
          'figure',
          { className: 'portfolio-card__media' },
          el('img', {
            src: attributes.imageUrl,
            alt: attributes.imageAlt || '',
          })
        )
      : el(
          'div',
          {
            className: 'portfolio-card__media portfolio-card__media--placeholder',
            role: 'img',
            'aria-label': __('Screenshot placeholder', 'alanfullbeard-lcars'),
          },
          el(
            'span',
            { className: 'portfolio-card__placeholder-label' },
            __('Screenshot pending', 'alanfullbeard-lcars')
          )
        );

    if (!setAttributes) {
      return image;
    }

    return el(
      'div',
      { className: 'portfolio-card__media-editor' },
      image,
      el(
        MediaUploadCheck,
        {},
        el(MediaUpload, {
          allowedTypes: ['image'],
          value: attributes.imageId,
          onSelect: (media) =>
            setAttributes({
              imageId: media.id || 0,
              imageUrl: getImageUrl(media),
              imageAlt: getImageAlt(media),
            }),
          render: ({ open }) =>
            el(
              Button,
              {
                variant: attributes.imageUrl ? 'secondary' : 'primary',
                onClick: open,
              },
              attributes.imageUrl
                ? __('Replace image', 'alanfullbeard-lcars')
                : __('Choose image', 'alanfullbeard-lcars')
            ),
        })
      ),
      attributes.imageUrl &&
        el(
          Button,
          {
            variant: 'link',
            isDestructive: true,
            onClick: () =>
              setAttributes({
                imageId: 0,
                imageUrl: '',
                imageAlt: '',
              }),
          },
          __('Remove image', 'alanfullbeard-lcars')
        )
    );
  }

  function renderBody(attributes, setAttributes) {
    const { eyebrow, heading, meta, summary, linkUrl, linkLabel } = attributes;
    const canEdit = typeof setAttributes === 'function';
    const externalUrl = getExternalUrl(linkUrl);

    return el(
      'div',
      { className: 'portfolio-card__body' },
      canEdit
        ? el(RichText, {
            tagName: 'p',
            className: 'eyebrow portfolio-card__eyebrow',
            value: eyebrow,
            allowedFormats: [],
            placeholder: __('Project type', 'alanfullbeard-lcars'),
            onChange: (value) => setAttributes({ eyebrow: value }),
          })
        : eyebrow &&
            el(RichText.Content, {
              tagName: 'p',
              className: 'eyebrow portfolio-card__eyebrow',
              value: eyebrow,
            }),
      canEdit
        ? el(RichText, {
            tagName: 'h2',
            className: 'portfolio-card__title',
            value: heading,
            allowedFormats: [],
            placeholder: __('Project heading', 'alanfullbeard-lcars'),
            onChange: (value) => setAttributes({ heading: value }),
          })
        : heading &&
            el(RichText.Content, {
              tagName: 'h2',
              className: 'portfolio-card__title',
              value: heading,
            }),
      canEdit
        ? el(RichText, {
            tagName: 'p',
            className: 'portfolio-card__meta',
            value: meta,
            allowedFormats: ['core/bold', 'core/italic'],
            placeholder: __('Role, stack, or year', 'alanfullbeard-lcars'),
            onChange: (value) => setAttributes({ meta: value }),
          })
        : meta &&
            el(RichText.Content, {
              tagName: 'p',
              className: 'portfolio-card__meta',
              value: meta,
            }),
      canEdit
        ? el(RichText, {
            tagName: 'div',
            className: 'portfolio-card__summary',
            value: summary,
            allowedFormats: BODY_ALLOWED_FORMATS,
            placeholder: __('Short project summary.', 'alanfullbeard-lcars'),
            onChange: (value) => setAttributes({ summary: value }),
          })
        : summary &&
            el(RichText.Content, {
              tagName: 'div',
              className: 'portfolio-card__summary',
              value: summary,
            }),
      externalUrl &&
        linkLabel &&
        el(
          'p',
          { className: 'portfolio-card__action' },
          el(
            'a',
            {
              className: 'portfolio-card__link',
              href: externalUrl,
              target: '_blank',
              rel: 'noopener noreferrer',
              onClick: canEdit
                ? (event) => event.preventDefault()
                : undefined,
            },
            el('span', {}, linkLabel),
            el(
              'span',
              {
                className: 'portfolio-card__link-icon',
                'aria-hidden': true,
              },
              '↗'
            ),
            el(
              'span',
              { className: 'screen-reader-text' },
              __('(opens in a new tab)', 'alanfullbeard-lcars')
            )
          )
        )
    );
  }

  registerBlockType('alanfullbeard/portfolio-card', {
    title: __('LCARS Portfolio Card', 'alanfullbeard-lcars'),
    description: __('A portfolio case-study card with optional media, project metadata, and a call to action.', 'alanfullbeard-lcars'),
    category: 'alanfullbeard-lcars',
    icon,
    keywords: [
      __('portfolio', 'alanfullbeard-lcars'),
      __('case study', 'alanfullbeard-lcars'),
      __('project', 'alanfullbeard-lcars'),
    ],
    attributes: {
      eyebrow: {
        type: 'string',
        default: '',
      },
      heading: {
        type: 'string',
        default: '',
      },
      meta: {
        type: 'string',
        default: '',
      },
      summary: {
        type: 'string',
        default: '',
      },
      imageId: {
        type: 'number',
        default: 0,
      },
      imageUrl: {
        type: 'string',
        default: '',
      },
      imageAlt: {
        type: 'string',
        default: '',
      },
      imagePosition: {
        type: 'string',
        default: 'right',
      },
      accent: {
        type: 'string',
        default: 'orange',
      },
      linkUrl: {
        type: 'string',
        default: '',
      },
      linkLabel: {
        type: 'string',
        default: __('Visit project', 'alanfullbeard-lcars'),
      },
    },
    edit({ attributes, setAttributes }) {
      const imagePosition = getImagePosition(attributes.imagePosition);
      const blockProps = useBlockProps({
        className: getCardClassName(attributes),
      });

      return el(
        Fragment,
        {},
        el(
          InspectorControls,
          {},
          el(
            PanelBody,
            { title: __('Card Settings', 'alanfullbeard-lcars'), initialOpen: true },
            el(SelectControl, {
              label: __('Accent color', 'alanfullbeard-lcars'),
              value: getAccent(attributes.accent),
              options: ACCENT_OPTIONS,
              onChange: (value) => setAttributes({ accent: getAccent(value) }),
            }),
            el(
              ButtonGroup,
              { className: 'portfolio-card__position-control' },
              el(
                Button,
                {
                  variant: imagePosition === 'left' ? 'primary' : 'secondary',
                  onClick: () => setAttributes({ imagePosition: 'left' }),
                },
                __('Image left', 'alanfullbeard-lcars')
              ),
              el(
                Button,
                {
                  variant: imagePosition === 'right' ? 'primary' : 'secondary',
                  onClick: () => setAttributes({ imagePosition: 'right' }),
                },
                __('Image right', 'alanfullbeard-lcars')
              )
            ),
            el(TextControl, {
              label: __('Image alt text', 'alanfullbeard-lcars'),
              value: attributes.imageAlt,
              onChange: (value) => setAttributes({ imageAlt: value }),
            }),
            el(TextControl, {
              label: __('Link URL', 'alanfullbeard-lcars'),
              type: 'url',
              value: attributes.linkUrl,
              help: __('Enter a complete http or https URL. Portfolio links open in a new tab.', 'alanfullbeard-lcars'),
              onChange: (value) => setAttributes({ linkUrl: value }),
            }),
            attributes.linkUrl &&
              !getExternalUrl(attributes.linkUrl) &&
              el(
                Notice,
                {
                  status: 'warning',
                  isDismissible: false,
                  className: 'portfolio-card__url-warning',
                },
                __('Enter a valid external http or https URL.', 'alanfullbeard-lcars')
              ),
            el(TextControl, {
              label: __('Link label', 'alanfullbeard-lcars'),
              value: attributes.linkLabel,
              onChange: (value) => setAttributes({ linkLabel: value }),
            })
          )
        ),
        el(
          'article',
          blockProps,
          imagePosition === 'left' && renderMedia(attributes, setAttributes),
          renderBody(attributes, setAttributes),
          imagePosition === 'right' && renderMedia(attributes, setAttributes)
        )
      );
    },
    save() {
      return null;
    },
  });
})(window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n);
