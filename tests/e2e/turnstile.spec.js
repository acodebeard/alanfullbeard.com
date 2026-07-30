const { test, expect } = require('@playwright/test');

for (const [pageName, pagePath] of [
  ['Home', './'],
  ['Privacy Policy', './privacy-policy/'],
  ['Portfolio', './portfolio/'],
]) {
  test(`Turnstile does not load on the ${pageName} page`, async ({ page }) => {
    const turnstileRequests = [];

    page.on('request', (request) => {
      if (request.url().includes('challenges.cloudflare.com')) {
        turnstileRequests.push(request.url());
      }
    });

    await page.goto(pagePath);
    await page.waitForLoadState('networkidle');

    expect(turnstileRequests).toEqual([]);
    await expect(page.locator('script[src*="challenges.cloudflare.com"]')).toHaveCount(0);
    await expect.poll(() => page.evaluate(() => typeof window.turnstile)).toBe('undefined');
  });
}

test('configured Turnstile initializes without browser errors', async ({ page }) => {
  const diagnostics = [];

  page.on('console', (message) => {
    if ('error' === message.type()) {
      diagnostics.push(`console error: ${message.text()}`);
    }
  });
  page.on('pageerror', (error) => {
    diagnostics.push(`page error: ${error.message}`);
  });
  page.on('requestfailed', (request) => {
    if (request.url().includes('challenges.cloudflare.com')) {
      const url = new URL(request.url());
      diagnostics.push(`request failed: ${url.origin}${url.pathname}`);
    }
  });

  await page.route('**/contact/', async (route) => {
    const response = await route.fetch();
    const body = await response.text();
    const testBody = body.replace(
      /data-sitekey="[^"]+"/,
      'data-sitekey="1x00000000000000000000AA"'
    );

    await route.fulfill({
      response,
      body: testBody,
    });
  });

  await page.goto('./contact/');

  const widget = page.locator('.cf-turnstile');
  await expect(widget).toHaveCount(1);
  await expect(widget).toHaveAttribute('data-appearance', 'interaction-only');
  await expect.poll(
    () => page.evaluate(() => typeof window.turnstile),
    { timeout: 10000 }
  ).toBe('object');

  expect(
    diagnostics,
    `Turnstile diagnostics:\n${diagnostics.join('\n')}`
  ).toEqual([]);
  await expect(page.locator('iframe[src*="challenges.cloudflare.com"]')).toHaveCount(0);
});
