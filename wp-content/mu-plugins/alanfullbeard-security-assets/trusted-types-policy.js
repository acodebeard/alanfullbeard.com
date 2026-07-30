(() => {
  'use strict';

  if (
    !window.trustedTypes
    || !window.DOMPurify
    || 'function' !== typeof window.DOMPurify.sanitize
  ) {
    return;
  }

  const allowedScriptUrl = (value) => {
    const url = new URL(String(value), document.baseURI);
    const isSameOrigin = url.origin === window.location.origin;
    const isTurnstile = (
      'https:' === url.protocol
      && 'challenges.cloudflare.com' === url.hostname
    );

    if (!isSameOrigin && !isTurnstile) {
      throw new TypeError(`Blocked script URL origin: ${url.origin}`);
    }

    if (!['http:', 'https:'].includes(url.protocol)) {
      throw new TypeError(`Blocked script URL protocol: ${url.protocol}`);
    }

    return url.href;
  };

  window.trustedTypes.createPolicy('default', {
    createHTML: (value) => window.DOMPurify.sanitize(String(value), {
      RETURN_TRUSTED_TYPE: false,
      FORBID_TAGS: ['script', 'base', 'object', 'embed', 'iframe'],
      FORBID_ATTR: ['srcdoc'],
    }),
    createScriptURL: allowedScriptUrl,
  });

  // Initialize DOMPurify's private policy before any third-party script runs.
  // DOMPurify uses it only to wrap content after sanitization.
  window.DOMPurify.sanitize('', {
    RETURN_TRUSTED_TYPE: false,
  });
})();
