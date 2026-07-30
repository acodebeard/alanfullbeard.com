const { test, expect } = require('@playwright/test');

const liveSecurityUrl = process.env.AFB_SECURITY_REPORT_ONLY_URL;

test.describe('live security-policy audit', () => {
  test.skip(!liveSecurityUrl, 'AFB_SECURITY_REPORT_ONLY_URL is not set.');

  for (const pagePath of ['/', '/contact/', '/privacy-policy/', '/portfolio/']) {
    test(`${pagePath} has no security-policy violations`, async ({ page }) => {
      const diagnostics = [];

      await page.addInitScript(() => {
        window.__afbSecurityPolicyViolations = [];
        document.addEventListener('securitypolicyviolation', (event) => {
          window.__afbSecurityPolicyViolations.push({
            blockedURI: event.blockedURI,
            disposition: event.disposition,
            effectiveDirective: event.effectiveDirective,
            violatedDirective: event.violatedDirective,
          });
        });
      });

      page.on('console', (message) => {
        if ('error' !== message.type()) {
          return;
        }

        const text = message.text();
        const expectedReportOnlyWarning =
          "The Content Security Policy directive 'upgrade-insecure-requests' " +
          'is ignored when delivered in a report-only policy.';

        if (text === expectedReportOnlyWarning) {
          return;
        }

        const location = message.location();
        const expectedTurnstileDiagnostic =
          '%c%d font-size:0;color:transparent NaN';
        const isTurnstileDiagnostic =
          text === expectedTurnstileDiagnostic &&
          location.url.startsWith('https://challenges.cloudflare.com/');

        if (isTurnstileDiagnostic) {
          return;
        }

        diagnostics.push(
          `console error: ${text} (${location.url}:${location.lineNumber})`
        );
      });
      page.on('pageerror', (error) => {
        diagnostics.push(`page error: ${error.message}`);
      });

      const url = new URL(pagePath, liveSecurityUrl).href;
      const response = await page.goto(url, { waitUntil: 'domcontentloaded' });
      expect(response).not.toBeNull();
      await page.waitForTimeout(5000);

      const headers = response.headers();
      const reportOnly = headers['content-security-policy-report-only'] || '';
      const enforced = headers['content-security-policy'] || '';
      const activePolicy = reportOnly || enforced;

      expect(headers['cross-origin-opener-policy']).toBe('same-origin');
      expect(headers['x-frame-options']).toBe('SAMEORIGIN');
      expect(activePolicy).toContain("script-src 'nonce-");
      expect(activePolicy).toContain("require-trusted-types-for 'script'");
      expect(enforced).toContain('upgrade-insecure-requests');

      const violations = await page.evaluate(
        () => window.__afbSecurityPolicyViolations
      );

      expect(
        violations,
        `Security policy violations:\n${JSON.stringify(violations, null, 2)}`
      ).toEqual([]);
      expect(
        diagnostics,
        `Browser diagnostics:\n${diagnostics.join('\n')}`
      ).toEqual([]);
    });
  }
});
