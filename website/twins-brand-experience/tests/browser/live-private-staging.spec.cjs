const { test, expect } = require('@playwright/test');

const stageUrl = process.env.TWINS_STAGE_URL || '';
const stageUser = process.env.TWINS_STAGE_USER || '';
const stagePassword = process.env.TWINS_STAGE_PASSWORD || '';
const configured = stageUrl !== '' && stageUser !== '' && stagePassword !== '';

test.use({
  baseURL: configured ? stageUrl : 'http://127.0.0.1:41739',
  httpCredentials: configured ? { username: stageUser, password: stagePassword } : undefined,
});
test.describe('exact private staging candidate', () => {
  test.skip(!configured, 'private staging credentials are environment-only');

  test.beforeEach(async ({ page }) => {
    expect(stageUrl).toBe('https://danielj140.sg-host.com/');
    page.on('request', request => {
      expect(['GET', 'HEAD']).toContain(request.method());
      expect(new URL(request.url()).origin).toBe('https://danielj140.sg-host.com');
    });
  });

  test('homepage exposes the approved brand experience behind staging safety', async ({ page }) => {
    const response = await page.goto('/');
    expect(response).not.toBeNull();
    expect(response.status()).toBeLessThan(400);
    expect(response.headers()['x-robots-tag'] || '').toContain('noindex');
    await expect(page.locator('h1')).toHaveText('Garage Door Repair & Installation, Done Right Today.');
    await expect(page.getByRole('link', { name: 'Request a Quote', exact: true }).first()).toBeVisible();
    await expect(page.getByRole('button', { name: 'Book Online', exact: true }).first()).toBeVisible();
    await expect(page.locator('.twins-brand-logo img')).toBeVisible();
    await expect(page.locator('.twins-brand-twin')).toHaveCount(2);
    await expect(page.locator('[data-section="team-story"] picture')).toHaveCount(2);
    await expect(page.locator('[data-section="review-slider"]')).toBeVisible();
    await expect(page.locator('nav[aria-label="Primary navigation"]')).toBeAttached();
  });

  for (const route of ['our-team', 'careers', 'contact-us', 'reviews']) {
    test(`${route} is rebuilt with shared brand chrome`, async ({ page }) => {
      const response = await page.goto(`/${route}/`);
      expect(response).not.toBeNull();
      expect(response.status()).toBeLessThan(400);
      await expect(page.locator('.twins-brand-header')).toBeVisible();
      await expect(page.locator('.twins-brand-footer')).toBeVisible();
      await expect(page.getByText('Request a Quote', { exact: true }).first()).toBeVisible();
      await expect(page.locator('main h1')).toBeVisible();
    });
  }

  test('staging interactions stay inert and same-origin', async ({ page }) => {
    const methods = [];
    page.on('request', request => methods.push(request.method()));
    await page.goto('/');
    await page.getByRole('button', { name: 'Book Online', exact: true }).first().click();
    await expect(page.locator('[role="dialog"]')).toBeVisible();
    const quote = page.getByRole('link', { name: 'Request a Quote', exact: true }).first();
    const href = await quote.getAttribute('href');
    expect(new URL(href, stageUrl).origin).toBe('https://danielj140.sg-host.com');
    expect(methods.every(method => method === 'GET' || method === 'HEAD')).toBe(true);
  });
});
