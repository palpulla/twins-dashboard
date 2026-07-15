const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests/browser',
  timeout: 30000,
  expect: { timeout: 5000 },
  fullyParallel: false,
  workers: 1,
  use: {
    baseURL: process.env.TWINS_TEST_BASE_URL || 'http://127.0.0.1:41739',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  webServer: process.env.TWINS_TEST_BASE_URL ? undefined : {
    command: 'node tests/browser/fixture-server.mjs --port 41739',
    port: 41739,
    reuseExistingServer: false,
  },
});
