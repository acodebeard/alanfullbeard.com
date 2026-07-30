(() => {
  const button = document.querySelector('.nav-toggle');
  const nav = document.getElementById('primary-navigation');

  if (!button || !nav) return;

  button.addEventListener('click', () => {
    const isOpen = button.getAttribute('aria-expanded') === 'true';
    button.setAttribute('aria-expanded', String(!isOpen));
    nav.classList.toggle('is-open', !isOpen);
  });
})();

(() => {
  const KEY = 'odometer_years_v1';
  const odometer = document.querySelector('.odometer');
  const track = odometer?.querySelector(':scope > div');
  if (!odometer || !track) return;

  const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  const setFinal = () => {
    odometer.classList.add('odometer--final');
    const sr = document.getElementById('yearsSr');
    if (sr) sr.textContent = '14 years in web development and design.';
  };

  try {
    if (prefersReduced || localStorage.getItem(KEY)) {
      setFinal();
      return;
    }

    track.addEventListener('animationend', () => {
      try {
        localStorage.setItem(KEY, '1');
      } catch {}
      setFinal();
    }, {
      once: true,
    });
  } catch {
    setFinal();
  }
})();
