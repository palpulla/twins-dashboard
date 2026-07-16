const { test, expect } = require('@playwright/test');

const stageUrl = process.env.TWINS_STAGE_URL || '';
const stageUser = process.env.TWINS_STAGE_USER || '';
const stagePassword = process.env.TWINS_STAGE_PASSWORD || '';
const suppliedConfiguration = [stageUrl, stageUser, stagePassword].filter(value => value !== '').length;
const configured = stageUrl !== '' && stageUser !== '' && stagePassword !== '';
const stageOrigin = 'https://danielj140.sg-host.com';
const routes = [
  '/',
  '/il/',
  '/reviews/',
  '/contact-us/',
  '/careers/',
  '/garage-door-spring-repair/',
  '/clopay-garage-doors/',
  '/clopay-gallery-steel/?product=12',
  '/door-builder/',
];
const viewports = [1440, 1200, 900, 768, 390, 360, 320];
const allowedDocumentRoutes = new Set(routes);
const wordpressControlPath = /^\/(?:wp-admin(?:\/|$)|wp-login\.php$|wp-json(?:\/|$)|xmlrpc\.php$|wp-cron\.php$|wp-comments-post\.php$|wp-signup\.php$|wp-activate\.php$)/i;
const dangerousQueryKeys = new Set([
  '_wpnonce',
  'action',
  'customize_changeset_uuid',
  'doing_wp_cron',
  'elementor-preview',
  'nonce',
  'preview',
  'preview_id',
  'preview_nonce',
  'rest_route',
  'wc-ajax',
]);
const staticAssetPath = /^(?:\/(?:favicon\.ico|robots\.txt)|(?:\/assets\/.+|\/wp-content\/(?:mu-plugins|plugins|themes|uploads)\/.+|\/wp-includes\/.+)\.(?:avif|css|eot|gif|ico|jpe?g|js|json|mjs|otf|png|svg|ttf|webp|woff2?))$/i;
const staticAssetQueryKeys = new Set(['v', 'ver']);

if (suppliedConfiguration !== 0 && suppliedConfiguration !== 3) {
  throw new Error('Private staging browser configuration is incomplete.');
}

test.use({
  baseURL: configured ? stageUrl : 'http://127.0.0.1:41739',
  httpCredentials: configured ? { username: stageUser, password: stagePassword } : undefined,
  trace: 'off',
  screenshot: 'off',
  video: 'off',
});

async function visibleCount(locator) {
  return locator.evaluateAll(elements => elements.filter(element => {
    const style = getComputedStyle(element);
    const box = element.getBoundingClientRect();
    return style.display !== 'none' && style.visibility !== 'hidden' && box.width > 0 && box.height > 0;
  }).length);
}

function requestViolation(request, allowedOrigin) {
  let url;
  try {
    url = new URL(request.url());
  } catch {
    return 'REQUEST_URL_INVALID';
  }
  if (url.username !== '' || url.password !== '') return 'REQUEST_URL_CREDENTIALS_FORBIDDEN';
  if (!['GET', 'HEAD'].includes(request.method())) return 'READ_ONLY_METHOD_REQUIRED';
  if (url.origin !== allowedOrigin) return 'SAME_ORIGIN_REQUIRED';
  if (wordpressControlPath.test(url.pathname)) return 'WORDPRESS_CONTROL_PATH_FORBIDDEN';
  for (const key of url.searchParams.keys()) {
    if (dangerousQueryKeys.has(key.toLowerCase())) return 'DANGEROUS_QUERY_FORBIDDEN';
  }
  const pathAndQuery = `${url.pathname}${url.search}`;
  if (request.resourceType() === 'document' && allowedDocumentRoutes.has(pathAndQuery)) return '';
  if (request.resourceType() === 'document') return 'DOCUMENT_ROUTE_NOT_ALLOWED';
  if (!staticAssetPath.test(url.pathname)) return 'STATIC_ASSET_PATH_FORBIDDEN';
  for (const [key, value] of url.searchParams.entries()) {
    if (!staticAssetQueryKeys.has(key.toLowerCase()) || !/^[a-z0-9._-]{1,128}$/i.test(value)) {
      return 'STATIC_ASSET_QUERY_FORBIDDEN';
    }
  }
  return '';
}

async function installReadOnlyRouting(page, allowedOrigin) {
  const violations = [];
  await page.route('**/*', async route => {
    const request = route.request();
    const code = requestViolation(request, allowedOrigin);
    if (code !== '') {
      violations.push(code);
      await route.abort('blockedbyclient');
      return;
    }
    await route.continue();
  });
  return violations;
}

async function assertNoNetworkViolations(page, violations) {
  try {
    await page.waitForLoadState('networkidle', { timeout: 2000 });
  } catch {
    throw new Error('PAGE_NOT_QUIESCENT');
  }
  await page.waitForTimeout(100);
  expect(violations).toEqual([]);
}

function assertFinalLocation(page, route) {
  const final = new URL(page.url());
  if (final.username !== '' || final.password !== '' || final.origin !== stageOrigin ||
      `${final.pathname}${final.search}` !== route || final.hash !== '') {
    throw new Error('FINAL_ROUTE_MISMATCH');
  }
}

async function assertGeometryAndContrast(page) {
  const result = await page.evaluate(() => {
    const visible = element => {
      const style = getComputedStyle(element);
      const box = element.getBoundingClientRect();
      return style.display !== 'none' && style.visibility !== 'hidden' &&
        Number(style.opacity) > 0 && box.width > 0 && box.height > 0;
    };
    const parseColor = value => {
      const match = String(value).match(/rgba?\(([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)(?:[,\s/]+([\d.]+))?\)/i);
      return match ? [Number(match[1]), Number(match[2]), Number(match[3]), match[4] === undefined ? 1 : Number(match[4])] : null;
    };
    const backgroundCandidates = element => {
      let current = element;
      while (current) {
        const style = getComputedStyle(current);
        const color = parseColor(style.backgroundColor);
        const backgroundImage = style.backgroundImage;
        if (backgroundImage !== 'none') {
          const colors = Array.from(backgroundImage.matchAll(
            /rgba?\(([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)(?:[,\s/]+([\d.]+))?\)/gi,
          ), match => [
            Number(match[1]),
            Number(match[2]),
            Number(match[3]),
            match[4] === undefined ? 1 : Number(match[4]),
          ]);
          const opaqueOverlayColors = colors.filter(candidate => candidate[3] >= 0.9);
          const hasOpaqueOverlay = opaqueOverlayColors.length >= 2;
          if (backgroundImage.includes('url(') && !hasOpaqueOverlay) return null;
          if (!color || color[3] < 0.9) {
            return hasOpaqueOverlay ? opaqueOverlayColors.map(candidate => candidate.slice(0, 3)) : null;
          }
          const base = color.slice(0, 3);
          return [base, ...colors.map(candidate => candidate.slice(0, 3).map(
            (channel, index) => Math.round((channel * candidate[3]) + (base[index] * (1 - candidate[3]))),
          ))];
        }
        if (color && color[3] >= 0.9) return [color.slice(0, 3)];
        current = current.parentElement;
      }
      return null;
    };
    const luminance = color => {
      const channels = color.map(value => {
        const normalized = value / 255;
        return normalized <= 0.04045 ? normalized / 12.92 : ((normalized + 0.055) / 1.055) ** 2.4;
      });
      return (0.2126 * channels[0]) + (0.7152 * channels[1]) + (0.0722 * channels[2]);
    };
    const ratio = (first, second) => {
      const a = luminance(first);
      const b = luminance(second);
      return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
    };
    const header = document.querySelector('.twins-brand-header');
    const sections = Array.from(document.querySelectorAll(
      '#twins-overhaul-main > section, main.twins-brand-page > section',
    )).filter(visible);
    const clientWidth = document.documentElement.clientWidth;
    const headerBox = header ? header.getBoundingClientRect() : null;
    const firstBox = sections[0] ? sections[0].getBoundingClientRect() : null;
    const contrastViolations = Array.from(document.querySelectorAll([
      '.twins-brand-header a',
      '.twins-brand-header button',
      'main h1',
      'main h2',
      'main h3',
      'main p',
      'main span',
      'main li',
      'main dt',
      'main dd',
      'main a',
      'main button',
      'main summary',
      '.twins-brand-kicker',
      '.twins-brand-footer a',
      '.twins-brand-footer p',
      '.twins-brand-footer span',
    ].join(', '))).filter(element => visible(element) && (element.textContent || '').trim() !== '').filter(element => {
      const style = getComputedStyle(element);
      const foreground = parseColor(style.color);
      const backgrounds = backgroundCandidates(element);
      if (!foreground || foreground[3] < 0.9 || backgrounds === null || backgrounds.length === 0) return true;
      const fontSize = parseFloat(style.fontSize);
      const parsedWeight = parseInt(style.fontWeight, 10);
      const fontWeight = Number.isFinite(parsedWeight) ? parsedWeight : (style.fontWeight === 'bold' ? 700 : 400);
      const threshold = fontSize >= 24 || (fontSize >= 18.66 && fontWeight >= 700) ? 3 : 4.5;
      return Math.min(...backgrounds.map(background => ratio(foreground.slice(0, 3), background))) < threshold;
    });
    return {
      headerGap: headerBox && firstBox ? Math.max(0, firstBox.top - headerBox.bottom) : null,
      sectionCount: sections.length,
      narrowSections: sections.filter(section => {
        const box = section.getBoundingClientRect();
        return box.left > 1.5 || box.right < clientWidth - 1.5;
      }).length,
      overflow: document.documentElement.scrollWidth > clientWidth,
      contrastViolations: contrastViolations.length,
    };
  });
  if (result.headerGap === null || result.headerGap > 2) throw new Error('HEADER_SECTION_GAP');
  if (result.sectionCount < 1 || result.narrowSections !== 0) throw new Error('SECTION_NOT_VIEWPORT_WIDE');
  if (result.overflow) throw new Error('HORIZONTAL_OVERFLOW');
  if (result.contrastViolations !== 0) throw new Error('TEXT_CONTRAST_INVALID');
}

async function twinMotionSnapshot(page) {
  return page.locator('.twins-brand-twin').evaluateAll(elements => elements.map(element => {
    const style = getComputedStyle(element);
    const box = element.getBoundingClientRect();
    return {
      animationName: style.animationName,
      transform: style.transform,
      left: Number(box.left.toFixed(2)),
      top: Number(box.top.toFixed(2)),
    };
  }));
}

async function assertMobileHomeBehavior(page, width) {
  if (![390, 320].includes(width)) return;
  const first = await twinMotionSnapshot(page);
  await page.waitForTimeout(450);
  const second = await twinMotionSnapshot(page);
  if (first.length !== 2 || second.length !== 2 ||
      first.some(item => item.animationName === 'none') ||
      first.every((item, index) => item.transform === second[index].transform &&
        item.left === second[index].left && item.top === second[index].top)) {
    throw new Error('TWIN_MOTION_MISSING');
  }
  const trigger = page.locator('.twins-brand-menu-trigger');
  await trigger.click();
  if (await trigger.getAttribute('aria-expanded') !== 'true' ||
      await page.locator('#twins-brand-drawer').getAttribute('aria-hidden') !== 'false' ||
      await visibleCount(page.locator('#twins-brand-drawer nav a')) < 1) {
    throw new Error('MOBILE_MENU_INVALID');
  }
  const quote = page.locator('#twins-brand-drawer .twins-brand-cta--quote');
  const href = await quote.getAttribute('href');
  if (href === null || new URL(href, stageUrl).href !== `${stageOrigin}/contact-us/`) {
    throw new Error('MOBILE_MENU_INVALID');
  }
  await Promise.all([
    page.waitForURL(`${stageOrigin}/contact-us/`),
    quote.click(),
  ]);
  assertFinalLocation(page, '/contact-us/');
  await page.goBack({ waitUntil: 'domcontentloaded' });
  assertFinalLocation(page, '/');
}

async function assertRouteSpecific(page, route, width) {
  if (route === '/') {
    await expect(page.locator('.twins-brand-home')).toHaveCount(1);
    await expect(page.locator('h1')).toHaveText('Garage Door Repair & Installation, Done Right Today.');
    await expect(page.getByRole('link', { name: /^Request a Quote(?: →)?$/ }).first()).toBeVisible();
    await expect(page.getByRole('button', { name: /^Book Online(?: →)?$/ }).first()).toBeVisible();
    await expect(page.locator('.twins-brand-logo img')).toBeVisible();
    expect(await visibleCount(page.locator('.twins-brand-twin'))).toBe(2);
    await expect(page.locator('[data-section="team-story"] picture')).toHaveCount(2);
    await expect(page.locator('[data-section="review-slider"]')).toHaveCount(1);
    await expect(page.locator('nav[aria-label="Primary navigation"]')).toHaveCount(1);
    await assertMobileHomeBehavior(page, width);
  }
  if (route === '/il/') {
    const contextualPhones = page.locator([
      '.twins-brand-header > .twins-brand-phone',
      '.twins-brand-footer-intro > .twins-brand-phone',
      '.twins-brand-mobile-actions > a:first-child',
      'main .twins-brand-cta--call',
      'main .twins-brand-service-phone',
    ].join(', '));
    expect(await contextualPhones.count()).toBeGreaterThanOrEqual(3);
    expect(await contextualPhones.evaluateAll(links => links.every(link => link.getAttribute('href') === 'tel:+18158002025'))).toBe(true);
    expect(await contextualPhones.evaluateAll(links => links.some(link => /608|833/.test(link.textContent || '')))).toBe(false);
  }
  if (route === '/contact-us/') {
    await expect(page.locator('.twins-brand-contact-page')).toHaveCount(1);
    await expect(page.locator('h1')).toHaveText('Request a Quote');
    await expect(page.locator('[data-preview-kind="quote"]')).toHaveCount(1);
    for (const phone of ['tel:+16084202377', 'tel:+18338332010', 'tel:+18158002025']) {
      await expect(page.locator(`.twins-brand-contact-market-grid a[href="${phone}"]`)).toHaveCount(1);
    }
  }
  if (route === '/reviews/') {
    await expect(page.locator('.twins-brand-reviews-page')).toHaveCount(1);
    expect(await page.locator('.twins-brand-review-list .twins-brand-review-card--list').count()).toBeGreaterThan(0);
    await expect(page.locator('[data-twins-review-slider], .twins-brand-review-dots')).toHaveCount(0);
  }
  if (route === '/careers/') {
    await expect(page.locator('.twins-brand-careers-page')).toHaveCount(1);
    await expect(page.locator('.twins-brand-careers-crew-photo')).toBeVisible();
    await expect(page.locator('[data-preview-kind="application"]')).toHaveCount(1);
    await expect(page.getByRole('link', { name: 'Preview the application' })).toBeVisible();
  }
  if (route === '/garage-door-spring-repair/') {
    await expect(page.locator('.twins-brand-service-page .twins-brand-direct-answer')).toHaveCount(1);
    await expect(page.locator('.twins-brand-service-safety')).toContainText(/dangerous tension/i);
    await expect(page.locator('.twins-brand-service-safety')).toContainText(/trained professional/i);
    const faqCount = await page.locator('.twins-brand-faq details').count();
    expect(faqCount).toBeGreaterThanOrEqual(4);
    expect(faqCount).toBeLessThanOrEqual(6);
  }
  if (route === '/clopay-garage-doors/' || route === '/clopay-gallery-steel/?product=12') {
    await expect(page.locator('.twins-brand-catalog-page')).toHaveCount(1);
    expect(await page.locator('.twins-brand-catalog-page img').count()).toBeGreaterThan(0);
    expect(await page.locator('.twins-brand-catalog-page img').evaluateAll(images => images.every(image => {
      const url = new URL(image.currentSrc || image.src, location.href);
      return url.origin === location.origin &&
        /^\/wp-content\/mu-plugins\/twins-staging-assets\/clopay\/[a-f0-9]{2}\/[a-f0-9]{64}\.(?:webp|jpg)$/.test(url.pathname);
    }))).toBe(true);
  }
  if (route === '/clopay-gallery-steel/?product=12') {
    await expect(page.locator('h1')).toContainText(/Gallery.*Steel/i);
  }
  if (route === '/door-builder/') {
    const builder = page.locator('[data-twins-overhaul-builder]');
    await expect(builder).toHaveAttribute('data-builder-enhanced', 'true');
    await expect(page.locator('[data-builder-product-id]')).toHaveCount(23);
  }
}

test('request guard aborts fixture POST and off-origin traffic before transmission', async ({ page, request }) => {
  const fixtureOrigin = 'http://127.0.0.1:41739';
  const violations = await installReadOnlyRouting(page, fixtureOrigin);
  await page.goto(`${fixtureOrigin}/`, { waitUntil: 'domcontentloaded' });
  await page.evaluate(async () => {
    await fetch('/__blocked-post', { method: 'POST', body: 'must-not-arrive' }).catch(() => {});
    await fetch('http://localhost:41739/__blocked-off-origin').catch(() => {});
    await fetch('/wp-admin/').catch(() => {});
    await fetch('/?rest_route=/wp/v2/users').catch(() => {});
    await fetch('/custom.php?do=delete').catch(() => {});
    await fetch('/assets/css/twins-brand.css?do=delete').catch(() => {});
    const frame = document.createElement('iframe');
    frame.src = '/unapproved/';
    document.body.append(frame);
  });
  await page.waitForTimeout(100);
  expect([...violations].sort()).toEqual([
    'DANGEROUS_QUERY_FORBIDDEN',
    'DOCUMENT_ROUTE_NOT_ALLOWED',
    'READ_ONLY_METHOD_REQUIRED',
    'SAME_ORIGIN_REQUIRED',
    'STATIC_ASSET_PATH_FORBIDDEN',
    'STATIC_ASSET_QUERY_FORBIDDEN',
    'WORDPRESS_CONTROL_PATH_FORBIDDEN',
  ]);

  const ledgerResponse = await request.get(`${fixtureOrigin}/__fixture-ledger`);
  expect(ledgerResponse.ok()).toBe(true);
  const ledger = await ledgerResponse.json();
  expect(ledger.some(entry => entry.path === '/__blocked-post')).toBe(false);
  expect(ledger.some(entry => entry.path === '/__blocked-off-origin')).toBe(false);
  expect(ledger.some(entry => entry.path === '/wp-admin/')).toBe(false);
  expect(ledger.some(entry => entry.path === '/unapproved/')).toBe(false);
  expect(ledger.some(entry => entry.path === '/custom.php')).toBe(false);
});

test.describe('exact private staging candidate', () => {
  test.skip(!configured, 'private staging credentials are environment-only');

  test('unauthenticated staging requires the pinned Basic Auth boundary', async ({ browser }) => {
    const context = await browser.newContext({ serviceWorkers: 'block' });
    const page = await context.newPage();
    const violations = await installReadOnlyRouting(page, stageOrigin);
    try {
      const response = await page.goto(stageUrl, { waitUntil: 'domcontentloaded' });
      if (response === null || ![401, 403].includes(response.status()) ||
          !/\bbasic\b/i.test(String(response.headers()['www-authenticate'] || ''))) {
        throw new Error('BASIC_AUTH_CHALLENGE_MISSING');
      }
      await assertNoNetworkViolations(page, violations);
    } finally {
      await context.close();
    }
  });

  for (const width of viewports) {
    for (const route of routes) {
      test(`${route} is safe and unified at ${width}px`, async ({ page }) => {
        expect(stageUrl).toBe('https://danielj140.sg-host.com/');
        await page.setViewportSize({ width, height: 900 });
        const violations = await installReadOnlyRouting(page, stageOrigin);

        const response = await page.goto(route, { waitUntil: 'domcontentloaded' });
        expect(response).not.toBeNull();
        expect(response.status()).toBeLessThan(400);
        expect((response.headers()['x-robots-tag'] || '').toLowerCase()).toContain('noindex');
        assertFinalLocation(page, route);
        await assertNoNetworkViolations(page, violations);

        await expect(page.locator('.twins-brand-header')).toHaveCount(1);
        await expect(page.locator('.twins-brand-header')).toBeVisible();
        await expect(page.locator('.twins-brand-footer')).toHaveCount(1);
        await expect(page.locator('.twins-brand-footer')).toBeVisible();
        await expect(page.locator('.twins-overhaul-header')).toHaveCount(0);
        expect(await visibleCount(page.locator('h1'))).toBe(1);
        await expect(page.locator('form, [type="submit"], [formaction]')).toHaveCount(0);

        await assertGeometryAndContrast(page);
        await assertRouteSpecific(page, route, width);
        await assertNoNetworkViolations(page, violations);
        assertFinalLocation(page, route);
      });
    }
  }

  test('staging interactions remain inert and same-origin', async ({ page }) => {
    const violations = await installReadOnlyRouting(page, stageOrigin);
    await page.goto('/');
    await page.getByRole('button', { name: /^Book Online(?: →)?$/ }).first().click();
    await expect(page.getByRole('dialog', { name: 'Book with Twins' })).toBeVisible();
    const quote = page.getByRole('link', { name: /^Request a Quote(?: →)?$/ }).first();
    const href = await quote.getAttribute('href');
    expect(new URL(href, stageUrl).origin).toBe(stageOrigin);
    await assertNoNetworkViolations(page, violations);
    assertFinalLocation(page, '/');
  });

  test('mobile Twins become static when reduced motion is requested', async ({ browser }) => {
    for (const width of [390, 320]) {
      const context = await browser.newContext({
        baseURL: stageUrl,
        httpCredentials: { username: stageUser, password: stagePassword },
        reducedMotion: 'reduce',
        serviceWorkers: 'block',
        viewport: { width, height: 900 },
      });
      const page = await context.newPage();
      const violations = await installReadOnlyRouting(page, stageOrigin);
      try {
        const response = await page.goto('/', { waitUntil: 'domcontentloaded' });
        expect(response).not.toBeNull();
        expect(response.status()).toBeLessThan(400);
        assertFinalLocation(page, '/');
        await assertNoNetworkViolations(page, violations);
        const first = await twinMotionSnapshot(page);
        await page.waitForTimeout(450);
        const second = await twinMotionSnapshot(page);
        if (first.length !== 2 || first.some(item => item.animationName !== 'none') ||
            JSON.stringify(first) !== JSON.stringify(second)) {
          throw new Error('REDUCED_MOTION_INVALID');
        }
        assertFinalLocation(page, '/');
      } finally {
        await context.close();
      }
    }
  });
});
