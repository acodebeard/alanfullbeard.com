(function () {
  'use strict';

  const KEY_PREFIX = 'alanfullbeard_odometer_years_v1_';
  const ANIMATE_CLASS = 'odometer--animate';
  const FINAL_CLASS = 'odometer--final';
  const ANIMATION_FALLBACK_MS = 9000;
  const odometer = document.querySelector('.odometer[data-odometer-years]');

  if (!odometer) {
    return;
  }

  const key = KEY_PREFIX + odometer.dataset.odometerYears;

  function setFinal() {
    odometer.classList.remove(ANIMATE_CLASS);
    odometer.classList.add(FINAL_CLASS);
  }

  function rememberAnimation() {
    try {
      localStorage.setItem(key, '1');
    } catch (error) {
      // Storage can be unavailable in private modes; the visual final state still matters.
    }

    setFinal();
  }

  if (
    window.matchMedia &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches
  ) {
    rememberAnimation();
    return;
  }

  try {
    if (localStorage.getItem(key) === '1') {
      setFinal();
      return;
    }
  } catch (error) {
    setFinal();
    return;
  }

  odometer.classList.add(ANIMATE_CLASS);
  odometer.addEventListener('animationend', rememberAnimation, { once: true });
  window.setTimeout(rememberAnimation, ANIMATION_FALLBACK_MS);
}());
