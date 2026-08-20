import { createServer } from 'node:http';
import { createReadStream, existsSync, statSync } from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const ROOT = process.argv[2];
const PAGE = process.argv[3];
const SEL = process.argv[4] || '.twins-brand-nav-panel--areas';
const TRIG = process.argv[5] || '.twins-brand-nav-group--areas .twins-brand-nav-trigger';

const mime = new Map([['.html', 'text/html; charset=utf-8'], ['.css', 'text/css; charset=utf-8'],
  ['.js', 'text/javascript; charset=utf-8'], ['.png', 'image/png'], ['.jpg', 'image/jpeg'],
  ['.jpeg', 'image/jpeg'], ['.webp', 'image/webp'], ['.woff2', 'font/woff2'], ['.json', 'application/json']]);
const server = createServer((req, res) => {
  const p = path.join(ROOT, decodeURIComponent(req.url.split('?')[0]));
  if (!p.startsWith(ROOT) || !existsSync(p) || !statSync(p).isFile()) { res.writeHead(404); res.end(); return; }
  res.writeHead(200, { 'Content-Type': mime.get(path.extname(p)) || 'application/octet-stream' });
  createReadStream(p).pipe(res);
});
await new Promise(r => server.listen(41992, '127.0.0.1', r));
const browser = await chromium.launch();
const rows = [];
for (const w of [1201, 1280, 1366, 1440, 1536, 1600, 1920, 2560]) {
  const ctx = await browser.newContext({ viewport: { width: w, height: 900 } });
  const page = await ctx.newPage();
  await page.goto(`http://127.0.0.1:41992/${PAGE}`, { waitUntil: 'load' });
  const t = page.locator(TRIG);
  if (!(await t.count())) { rows.push({ w, note: 'no trigger' }); await ctx.close(); continue; }
  await t.click();
  await page.waitForTimeout(220);
  rows.push(await page.evaluate(([sel, w]) => {
    const p = document.querySelector(sel);
    const b = p.getBoundingClientRect();
    const firstLink = p.querySelector('a');
    const fb = firstLink ? firstLink.getBoundingClientRect() : null;
    return {
      w,
      panelLeft: Math.round(b.left), panelRight: Math.round(b.right), panelWidth: Math.round(b.width),
      clippedLeftPx: Math.max(0, Math.round(-b.left)),
      clippedRightPx: Math.max(0, Math.round(b.right - document.documentElement.clientWidth)),
      firstLinkLeft: fb ? Math.round(fb.left) : null,
      docScrollWidth: document.documentElement.scrollWidth,
      docClientWidth: document.documentElement.clientWidth,
      panelScrollable: p.scrollHeight > p.clientHeight,
      panelHeight: Math.round(b.height),
    };
  }, [SEL, w]));
  await ctx.close();
}
// keyboard focus indicator on the first hub pill
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await ctx.newPage();
await page.goto(`http://127.0.0.1:41992/${PAGE}`, { waitUntil: 'load' });
const t = page.locator(TRIG);
let focusInfo = null;
if (await t.count()) {
  await t.focus();
  await page.keyboard.press('ArrowDown');   // opens and focuses first link
  await page.waitForTimeout(150);
  // walk to the first hub pill
  focusInfo = await page.evaluate(() => {
    const panel = document.querySelector('.twins-brand-nav-panel--areas');
    const pill = panel.querySelector('.twins-brand-area-hub, .twins-brand-area-jump');
    const town = panel.querySelector('.twins-brand-area-towns a');
    const snap = el => { const s = getComputedStyle(el); return { color: s.color, background: s.backgroundColor, outlineColor: s.outlineColor, outlineWidth: s.outlineWidth, borderColor: s.borderTopColor, boxShadow: s.boxShadow }; };
    const out = {};
    out.pillResting = snap(pill);
    out.townResting = snap(town);
    return out;
  });
  // real keyboard focus: tab until the pill is the active element
  await page.evaluate(() => { document.querySelector('.twins-brand-area-towns a').focus(); });
  const kb = async (sel) => {
    await page.evaluate((s) => { window.__t = document.querySelector(s); }, sel);
    for (let i = 0; i < 60; i++) {
      const hit = await page.evaluate(() => document.activeElement === window.__t);
      if (hit) break;
      await page.keyboard.press('Tab');
    }
    return page.evaluate(() => {
      const el = document.activeElement;
      const s = getComputedStyle(el);
      return { tag: el.className, focusVisible: el.matches(':focus-visible'), color: s.color, background: s.backgroundColor, outlineColor: s.outlineColor, outlineWidth: s.outlineWidth, borderColor: s.borderTopColor, boxShadow: s.boxShadow };
    });
  };
  focusInfo.pillKeyboard = await kb('.twins-brand-nav-panel--areas .twins-brand-area-hub');
  await page.evaluate(() => { document.querySelector('.twins-brand-area-towns a').focus(); });
  focusInfo.townKeyboard = await kb('.twins-brand-nav-panel--areas .twins-brand-area-towns a');
}
await ctx.close();
await browser.close();
server.close();
console.log(JSON.stringify({ geometry: rows, focus: focusInfo }, null, 1));
