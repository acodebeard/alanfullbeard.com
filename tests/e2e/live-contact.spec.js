const { test, expect } = require('@playwright/test');

const liveSubmissionEnabled =
  process.env.RUN_LIVE_CONTACT_SUBMISSION === '1' &&
  process.env.WP_BASE_URL === 'https://alanfullbeard.com/';

test.skip(
  !liveSubmissionEnabled,
  'Set RUN_LIVE_CONTACT_SUBMISSION=1 and the production WP_BASE_URL to send the launch test.'
);

test('live contact submission passes Turnstile and MailerSend', async ({ page }) => {
  test.slow();

  const pageErrors = [];

  page.on('pageerror', (error) => {
    pageErrors.push(error.message);
  });

  await page.goto('./contact/');

  const turnstileToken = page.locator('input[name="_wpcf7_turnstile_response"]');

  await expect(page.locator('.contact-form__turnstile .wpcf7-turnstile')).toHaveCount(1);
  await expect(turnstileToken).toHaveCount(1, { timeout: 20000 });
  await expect
    .poll(() => turnstileToken.inputValue(), { timeout: 30000 })
    .not.toBe('');

  await page.locator('#contact-name').fill('Alanfullbeard launch test');
  await page.locator('#contact-email').fill('alan@alanfullbeard.com');
  await page.locator('#contact-message').fill(
    'Automated launch verification. This confirms Contact Form 7, Turnstile, MailerSend, and automatic private WordPress storage are working on the live site.'
  );

  await expect(page.locator('#contact-phone')).toHaveValue('');
  await expect(page.getByRole('radio', { name: "No, please don't text me" })).toBeChecked();
  await expect(page.locator('input[name="message-storage"]')).toHaveCount(0);

  const feedbackResponse = page.waitForResponse(
    (response) =>
      response.request().method() === 'POST' &&
      response.url().includes('/wp-json/contact-form-7/v1/contact-forms/')
  );

  await page.getByRole('button', { name: 'Send message' }).click();

  const response = await feedbackResponse;
  const payload = await response.json();

  expect(response.status()).toBe(200);
  expect(payload.status).toBe('mail_sent');
  await expect(page.locator('.wpcf7-response-output')).toContainText(
    'Thank you for your message. It has been sent.'
  );
  expect(pageErrors).toEqual([]);
});
