const { test, expect } = require('@playwright/test');
const fs = require('node:fs');
const path = require('node:path');

const overviewFixture = '/tests/browser/fixtures/catalog-overview.html';
const productFixture = '/tests/browser/fixtures/catalog-product.html';
const builderFixture = '/tests/browser/fixtures/builder.html';
const costFixture = '/tests/browser/fixtures/cost-zip.html';
const widths = [1440, 768, 390, 320];
const catalogBytes = fs.readFileSync(
  path.resolve(__dirname, '../../../staging-safety/mu-plugins/twins-staging-assets/clopay-products.json'),
  'utf8',
);

function channels(value) {
  const match = value.match(/rgba?\(([^)]+)\)/);
  if (!match) throw new Error(`Unsupported computed color: ${value}`);
  const parts = match[1].split(/[ ,/]+/).filter(Boolean).map(Number);
  return { r: parts[0], g: parts[1], b: parts[2], a: Number.isFinite(parts[3]) ? parts[3] : 1 };
}

function blend(top, bottom) {
  const alpha = top.a + bottom.a * (1 - top.a);
  if (alpha === 0) return { r: 255, g: 255, b: 255, a: 1 };
  return {
    r: (top.r * top.a + bottom.r * bottom.a * (1 - top.a)) / alpha,
    g: (top.g * top.a + bottom.g * bottom.a * (1 - top.a)) / alpha,
    b: (top.b * top.a + bottom.b * bottom.a * (1 - top.a)) / alpha,
    a: alpha,
  };
}

function luminance(color) {
  const linear = value => {
    value /= 255;
    return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
  };
  return 0.2126 * linear(color.r) + 0.7152 * linear(color.g) + 0.0722 * linear(color.b);
}

async function contrast(locator) {
  const values = await locator.evaluate(element => {
    const layers = [];
    for (let current = element; current; current = current.parentElement) {
      layers.push(getComputedStyle(current).backgroundColor);
    }
    return { color: getComputedStyle(element).color, layers };
  });
  let background = { r: 255, g: 255, b: 255, a: 1 };
  for (const layer of values.layers.reverse()) background = blend(channels(layer), background);
  const [light, dark] = [luminance(channels(values.color)), luminance(background)].sort((a, b) => b - a);
  return (light + .05) / (dark + .05);
}

async function routeBuilderFixture(page) {
  await page.route(`**${builderFixture}`, async route => {
    const response = await route.fetch();
    const fixture = await response.text();
    if (!fixture.includes('__CATALOG_JSON__')) throw new Error('Builder fixture catalog marker is missing.');
    await route.fulfill({ response, body: fixture.replace('__CATALOG_JSON__', catalogBytes) });
  });
}

test('catalog overview and product remain local, contrast-safe, and responsive', async ({ page }) => {
  for (const fixture of [overviewFixture, productFixture]) {
    for (const width of widths) {
      await page.setViewportSize({ width, height: 900 });
      await page.goto(fixture);
      await expect(page.locator('h1')).toHaveCount(1);
      expect(await contrast(page.locator('.twins-brand-catalog-hero h1')), `${fixture} ${width}px heading`).toBeGreaterThanOrEqual(4.5);
      expect(await contrast(page.locator('.twins-brand-catalog-hero__copy > p')), `${fixture} ${width}px lead`).toBeGreaterThanOrEqual(4.5);
      expect(
        await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth),
        `${fixture} ${width}px overflow`,
      ).toBeTruthy();

      for (const action of await page.locator('.twins-brand-catalog-actions a').all()) {
        const box = await action.boundingBox();
        expect(box.height, `${fixture} ${width}px action height`).toBeGreaterThanOrEqual(44);
      }
      for (const image of await page.locator('.twins-brand-catalog-page img').all()) {
        await expect(image).toHaveAttribute(
          'src',
          /^\/wp-content\/mu-plugins\/twins-staging-assets\/clopay\/[a-f0-9]{2}\/[a-f0-9]{64}\.(?:webp|jpg)$/,
        );
        expect(await image.evaluate(element => element.naturalWidth), `${fixture} local image`).toBeGreaterThan(0);
      }
    }
  }
});

test('frozen builder enhances 23 products with keyboard focus and no overflow', async ({ page }) => {
  await routeBuilderFixture(page);
  for (const width of [1440, 390, 320]) {
    await page.setViewportSize({ width, height: 900 });
    await page.goto(builderFixture);
    const builder = page.locator('[data-twins-overhaul-builder]');
    await expect(builder).toHaveAttribute('data-builder-enhanced', 'true');
    await expect(page.locator('[data-builder-fallback]')).toBeHidden();
    await expect(page.locator('[data-builder-product-id]')).toHaveCount(23);
    await page.locator('[data-builder-product-id="170"]').click();
    await expect(page.locator('.twins-builder__step-heading')).toHaveText('Design');
    await expect(page.locator('.twins-builder__step-heading')).toBeFocused();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth), `${width}px builder overflow`).toBeTruthy();
    for (const control of await page.locator('.twins-builder button:visible').all()) {
      const box = await control.boundingBox();
      expect(box.height, `${width}px ${await control.innerText()}`).toBeGreaterThanOrEqual(44);
    }
  }
});

test('cost ZIP control validates locally and routes only to a fixed same-origin guide', async ({ page }) => {
  await page.route('**/wi/garage-door-cost-in-madison-wi/', route => route.fulfill({
    status: 200,
    contentType: 'text/html',
    body: '<!doctype html><title>Madison cost guide</title>',
  }));
  await page.goto(costFixture);
  const input = page.getByLabel('ZIP code');
  await input.fill('53');
  await page.getByRole('button', { name: 'Check My ZIP' }).click();
  await expect(input).toHaveAttribute('aria-invalid', 'true');
  await expect(page.getByRole('status')).toHaveText('Enter a valid 5-digit ZIP code.');
  await input.fill('53703');
  await Promise.all([
    page.waitForURL('**/wi/garage-door-cost-in-madison-wi/'),
    input.press('Enter'),
  ]);
  expect(new URL(page.url()).pathname).toBe('/wi/garage-door-cost-in-madison-wi/');
});

test('catalog and builder fixtures issue local GET requests only', async ({ page }) => {
  const requests = [];
  page.on('request', request => requests.push({ method: request.method(), url: request.url() }));
  await page.goto(overviewFixture);
  await routeBuilderFixture(page);
  await page.goto(builderFixture);
  await expect(page.locator('[data-twins-overhaul-builder]')).toHaveAttribute('data-builder-enhanced', 'true');
  expect(requests.length).toBeGreaterThan(3);
  for (const request of requests) {
    expect(request.method).toBe('GET');
    expect(new URL(request.url).origin).toBe(new URL(page.url()).origin);
  }
});
