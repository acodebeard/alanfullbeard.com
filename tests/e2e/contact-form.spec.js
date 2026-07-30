const { test, expect } = require('@playwright/test');

test('the former accessible-form URL redirects to Contact', async ({ page }) => {
  await page.goto('./accessible-form/');

  await expect(page).toHaveURL(/\/contact\/$/);
  await expect(page.getByRole('heading', { level: 1, name: 'Contact Alan' })).toBeVisible();
});

test('the Home contact action opens the Contact page', async ({ page }) => {
  await page.goto('./');

  await expect(page.getByRole('link', { name: 'Send a message' })).toHaveAttribute(
    'href',
    /\/contact\/$/
  );
});

for (const viewport of [
  { width: 320, height: 700 },
  { width: 390, height: 844 },
  { width: 1440, height: 1000 },
]) {
  test(`contact form fits at ${viewport.width}px`, async ({ page }) => {
    await page.setViewportSize(viewport);
    await page.goto('./contact/');
    await page.evaluate(() => document.fonts.ready);

    await expect(page.getByRole('heading', { level: 1, name: 'Contact Alan' })).toBeVisible();
    await expect(page.getByRole('textbox', { name: 'Name Required', exact: true })).toBeVisible();
    await expect(page.getByRole('textbox', { name: 'Email Required', exact: true })).toBeVisible();
    await expect(page.getByRole('textbox', { name: 'Phone Optional', exact: true })).toBeVisible();
    await expect(page.getByRole('radio', { name: "No, please don't text me" })).toBeChecked();
    await expect(page.getByRole('radio', { name: 'Yes, you can text me' })).not.toBeChecked();
    await expect(page.getByRole('textbox', { name: 'Message Required', exact: true })).toBeVisible();
    await expect(page.locator('input[name="message-storage"]')).toHaveCount(0);
    await expect(page.locator('#contact-form-privacy')).toContainText(
      'Submitting this form privately stores an encrypted copy in WordPress for spam review and follow-up for up to 180 days.'
    );
    await expect(page.locator('#contact-form-privacy')).toContainText(
      'The site may also attempt to send Alan an email notification.'
    );
    await expect(page.getByRole('button', { name: 'Send message' })).toBeVisible();
    await expect(page.locator('.contact-form__turnstile .wpcf7-turnstile')).toHaveAttribute(
      'data-action',
      'contact'
    );
    await expect(page.locator('.contact-form__turnstile .wpcf7-turnstile')).toHaveAttribute(
      'data-appearance',
      'interaction-only'
    );
    await expect(page.locator('.contact-form__turnstile .wpcf7-turnstile')).toHaveAttribute(
      'data-size',
      'compact'
    );
    await expect(page.locator('input[name="username"]')).toHaveCount(0);
    await expect(page.locator('input[type="password"]')).toHaveCount(0);

    const layout = await page.locator('.contact-form').evaluate((element) => ({
      right: element.getBoundingClientRect().right,
      scrollWidth: document.documentElement.scrollWidth,
      viewportWidth: window.innerWidth,
    }));

    expect(layout.right).toBeLessThanOrEqual(layout.viewportWidth + 1);
    expect(layout.scrollWidth).toBeLessThanOrEqual(layout.viewportWidth + 1);

    const actionOrder = await page.locator('.contact-form__actions').evaluate((element) =>
      [...element.children].map((child) => child.className)
    );

    expect(actionOrder[0]).toContain('contact-form__turnstile');
    expect(actionOrder[1]).toContain('contact-form__submit');
  });
}

test('contact form returns accessible server-side errors', async ({ page }) => {
  await page.goto('./contact/');

  await page.getByRole('button', { name: 'Send message' }).click();

  await expect(page.locator('#contact-name')).toHaveAttribute('aria-invalid', 'true');
  await expect(page.locator('#contact-email')).toHaveAttribute('aria-invalid', 'true');
  await expect(page.locator('#contact-message')).toHaveAttribute('aria-invalid', 'true');
  await expect(page.locator('.wpcf7-response-output')).toContainText(
    'One or more fields have an error.'
  );
});

test('permission to text requires a phone number and preserves the choice', async ({ page }) => {
  await page.goto('./contact/');

  await page.getByRole('textbox', { name: 'Name Required', exact: true }).fill('Test Visitor');
  await page.getByRole('textbox', { name: 'Email Required', exact: true }).fill('visitor@example.org');
  await page.getByRole('textbox', { name: 'Message Required', exact: true }).fill(
    'Testing permission validation.'
  );
  await page.getByRole('radio', { name: 'Yes, you can text me' }).check();
  await page.getByRole('button', { name: 'Send message' }).click();

  await expect(page.locator('#contact-phone')).toHaveAttribute('aria-invalid', 'true');
  await expect(
    page.locator('[data-name="your-phone"] .wpcf7-not-valid-tip')
  ).toHaveText('Enter a phone number if Alan may text you.');
  await expect(page.getByRole('radio', { name: 'Yes, you can text me' })).toBeChecked();
});
