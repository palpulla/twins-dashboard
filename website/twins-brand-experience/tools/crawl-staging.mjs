import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';

export const STAGE_URL = 'https://danielj140.sg-host.com/';
export const STAGE_ORIGIN = 'https://danielj140.sg-host.com';
export const ROUTES = Object.freeze([
  '/',
  '/il/',
  '/reviews/',
  '/contact-us/',
  '/careers/',
  '/garage-door-spring-repair/',
  '/clopay-garage-doors/',
  '/clopay-gallery-steel/?product=12',
  '/door-builder/',
]);
export const VIEWPORTS = Object.freeze([1440, 1200, 900, 768, 390, 360, 320]);
export const SCREENSHOT_VIEWPORTS = Object.freeze([1440, 768, 390, 320]);

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const evidenceRoot = path.join(root, 'test-results', 'staging-crawl');
const allowedMethods = new Set(['GET', 'HEAD']);
const allowedDocumentRoutes = new Set(ROUTES);
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
const staticAssetPath = /^(?:\/(?:favicon\.ico|robots\.txt)|(?:\/assets\/.+|(?:\/(?:wi|ky|il))?\/wp-content\/(?:mu-plugins|plugins|themes|uploads)\/.+|(?:\/(?:wi|ky|il))?\/wp-includes\/.+)\.(?:avif|css|eot|gif|ico|jpe?g|js|json|mjs|otf|png|svg|ttf|webp|woff2?))$/i;
const staticAssetQueryKeys = new Set(['v', 'ver']);
const envelope = {
  writeAuthority: false,
  productionWriteAuthority: false,
  stagingMutation: false,
};

export function validateRequest(method, target, resourceType = '') {
  let url;
  try {
    url = new URL(target);
  } catch {
    throw new Error('REQUEST_URL_INVALID');
  }
  if (url.username !== '' || url.password !== '') throw new Error('REQUEST_URL_CREDENTIALS_FORBIDDEN');
  if (!allowedMethods.has(method)) throw new Error('READ_ONLY_METHOD_REQUIRED');
  if (url.origin !== STAGE_ORIGIN) throw new Error('SAME_ORIGIN_REQUIRED');
  if (wordpressControlPath.test(url.pathname)) throw new Error('WORDPRESS_CONTROL_PATH_FORBIDDEN');
  for (const key of url.searchParams.keys()) {
    if (dangerousQueryKeys.has(key.toLowerCase())) throw new Error('DANGEROUS_QUERY_FORBIDDEN');
  }
  const pathAndQuery = `${url.pathname}${url.search}`;
  if ((resourceType === 'document' || resourceType === '') && allowedDocumentRoutes.has(pathAndQuery)) {
    return { method, origin: url.origin };
  }
  if (resourceType === 'document') throw new Error('DOCUMENT_ROUTE_NOT_ALLOWED');
  if (!staticAssetPath.test(url.pathname)) throw new Error('STATIC_ASSET_PATH_FORBIDDEN');
  for (const [key, value] of url.searchParams.entries()) {
    if (!staticAssetQueryKeys.has(key.toLowerCase()) || !/^[a-z0-9._-]{1,128}$/i.test(value)) {
      throw new Error('STATIC_ASSET_QUERY_FORBIDDEN');
    }
  }
  return { method, origin: url.origin };
}

export function validateFinalLocation(target, expectedRoute) {
  let url;
  try {
    url = new URL(target);
  } catch {
    throw new Error('FINAL_ROUTE_INVALID');
  }
  if (url.username !== '' || url.password !== '' || url.origin !== STAGE_ORIGIN ||
      `${url.pathname}${url.search}` !== expectedRoute || url.hash !== '') {
    throw new Error('FINAL_ROUTE_MISMATCH');
  }
  return { pathAndQuery: expectedRoute };
}

export function contrastRatio(first, second) {
  const luminance = color => {
    const channels = color.map(value => {
      const normalized = value / 255;
      return normalized <= 0.04045 ? normalized / 12.92 : ((normalized + 0.055) / 1.055) ** 2.4;
    });
    return (0.2126 * channels[0]) + (0.7152 * channels[1]) + (0.0722 * channels[2]);
  };
  const a = luminance(first);
  const b = luminance(second);
  return Number(((Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05)).toFixed(6));
}

function emit(status, details = {}, exitCode = 0) {
  process.stdout.write(`${JSON.stringify({ status, ...envelope, ...details })}\n`);
  process.exitCode = exitCode;
}

function safeSlug(route) {
  if (route === '/') return 'home';
  return route
    .replace(/^\//, '')
    .replace(/\/$/, '')
    .replace(/[^a-z0-9]+/gi, '-')
    .replace(/^-|-$/g, '')
    .toLowerCase();
}

function controlledError(error) {
  const message = error instanceof Error ? error.message : '';
  return /^[A-Z0-9_:-]+$/.test(message) ? message : 'CRAWL_ASSERTION_FAILED';
}

async function visibleCount(page, selector) {
  return page.locator(selector).evaluateAll(elements => elements.filter(element => {
    const style = getComputedStyle(element);
    const box = element.getBoundingClientRect();
    return style.display !== 'none' && style.visibility !== 'hidden' && box.width > 0 && box.height > 0;
  }).length);
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
      await visibleCount(page, '#twins-brand-drawer nav a') < 1) {
    throw new Error('MOBILE_MENU_INVALID');
  }
  const quote = page.locator('#twins-brand-drawer .twins-brand-cta--quote');
  const href = await quote.getAttribute('href');
  if (href === null || new URL(href, STAGE_URL).href !== `${STAGE_ORIGIN}/contact-us/`) {
    throw new Error('MOBILE_MENU_INVALID');
  }
  await Promise.all([
    page.waitForURL(`${STAGE_ORIGIN}/contact-us/`),
    quote.click(),
  ]);
  validateFinalLocation(page.url(), '/contact-us/');
  await page.goBack({ waitUntil: 'domcontentloaded' });
  validateFinalLocation(page.url(), '/');
}

async function assertRouteSpecific(page, route, width) {
  if (route === '/') {
    if (await page.locator('.twins-brand-home').count() !== 1) throw new Error('HOME_SHELL_INVALID');
    if ((await page.locator('h1').innerText()).trim() !== 'Garage Door Repair & Installation, Done Right Today.') {
      throw new Error('HOME_HEADING_INVALID');
    }
    if (await page.getByRole('link', { name: /^Request a Quote(?: →)?$/ }).count() < 1) throw new Error('HOME_QUOTE_CTA_MISSING');
    if (await page.getByRole('button', { name: /^Book Online(?: →)?$/ }).count() < 1) throw new Error('HOME_BOOKING_CTA_MISSING');
    if (await visibleCount(page, '.twins-brand-logo img') !== 1) throw new Error('HOME_LOGO_MISSING');
    if (await visibleCount(page, '.twins-brand-twin') !== 2) throw new Error('HOME_TWINS_MISSING');
    if (await page.locator('[data-section="team-story"] picture').count() !== 2) throw new Error('HOME_TEAM_IMAGES_MISSING');
    if (await page.locator('[data-section="review-slider"]').count() !== 1) throw new Error('HOME_REVIEW_PROOF_MISSING');
    if (await page.locator('nav[aria-label="Primary navigation"]').count() !== 1) throw new Error('HOME_NAVIGATION_MISSING');
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
    if (await contextualPhones.count() < 3 ||
        !(await contextualPhones.evaluateAll(links => links.every(link => link.getAttribute('href') === 'tel:+18158002025')))) {
      throw new Error('ILLINOIS_PHONE_INVALID');
    }
    if (await contextualPhones.evaluateAll(links => links.some(link => /608|833/.test(link.textContent || '')))) {
      throw new Error('ILLINOIS_CONTEXT_PHONE_CONFLICT');
    }
  }
  if (route === '/contact-us/') {
    if (await page.locator('.twins-brand-contact-page').count() !== 1) throw new Error('CONTACT_SHELL_INVALID');
    if ((await page.locator('h1').innerText()).trim() !== 'Request a Quote') throw new Error('CONTACT_HEADING_INVALID');
    if (await page.locator('[data-preview-kind="quote"]').count() !== 1) throw new Error('CONTACT_QUOTE_PREVIEW_MISSING');
    for (const phone of ['tel:+16084202377', 'tel:+18338332010', 'tel:+18158002025']) {
      if (await page.locator(`.twins-brand-contact-market-grid a[href="${phone}"]`).count() !== 1) {
        throw new Error('CONTACT_MARKET_PHONE_MISSING');
      }
    }
  }
  if (route === '/reviews/') {
    if (await page.locator('.twins-brand-reviews-page').count() !== 1) throw new Error('REVIEWS_SHELL_INVALID');
    if (await page.locator('.twins-brand-review-list .twins-brand-review-card--list').count() < 1) throw new Error('REVIEWS_COLLECTION_MISSING');
    if (await page.locator('[data-twins-review-slider], .twins-brand-review-dots').count() !== 0) throw new Error('REVIEWS_COLLECTION_MOVES');
  }
  if (route === '/careers/') {
    if (await page.locator('.twins-brand-careers-page').count() !== 1) throw new Error('CAREERS_SHELL_INVALID');
    if (await visibleCount(page, '.twins-brand-careers-crew-photo') !== 1) throw new Error('CAREERS_CREW_PHOTO_MISSING');
    if (await page.locator('[data-preview-kind="application"]').count() !== 1) throw new Error('CAREERS_PREVIEW_MISSING');
  }
  if (route === '/garage-door-spring-repair/') {
    if (await page.locator('.twins-brand-service-page .twins-brand-direct-answer').count() !== 1) throw new Error('SPRING_DIRECT_ANSWER_MISSING');
    const safety = (await page.locator('.twins-brand-service-safety').innerText()).toLowerCase();
    if (!safety.includes('dangerous tension') || !safety.includes('trained professional')) throw new Error('SPRING_SAFETY_INVALID');
    const faqCount = await page.locator('.twins-brand-faq details').count();
    if (faqCount < 4 || faqCount > 6) throw new Error('SPRING_FAQ_COUNT_INVALID');
  }
  if (route === '/clopay-garage-doors/' || route === '/clopay-gallery-steel/?product=12') {
    if (await page.locator('.twins-brand-catalog-page').count() !== 1) throw new Error('CLOPAY_SHELL_INVALID');
    if (await page.locator('.twins-brand-catalog-page img').count() < 1) throw new Error('CLOPAY_IMAGE_MISSING');
    const localImages = await page.locator('.twins-brand-catalog-page img').evaluateAll(images => images.every(image => {
      const url = new URL(image.currentSrc || image.src, location.href);
      return url.origin === location.origin &&
        /^\/wp-content\/mu-plugins\/twins-staging-assets\/clopay\/[a-f0-9]{2}\/[a-f0-9]{64}\.(?:webp|jpg)$/.test(url.pathname);
    }));
    if (!localImages) throw new Error('CLOPAY_IMAGE_AUTHORITY_INVALID');
  }
  if (route === '/clopay-gallery-steel/?product=12') {
    const heading = (await page.locator('h1').innerText()).toLowerCase();
    if (!heading.includes('gallery') || !heading.includes('steel')) throw new Error('CLOPAY_PRODUCT_MAPPING_INVALID');
  }
  if (route === '/door-builder/') {
    const builder = page.locator('[data-twins-overhaul-builder]');
    await builder.waitFor({ state: 'attached', timeout: 5000 });
    if (await builder.getAttribute('data-builder-enhanced') !== 'true') throw new Error('BUILDER_ENHANCEMENT_MISSING');
    if (await page.locator('[data-builder-product-id]').count() !== 23) throw new Error('BUILDER_CATALOG_COUNT_INVALID');
  }
}

async function settleAndAssertNetwork(page, networkViolations) {
  try {
    await page.waitForLoadState('networkidle', { timeout: 2000 });
  } catch {
    throw new Error('PAGE_NOT_QUIESCENT');
  }
  await page.waitForTimeout(100);
  if (networkViolations.length !== 0) throw new Error(networkViolations[0]);
}

async function assertGeometryAndContrast(page) {
  const result = await page.evaluate(() => {
    const visible = element => {
      const style = getComputedStyle(element);
      const box = element.getBoundingClientRect();
      return style.display !== 'none' && style.visibility !== 'hidden' &&
        Number(style.opacity) > 0 && box.width > 0 && box.height > 0;
    };
    const header = document.querySelector('.twins-brand-header');
    const sections = Array.from(document.querySelectorAll(
      '#twins-overhaul-main > section, main.twins-brand-page > section',
    )).filter(visible);
    const clientWidth = document.documentElement.clientWidth;
    const headerBox = header ? header.getBoundingClientRect() : null;
    const firstBox = sections[0] ? sections[0].getBoundingClientRect() : null;
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
    ].join(', '))).filter(element => visible(element) && (element.textContent || '').trim() !== '').flatMap(element => {
      const style = getComputedStyle(element);
      const foreground = parseColor(style.color);
      const backgrounds = backgroundCandidates(element);
      if (!foreground || foreground[3] < 0.9 || backgrounds === null || backgrounds.length === 0) return ['unproven'];
      const fontSize = parseFloat(style.fontSize);
      const parsedWeight = parseInt(style.fontWeight, 10);
      const fontWeight = Number.isFinite(parsedWeight) ? parsedWeight : (style.fontWeight === 'bold' ? 700 : 400);
      const threshold = fontSize >= 24 || (fontSize >= 18.66 && fontWeight >= 700) ? 3 : 4.5;
      return Math.min(...backgrounds.map(background => ratio(foreground.slice(0, 3), background))) < threshold
        ? ['low']
        : [];
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

async function inspectRoute(page, route, width, networkViolations) {
  await page.setViewportSize({ width, height: 900 });
  const response = await page.goto(route, { waitUntil: 'domcontentloaded', timeout: 30000 });
  if (response === null) throw new Error('NAVIGATION_RESPONSE_MISSING');
  if (response.status() >= 400) throw new Error(`HTTP_STATUS_${response.status()}`);
  validateFinalLocation(page.url(), route);
  if (!String(response.headers()['x-robots-tag'] || '').toLowerCase().includes('noindex')) {
    throw new Error('NOINDEX_HEADER_MISSING');
  }
  await settleAndAssertNetwork(page, networkViolations);
  if (await page.locator('.twins-brand-header').count() !== 1) throw new Error('HEADER_COUNT_INVALID');
  if (await page.locator('.twins-brand-footer').count() !== 1) throw new Error('FOOTER_COUNT_INVALID');
  if (await page.locator('.twins-overhaul-header').count() !== 0) throw new Error('LEGACY_HEADER_PRESENT');
  if (await visibleCount(page, 'h1') !== 1) throw new Error('VISIBLE_H1_COUNT_INVALID');
  if (await page.locator('form, [type="submit"], [formaction]').count() !== 0) throw new Error('SUBMISSION_SURFACE_PRESENT');

  await assertGeometryAndContrast(page);
  await assertRouteSpecific(page, route, width);
  return {
    route,
    width,
    status: response.status(),
    noindex: true,
    title: await page.title(),
  };
}

async function verifyBasicAuthBoundary(browser) {
  const context = await browser.newContext({ serviceWorkers: 'block' });
  const page = await context.newPage();
  const networkViolations = [];
  await page.route('**/*', async intercepted => {
    const request = intercepted.request();
    try {
      validateRequest(request.method(), request.url(), request.resourceType());
      await intercepted.continue();
    } catch (error) {
      networkViolations.push(controlledError(error));
      await intercepted.abort('blockedbyclient');
    }
  });
  try {
    const response = await page.goto(STAGE_URL, { waitUntil: 'domcontentloaded', timeout: 15000 });
    if (response === null || ![401, 403].includes(response.status()) ||
        !/\bbasic\b/i.test(String(response.headers()['www-authenticate'] || ''))) {
      throw new Error('BASIC_AUTH_CHALLENGE_MISSING');
    }
    await page.waitForTimeout(100);
    if (networkViolations.length !== 0) throw new Error(networkViolations[0]);
    return {
      status: response.status(),
      challenge: 'Basic',
      writeAuthority: false,
    };
  } finally {
    await page.close();
    await context.close();
  }
}

async function verifyReducedMotion(browser, user, password) {
  const widths = [];
  for (const width of [390, 320]) {
    const context = await browser.newContext({
      baseURL: STAGE_URL,
      httpCredentials: { username: user, password },
      reducedMotion: 'reduce',
      serviceWorkers: 'block',
      viewport: { width, height: 900 },
    });
    const page = await context.newPage();
    const networkViolations = [];
    await page.route('**/*', async intercepted => {
      const request = intercepted.request();
      try {
        validateRequest(request.method(), request.url(), request.resourceType());
        await intercepted.continue();
      } catch (error) {
        networkViolations.push(controlledError(error));
        await intercepted.abort('blockedbyclient');
      }
    });
    try {
      const response = await page.goto('/', { waitUntil: 'domcontentloaded', timeout: 30000 });
      if (response === null || response.status() >= 400) throw new Error('REDUCED_MOTION_INVALID');
      validateFinalLocation(page.url(), '/');
      await settleAndAssertNetwork(page, networkViolations);
      const first = await twinMotionSnapshot(page);
      await page.waitForTimeout(450);
      const second = await twinMotionSnapshot(page);
      if (first.length !== 2 || first.some(item => item.animationName !== 'none') ||
          JSON.stringify(first) !== JSON.stringify(second)) {
        throw new Error('REDUCED_MOTION_INVALID');
      }
      validateFinalLocation(page.url(), '/');
      widths.push(width);
    } finally {
      await context.close();
    }
  }
  return { widths, status: 'static', writeAuthority: false };
}

async function runConfiguredCrawl(user, password) {
  fs.rmSync(evidenceRoot, { recursive: true, force: true });
  fs.mkdirSync(evidenceRoot, { recursive: true, mode: 0o700 });
  const browser = await chromium.launch({ headless: true });
  const visits = [];
  const failures = [];
  const screenshotPaths = [];
  const failureScreenshotPaths = [];
  let basicAuth = null;
  let reducedMotion = null;
  let context = null;

  try {
    basicAuth = await verifyBasicAuthBoundary(browser);
    context = await browser.newContext({
      baseURL: STAGE_URL,
      httpCredentials: { username: user, password },
      serviceWorkers: 'block',
    });
    for (const width of VIEWPORTS) {
      for (const route of ROUTES) {
        const page = await context.newPage();
        const networkViolations = [];
        await page.route('**/*', async intercepted => {
          const request = intercepted.request();
          try {
            validateRequest(request.method(), request.url(), request.resourceType());
            await intercepted.continue();
          } catch (error) {
            networkViolations.push(controlledError(error));
            await intercepted.abort('blockedbyclient');
          }
        });
        try {
          const visit = await inspectRoute(page, route, width, networkViolations);
          if (SCREENSHOT_VIEWPORTS.includes(width)) {
            const relative = path.posix.join('screenshots', `${safeSlug(route)}-${width}.png`);
            const destination = path.join(evidenceRoot, relative);
            fs.mkdirSync(path.dirname(destination), { recursive: true, mode: 0o700 });
            await page.screenshot({ path: destination, fullPage: true });
            screenshotPaths.push(relative);
          }
          await settleAndAssertNetwork(page, networkViolations);
          validateFinalLocation(page.url(), route);
          visits.push(visit);
        } catch (error) {
          const failure = { route, width, code: controlledError(error) };
          try {
            const relative = path.posix.join('failure-screenshots', `${safeSlug(route)}-${width}.png`);
            const destination = path.join(evidenceRoot, relative);
            fs.mkdirSync(path.dirname(destination), { recursive: true, mode: 0o700 });
            await page.screenshot({ path: destination, fullPage: true, timeout: 5000 });
            failure.screenshot = relative;
            failureScreenshotPaths.push(relative);
          } catch {
            failure.screenshot = null;
          }
          failures.push(failure);
        } finally {
          await page.close();
        }
      }
    }
    reducedMotion = await verifyReducedMotion(browser, user, password);
  } catch (error) {
    failures.push({ route: '/', width: 0, code: controlledError(error), screenshot: null });
  } finally {
    if (context !== null) await context.close();
    await browser.close();
  }

  const evidence = {
    schemaVersion: 1,
    applicationIdentity: STAGE_URL,
    routes: ROUTES,
    viewports: VIEWPORTS,
    routeVisits: visits,
    screenshots: screenshotPaths,
    failureScreenshots: failureScreenshotPaths,
    basicAuth,
    reducedMotion,
    failures,
    ...envelope,
  };
  const evidencePath = path.join(evidenceRoot, 'evidence.json');
  fs.writeFileSync(evidencePath, `${JSON.stringify(evidence, null, 2)}\n`, { mode: 0o600 });
  if (failures.length !== 0) {
    emit('PRIVATE_STAGING_CRAWL_FAILED', {
      visits: visits.length,
      failures: failures.length,
      evidence: path.relative(root, evidencePath),
    }, 1);
    return;
  }
  emit('PRIVATE_STAGING_CRAWL_PASSED', {
    visits: visits.length,
    screenshots: screenshotPaths.length,
    evidence: path.relative(root, evidencePath),
  });
}

async function main() {
  if (process.argv.length !== 2) {
    emit('INVALID_ARGUMENT', {}, 2);
    return;
  }
  const stageUrl = process.env.TWINS_STAGE_URL || '';
  const user = process.env.TWINS_STAGE_USER || '';
  const password = process.env.TWINS_STAGE_PASSWORD || '';
  const supplied = [stageUrl, user, password].filter(value => value !== '').length;
  if (supplied === 0) {
    emit('PRIVATE_STAGING_CRAWL_SKIPPED', { reason: 'PRIVATE_STAGING_CONFIGURATION_REQUIRED' });
    return;
  }
  if (supplied !== 3) {
    emit('PRIVATE_STAGING_CONFIGURATION_INCOMPLETE', {}, 2);
    return;
  }
  if (stageUrl !== STAGE_URL) {
    emit('PRIVATE_STAGING_IDENTITY_INVALID', {}, 2);
    return;
  }
  await runConfiguredCrawl(user, password);
}

if (path.resolve(process.argv[1] || '') === fileURLToPath(import.meta.url)) {
  main().catch(() => emit('PRIVATE_STAGING_CRAWL_INDETERMINATE', {}, 1));
}
