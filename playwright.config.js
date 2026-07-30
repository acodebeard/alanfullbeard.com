const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests/e2e',
  outputDir: './test-results',
  reporter: 'line',
  use: {
    baseURL: process.env.WP_BASE_URL || 'http://localhost/alanfullbeard/',
    headless: true,
    screenshot: 'only-on-failure',
  },
});
