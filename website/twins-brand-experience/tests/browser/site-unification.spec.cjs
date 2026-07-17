const { test, expect } = require('@playwright/test');

const fixture = '/tests/browser/fixtures/brand-host-shell.html';
const widths = [1440, 768, 390, 320];

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

function contrastRatio(one, two) {
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
  return contrastRatio(channels(values.color), background);
}

async function visibleLocators(locator) {
  const results = [];
  for (const candidate of await locator.all()) {
    if (await candidate.isVisible()) results.push(candidate);
  }
  return results;
}

async function prepareCareersFixture(page, width) {
  await page.setViewportSize({ width, height: 900 });
  await page.goto(fixture);
  await page.locator('.twins-brand-page-hero').evaluate(element => element.remove());
  await page.locator('.twins-brand-page-nav').evaluate(element => {
    element.setAttribute('aria-label', 'Careers page navigation');
    element.innerHTML = `
      <a href="#why-twins">Why Twins</a>
      <a href="#roles">Ways to contribute</a>
      <a href="#process">What happens next</a>
      <a href="#apply">Application preview</a>
    `;
  });
  await page.locator('.twins-brand-careers-hero').evaluate(element => {
    element.querySelector('h1').textContent = 'Do work you are proud to put your name on.';
    const cta = element.querySelector('.twins-brand-cta');
    cta.className = 'twins-brand-cta twins-brand-cta--quote';
    cta.textContent = 'Preview the application';
    const image = document.createElement('div');
    image.className = 'twins-brand-careers-hero-image';
    image.innerHTML = `
      <img
        class="twins-brand-careers-crew-photo"
        src="/assets/images/team/twins-crew-fleet-1280w.webp"
        width="1280"
        height="686"
        alt="Twins Garage Doors crew standing with branded service vehicles"
      >
    `;
    element.append(image);
  });
  await expect(page.locator('.twins-brand-careers-crew-photo')).toBeVisible();
}

test('Careers navigation and hero remain visible at every supported width', async ({ page }) => {
  for (const width of widths) {
    await prepareCareersFixture(page, width);

    const header = await page.locator('.twins-brand-header').boundingBox();
    const subnav = await page.locator('.twins-brand-page-nav').boundingBox();
    expect(subnav.y, `${width}px page navigation starts below header`).toBeGreaterThanOrEqual(header.y + header.height - 1);

    const cta = page.locator('.twins-brand-careers-hero .twins-brand-cta');
    await expect(cta).toHaveText(/Preview the application|Start your application/);
    expect(await computedContrast(cta), `${width}px Careers CTA contrast`).toBeGreaterThanOrEqual(4.5);
    const ctaBounds = await cta.boundingBox();
    if (width === 1440) expect(ctaBounds.y + ctaBounds.height, 'desktop Careers CTA stays inside the first fold').toBeLessThanOrEqual(900);

    const navStyle = await page.locator('.twins-brand-page-nav').evaluate(element => {
      const style = getComputedStyle(element);
      return { position: style.position, top: style.top };
    });
    if (width <= 768) {
      expect(navStyle.position, `${width}px page navigation is non-sticky`).toBe('relative');
      expect(['auto', '0px'], `${width}px page navigation clears sticky offset`).toContain(navStyle.top);
    } else {
      await page.evaluate(() => scrollTo(0, 80));
      await expect(page.locator('.twins-brand-header')).toHaveAttribute('data-compressed', 'true');
      await page.waitForTimeout(300);
      const contracts = await page.evaluate(() => ({
        headerHeight: getComputedStyle(document.body).getPropertyValue('--twins-header-height').trim(),
        navTop: getComputedStyle(document.querySelector('.twins-brand-page-nav')).top,
      }));
      expect(parseFloat(contracts.navTop), `${width}px page navigation consumes header contract`)
        .toBeCloseTo(parseFloat(contracts.headerHeight), 1);
      const compressedHeader = await page.locator('.twins-brand-header').boundingBox();
      const stickySubnav = await page.locator('.twins-brand-page-nav').boundingBox();
      expect(stickySubnav.y, `${width}px sticky page navigation clears compressed header`)
        .toBeGreaterThanOrEqual(compressedHeader.y + compressedHeader.height - 1);
    }

    const headingSize = await page.locator('.twins-brand-careers-hero h1').evaluate(element => parseFloat(getComputedStyle(element).fontSize));
    if (width === 1440) expect(headingSize, 'desktop Careers heading remains bounded').toBeLessThanOrEqual(90);

    const crew = await page.locator('.twins-brand-careers-crew-photo').boundingBox();
    if (width === 1440) expect(crew.y + await page.evaluate(() => scrollY), 'owned crew image enters the first 1100 document pixels').toBeLessThan(1100);
  }
});

test('branded sections escape Astra wrappers without gaps or horizontal overflow', async ({ page }) => {
  for (const width of widths) {
    await page.setViewportSize({ width, height: 900 });
    await page.goto(fixture);

    for (const selector of ['.twins-brand-header', '.twins-brand-page-hero', '.twins-brand-footer']) {
      const bounds = await page.locator(selector).boundingBox();
      expect(bounds.x, `${width}px ${selector} left edge`).toBeLessThanOrEqual(0.5);
      expect(bounds.width, `${width}px ${selector} width`).toBeGreaterThanOrEqual(width - 1);
    }

    const header = await page.locator('.twins-brand-header').boundingBox();
    const hero = await page.locator('.twins-brand-page-hero').boundingBox();
    expect(Math.abs(hero.y - (header.y + header.height)), `${width}px header/hero gap`).toBeLessThanOrEqual(1);
    await expect(page.locator('.entry-header')).toBeHidden();
    await expect(page.locator('#masthead.site-header')).toBeHidden();
    await expect(page.locator('#colophon.site-footer')).toBeHidden();
    await expect(page.locator('.elementor-location-header')).toBeHidden();
    await expect(page.locator('.elementor-location-footer')).toBeHidden();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth), `${width}px overflow`).toBeTruthy();
  }
});

test('shared host-shell variables expose the reserved header and bounded content contracts', async ({ page }) => {
  for (const width of widths) {
    await page.setViewportSize({ width, height: 900 });
    await page.goto(fixture);
    const readContracts = () => page.evaluate(() => {
      const body = getComputedStyle(document.body);
      const header = getComputedStyle(document.querySelector('.twins-brand-header'));
      const probe = getComputedStyle(document.querySelector('.twins-brand-content-shell-probe'));
      return {
        bodyHeader: body.getPropertyValue('--twins-header-height').trim(),
        inheritedHeader: header.getPropertyValue('--twins-header-height').trim(),
        renderedHeader: document.querySelector('.twins-brand-header').getBoundingClientRect().height,
        contentShell: body.getPropertyValue('--twins-content-shell').trim(),
        shellPadding: parseFloat(probe.paddingLeft),
      };
    });
    const values = await readContracts();
    expect(parseFloat(values.bodyHeader), `${width}px body header contract`).toBeCloseTo(values.renderedHeader, 1);
    expect(parseFloat(values.inheritedHeader), `${width}px inherited header contract`).toBeCloseTo(values.renderedHeader, 1);
    expect(values.contentShell, `${width}px content shell contract`).not.toBe('');
    expect(values.shellPadding, `${width}px bounded content gutter`).toBeCloseTo(width > 1320 ? (width - 1320) / 2 : 28, 1);

    await page.evaluate(() => scrollTo(0, 500));
    await expect(page.locator('.twins-brand-header')).toHaveAttribute('data-compressed', 'true');
    await page.waitForTimeout(300);
    const compressed = await readContracts();
    expect(parseFloat(compressed.bodyHeader), `${width}px compressed body header contract`).toBeCloseTo(compressed.renderedHeader, 1);
    expect(parseFloat(compressed.inheritedHeader), `${width}px compressed inherited header contract`).toBeCloseTo(compressed.renderedHeader, 1);
  }
});

test('brand foregrounds retain AA contrast under known WordPress host conflicts', async ({ page }) => {
  for (const width of widths) {
    await page.setViewportSize({ width, height: 900 });
    await page.goto(fixture);
    await page.addStyleTag({
      content: `
        body.twins-overhaul-preview a { color: inherit; }
        .entry-content h2 { color: #3a3a3a; }
      `,
    });

    const required = [
      ['Careers CTA', page.locator('.twins-brand-careers-hero a.twins-brand-cta')],
      ['page hero kicker', page.locator('.twins-brand-page-hero .twins-brand-kicker')],
      ['careers hero kicker', page.locator('.twins-brand-careers-hero .twins-brand-kicker')],
      ['review kicker', page.locator('.twins-brand-reviews-collection .twins-brand-kicker')],
      ['careers process kicker', page.locator('.twins-brand-careers-process .twins-brand-kicker')],
      ['home review kicker', page.locator('.twins-brand-review-proof .twins-brand-kicker')],
      ['door builder kicker', page.locator('.twins-brand-door-builder .twins-brand-kicker')],
      ['team values kicker', page.locator('.twins-brand-team-values .twins-brand-kicker')],
      ['team careers kicker', page.locator('.twins-brand-team-careers .twins-brand-kicker')],
    ];
    for (const [label, locator] of required) {
      expect(await computedContrast(locator), `${width}px ${label}`).toBeGreaterThanOrEqual(4.5);
    }

    const darkHeadings = [
      ['door builder heading', page.locator('.twins-brand-door-builder h2')],
      ['team values heading', page.locator('.twins-brand-team-values h2')],
      ['team careers heading', page.locator('.twins-brand-team-careers h2')],
      ['final CTA heading', page.locator('.twins-brand-final-cta h2')],
      ['footer group heading', page.locator('.twins-brand-footer-group h2')],
    ];
    for (const [label, locator] of darkHeadings) {
      expect(await computedContrast(locator), `${width}px ${label}`).toBeGreaterThanOrEqual(4.5);
    }

    const hostConflictAnchors = [
      ['production booking anchor', page.locator('.twins-brand-header a.twins-brand-cta--book')],
      ['footer phone anchor', page.locator('.twins-brand-footer a.twins-brand-phone')],
      ['page navigation anchor', page.locator('.twins-brand-page-nav a')],
    ];
    for (const [label, locator] of hostConflictAnchors) {
      expect(await computedContrast(locator), `${width}px ${label}`).toBeGreaterThanOrEqual(4.5);
    }

    for (const control of await visibleLocators(page.locator('[data-host-contrast-control]'))) {
      expect(await computedContrast(control), `${width}px header control: ${await control.innerText()}`).toBeGreaterThanOrEqual(4.5);
    }
    for (const anchor of await visibleLocators(page.locator('a'))) {
      const label = (await anchor.innerText()).trim();
      if (label === '') continue;
      expect(await computedContrast(anchor), `${width}px anchor: ${label}`).toBeGreaterThanOrEqual(4.5);
    }

    const pageNavLink = page.locator('.twins-brand-page-nav a');
    await pageNavLink.focus();
    expect(await pageNavLink.evaluate(element => element.matches(':focus-visible')), `${width}px page nav focus-visible`).toBeTruthy();
    expect(await computedContrast(pageNavLink), `${width}px page nav focus`).toBeGreaterThanOrEqual(4.5);
    await pageNavLink.hover();
    expect(await computedContrast(pageNavLink), `${width}px page nav hover`).toBeGreaterThanOrEqual(4.5);

    for (const button of await visibleLocators(page.locator('button'))) {
      expect(await computedContrast(button), `${width}px button: ${await button.innerText()}`).toBeGreaterThanOrEqual(4.5);
    }
    await expect(page.locator('.twins-brand-careers-hero a.twins-brand-cta')).toHaveCSS('text-decoration-line', 'none');

    const booking = page.locator('[data-twins-booking-dialog]');
    await booking.evaluate(element => { element.hidden = false; });
    await expect(booking).toBeVisible();
    expect(await computedContrast(booking.locator('.twins-brand-kicker')), `${width}px booking dialog kicker`).toBeGreaterThanOrEqual(4.5);
    expect(await computedContrast(booking.locator('h2')), `${width}px booking dialog heading`).toBeGreaterThanOrEqual(4.5);
  }
});

test('host-shell fixture remains local, inert, and free of production submission surfaces', async ({ page }) => {
  const requests = [];
  page.on('request', request => requests.push({ method: request.method(), origin: new URL(request.url()).origin }));
  await page.goto(fixture);

  await expect(page.locator('form, [type="submit"], [formaction]')).toHaveCount(0);
  expect(await page.locator('button').evaluateAll(buttons => buttons.every(button => button.type === 'button'))).toBeTruthy();
  expect(requests.every(request => request.method === 'GET')).toBeTruthy();
  expect(requests.every(request => request.origin === new URL(page.url()).origin)).toBeTruthy();
});
