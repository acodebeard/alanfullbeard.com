const { test, expect } = require('@playwright/test');

const publicPages = [
  ['Home', './'],
  ['Contact', './contact/'],
  ['Privacy Policy', './privacy-policy/'],
];

for (const [pageName, pagePath] of publicPages) {
  test(`${pageName} enforces the public security-header baseline`, async ({ page }) => {
    const response = await page.goto(pagePath);
    expect(response).not.toBeNull();

    const headers = response.headers();
    const csp = headers['content-security-policy'] || '';

    expect(headers['cross-origin-opener-policy']).toBe('same-origin');
    expect(headers['x-frame-options']).toBe('SAMEORIGIN');
    expect(headers['x-content-type-options']).toBe('nosniff');
    expect(csp).toContain("script-src 'nonce-");
    expect(csp).toContain("'strict-dynamic'");
    expect(csp).toContain("script-src-attr 'none'");
    expect(csp).toContain("frame-ancestors 'self'");
    expect(csp).toContain("trusted-types default dompurify");
    expect(csp).toContain("require-trusted-types-for 'script'");
    expect(csp).not.toContain("'unsafe-eval'");

    const executableScriptsWithoutNonce = await page.locator(
      'script:not([nonce]):not([type="application/ld+json"]):not([type="application/json"])'
    ).count();

    expect(executableScriptsWithoutNonce).toBe(0);
  });
}

test('Trusted Types sanitizes legacy HTML sinks and rejects unapproved script URLs', async ({ page, browserName }) => {
  test.skip('chromium' !== browserName, 'Trusted Types enforcement is Chromium-specific.');

  await page.goto('./contact/');

  const result = await page.evaluate(() => {
    const fixture = document.createElement('div');
    fixture.innerHTML = '<img src="x" onerror="window.__afbXss = true"><script>window.__afbXss = true</script>';

    let unapprovedScriptUrlBlocked = false;
    const script = document.createElement('script');

    try {
      script.src = 'https://example.invalid/unapproved.js';
    } catch (error) {
      unapprovedScriptUrlBlocked = error instanceof TypeError;
    }

    return {
      sanitizerLoaded: Boolean(window.DOMPurify),
      eventHandlerRemoved: !fixture.querySelector('img')?.hasAttribute('onerror'),
      scriptRemoved: !fixture.querySelector('script'),
      unapprovedScriptUrlBlocked,
      payloadExecuted: Boolean(window.__afbXss),
    };
  });

  expect(result).toEqual({
    sanitizerLoaded: true,
    eventHandlerRemoved: true,
    scriptRemoved: true,
    unapprovedScriptUrlBlocked: true,
    payloadExecuted: false,
  });
});
