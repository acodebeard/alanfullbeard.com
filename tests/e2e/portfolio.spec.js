const { test, expect } = require('@playwright/test');

const projects = [
  {
    title: 'Phoenix New Times',
    url: 'https://phoenixnewtimes.com',
  },
  {
    title: 'Destination Kona Coast',
    url: 'https://destinationkonacoast.org',
  },
  {
    title: 'Red Meat',
    url: 'https://redmeat.com',
  },
  {
    title: 'AAA Gas & Plumbing',
    url: 'https://anythinggas.com',
  },
  {
    title: 'Laffs Comedy Caffé',
    url: 'https://laffstucson.com',
  },
];

test('Portfolio page renders the homepage project links through LCARS cards', async ({ page }) => {
  const response = await page.goto('./portfolio/');

  expect(response).not.toBeNull();
  expect(response.ok()).toBe(true);
  await expect(page.getByRole('heading', { level: 1, name: 'Portfolio' })).toBeVisible();
  await expect(page.locator('.portfolio-page__lede')).toContainText(
    'Selected web work'
  );

  const cards = page.locator('article.portfolio-card');
  await expect(cards).toHaveCount(projects.length);

  for (const [index, project] of projects.entries()) {
    const card = cards.nth(index);
    const link = card.locator('.portfolio-card__link');
    const image = card.locator(
      '.portfolio-card__image:not(.portfolio-card__image--placeholder)'
    );

    await expect(card.getByRole('heading', { level: 2 })).toHaveText(project.title);
    await expect(card.locator('.portfolio-card__summary')).not.toBeEmpty();
    await expect(link).toHaveAttribute('href', project.url);
    await expect(link).toHaveAttribute('target', '_blank');
    await expect(link).toHaveAttribute('rel', 'noopener noreferrer');
    await expect(link.locator('.screen-reader-text')).toHaveText(
      '(opens in a new tab)'
    );
    await expect(image).toHaveCount(1);
    await expect(image).not.toHaveAttribute('alt', '');
    await expect
      .poll(() => image.evaluate((element) => element.naturalWidth))
      .toBeGreaterThan(0);
  }
});

test('Portfolio headlines are white and left images stay in their grid column', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.goto('./portfolio/');

  const pageTitle = page.getByRole('heading', { level: 1, name: 'Portfolio' });
  const cardTitles = page.locator('.portfolio-card__title');

  await expect
    .poll(() => pageTitle.evaluate((element) => getComputedStyle(element).color))
    .toBe('rgb(255, 255, 255)');

  for (let index = 0; index < await cardTitles.count(); index += 1) {
    await expect
      .poll(() => cardTitles.nth(index).evaluate((element) => getComputedStyle(element).color))
      .toBe('rgb(255, 255, 255)');
  }

  const leftCards = page.locator('.portfolio-card--image-left');
  await expect(leftCards).toHaveCount(2);

  for (let index = 0; index < await leftCards.count(); index += 1) {
    const card = leftCards.nth(index);
    const mediaBox = await card.locator('.portfolio-card__media').boundingBox();
    const imageBox = await card.locator('.portfolio-card__image').boundingBox();
    const bodyBox = await card.locator('.portfolio-card__body').boundingBox();

    expect(mediaBox).not.toBeNull();
    expect(imageBox).not.toBeNull();
    expect(bodyBox).not.toBeNull();
    expect(mediaBox.x + mediaBox.width).toBeLessThanOrEqual(bodyBox.x);
    expect(imageBox.x).toBeGreaterThanOrEqual(mediaBox.x - 1);
    expect(imageBox.x + imageBox.width).toBeLessThanOrEqual(
      mediaBox.x + mediaBox.width + 1
    );
  }
});

test('Portfolio description links open in isolated new tabs and announce the behavior', async ({ page }) => {
  await page.goto('./portfolio/');

  const descriptionLinks = page.locator('.portfolio-card__summary a');
  expect(await descriptionLinks.count()).toBeGreaterThan(0);

  for (let index = 0; index < await descriptionLinks.count(); index += 1) {
    const descriptionLink = descriptionLinks.nth(index);
    await expect(descriptionLink).toHaveAttribute('target', '_blank');

    const rel = (await descriptionLink.getAttribute('rel')) || '';
    expect(rel.split(/\s+/)).toContain('noopener');
    expect(rel.split(/\s+/)).toContain('noreferrer');

    const describedBy = (await descriptionLink.getAttribute('aria-describedby')) || '';
    const noticeId = describedBy
      .split(/\s+/)
      .find((value) => value.startsWith('portfolio-card-new-tab-'));

    expect(noticeId).toBeTruthy();
    await expect(page.locator(`#${noticeId}`)).toHaveText('(opens in a new tab)');
  }
});

test('Portfolio cards remain within the mobile viewport', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('./portfolio/');

  const overflow = await page.evaluate(() => ({
    documentWidth: document.documentElement.scrollWidth,
    viewportWidth: document.documentElement.clientWidth,
  }));

  expect(overflow.documentWidth).toBeLessThanOrEqual(overflow.viewportWidth);
  await expect(page.locator('article.portfolio-card')).toHaveCount(projects.length);
  await expect(page.locator('.portfolio-card__media').first()).toBeVisible();
});
