const { test, expect } = require('@playwright/test');

const fixture = '/tests/browser/fixtures/location-mobile.html';

test('location Twin mobile artwork sits below content and controls without horizontal overflow', async ({ page }) => {
  for (const width of [390, 360, 320]) {
    await page.setViewportSize({ width, height: 844 });
    await page.goto(fixture);
    await expect(page.locator('.twins-location-twin')).toHaveCount(4);

    for (const [sectionSelector, twinSelector] of [
      ['.twins-location-system', '.twins-location-twin--system'],
      ['.twins-location-guidance', '.twins-location-twin--guidance'],
      ['.twins-location-final-cta', '.twins-location-twin--final-right'],
    ]) {
      const region = await page.locator(sectionSelector).evaluate((section, selector) => {
        const twin = section.querySelector(selector);
        const content = Array.from(section.children).filter(child => !child.matches('.twins-location-twin'));
        const twinBox = twin.getBoundingClientRect();
        return {
          clearance: twinBox.top - Math.max(...content.map(child => child.getBoundingClientRect().bottom)),
          twinHeight: twinBox.height,
        };
      }, twinSelector);
      expect(region.twinHeight, `${width}px ${sectionSelector} renders its artwork`).toBeGreaterThan(0);
      expect(region.clearance, `${width}px ${sectionSelector} content clears its artwork row`).toBeGreaterThanOrEqual(16);
    }

    expect(await page.evaluate(() => document.documentElement.scrollWidth === document.documentElement.clientWidth), `${width}px overflow`)
      .toBeTruthy();
  }
});
