const { test, expect } = require('@playwright/test');

for (const pagePath of ['./', './contact/', './privacy-policy/']) {
  test(`${pagePath} has basic search and social metadata`, async ({ page }) => {
    await page.goto(pagePath);

    const descriptions = page.locator('meta[name="description"]');

    await expect(descriptions).toHaveCount(1);
    const content = await descriptions.getAttribute('content');

    expect(content.trim().length).toBeGreaterThan(50);
    await expect(page.locator('meta[property="og:title"]')).toHaveCount(1);
    await expect(page.locator('meta[property="og:description"]')).toHaveCount(1);
    await expect(page.locator('meta[property="og:url"]')).toHaveCount(1);
    await expect(page.locator('meta[name="twitter:card"]')).toHaveCount(1);
    await expect(page.locator('meta[name="twitter:title"]')).toHaveCount(1);

    const jsonLd = page.locator('script[type="application/ld+json"]');

    await expect(jsonLd).toHaveCount(1);
    const schema = JSON.parse(await jsonLd.textContent());
    const types = schema['@graph'].map((item) => item['@type']);

    expect(schema['@context']).toBe('https://schema.org');
    expect(types).toContain('WebSite');
    expect(types).toContain('Person');

    if ('./' === pagePath) {
      expect(types).toContain('ProfilePage');
    } else if ('./contact/' === pagePath) {
      expect(types).toContain('ContactPage');
    } else {
      expect(types).toContain('WebPage');
    }
  });
}
