const { test, expect } = require('@playwright/test');

const fixture = '/tests/browser/fixtures/location-modern.html';
const viewports = [
  { width: 1440, height: 1000 },
  { width: 1024, height: 900 },
  { width: 768, height: 900 },
  { width: 390, height: 844 },
  { width: 360, height: 844 },
  { width: 320, height: 844 },
];

function luminance(color) {
  const channels = color.match(/\d+(?:\.\d+)?/g)?.slice(0, 3).map(Number);
  if (!channels || channels.length !== 3) throw new Error(`Unsupported color: ${color}`);
  const linear = value => {
    const channel = value / 255;
    return channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
  };
  return 0.2126 * linear(channels[0]) + 0.7152 * linear(channels[1]) + 0.0722 * linear(channels[2]);
}

function contrastRatio(first, second) {
  const [lighter, darker] = [luminance(first), luminance(second)].sort((a, b) => b - a);
  return (lighter + 0.05) / (darker + 0.05);
}

function visibleTargetAudit(page) {
  return page.locator([
    '.twins-brand-market-menu > summary',
    '.twins-brand-market-menu-panel a',
    '.twins-brand-utility .twins-brand-phone',
    '.twins-brand-primary-nav button',
    '.twins-brand-primary-nav a',
    '.twins-brand-fascia > .twins-brand-cta',
    '.twins-brand-menu-trigger',
    '.twins-brand-drawer-close',
    '.twins-brand-drawer a',
    '.twins-brand-drawer .twins-brand-cta',
    '[data-booking-close]',
    '[data-booking-finalize]',
    '.twins-location-service-link',
    '.twins-location-hero .twins-brand-cta',
    '.twins-location-final-cta .twins-brand-cta',
    '.twins-brand-mobile-actions a',
  ].join(', ')).evaluateAll(nodes => nodes.map(node => {
    const style = getComputedStyle(node);
    const rect = node.getBoundingClientRect();
    return {
      label: node.textContent.trim() || node.getAttribute('aria-label'),
      visible: style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0,
      width: rect.width,
      height: rect.height,
    };
  }));
}

async function expectVisibleTargetsMeetMinimum(page, viewportWidth, phase) {
  const targets = await visibleTargetAudit(page);
  for (const target of targets.filter(target => target.visible)) {
    expect(target.height, `${viewportWidth}px ${phase} target ${target.label} is at least 44px tall`).toBeGreaterThanOrEqual(44);
    expect(target.width, `${viewportWidth}px ${phase} target ${target.label} is at least 44px wide`).toBeGreaterThanOrEqual(44);
  }
}

for (const viewport of viewports) {
  test(`modern location layout holds at ${viewport.width}px`, async ({ page }) => {
    await page.setViewportSize(viewport);
    await page.goto(fixture);

    await expect(page.locator('.twins-brand-header')).toHaveCount(1);
    await expect(page.locator('.twins-brand-header--location')).toHaveCount(0);
    await expect(page.locator('.twins-brand-utility')).toHaveCount(1);
    await expect(page.locator('.twins-brand-fascia')).toHaveCount(1);
    await expect(page.locator('.twins-brand-primary-nav')).toHaveCount(1);
    await expect(page.locator('.twins-brand-nav-group')).toHaveCount(5);
    await expect(page.locator('.twins-brand-drawer')).toHaveCount(1);
    await expect(page.locator('[data-twins-booking-open]')).toHaveCount(2);
    await expect(page.locator('[data-twins-booking-dialog]')).toHaveCount(1);
    await expect(page.locator('.twins-location-hero')).toHaveCount(1);
    await expect(page.locator('.twins-location-trust')).toHaveCount(1);
    await expect(page.locator('.twins-location-service-card')).toHaveCount(3);
    await expect(page.locator('.twins-location-local-proof')).toHaveCount(1);
    await expect(page.locator('.twins-location-final-cta')).toHaveCount(1);
    await expect(page.locator('#twins-overhaul-main > [data-location-reveal]')).toHaveCount(5);
    await expect(page.locator('.twins-location-hero-stage, .twins-location-orbit, .twins-location-system, .twins-location-guidance, .twins-location-process, .twins-location-branch, .twins-location-nearby, .twins-location-faq')).toHaveCount(0);
    await expect(page.locator('.twins-location-hero .twins-brand-cta--quote')).toHaveText('Get a Free Quote');
    await expect(page.locator('.twins-location-twin')).toHaveCount(3);
    await expect(page.locator('.twins-location-twin--services')).toHaveCount(1);
    await expect(page.locator('.twins-location-twin--final-left')).toHaveCount(1);
    await expect(page.locator('.twins-location-twin--final-right')).toHaveCount(1);
    await expect(page.locator('.twins-location-twin--hero, .twins-location-twin--guidance')).toHaveCount(0);
    expect(await page.locator('.twins-location-twin').evaluateAll(nodes => nodes.map(node => ({
      alt: node.getAttribute('alt'),
      ariaHidden: node.getAttribute('aria-hidden'),
    })))).toEqual([
      { alt: '', ariaHidden: 'true' },
      { alt: '', ariaHidden: 'true' },
      { alt: '', ariaHidden: 'true' },
    ]);

    await expect(page.locator('.twins-location-title-accent')).toContainText('in Rockford');
    await expect(page.locator('.twins-location-trust[role="list"]')).toHaveCount(1);
    await expect(page.locator('.twins-location-trust > [role="listitem"]')).toHaveCount(3);
    await expect(page.locator('.twins-location-trust [role="listitem"]').first()).toContainText('699 customer reviews');
    await expect(page.locator('.twins-location-service-card h3')).toHaveText([
      'Garage Door Repair',
      'Garage door opener service',
      'Garage door installation',
    ]);
    await expect(page.locator('.twins-location-local-proof-media picture')).toHaveCount(1);
    await expect(page.locator('.twins-location-local-proof-image')).toHaveAttribute(
      'alt',
      'Before and after view of a real Twins garage door installation',
    );
    await expect(page.locator('.twins-location-proof-list li strong')).toHaveText([
      'Complete system inspection',
      'Plain-language options',
      'Respect for your home',
    ]);
    await expect(page.locator('.twins-location-address')).toHaveText('5758 Elaine Dr Ste 110, Rockford, IL 61108');

    await expect(page.locator('.twins-location-final-cta .twins-brand-final-actions > a').first()).toHaveClass(/twins-brand-cta--quote/);
    await expect(page.locator('.twins-location-final-cta .twins-brand-final-actions > a').last()).toHaveClass(/twins-brand-cta--call/);
    await expect(page.locator('.twins-location-final-cta')).toHaveAttribute('aria-labelledby', 'twins-brand-editorial-final-title');
    await expect(page.locator('.twins-location-final-cta .twins-brand-kicker')).toHaveText('Rockford');
    await expect(page.locator('.twins-location-final-cta h2')).toHaveAttribute('id', 'twins-brand-editorial-final-title');
    await expect(page.locator('.twins-location-final-cta > p')).toHaveText('Call Twins or request a quote. We will help you choose the right next step for the door, opener, or installation.');

    await expect(page.locator('.twins-brand-footer')).toHaveCount(1);
    await expect(page.locator('.twins-brand-footer-group h2')).toHaveText(['Services', 'Garage Doors', 'Service Areas', 'Resources', 'About']);
    expect(await page.locator('.twins-brand-footer-group').evaluateAll(groups => groups.map(group => group.querySelectorAll('a').length)))
      .toEqual([5, 3, 3, 5, 4]);
    await expect(page.locator('.twins-brand-footer-group').first()).not.toContainText('Spring Repair');
    await expect(page.locator('.twins-brand-footer-group').nth(3)).not.toContainText('Wisconsin Garage Door Cost Guide');
    await expect(page.locator('.twins-brand-footer-nap')).toContainText('5758 Elaine Dr Ste 110, Rockford, IL 61108');
    await expect(page.locator('.twins-brand-mobile-actions > a')).toHaveCount(2);
    await expect(page.locator('.twins-brand-mobile-actions > a')).toHaveText(['Call Now', 'Get a Free Quote']);

    for (const selector of [
      '.twins-location-final-cta .twins-brand-door-art--door',
      '.twins-brand-footer-door.twins-brand-door-art--door',
    ]) {
      const art = page.locator(selector);
      await expect(art).toHaveAttribute('viewBox', '0 0 220 190');
      await expect(art.locator('.twins-da-gold')).toHaveCount(1);
      await expect(art.locator('.twins-da-window-frame')).toHaveCount(4);
      await expect(art.locator('.twins-da-glass')).toHaveCount(4);
      await expect(art.locator('.twins-da-panel')).toHaveCount(12);
    }

    const hierarchy = await page.locator('.twins-location-hero').evaluate(hero => {
      const quote = hero.querySelector('.twins-brand-cta--quote');
      const call = hero.querySelector('.twins-brand-cta--call');
      return {
        quoteBackground: getComputedStyle(quote).backgroundColor,
        callBackground: getComputedStyle(call).backgroundColor,
        quoteBeforeCall: Boolean(quote.compareDocumentPosition(call) & Node.DOCUMENT_POSITION_FOLLOWING),
      };
    });
    expect(hierarchy.quoteBeforeCall, `${viewport.width}px quote precedes the secondary phone action`).toBeTruthy();
    expect(hierarchy.quoteBackground, `${viewport.width}px quote and phone actions remain visually distinct`)
      .not.toBe(hierarchy.callBackground);
    expect(luminance(hierarchy.quoteBackground), `${viewport.width}px quote action remains the brighter primary target`)
      .toBeGreaterThan(luminance(hierarchy.callBackground));

    const contrast = await page.evaluate(() => {
      const hero = document.querySelector('.twins-location-hero');
      const kicker = hero.querySelector('.twins-brand-kicker');
      const paragraph = hero.querySelector('.twins-location-hero-copy > p');
      const services = document.querySelector('.twins-location-services');
      const serviceLink = services.querySelector('.twins-location-service-link');
      return {
        heroBackground: getComputedStyle(hero).backgroundColor,
        kicker: getComputedStyle(kicker).color,
        paragraph: getComputedStyle(paragraph).color,
        pageBackground: getComputedStyle(document.querySelector('.twins-location-page')).backgroundColor,
        serviceLink: getComputedStyle(serviceLink).color,
      };
    });
    expect(contrastRatio(contrast.kicker, contrast.heroBackground), `${viewport.width}px hero kicker remains readable`).toBeGreaterThanOrEqual(4.5);
    expect(contrastRatio(contrast.paragraph, contrast.heroBackground), `${viewport.width}px hero copy remains readable`).toBeGreaterThanOrEqual(4.5);
    expect(contrastRatio(contrast.serviceLink, contrast.pageBackground), `${viewport.width}px service links remain readable`).toBeGreaterThanOrEqual(4.5);

    const geometry = await page.evaluate(() => {
      const rect = selector => document.querySelector(selector).getBoundingClientRect();
      const hero = rect('.twins-location-hero');
      const services = rect('.twins-location-services');
      const local = rect('.twins-location-local-proof');
      const heroMedia = rect('.twins-location-hero-media');
      const heroCopy = rect('.twins-location-hero-copy');
      const servicesHeading = rect('.twins-location-section-heading');
      const localMedia = rect('.twins-location-local-proof-media');
      const localCopy = rect('.twins-location-local-proof-copy');
      const heroGridContentWidth = heroCopy.width + heroMedia.width;
      return { hero, services, local, heroMedia, heroCopy, heroGridContentWidth, servicesHeading, localMedia, localCopy };
    });
    expect(Math.abs(geometry.heroCopy.left - geometry.servicesHeading.left)).toBeLessThanOrEqual(1);
    expect(Math.abs(geometry.servicesHeading.left - geometry.localMedia.left)).toBeLessThanOrEqual(1);
    expect(geometry.hero.right).toBeLessThanOrEqual(viewport.width + 1);
    expect(geometry.services.right).toBeLessThanOrEqual(viewport.width + 1);
    expect(geometry.local.right).toBeLessThanOrEqual(viewport.width + 1);
    expect(geometry.heroMedia.height).toBeLessThanOrEqual(viewport.width <= 768 ? 311 : 461);
    expect(geometry.localMedia.height).toBeLessThanOrEqual(viewport.width <= 768 ? 311 : 441);
    if (viewport.width > 768) {
      const heroMediaShare = geometry.heroMedia.width / geometry.heroGridContentWidth;
      expect(heroMediaShare).toBeGreaterThanOrEqual(.40);
      expect(heroMediaShare).toBeLessThanOrEqual(.44);
      expect(geometry.localMedia.height).toBeLessThanOrEqual(geometry.localCopy.height + 1);
    } else {
      expect(geometry.heroCopy.bottom).toBeLessThanOrEqual(geometry.heroMedia.top + 1);
    }

    const layout = await page.evaluate(() => {
      const overlaps = [];
      const clipped = [];
      const textSelectors = 'h1, h2, h3, p, a, button, [role="button"], li, figcaption, strong, span:not([aria-hidden="true"])';
      const layoutSelectors = [
        '.twins-brand-header',
        '.twins-brand-utility',
        '.twins-brand-fascia',
        '.twins-location-hero',
        '.twins-location-hero-copy',
        '.twins-location-hero-media',
        '.twins-location-hero-image',
        '.twins-location-trust',
        '.twins-location-trust > div',
        '.twins-location-services',
        '.twins-location-section-heading',
        '.twins-location-service-grid',
        '.twins-location-service-card',
        '.twins-location-service-link',
        '.twins-location-local-proof',
        '.twins-location-local-proof-media',
        '.twins-location-local-proof-image',
        '.twins-location-local-proof-copy',
        '.twins-location-proof-list',
        '.twins-location-final-cta',
        '.twins-location-final-cta > :not(.twins-location-twin)',
        '.twins-brand-final-actions',
        '.twins-brand-footer',
        '.twins-brand-footer-intro',
        '.twins-brand-footer-nav',
        '.twins-brand-mobile-actions',
      ];
      const intersects = (one, two) => one.left < two.right && one.right > two.left && one.top < two.bottom && one.bottom > two.top;
      const visible = node => {
        const style = getComputedStyle(node);
        const rect = node.getBoundingClientRect();
        return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
      };
      const textRects = node => {
        if (node.matches('a, button, [role="button"]')) return [node.getBoundingClientRect()];
        const range = document.createRange();
        range.selectNodeContents(node);
        return Array.from(range.getClientRects()).filter(rect => rect.width > 0 && rect.height > 0);
      };
      const bounds = rect => ({
        left: rect.left,
        top: rect.top,
        right: rect.right,
        bottom: rect.bottom,
        width: rect.width,
        height: rect.height,
      });

      for (const selector of layoutSelectors) {
        for (const node of document.querySelectorAll(selector)) {
          const style = getComputedStyle(node);
          const rect = node.getBoundingClientRect();
          if (style.display === 'none' || style.visibility === 'hidden') continue;
          if (rect.width <= 0 || rect.height <= 0 || rect.left < -1 || rect.right > innerWidth + 1) {
            clipped.push({ selector, left: rect.left, right: rect.right, width: rect.width, height: rect.height });
          }
        }
      }

      for (const twin of document.querySelectorAll('.twins-location-twin')) {
        if (!visible(twin)) continue;
        const composition = twin.closest('.twins-location-services, .twins-location-final-cta');
        if (!composition) {
          overlaps.push({ twin: twin.className, reason: 'missing approved composition container' });
          continue;
        }
        const twinRect = twin.getBoundingClientRect();
        for (const node of composition.querySelectorAll(textSelectors)) {
          if (!visible(node)) continue;
          const collision = textRects(node).find(rect => intersects(twinRect, rect));
          if (collision) {
            overlaps.push({
              twin: twin.className,
              twinRect: bounds(twinRect),
              content: node.textContent.trim().slice(0, 80),
              contentRect: bounds(collision),
              compositionRect: bounds(composition.getBoundingClientRect()),
            });
          }
        }
      }

      const root = document.scrollingElement;
      return {
        overlaps,
        clipped,
        rootScrollWidth: root.scrollWidth,
        rootClientWidth: root.clientWidth,
        documentScrollWidth: document.documentElement.scrollWidth,
        documentClientWidth: document.documentElement.clientWidth,
      };
    });

    expect(layout.overlaps, `${viewport.width}px characters never collide with readable content or controls`).toEqual([]);
    expect(layout.clipped, `${viewport.width}px meaningful layout stays inside the viewport`).toEqual([]);
    expect(layout.rootScrollWidth, `${viewport.width}px page scrolling root has no horizontal overflow`)
      .toBeLessThanOrEqual(layout.rootClientWidth);
    expect(layout.documentScrollWidth, `${viewport.width}px document has no horizontal overflow`)
      .toBeLessThanOrEqual(layout.documentClientWidth);

    if (viewport.width <= 390) {
      await expect(page.locator('body')).toHaveAttribute('data-twins-location-hero-active', 'true');
      const actionsClearHero = await page.evaluate(() => {
        const actions = document.querySelector('.twins-brand-mobile-actions').getBoundingClientRect();
        const intersects = (one, two) => one.left < two.right && one.right > two.left && one.top < two.bottom && one.bottom > two.top;
        return [...document.querySelectorAll('.twins-location-hero-copy, .twins-location-hero-media, .twins-location-actions')]
          .filter(node => getComputedStyle(node).display !== 'none')
          .filter(node => intersects(actions, node.getBoundingClientRect()))
          .map(node => node.className);
      });
      expect(actionsClearHero, `${viewport.width}px mobile quick actions do not cover hero content`).toEqual([]);
    }

    await expectVisibleTargetsMeetMinimum(page, viewport.width, 'visible');

    const fasciaBooking = page.locator('.twins-brand-fascia > [data-twins-booking-open]:visible');
    if (await fasciaBooking.count()) {
      await fasciaBooking.click();
      const booking = page.locator('[data-twins-booking-dialog]');
      await expect(booking).toBeVisible();
      await expect(page.locator('[data-booking-close]')).toBeFocused();
      await expectVisibleTargetsMeetMinimum(page, viewport.width, 'open-booking');
      await page.locator('[data-booking-finalize]').click();
      await expect(page.locator('[data-booking-status]')).toBeVisible();
      await page.locator('[data-booking-close]').click();
      await expect(booking).toBeHidden();
      await expect(fasciaBooking).toBeFocused();
    }

    const menu = page.locator('.twins-brand-menu-trigger:visible');
    if (await menu.count()) {
      await menu.click();
      await expect(page.locator('.twins-brand-drawer')).not.toHaveAttribute('hidden', '');
      await expect(page.locator('.twins-brand-drawer-close')).toBeFocused();
      await expectVisibleTargetsMeetMinimum(page, viewport.width, 'opened-drawer');
      await page.locator('.twins-brand-drawer [data-twins-booking-open]').click();
      const booking = page.locator('[data-twins-booking-dialog]');
      await expect(page.locator('.twins-brand-drawer')).toBeHidden();
      await expect(booking).toBeVisible();
      await expect(page.locator('[data-booking-close]')).toBeFocused();
      await expectVisibleTargetsMeetMinimum(page, viewport.width, 'drawer-opened-booking');
      await page.keyboard.press('Escape');
      await expect(booking).toBeHidden();
      await expect(menu).toBeFocused();
    }
  });
}

test('reduced motion keeps every reveal visible, static, and readable', async ({ page }) => {
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.goto(fixture);

  const motion = await page.locator('.twins-location-twin').evaluateAll(nodes => nodes.map(node => {
    const style = getComputedStyle(node);
    return { animationName: style.animationName, transform: style.transform };
  }));
  expect(motion).toHaveLength(3);
  for (const mascot of motion) {
    expect(mascot.animationName).toBe('none');
    expect(mascot.transform).toBe('none');
  }

  const reveals = await page.locator('[data-location-reveal]').evaluateAll(nodes => nodes.map(node => {
    const style = getComputedStyle(node);
    const rect = node.getBoundingClientRect();
    return {
      opacity: style.opacity,
      transform: style.transform,
      display: style.display,
      visibility: style.visibility,
      width: rect.width,
      height: rect.height,
      text: node.innerText.trim(),
      visibleState: node.dataset.locationVisible,
    };
  }));

  expect(reveals).toHaveLength(5);
  for (const reveal of reveals) {
    expect(reveal.opacity).toBe('1');
    expect(reveal.transform).toBe('none');
    expect(reveal.display).not.toBe('none');
    expect(reveal.visibility).toBe('visible');
    expect(reveal.width).toBeGreaterThan(0);
    expect(reveal.height).toBeGreaterThan(0);
    expect(reveal.text.length).toBeGreaterThan(0);
    expect(reveal.visibleState).toBe('true');
  }
});
