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

function visibleTargetAudit(page) {
  return page.locator([
    '.twins-location-service-card a',
    '.twins-brand-header--location .twins-brand-location-nav a',
    '.twins-brand-header--location .twins-brand-location-phone',
    '.twins-brand-header--location .twins-brand-cta--quote',
    '.twins-brand-header--location .twins-brand-menu-trigger',
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

for (const viewport of viewports) {
  test(`modern location layout holds at ${viewport.width}px`, async ({ page }) => {
    await page.setViewportSize(viewport);
    await page.goto(fixture);

    await expect(page.locator('.twins-brand-header--location')).toHaveCount(1);
    await expect(page.locator('.twins-brand-header--location .twins-brand-location-nav > a')).toHaveCount(3);
    await expect(page.locator('.twins-brand-header--location .twins-brand-fascia--location > .twins-brand-location-phone')).toHaveCount(1);
    await expect(page.locator('.twins-brand-header--location .twins-brand-fascia--location > .twins-brand-cta--quote')).toHaveCount(1);
    await expect(page.locator('.twins-brand-header--location .twins-brand-cta--book')).toHaveCount(0);
    await expect(page.locator('body')).not.toContainText('Book Online');
    await expect(page.locator('.twins-brand-drawer--location')).toHaveCount(1);
    await expect(page.locator('.twins-location-system .twins-brand-door-art--door-open')).toHaveCount(1);
    await expect(page.locator('.twins-location-process')).toHaveCount(1);
    await expect(page.locator('.twins-location-branch')).toHaveCount(1);
    await expect(page.locator('.twins-location-nearby')).toHaveCount(1);
    await expect(page.locator('.twins-location-faq')).toHaveCount(1);
    await expect(page.locator('.twins-brand-footer')).toHaveCount(1);
    await expect(page.locator('.twins-brand-mobile-actions > a')).toHaveCount(2);
    await expect(page.locator('.twins-location-service-card .twins-brand-door-art')).toHaveCount(3);
    await expect(page.locator('.twins-location-twin')).toHaveCount(3);
    await expect(page.locator('.twins-location-hero .twins-brand-cta--quote')).toHaveCount(1);
    await expect(page.locator('.twins-location-hero .twins-brand-cta--call')).toHaveCount(1);
    await expect(page.locator('.twins-location-final-cta > .twins-brand-cta-art')).toHaveCount(1);
    await expect(page.locator('.twins-location-final-cta')).toHaveAttribute('aria-labelledby', 'twins-brand-editorial-final-title');
    await expect(page.locator('.twins-location-final-cta .twins-brand-kicker')).toHaveText('Madison');
    await expect(page.locator('.twins-location-final-cta h2')).toHaveAttribute('id', 'twins-brand-editorial-final-title');
    await expect(page.locator('.twins-location-final-cta > p')).toHaveText('Call Twins or request a quote. We will help you choose the right next step for the door, opener, or installation.');
    await expect(page.locator('.twins-location-final-cta .twins-brand-final-actions > a').first()).toHaveClass(/twins-brand-cta--call/);
    await expect(page.locator('.twins-location-final-cta .twins-brand-final-actions > a').last()).toHaveClass(/twins-brand-cta--quote/);

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

    const mascotVisibility = await page.locator('.twins-location-twin').evaluateAll(nodes => nodes.map(node => {
      const style = getComputedStyle(node);
      const rect = node.getBoundingClientRect();
      return {
        className: node.className,
        visible: style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0,
      };
    }));
    const expectedVisibility = {
      'twins-location-twin--hero': viewport.width >= 481,
      'twins-location-twin--guidance': true,
      'twins-location-twin--final-right': true,
    };
    for (const [placement, expected] of Object.entries(expectedVisibility)) {
      const mascot = mascotVisibility.find(entry => entry.className.includes(placement));
      expect(mascot, `${viewport.width}px ${placement} fixture mascot is present`).toBeTruthy();
      expect(mascot.visible, `${viewport.width}px ${placement} visibility follows the responsive contract`).toBe(expected);
    }

    const layout = await page.evaluate(() => {
      const overlaps = [];
      const clipped = [];
      const textSelectors = 'h1, h2, h3, p, a, button, [role="button"]';
      const viewportTolerance = 1;
      const layoutSelectors = [
        '.twins-brand-header--location',
        '.twins-location-hero',
        '.twins-location-hero-copy',
        '.twins-location-hero-media',
        '.twins-location-hero-image',
        '.twins-location-proof',
        '.twins-location-proof > div',
        '.twins-location-system',
        '.twins-location-system-visual',
        '.twins-location-system > div:last-child',
        '.twins-location-services',
        '.twins-location-service-card',
        '.twins-location-guidance',
        '.twins-location-guidance-copy',
        '.twins-location-warning-card',
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

      for (const selector of layoutSelectors) {
        for (const node of document.querySelectorAll(selector)) {
          const style = getComputedStyle(node);
          const rect = node.getBoundingClientRect();
          if (style.display === 'none' || style.visibility === 'hidden') continue;
          if (rect.width <= 0 || rect.height <= 0 || rect.left < -viewportTolerance || rect.right > innerWidth + viewportTolerance) {
            clipped.push({ selector, left: rect.left, right: rect.right, width: rect.width, height: rect.height });
          }
        }
      }

      for (const twin of document.querySelectorAll('.twins-location-twin')) {
        if (!visible(twin)) continue;
        const composition = twin.closest('.twins-location-hero-media, .twins-location-guidance, .twins-location-final-cta');
        if (!composition) {
          overlaps.push({ twin: twin.className, reason: 'missing approved composition container' });
          continue;
        }
        const twinRect = twin.getBoundingClientRect();
        for (const node of composition.querySelectorAll(textSelectors)) {
          if (!visible(node)) continue;
          if (textRects(node).some(rect => intersects(twinRect, rect))) {
            overlaps.push({ twin: twin.className, content: node.textContent.trim().slice(0, 80) });
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

    expect(layout.overlaps, `${viewport.width}px mascots may compose with media or empty space, never readable content or controls`)
      .toEqual([]);
    expect(layout.clipped, `${viewport.width}px significant sections and content stay within the horizontal viewport; only explicitly decorative mascots may clip`)
      .toEqual([]);
    expect(layout.rootScrollWidth, `${viewport.width}px page scrolling root has no horizontal overflow`)
      .toBeLessThanOrEqual(layout.rootClientWidth);
    expect(layout.documentScrollWidth, `${viewport.width}px document has no horizontal overflow`)
      .toBeLessThanOrEqual(layout.documentClientWidth);

    const targets = await visibleTargetAudit(page);
    for (const target of targets.filter(target => target.visible)) {
      expect(target.height, `${viewport.width}px visible target ${target.label} is at least 44px tall`).toBeGreaterThanOrEqual(44);
      expect(target.width, `${viewport.width}px visible target ${target.label} is usable at its rendered width`).toBeGreaterThanOrEqual(44);
    }

    const focusableHeaderActions = page.locator([
      '.twins-brand-header--location .twins-brand-location-nav a:visible',
      '.twins-brand-header--location .twins-brand-location-phone:visible',
      '.twins-brand-header--location .twins-brand-cta--quote:visible',
      '.twins-brand-header--location .twins-brand-menu-trigger:visible',
    ].join(', '));
    const actionCount = await focusableHeaderActions.count();
    for (let index = 0; index < actionCount; index += 1) {
      const action = focusableHeaderActions.nth(index);
      await action.focus();
      const focus = await action.evaluate(node => {
        const style = getComputedStyle(node);
        return {
          active: document.activeElement === node,
          outline: style.outlineStyle,
          outlineWidth: Number.parseFloat(style.outlineWidth),
          color: style.color,
        };
      });
      expect(focus.active, `${viewport.width}px compact header action ${index + 1} accepts keyboard focus`).toBeTruthy();
      expect(
        focus.outline !== 'none' || focus.outlineWidth > 0 || focus.color === 'rgb(255, 200, 61)',
        `${viewport.width}px focused compact header action ${index + 1} has visible focus styling`,
      ).toBeTruthy();
    }

    const menu = page.locator('.twins-brand-header--location .twins-brand-menu-trigger:visible');
    if (await menu.count()) {
      await menu.press('Enter');
      await expect(page.locator('.twins-brand-drawer--location')).not.toHaveAttribute('hidden', '');
      await expect(page.locator('.twins-brand-drawer-close')).toBeFocused();
      await page.locator('.twins-brand-drawer-close').press('Enter');
      await expect(menu).toBeFocused();
    }
  });
}

test('reduced motion keeps mascots static', async ({ page }) => {
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.goto(fixture);

  const motion = await page.locator('.twins-location-twin, .twins-brand-door-art--door-open .twins-da-curtain').evaluateAll(nodes => nodes.map(node => {
    const style = getComputedStyle(node);
    return { animationName: style.animationName, transform: style.transform };
  }));

  for (const mascot of motion) {
    expect(mascot.animationName).toBe('none');
    expect(mascot.transform).toBe('none');
  }
});
