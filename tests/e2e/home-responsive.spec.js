const { test, expect } = require('@playwright/test');

test('home does not load WordPress emoji assets', async ({ page }) => {
  await page.goto('./');

  await expect(page.locator('#wp-emoji-settings')).toHaveCount(0);
  await expect(page.locator('#wp-emoji-styles-inline-css')).toHaveCount(0);
  await expect(page.locator('script[src*="wp-emoji"]')).toHaveCount(0);
});

test('odometer rolls at a readable pace without an early final snap', async ({ page }) => {
  await page.goto('./');
  await page.evaluate(() => localStorage.clear());
  await page.reload();

  const odometer = page.locator('.odometer');

  await expect(odometer).toHaveClass(/odometer--animate/);
  expect(
    await odometer.locator(':scope > div').evaluate(
      (track) => getComputedStyle(track).animationDuration
    )
  ).toBe('8s');

  await page.waitForTimeout(2250);
  await expect(odometer).toHaveClass(/odometer--animate/);
});

test('Portfolio summary heading links internally without changing its resting style', async ({ page }) => {
  await page.goto('./');

  const heading = page.getByRole('heading', { level: 2, name: 'Portfolio' });
  const link = heading.locator('a.summary-card__heading-link');

  await expect(link).toHaveAttribute('href', /\/portfolio\/?$/);

  const restingStyles = await link.evaluate((element) => {
    const headingStyles = getComputedStyle(element.parentElement);
    const linkStyles = getComputedStyle(element);

    return {
      headingColor: headingStyles.color,
      headingFontFamily: headingStyles.fontFamily,
      headingFontSize: headingStyles.fontSize,
      linkColor: linkStyles.color,
      linkFontFamily: linkStyles.fontFamily,
      linkFontSize: linkStyles.fontSize,
      linkTextDecoration: linkStyles.textDecorationLine,
    };
  });

  expect(restingStyles.linkColor).toBe(restingStyles.headingColor);
  expect(restingStyles.linkFontFamily).toBe(restingStyles.headingFontFamily);
  expect(restingStyles.linkFontSize).toBe(restingStyles.headingFontSize);
  expect(restingStyles.linkTextDecoration).toBe('none');

  await link.hover();
  await expect
    .poll(() => link.evaluate((element) => getComputedStyle(element).textDecorationLine))
    .toContain('underline');

  await link.focus();
  await expect
    .poll(() => link.evaluate((element) => getComputedStyle(element).outlineStyle))
    .not.toBe('none');
});

for (const viewport of [
  { width: 320, height: 700 },
  { width: 390, height: 844 },
  { width: 1440, height: 1000 },
]) {
  test(`home hero fits at ${viewport.width}px`, async ({ page }) => {
    await page.setViewportSize(viewport);
    await page.goto('./');
    await page.evaluate(() => document.fonts.ready);

    const header = page.locator('.site-header');
    const initialHeader = await header.evaluate((element) => {
      const brandName = element.querySelector('.brand__name');
      const tagline = element.querySelector('.brand__tagline');
      const navLink = element.querySelector('.site-nav > ul > li > a');

      return {
        height: element.getBoundingClientRect().height,
        brandFontSize: Number.parseFloat(getComputedStyle(brandName).fontSize),
        navFontSize: Number.parseFloat(getComputedStyle(navLink).fontSize),
        taglineDisplay: getComputedStyle(tagline).display,
      };
    });

    await expect(page.locator('#alanfullbeard-lcars-style-css')).toHaveAttribute(
      'href',
      /\/style(?:\.min)?\.css(?:\?|$)/
    );

    const label = page.locator('.home-experience__label');
    const labelBox = await label.boundingBox();

    expect(labelBox).not.toBeNull();
    expect(labelBox.x + labelBox.width).toBeLessThanOrEqual(viewport.width + 1);
    const labelWidths = await label.evaluate((element) => ({
      client: element.clientWidth,
      scroll: element.scrollWidth,
    }));

    expect(labelWidths.scroll).toBeLessThanOrEqual(labelWidths.client + 1);

    const media = page.locator('.home-hero__media');

    if (await media.count()) {
      const mediaBox = await media.boundingBox();

      expect(mediaBox).not.toBeNull();
      expect(mediaBox.width).toBeGreaterThanOrEqual(180);
      expect(mediaBox.x + mediaBox.width).toBeLessThanOrEqual(viewport.width + 1);

      const image = media.locator('img');
      const imageSize = await image.evaluate((element) => ({
        height: element.naturalHeight,
        width: element.naturalWidth,
      }));

      expect(imageSize.width).toBeGreaterThan(0);
      expect(imageSize.height).toBeGreaterThan(0);
    }

    const linkGroups = page.locator('.summary-card__links');

    for (let index = 0; index < await linkGroups.count(); index += 1) {
      const linkGroup = linkGroups.nth(index);
      const widths = await linkGroup.evaluate((element) => ({
        client: element.clientWidth,
        scroll: element.scrollWidth,
      }));

      expect(widths.scroll).toBeLessThanOrEqual(widths.client + 1);

      const links = linkGroup.locator('a');

      for (let linkIndex = 0; linkIndex < await links.count(); linkIndex += 1) {
        const linkBox = await links.nth(linkIndex).boundingBox();

        expect(linkBox).not.toBeNull();
        expect(linkBox.x).toBeGreaterThanOrEqual(-1);
        expect(linkBox.x + linkBox.width).toBeLessThanOrEqual(viewport.width + 1);
      }
    }

    await page.evaluate(() => window.scrollTo(0, 500));
    await expect(header).toHaveClass(/site-header--compact/);
    await page.waitForTimeout(200);

    const compactHeader = await header.evaluate((element) => {
      const brandName = element.querySelector('.brand__name');
      const content = element.querySelector('.site-header__content');
      const navLinks = Array.from(element.querySelectorAll('.site-nav > ul > li > a'));
      const tagline = element.querySelector('.brand__tagline');
      const bounds = element.getBoundingClientRect();

      return {
        top: bounds.top,
        right: bounds.right,
        height: bounds.height,
        brandFontSize: Number.parseFloat(getComputedStyle(brandName).fontSize),
        navFontSize: Number.parseFloat(getComputedStyle(navLinks[0]).fontSize),
        linkHeights: navLinks.map((link) => link.getBoundingClientRect().height),
        outerRadius: getComputedStyle(element).borderRadius,
        contentRadius: getComputedStyle(content).borderRadius,
        documentWidth: document.documentElement.scrollWidth,
        taglineDisplay: getComputedStyle(tagline).display,
      };
    });

    expect(compactHeader.top).toBeGreaterThanOrEqual(-1);
    expect(compactHeader.top).toBeLessThanOrEqual(1);
    expect(compactHeader.right).toBeLessThanOrEqual(viewport.width + 1);

    if (viewport.width > 800) {
      expect(compactHeader.height).toBeLessThan(initialHeader.height);
      expect(compactHeader.height).toBeCloseTo(44, 0);
      expect(initialHeader.brandFontSize).toBeGreaterThan(initialHeader.navFontSize);
      expect(initialHeader.taglineDisplay).not.toBe('none');
      expect(Math.abs(compactHeader.brandFontSize - compactHeader.navFontSize)).toBeLessThanOrEqual(0.1);
      expect(compactHeader.linkHeights.every((height) => height >= 44)).toBe(true);
    } else {
      expect(initialHeader.height).toBeCloseTo(60, 0);
      expect(compactHeader.height).toBeCloseTo(60, 0);
      expect(initialHeader.taglineDisplay).toBe('none');
      expect(Math.abs(compactHeader.brandFontSize - initialHeader.brandFontSize)).toBeLessThanOrEqual(0.1);
    }

    expect(compactHeader.taglineDisplay).toBe('none');
    expect(compactHeader.outerRadius).not.toBe('0px');
    expect(compactHeader.contentRadius).not.toBe('0px');
    expect(compactHeader.documentWidth).toBeLessThanOrEqual(viewport.width + 1);
  });
}

for (const viewport of [
  { width: 320, height: 700 },
  { width: 390, height: 844 },
  { width: 800, height: 900 },
]) {
  test(`mobile header menu is usable at ${viewport.width}px`, async ({ page }) => {
    await page.setViewportSize(viewport);
    await page.goto('./');
    await page.evaluate(() => document.fonts.ready);

    const header = page.locator('.site-header');
    const toggle = page.getByRole('button', { name: 'Menu' });
    const navigation = page.locator('#primary-navigation');
    const navLinks = navigation.locator('a');
    const initialHeaderHeight = await header.evaluate((element) => element.getBoundingClientRect().height);

    await expect(toggle).toBeVisible();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(navigation).toHaveAttribute('aria-hidden', 'true');
    await expect(navigation).toBeHidden();
    expect(initialHeaderHeight).toBeCloseTo(60, 0);

    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await expect(navigation).toHaveAttribute('aria-hidden', 'false');
    await expect(navigation).toBeVisible();

    const navigationBox = await navigation.boundingBox();

    expect(navigationBox).not.toBeNull();
    expect(navigationBox.x).toBeGreaterThanOrEqual(-1);
    expect(navigationBox.x + navigationBox.width).toBeLessThanOrEqual(viewport.width + 1);
    expect(await navLinks.count()).toBeGreaterThan(0);

    for (let linkIndex = 0; linkIndex < await navLinks.count(); linkIndex += 1) {
      const linkBox = await navLinks.nth(linkIndex).boundingBox();

      expect(linkBox).not.toBeNull();
      expect(linkBox.height).toBeGreaterThanOrEqual(43.9);
      expect(linkBox.x).toBeGreaterThanOrEqual(navigationBox.x - 1);
      expect(linkBox.x + linkBox.width).toBeLessThanOrEqual(navigationBox.x + navigationBox.width + 1);
    }

    await page.keyboard.press('Escape');
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(navigation).toBeHidden();
    await expect(toggle).toBeFocused();

    await page.evaluate(() => window.scrollTo(0, 500));
    await expect(header).toHaveClass(/site-header--compact/);
    await expect(toggle).toBeVisible();
    expect(await header.evaluate((element) => element.getBoundingClientRect().height)).toBeCloseTo(60, 0);
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(viewport.width + 1);
  });
}

test('header title yields space to single-line desktop navigation', async ({ page }) => {
  let previousTitleFontSize = 0;
  let fullNavFontSize = null;

  for (const width of [801, 880, 1024, 1440]) {
    await page.setViewportSize({ width, height: 1000 });
    await page.goto('./');
    await page.evaluate(() => document.fonts.ready);
    await expect(page.getByRole('button', { name: 'Menu' })).toBeHidden();

    const metrics = await page.locator('.site-header__content').evaluate((content) => {
      const title = content.querySelector('.brand__name');
      const tagline = content.querySelector('.brand__tagline');
      const brand = content.querySelector('.brand');
      const navigation = content.querySelector('.site-nav');
      const list = navigation.querySelector(':scope > ul');
      const links = Array.from(list.querySelectorAll(':scope > li > a'));
      const labels = links.map((link) => link.querySelector(':scope > span'));
      const contentBounds = content.getBoundingClientRect();
      const brandBounds = brand.getBoundingClientRect();
      const navigationBounds = navigation.getBoundingClientRect();

      return {
        brandRight: brandBounds.right,
        contentRight: contentBounds.right,
        documentWidth: document.documentElement.scrollWidth,
        listFlexWrap: getComputedStyle(list).flexWrap,
        navigationLeft: navigationBounds.left,
        navigationRight: navigationBounds.right,
        navFontSizes: links.map((link) => Number.parseFloat(getComputedStyle(link).fontSize)),
        navLinkWidths: links.map((link) => link.getBoundingClientRect().width),
        navLabelsFit: labels.every((label) => label.scrollWidth <= label.clientWidth + 1),
        navLabelsWhiteSpace: labels.map((label) => getComputedStyle(label).whiteSpace),
        taglineFits: tagline.scrollWidth <= tagline.clientWidth + 1,
        taglineTextOverflow: getComputedStyle(tagline).textOverflow,
        taglineWhiteSpace: getComputedStyle(tagline).whiteSpace,
        titleFits: title.scrollWidth <= title.clientWidth + 1,
        titleFontSize: Number.parseFloat(getComputedStyle(title).fontSize),
        titleWhiteSpace: getComputedStyle(title).whiteSpace,
      };
    });

    if (null === fullNavFontSize) {
      fullNavFontSize = metrics.navFontSizes[0];
    }

    expect(metrics.titleFontSize).toBeGreaterThanOrEqual(previousTitleFontSize - 0.1);
    expect(metrics.navFontSizes.every((fontSize) => Math.abs(fontSize - fullNavFontSize) <= 0.1)).toBe(true);
    expect(metrics.titleWhiteSpace).toBe('nowrap');
    expect(metrics.taglineWhiteSpace).toBe('nowrap');
    expect(metrics.navLabelsWhiteSpace.every((whiteSpace) => whiteSpace === 'nowrap')).toBe(true);
    expect(metrics.listFlexWrap).toBe('nowrap');
    expect(metrics.titleFits).toBe(true);
    expect(metrics.taglineFits || metrics.taglineTextOverflow === 'ellipsis').toBe(true);
    expect(metrics.navLabelsFit).toBe(true);
    expect(Math.max(...metrics.navLinkWidths)).toBeLessThanOrEqual(Math.min(...metrics.navLinkWidths) * 1.2);
    expect(metrics.brandRight).toBeLessThanOrEqual(metrics.navigationLeft + 1);
    expect(metrics.navigationRight).toBeLessThanOrEqual(metrics.contentRight + 1);
    expect(metrics.documentWidth).toBeLessThanOrEqual(width + 1);

    await page.evaluate(() => window.scrollTo(0, 500));
    await expect(page.locator('.site-header')).toHaveClass(/site-header--compact/);

    const compactMetrics = await page.locator('.site-header__content').evaluate((content) => {
      const title = content.querySelector('.brand__name');
      const brand = content.querySelector('.brand');
      const navigation = content.querySelector('.site-nav');
      const list = navigation.querySelector(':scope > ul');
      const links = Array.from(list.querySelectorAll(':scope > li > a'));
      const labels = links.map((link) => link.querySelector(':scope > span'));
      const brandBounds = brand.getBoundingClientRect();
      const navigationBounds = navigation.getBoundingClientRect();

      return {
        brandRight: brandBounds.right,
        documentWidth: document.documentElement.scrollWidth,
        listFlexWrap: getComputedStyle(list).flexWrap,
        navigationLeft: navigationBounds.left,
        navFontSizes: links.map((link) => Number.parseFloat(getComputedStyle(link).fontSize)),
        navLinkWidths: links.map((link) => link.getBoundingClientRect().width),
        navLabelsFit: labels.every((label) => label.scrollWidth <= label.clientWidth + 1),
        navLabelsWhiteSpace: labels.map((label) => getComputedStyle(label).whiteSpace),
        titleFits: title.scrollWidth <= title.clientWidth + 1,
        titleWhiteSpace: getComputedStyle(title).whiteSpace,
      };
    });

    expect(compactMetrics.navFontSizes.every((fontSize) => Math.abs(fontSize - fullNavFontSize) <= 0.1)).toBe(true);
    expect(compactMetrics.titleWhiteSpace).toBe('nowrap');
    expect(compactMetrics.navLabelsWhiteSpace.every((whiteSpace) => whiteSpace === 'nowrap')).toBe(true);
    expect(compactMetrics.listFlexWrap).toBe('nowrap');
    expect(compactMetrics.titleFits).toBe(true);
    expect(compactMetrics.navLabelsFit).toBe(true);
    expect(Math.max(...compactMetrics.navLinkWidths)).toBeLessThanOrEqual(Math.min(...compactMetrics.navLinkWidths) * 1.2);
    expect(compactMetrics.brandRight).toBeLessThanOrEqual(compactMetrics.navigationLeft + 1);
    expect(compactMetrics.documentWidth).toBeLessThanOrEqual(width + 1);

    previousTitleFontSize = metrics.titleFontSize;
  }
});

test('primary navigation wraps every link label in a span', async ({ page }) => {
  await page.goto('./');

  const navLinks = page.locator('.site-nav a');
  const wrappedLabels = page.locator('.site-nav a > span');
  const navLinkCount = await navLinks.count();

  expect(navLinkCount).toBeGreaterThan(0);
  await expect(wrappedLabels).toHaveCount(navLinkCount);
});

test('main navigation hover stays visually anchored while compacting', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.goto('./');
  await page.evaluate(() => document.fonts.ready);

  const header = page.locator('.site-header');
  const navLink = page.locator('.site-nav > ul > li > a').first();
  const label = navLink.locator(':scope > span');

  await navLink.hover();

  const expandedLabel = await label.evaluate((element) => {
    const styles = getComputedStyle(element);
    const [originX, originY] = styles.transformOrigin
      .split(/\s+/)
      .map((value) => Number.parseFloat(value));

    return {
      display: styles.display,
      height: element.offsetHeight,
      originX,
      originY,
      width: element.offsetWidth,
    };
  });

  await header.evaluate((element) => {
    window.mainNavCompactMutations = 0;
    const observer = new MutationObserver((records) => {
      window.mainNavCompactMutations += records.filter((record) => record.attributeName === 'class').length;
    });

    observer.observe(element, { attributes: true, attributeFilter: ['class'] });
    window.mainNavCompactObserver = observer;
  });

  await page.mouse.wheel(0, 240);
  await expect(header).toHaveClass(/site-header--compact/);
  await page.waitForTimeout(250);

  const compactLabel = await label.evaluate((element) => {
    const styles = getComputedStyle(element);
    const [originX, originY] = styles.transformOrigin
      .split(/\s+/)
      .map((value) => Number.parseFloat(value));

    return {
      display: styles.display,
      height: element.offsetHeight,
      originX,
      originY,
      width: element.offsetWidth,
    };
  });

  expect(compactLabel.display).toBe(expandedLabel.display);
  expect(expandedLabel.originX).toBeGreaterThanOrEqual(expandedLabel.width - 1);
  expect(expandedLabel.originY).toBeGreaterThanOrEqual(expandedLabel.height - 1);
  expect(Math.abs(compactLabel.originX - compactLabel.width / 2)).toBeLessThanOrEqual(1);
  expect(Math.abs(compactLabel.originY - compactLabel.height / 2)).toBeLessThanOrEqual(1);
  expect(await page.evaluate(() => window.mainNavCompactMutations)).toBe(1);

  await page.evaluate(() => window.mainNavCompactObserver.disconnect());
});

test('sticky header settles without toggling at its scroll thresholds', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.goto('./');
  await page.evaluate(() => document.fonts.ready);

  const header = page.locator('.site-header');
  const sentinel = page.locator('.site-header-sentinel');
  const sentinelTop = await sentinel.evaluate(
    (element) => element.getBoundingClientRect().top + window.scrollY
  );

  await header.evaluate((element) => {
    window.stickyHeaderClassMutations = 0;
    const observer = new MutationObserver((records) => {
      window.stickyHeaderClassMutations += records.filter((record) => record.attributeName === 'class').length;
    });

    observer.observe(element, { attributes: true, attributeFilter: ['class'] });
    window.stickyHeaderObserver = observer;
  });

  await page.evaluate((scrollTop) => window.scrollTo(0, scrollTop), sentinelTop - 20);
  await page.waitForTimeout(400);
  await expect(header).not.toHaveClass(/site-header--compact/);
  expect(await page.evaluate(() => window.stickyHeaderClassMutations)).toBe(0);

  await page.evaluate((scrollTop) => window.scrollTo(0, scrollTop), sentinelTop + 2);
  await expect(header).toHaveClass(/site-header--compact/);
  await page.waitForTimeout(600);
  expect(await page.evaluate(() => window.stickyHeaderClassMutations)).toBe(1);

  await page.evaluate(() => window.scrollTo(0, 0));
  await expect(header).not.toHaveClass(/site-header--compact/);
  await page.waitForTimeout(300);
  expect(await page.evaluate(() => window.stickyHeaderClassMutations)).toBe(2);

  await page.evaluate(() => window.stickyHeaderObserver.disconnect());
});
