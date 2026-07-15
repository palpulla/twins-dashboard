const { test, expect } = require('@playwright/test');

const fixture = '/tests/browser/fixtures/brand-home.html';
const widths = [1440, 1201, 1024, 768, 390, 360, 320];

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

function ratio(one, two) {
  const [light, dark] = [luminance(one), luminance(two)].sort((a, b) => b - a);
  return (light + 0.05) / (dark + 0.05);
}

async function computedContrast(locator) {
  const values = await locator.evaluate(element => {
    const layers = [];
    for (let current = element; current; current = current.parentElement) {
      layers.push(getComputedStyle(current).backgroundColor);
    }
    return { color: getComputedStyle(element).color, layers };
  });
  let background = { r: 255, g: 255, b: 255, a: 1 };
  for (const layer of values.layers.reverse()) background = blend(channels(layer), background);
  return { ratio: ratio(channels(values.color), background), foreground: channels(values.color), background };
}

test('drawer traps focus, closes, restores focus, and stops intercepting clicks', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(fixture);
  const menu = page.getByRole('button', { name: 'Menu' });
  await menu.click();
  const drawer = page.locator('#twins-brand-drawer');
  await expect(drawer).toBeVisible();
  await expect(drawer.getByRole('button', { name: 'Close menu' })).toBeFocused();
  await page.keyboard.press('Shift+Tab');
  await expect(drawer.getByRole('link', { name: 'Request a Quote' })).toBeFocused();
  await page.keyboard.press('Escape');
  await expect(drawer).toBeHidden();
  await expect(drawer).toHaveAttribute('aria-hidden', 'true');
  await expect(menu).toBeFocused();
  await page.getByRole('link', { name: 'Call Twins' }).first().click({ trial: true });
});

test('desktop dropdowns are keyboard operable and close cleanly', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.goto(fixture);
  const trigger = page.getByRole('button', { name: 'Services' });
  await trigger.focus();
  await page.keyboard.press('Enter');
  await expect(trigger).toHaveAttribute('aria-expanded', 'true');
  const panel = trigger.locator('xpath=following-sibling::*[1]');
  await expect(panel).toBeVisible();
  await page.keyboard.press('ArrowDown');
  await expect(panel.getByRole('link', { name: 'All Services' })).toBeFocused();
  await page.keyboard.press('Escape');
  await expect(trigger).toHaveAttribute('aria-expanded', 'false');
  await expect(trigger).toBeFocused();
});

test('booking dialog traps focus, closes outside or with Escape, and reports only local status', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.goto(fixture);
  const opener = page.getByRole('button', { name: 'Book Online' }).first();
  await opener.click();
  const overlay = page.locator('[data-twins-booking-dialog]');
  await expect(overlay).toBeVisible();
  await expect(page.getByRole('button', { name: 'Close booking preview' })).toBeFocused();
  await page.getByRole('button', { name: 'Continue on staging' }).click();
  await expect(page.locator('[data-booking-status]')).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(overlay).toBeHidden();
  await expect(opener).toBeFocused();
  await opener.click();
  await overlay.click({ position: { x: 8, y: 8 } });
  await expect(overlay).toBeHidden();
});

test('staging previews validate locally and make zero POST or external requests', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  const requests = [];
  page.on('request', request => requests.push([request.method(), new URL(request.url()).origin]));
  await page.goto(fixture);
  await page.getByRole('button', { name: 'Menu' }).click();
  await page.locator('#twins-brand-drawer').getByRole('button', { name: 'Book Online' }).click();
  await page.getByRole('button', { name: 'Continue on staging' }).click();
  await page.keyboard.press('Escape');

  const preview = page.locator('[data-preview-kind="quote"]');
  const final = preview.getByRole('button', { name: 'Review quote on staging' });
  await final.click();
  await expect(preview.getByLabel('Full name')).toBeFocused();
  await expect(preview.locator('[data-preview-status]')).toBeHidden();
  await preview.getByLabel('Full name').fill('Fixture Person');
  await preview.getByLabel('Phone').fill('6085550100');
  await preview.getByLabel('Email').fill('fixture@example.test');
  await preview.getByLabel('ZIP code').fill('53703');
  await preview.getByLabel('Service needed').selectOption({ label: 'Garage door repair' });
  await preview.getByLabel('Message').fill('Fixture only — browser mechanics');
  await final.click();
  await expect(preview.locator('[data-preview-status]')).toBeVisible();

  expect(requests.filter(([method]) => method !== 'GET')).toEqual([]);
  expect(requests.filter(([, origin]) => origin !== new URL(page.url()).origin)).toEqual([]);
});

test('review slider supports buttons, keyboard, touch, dots, and interaction pause', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(fixture);
  const slider = page.locator('[data-twins-review-slider]');
  const track = slider.locator('.twins-brand-review-track');
  await expect(slider.locator('[role="group"] button')).toHaveCount(5);
  const initial = await track.evaluate(element => getComputedStyle(element).transform);
  await slider.getByRole('button', { name: 'Next reviews' }).click();
  await expect.poll(() => track.evaluate(element => getComputedStyle(element).transform)).not.toBe(initial);
  const afterButton = await track.evaluate(element => getComputedStyle(element).transform);
  await slider.focus();
  await page.keyboard.press('ArrowRight');
  await expect.poll(() => track.evaluate(element => getComputedStyle(element).transform)).not.toBe(afterButton);
  await slider.dispatchEvent('touchstart', { touches: [{ identifier: 1, clientX: 280, clientY: 100 }] });
  await slider.dispatchEvent('touchend', { changedTouches: [{ identifier: 1, clientX: 100, clientY: 100 }] });
  await expect(slider).toHaveAttribute('data-interaction-paused', 'true');
  await slider.hover();
  await page.waitForTimeout(500);
  const paused = await track.evaluate(element => getComputedStyle(element).transform);
  await page.waitForTimeout(7200);
  expect(await track.evaluate(element => getComputedStyle(element).transform)).toBe(paused);
});

test('JavaScript-disabled previews remain structurally incapable of submission', async ({ browser }) => {
  const context = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 844 } });
  const page = await context.newPage();
  const requests = [];
  page.on('request', request => requests.push(request.method()));
  await page.goto(fixture);
  await expect(page.locator('form')).toHaveCount(0);
  await expect(page.locator('[type="submit"], [type="image"], [formaction], form [name], input[name], select[name], textarea[name], button[name]')).toHaveCount(0);
  await page.getByRole('button', { name: 'Review quote on staging' }).click();
  expect(requests.filter(method => method !== 'GET')).toEqual([]);
  await context.close();
});

test('primary CTAs expose visible focus, circular arrows, sheen, and pressed depth', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.goto(fixture);
  for (const locator of [page.getByRole('button', { name: 'Book Online' }).first(), page.getByRole('link', { name: 'Request a Quote' }).first()]) {
    await locator.focus();
    const focus = await locator.evaluate(element => {
      const style = getComputedStyle(element);
      const header = element.closest('.twins-brand-header');
      return {
        outline: style.outlineStyle,
        outlineWidth: parseFloat(style.outlineWidth),
        outlineColor: style.outlineColor,
        boxShadow: style.boxShadow,
        controlBackground: style.backgroundColor,
        adjacentBackground: getComputedStyle(header || element.parentElement).backgroundColor,
      };
    });
    expect(focus.outline !== 'none' || focus.outlineWidth > 0 || focus.boxShadow !== 'none').toBeTruthy();
    const ringColors = [channels(focus.outlineColor), ...(focus.boxShadow.match(/rgba?\([^)]+\)/g) || []).map(channels)];
    expect(Math.max(...ringColors.map(color => ratio(color, channels(focus.controlBackground))))).toBeGreaterThanOrEqual(3);
    expect(Math.max(...ringColors.map(color => ratio(color, channels(focus.adjacentBackground))))).toBeGreaterThanOrEqual(3);
    const after = await locator.evaluate(element => {
      const style = getComputedStyle(element, '::after');
      return { content: style.content, width: parseFloat(style.width), height: parseFloat(style.height), radius: style.borderRadius };
    });
    expect(after.content).toContain('→');
    expect(Math.abs(after.width - after.height)).toBeLessThan(1);
    expect(after.radius).not.toBe('0px');
    expect(await locator.evaluate(element => getComputedStyle(element).backgroundImage)).toContain('linear-gradient');
    await locator.hover();
    expect((await computedContrast(locator)).ratio).toBeGreaterThanOrEqual(4.5);
    await locator.evaluate(element => element.classList.add('twins-brand-test-active'));
    expect(await locator.evaluate(element => getComputedStyle(element).transform)).not.toBe('none');
    expect((await computedContrast(locator)).ratio).toBeGreaterThanOrEqual(4.5);
    await locator.evaluate(element => element.classList.remove('twins-brand-test-active'));
  }
});

test('seven-width matrix preserves logo floors, contrast, Twins, and deterministic truck placement', async ({ page }) => {
  for (const width of widths) {
    await page.setViewportSize({ width, height: width <= 390 ? 844 : 1000 });
    await page.goto(fixture);
    const expectedInitial = width >= 1201 ? 204 : width >= 769 ? 190 : width >= 391 ? 176 : width >= 361 ? 154 : width >= 321 ? 148 : 140;
    const logo = page.locator('.twins-brand-logo');
    expect((await logo.boundingBox()).width).toBeGreaterThanOrEqual(expectedInitial - 0.5);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBeTruthy();

    for (const control of [page.locator('.twins-brand-phone').first(), page.locator('.twins-brand-hero-actions .twins-brand-cta--call'), page.locator('.twins-brand-hero-actions .twins-brand-cta--quote')]) {
      const contrast = await computedContrast(control);
      expect(contrast.ratio, `${width}px ${await control.innerText()}`).toBeGreaterThanOrEqual(4.5);
    }
    const phoneColors = await computedContrast(page.locator('.twins-brand-phone').first());
    expect(phoneColors.foreground.r + phoneColors.foreground.g + phoneColors.foreground.b).toBeLessThan(690);

    for (const twin of ['.twins-brand-twin--left', '.twins-brand-twin--right']) await expect(page.locator(twin)).toBeVisible();
    if (width >= 1201) {
      await expect(page.locator('.twins-brand-primary-nav')).toBeVisible();
      expect((await computedContrast(page.getByRole('button', { name: 'Services' }))).ratio).toBeGreaterThanOrEqual(4.5);
      await expect(page.locator('.twins-brand-truck--hero')).toBeVisible();
      await expect(page.locator('.twins-brand-truck--mobile-proof')).toBeHidden();
    } else {
      await expect(page.getByRole('button', { name: 'Menu' })).toBeVisible();
      await expect(page.locator('.twins-brand-truck--hero')).toBeHidden();
      await expect(page.locator('.twins-brand-truck--mobile-proof')).toBeVisible();
    }

    await page.evaluate(() => scrollTo(0, 420));
    await expect(page.locator('[data-twins-header]')).toHaveAttribute('data-compressed', 'true');
    expect((await computedContrast(page.locator('.twins-brand-phone').first())).ratio).toBeGreaterThanOrEqual(4.5);
    const scrolledFloor = width >= 1201 ? 180 : expectedInitial;
    expect((await logo.boundingBox()).width).toBeGreaterThanOrEqual(scrolledFloor - 0.5);
    await page.evaluate(() => scrollTo(0, 0));
  }
});

test('Twins move across desktop and mobile loops while reduced motion is static', async ({ browser }) => {
  test.setTimeout(75000);
  for (const width of widths) {
    const context = await browser.newContext({ viewport: { width, height: width <= 390 ? 844 : 1000 } });
    const page = await context.newPage();
    await page.goto(fixture);
    const range = { left: [], right: [] };
    for (let sample = 0; sample <= 70; sample += 1) {
      const positions = await page.locator('.twins-brand-twin').evaluateAll(elements => elements.map(element => element.getBoundingClientRect().top));
      range.left.push(positions[0]);
      range.right.push(positions[1]);
      await page.waitForTimeout(100);
    }
    expect(Math.max(...range.left) - Math.min(...range.left), `${width}px left Twin`).toBeGreaterThanOrEqual(12);
    expect(Math.max(...range.right) - Math.min(...range.right), `${width}px right Twin`).toBeGreaterThanOrEqual(12);
    await context.close();
  }

  const reduced = await browser.newContext({ reducedMotion: 'reduce', viewport: { width: 390, height: 844 } });
  const reducedPage = await reduced.newPage();
  await reducedPage.goto(fixture);
  const first = await reducedPage.locator('.twins-brand-twin').evaluateAll(elements => elements.map(element => ({ rect: element.getBoundingClientRect().toJSON(), animation: getComputedStyle(element).animationName })));
  await reducedPage.waitForTimeout(1000);
  const second = await reducedPage.locator('.twins-brand-twin').evaluateAll(elements => elements.map(element => ({ rect: element.getBoundingClientRect().toJSON(), animation: getComputedStyle(element).animationName })));
  expect(first).toEqual(second);
  expect(first.every(item => item.animation === 'none')).toBeTruthy();
  await reduced.close();
});

test('fixture is console-clean and ledger contains local GET/HEAD only', async ({ page, request }) => {
  const messages = [];
  page.on('console', message => { if (['warning', 'error'].includes(message.type())) messages.push(message.text()); });
  page.on('pageerror', error => messages.push(error.message));
  await page.goto(fixture);
  await expect(page.locator('.twins-brand-hero')).toBeVisible();
  expect(messages).toEqual([]);
  const response = await request.get('/__fixture-ledger');
  expect(response.ok()).toBeTruthy();
  const ledger = await response.json();
  expect(ledger.length).toBeGreaterThan(1);
  expect(ledger.every(entry => ['GET', 'HEAD'].includes(entry.method))).toBeTruthy();
  expect(ledger.every(entry => entry.path.startsWith('/'))).toBeTruthy();
});
