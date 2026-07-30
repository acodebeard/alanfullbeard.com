(function () {
  'use strict';

  const header = document.querySelector('.site-header');

  if (!header) {
    return;
  }

  const sentinel = document.querySelector('.site-header-sentinel');
  const nav = header.querySelector('.site-nav');
  const navToggle = header.querySelector('.site-nav__toggle');
  const mobileNavigation = window.matchMedia('(max-width: 800px)');

  function setNavigationOpen(nextState, returnFocus) {
    if (!nav || !navToggle) {
      return;
    }

    const isOpen = mobileNavigation.matches && nextState;

    nav.classList.toggle('site-nav--open', isOpen);
    header.classList.toggle('site-header--nav-open', isOpen);
    navToggle.setAttribute('aria-expanded', String(isOpen));

    if (mobileNavigation.matches) {
      nav.setAttribute('aria-hidden', String(!isOpen));
    } else {
      nav.removeAttribute('aria-hidden');
    }

    if (returnFocus) {
      navToggle.focus();
    }
  }

  function syncMobileNavigation() {
    if (!nav || !navToggle) {
      return;
    }

    header.classList.toggle('site-header--nav-ready', mobileNavigation.matches);
    navToggle.hidden = !mobileNavigation.matches;
    setNavigationOpen(false, false);
  }

  if (nav && navToggle) {
    navToggle.addEventListener('click', function () {
      setNavigationOpen(navToggle.getAttribute('aria-expanded') !== 'true', false);
    });

    nav.addEventListener('click', function (event) {
      if (event.target.closest('a')) {
        setNavigationOpen(false, false);
      }
    });

    document.addEventListener('click', function (event) {
      if (!header.contains(event.target)) {
        setNavigationOpen(false, false);
      }
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && navToggle.getAttribute('aria-expanded') === 'true') {
        setNavigationOpen(false, true);
      }
    });

    mobileNavigation.addEventListener('change', syncMobileNavigation);
    syncMobileNavigation();
  }

  if (sentinel && 'IntersectionObserver' in window) {
    const compactObserver = new IntersectionObserver(function (entries) {
      const sentinelEntry = entries[0];

      if (sentinelEntry) {
        header.classList.toggle('site-header--compact', !sentinelEntry.isIntersecting);
      }
    });

    compactObserver.observe(sentinel);
  }
})();
