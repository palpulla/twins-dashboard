const { test, expect } = require('@playwright/test');

const fixture = '/tests/browser/fixtures/brand-home.html';
const reviewsFixture = '/tests/browser/fixtures/brand-reviews.html';
const widths = [1440, 1201, 1024, 768, 390, 360, 320];
const marketMenuFixture = `
<details class="twins-brand-market-menu">
  <summary>Choose your service area</summary>
  <div class="twins-brand-market-menu-panel">
    <a href="#markets"><strong>Wisconsin</strong><span>(608) 420-2377</span></a>
    <a href="#markets"><strong>Kentucky</strong><span>(833) 833-2010</span></a>
    <a href="#markets"><strong>Illinois preview</strong><span>(815) 800-2025</span><small>Private staging preview</small></a>
  </div>
</details>`;

async function routeFixtureWithMarketMenu(page) {
  await page.route(`**${fixture}`, async route => {
    const response = await route.fetch();
    const original = await response.text();
    const marker = '<span>Choose your service area</span>';
    if (!original.includes(marker)) throw new Error('Fixture utility marker is missing.');
    await route.fulfill({ response, body: original.replace(marker, marketMenuFixture) });
  });
}

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

function compositeLayers(layers) {
  let background = { r: 255, g: 255, b: 255, a: 1 };
  for (const layer of layers.reverse()) background = blend(channels(layer), background);
  return background;
}

async function visualSnapshot(locator) {
  const values = await locator.evaluate(element => {
    const layers = [];
    for (let current = element; current; current = current.parentElement) layers.push(getComputedStyle(current).backgroundColor);
    const style = getComputedStyle(element);
    return {
      color: style.color,
      fontSize: parseFloat(style.fontSize),
      fontWeight: parseInt(style.fontWeight, 10) || 400,
      layers,
      outlineStyle: style.outlineStyle,
      outlineWidth: parseFloat(style.outlineWidth),
      outlineColor: style.outlineColor,
      boxShadow: style.boxShadow,
      transform: style.transform,
    };
  });
  const controlBackground = compositeLayers([...values.layers]);
  const adjacentBackground = compositeLayers(values.layers.slice(1));
  const foreground = channels(values.color);
  return { ...values, foreground, controlBackground, adjacentBackground };
}

function textThreshold(snapshot) {
  const large = snapshot.fontSize >= 24 || (snapshot.fontWeight >= 700 && snapshot.fontSize >= 18.66);
  return large ? 3 : 4.5;
}

function ringContrast(color, surface) {
  return ratio(blend(color, surface), surface);
}

function isGold(color) {
  return color.r >= 220 && color.g >= 145 && color.g <= 220 && color.b <= 105;
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

test('market selector exposes approved phones with native keyboard semantics and bounded Escape close', async ({ page }) => {
  await routeFixtureWithMarketMenu(page);

  for (const width of [1440, 390, 320]) {
    await page.setViewportSize({ width, height: width <= 390 ? 844 : 1000 });
    await page.goto(fixture);
    const selector = page.locator('.twins-brand-market-menu');
    const summary = selector.locator('summary');

    await summary.click();
    await expect(selector).toHaveAttribute('open', '');
    await expect(selector.getByText('(815) 800-2025')).toBeVisible();

    const panel = selector.locator('.twins-brand-market-menu-panel');
    const panelBounds = await panel.boundingBox();
    expect(panelBounds.x, `${width}px market panel left edge`).toBeGreaterThanOrEqual(0);
    expect(panelBounds.x + panelBounds.width, `${width}px market panel right edge`).toBeLessThanOrEqual(width);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBeTruthy();

    for (const target of [summary, ...await panel.getByRole('link').all()]) {
      const bounds = await target.boundingBox();
      expect(bounds.width, `${width}px ${await target.innerText()} width`).toBeGreaterThanOrEqual(44);
      expect(bounds.height, `${width}px ${await target.innerText()} height`).toBeGreaterThanOrEqual(44);
      expect((await computedContrast(target)).ratio, `${width}px ${await target.innerText()} contrast`).toBeGreaterThanOrEqual(4.5);
    }

    await page.keyboard.press('Escape');
    await expect(selector).not.toHaveAttribute('open', '');
    await expect(summary).toBeFocused();
    expect(await summary.evaluate(element => element.matches(':focus-visible'))).toBeTruthy();
    const summaryFocus = await visualSnapshot(summary);
    const summaryRings = [
      ...(summaryFocus.outlineStyle !== 'none' && summaryFocus.outlineWidth >= 2 ? [channels(summaryFocus.outlineColor)] : []),
      ...(summaryFocus.boxShadow.match(/rgba?\([^)]+\)/g) || []).map(channels),
    ];
    expect(summaryRings.length, `${width}px summary focus indicator`).toBeGreaterThan(0);
    expect(Math.max(...summaryRings.map(color => ringContrast(color, summaryFocus.controlBackground))), `${width}px summary focus contrast`).toBeGreaterThanOrEqual(3);

    await page.keyboard.press('Enter');
    await expect(selector).toHaveAttribute('open', '');
    await page.keyboard.press('Tab');
    const firstMarket = panel.getByRole('link').first();
    await expect(firstMarket).toBeFocused();
    expect(await firstMarket.evaluate(element => element.matches(':focus-visible'))).toBeTruthy();
    await page.keyboard.press('Escape');
    await expect(selector).not.toHaveAttribute('open', '');
  }
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

test('featured review slider uses bounded controls and permanently pauses after manual navigation', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(fixture);
  const slider = page.locator('[data-twins-review-slider]');
  const track = slider.locator('.twins-brand-review-track');
  const status = slider.locator('[data-review-page-status]');
  await expect(status).toHaveText('1 of 5');
  await expect(slider.locator('.twins-brand-review-dots')).toHaveCount(0);
  const initial = await track.evaluate(element => getComputedStyle(element).transform);
  await slider.getByRole('button', { name: 'Next reviews' }).click();
  await expect(status).toHaveText('2 of 5');
  await expect.poll(() => track.evaluate(element => getComputedStyle(element).transform)).not.toBe(initial);
  await page.waitForTimeout(500);
  const afterManual = await track.evaluate(element => getComputedStyle(element).transform);
  await page.waitForTimeout(12_500);
  expect(await track.evaluate(element => getComputedStyle(element).transform)).toBe(afterManual);
  await slider.focus();
  await page.keyboard.press('ArrowRight');
  await expect(status).toHaveText('3 of 5');
  await slider.dispatchEvent('touchstart', { touches: [{ identifier: 1, clientX: 280, clientY: 100 }] });
  await slider.dispatchEvent('touchend', { changedTouches: [{ identifier: 1, clientX: 100, clientY: 100 }] });
  await expect(slider).toHaveAttribute('data-interaction-paused', 'true');
  await expect(status).toHaveText('4 of 5');
});

test('Reviews page keeps the complete verified collection static without autoplay markup', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(reviewsFixture);
  const list = page.locator('.twins-brand-review-list');
  await expect(list).toBeVisible();
  await expect(list.locator('.twins-brand-review-card')).toHaveCount(3);
  await expect(page.locator('[data-twins-review-slider], .twins-brand-review-track, [data-review-page-status]')).toHaveCount(0);
  const initial = await list.evaluate(element => getComputedStyle(element).transform);
  await page.waitForTimeout(12_500);
  expect(await list.evaluate(element => getComputedStyle(element).transform)).toBe(initial);
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

test('primary and contextual base CTAs expose circular arrows and a restrained sheen', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.goto(fixture);
  for (const locator of [
    page.getByRole('button', { name: 'Book Online' }).first(),
    page.getByRole('link', { name: 'Request a Quote' }).first(),
    page.getByRole('link', { name: 'Design Your Door' }),
  ]) {
    const after = await locator.evaluate(element => {
      const style = getComputedStyle(element, '::after');
      return { content: style.content, width: parseFloat(style.width), height: parseFloat(style.height), radius: style.borderRadius };
    });
    expect(after.content).toContain('→');
    expect(Math.abs(after.width - after.height)).toBeLessThan(1);
    expect(after.radius).not.toBe('0px');
    expect(await locator.evaluate(element => getComputedStyle(element).backgroundImage)).toContain('linear-gradient');
  }
});

test('brand controls remain readable and obviously interactive under WordPress host styles', async ({ page }) => {
  for (const width of [1440, 390]) {
    await page.setViewportSize({ width, height: width === 390 ? 844 : 1000 });
    await page.goto(fixture);
    await page.evaluate(() => {
      document.body.classList.add('twins-overhaul-preview', 'ast-single-post');
      document.querySelector('main')?.classList.add('entry-content');
    });
    await page.addStyleTag({
      content: `
        body.twins-overhaul-preview a { color: inherit; }
        .ast-single-post .entry-content a { text-decoration: underline; }
        .entry-content :where(h2), h2 { color: #3a3a3a; }
      `,
    });

    expect((await computedContrast(page.locator('.twins-brand-reviews h2'))).ratio).toBeGreaterThanOrEqual(4.5);
    expect((await computedContrast(page.locator('.twins-brand-hero-actions .twins-brand-cta--quote'))).ratio).toBeGreaterThanOrEqual(4.5);
    await expect(page.locator('.twins-brand-hero-actions .twins-brand-cta--quote')).toHaveCSS('text-decoration-line', 'none');

    for (const card of await page.locator('.twins-brand-market-card').all()) {
      expect((await computedContrast(card)).ratio, `${width}px ${await card.innerText()}`).toBeGreaterThanOrEqual(4.5);
      await expect(card).toHaveCSS('cursor', 'pointer');
      await expect(card).toHaveCSS('text-decoration-line', 'none');
      const bounds = await card.boundingBox();
      expect(bounds.width).toBeGreaterThanOrEqual(44);
      expect(bounds.height).toBeGreaterThanOrEqual(44);
    }
  }

  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.goto(fixture);
  const marketCard = page.locator('.twins-brand-market-card').first();
  const before = await marketCard.evaluate(element => getComputedStyle(element).transform);
  await marketCard.hover();
  await expect.poll(() => marketCard.evaluate(element => getComputedStyle(element).transform)).not.toBe(before);
});

test('service pathway cards expose a full-card click target', async ({ page }) => {
  for (const width of [1440, 390]) {
    await page.setViewportSize({ width, height: width === 390 ? 844 : 1000 });
    await page.goto(fixture);
    const card = page.locator('.twins-brand-service-card').first();
    await card.scrollIntoViewIfNeeded();
    const bounds = await card.boundingBox();
    const linkedText = await page.evaluate(({ x, y }) => {
      const hit = document.elementFromPoint(x, y);
      return hit?.closest('a')?.textContent?.trim() || '';
    }, { x: bounds.x + bounds.width - 18, y: bounds.y + 18 });
    expect(linkedText).toBe('Explore repair service');
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

test('all visible conversion and preview controls meet contrast in every real browser state and header mode', async ({ page, context }) => {
  test.setTimeout(120000);
  const cdp = await context.newCDPSession(page);
  await cdp.send('DOM.enable');
  await cdp.send('CSS.enable');
  const interactiveSelector = [
    '.twins-brand-phone',
    '.twins-brand-primary-nav .twins-brand-nav-trigger',
    '.twins-brand-menu-trigger',
    '.twins-brand-cta',
    '.twins-brand-mobile-actions a',
    '.twins-brand-preview-form input',
    '.twins-brand-preview-form select',
    '.twins-brand-preview-form textarea',
    '.twins-brand-preview-form button',
  ].join(',');
  const textSelector = `${interactiveSelector}, .twins-brand-preview-form label`;

  for (const width of widths) {
    await page.setViewportSize({ width, height: width <= 390 ? 844 : 1000 });
    await page.goto(fixture);
    for (const headerMode of ['initial', 'compressed']) {
      await page.evaluate(mode => scrollTo(0, mode === 'compressed' ? 420 : 0), headerMode);
      await expect(page.locator('[data-twins-header]')).toHaveAttribute('data-compressed', headerMode === 'compressed' ? 'true' : 'false');

      const probes = await page.evaluate(({ interactiveSelector: controls, textSelector: text }) => {
        let index = 0;
        const interactive = new Set(document.querySelectorAll(controls));
        return [...new Set(document.querySelectorAll(text))]
          .filter(element => element.checkVisibility({ checkOpacity: true, checkVisibilityCSS: true }) && element.getClientRects().length > 0)
          .map(element => {
            const token = `${index++}`;
            element.setAttribute('data-twins-contrast-probe', token);
            return {
              token,
              interactive: interactive.has(element),
              cta: element.matches('.twins-brand-cta'),
              label: (element.getAttribute('aria-label') || element.textContent || element.tagName).trim().replace(/\s+/g, ' ').slice(0, 80),
            };
          });
      }, { interactiveSelector, textSelector });
      expect(probes.length, `${width}px ${headerMode} probe coverage`).toBeGreaterThan(15);

      const documentNode = await cdp.send('DOM.getDocument', { depth: 0 });
      for (const probe of probes) {
        const selector = `[data-twins-contrast-probe="${probe.token}"]`;
        const locator = page.locator(selector);
        const node = await cdp.send('DOM.querySelector', { nodeId: documentNode.root.nodeId, selector });
        expect(node.nodeId, `${width}px ${headerMode} ${probe.label}`).toBeTruthy();
        const states = probe.interactive
          ? [['normal', []], ['hover', ['hover']], ['focus', ['focus', 'focus-visible']], ['pressed', ['active']]]
          : [['normal', []]];

        for (const [state, forcedPseudoClasses] of states) {
          await cdp.send('CSS.forcePseudoState', { nodeId: node.nodeId, forcedPseudoClasses });
          const snapshot = await visualSnapshot(locator);
          const textContrast = ratio(snapshot.foreground, snapshot.controlBackground);
          expect(textContrast, `${width}px ${headerMode} ${state} ${probe.label}`).toBeGreaterThanOrEqual(textThreshold(snapshot));
          if (isGold(snapshot.controlBackground)) {
            const isWhite = snapshot.foreground.r >= 245 && snapshot.foreground.g >= 245 && snapshot.foreground.b >= 245;
            expect(isWhite, `${width}px ${headerMode} ${state} white-on-gold ${probe.label}`).toBeFalsy();
          }

          if (state === 'focus') {
            const ringColors = [
              ...(snapshot.outlineStyle !== 'none' && snapshot.outlineWidth >= 2 ? [channels(snapshot.outlineColor)] : []),
              ...(snapshot.boxShadow.match(/rgba?\([^)]+\)/g) || []).map(channels),
            ];
            expect(ringColors.length, `${width}px ${headerMode} focus indicator ${probe.label}`).toBeGreaterThan(0);
            expect(Math.max(...ringColors.map(color => ringContrast(color, snapshot.controlBackground))), `${width}px ${headerMode} focus/control ${probe.label}`).toBeGreaterThanOrEqual(3);
            expect(Math.max(...ringColors.map(color => ringContrast(color, snapshot.adjacentBackground))), `${width}px ${headerMode} focus/adjacent ${probe.label}`).toBeGreaterThanOrEqual(3);
          }
          if (state === 'pressed' && probe.cta) expect(snapshot.transform, `${width}px ${headerMode} pressed ${probe.label}`).not.toBe('none');
        }
        await cdp.send('CSS.forcePseudoState', { nodeId: node.nodeId, forcedPseudoClasses: [] });
      }
      await page.evaluate(() => document.querySelectorAll('[data-twins-contrast-probe]').forEach(element => element.removeAttribute('data-twins-contrast-probe')));
    }
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
