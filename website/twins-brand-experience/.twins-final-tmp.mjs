import { createServer } from 'node:http';
import { createReadStream, existsSync, statSync } from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';
const ROOT=process.argv[2], PAGE=process.argv[3];
const mime=new Map([['.html','text/html; charset=utf-8'],['.css','text/css; charset=utf-8'],['.js','text/javascript; charset=utf-8'],['.png','image/png'],['.jpg','image/jpeg'],['.jpeg','image/jpeg'],['.webp','image/webp'],['.woff2','font/woff2'],['.json','application/json']]);
const server=createServer((req,res)=>{const p=path.join(ROOT,decodeURIComponent(req.url.split('?')[0]));if(!p.startsWith(ROOT)||!existsSync(p)||!statSync(p).isFile()){res.writeHead(404);res.end();return;}res.writeHead(200,{'Content-Type':mime.get(path.extname(p))||'application/octet-stream'});createReadStream(p).pipe(res);});
await new Promise(r=>server.listen(41995,'127.0.0.1',r));
const browser=await chromium.launch();
const R={};

// 1. short-viewport scroll safety net
{
  const ctx=await browser.newContext({viewport:{width:1440,height:700}});
  const page=await ctx.newPage();
  await page.goto(`http://127.0.0.1:41995/${PAGE}`,{waitUntil:'load'});
  await page.locator('.twins-brand-nav-group--areas .twins-brand-nav-trigger').click();
  await page.waitForTimeout(200);
  R.shortViewport=await page.evaluate(()=>{const p=document.querySelector('.twins-brand-nav-panel--areas');const b=p.getBoundingClientRect();return {viewportH:innerHeight,panelTop:Math.round(b.top),panelH:Math.round(b.height),panelBottom:Math.round(b.bottom),scrollable:p.scrollHeight>p.clientHeight,scrollHeight:p.scrollHeight,clientHeight:p.clientHeight};});
  await ctx.close();
}
// 2. keyboard focus indicator on both new element classes
{
  const ctx=await browser.newContext({viewport:{width:1440,height:900}});
  const page=await ctx.newPage();
  await page.goto(`http://127.0.0.1:41995/${PAGE}`,{waitUntil:'load'});
  await page.locator('.twins-brand-nav-group--areas .twins-brand-nav-trigger').focus();
  await page.keyboard.press('ArrowDown');
  await page.waitForTimeout(150);
  const snap=async()=>page.evaluate(()=>{const el=document.activeElement;const s=getComputedStyle(el);return {text:el.textContent.trim().slice(0,26),cls:el.className||'(town link)',focusVisible:el.matches(':focus-visible'),color:s.color,background:s.backgroundColor,outline:`${s.outlineWidth} ${s.outlineColor}`,boxShadow:s.boxShadow};});
  R.focusFirst=await snap();
  // End key -> last link in the panel
  await page.keyboard.press('End'); await page.waitForTimeout(80);
  R.focusEnd=await snap();
  await page.keyboard.press('Home'); await page.waitForTimeout(80);
  R.focusHome=await snap();
  // tab to the first hub pill
  for(let i=0;i<40;i++){const hit=await page.evaluate(()=>document.activeElement.classList.contains('twins-brand-area-hub'));if(hit)break;await page.keyboard.press('Tab');}
  R.focusHubPill=await snap();
  // Escape returns focus to the trigger and collapses
  await page.keyboard.press('Escape'); await page.waitForTimeout(120);
  R.escape=await page.evaluate(()=>({active:document.activeElement.textContent.trim().slice(0,20),expanded:document.querySelector('.twins-brand-nav-group--areas .twins-brand-nav-trigger').getAttribute('aria-expanded')}));
  await ctx.close();
}
// 3. mobile drawer, and what overflows the page
for (const [w,h] of [[390,844],[360,780],[320,700]]) {
  const ctx=await browser.newContext({viewport:{width:w,height:h}});
  const page=await ctx.newPage();
  await page.goto(`http://127.0.0.1:41995/${PAGE}`,{waitUntil:'load'});
  const before=await page.evaluate(()=>{
    const cw=document.documentElement.clientWidth;
    const bad=[...document.querySelectorAll('body *')].filter(e=>{const b=e.getBoundingClientRect();return b.width>0&&(b.right>cw+1||b.left<-1);}).slice(0,6).map(e=>({cls:e.className&&String(e.className).slice(0,48),tag:e.tagName,right:Math.round(e.getBoundingClientRect().right),left:Math.round(e.getBoundingClientRect().left)}));
    return {scrollWidth:document.documentElement.scrollWidth,clientWidth:cw,offenders:bad};
  });
  await page.locator('.twins-brand-menu-trigger').click();
  await page.waitForTimeout(150);
  const summaries=page.locator('.twins-brand-drawer-metro > summary');
  const n=await summaries.count();
  for(let i=0;i<n;i++) await summaries.nth(i).click();
  await page.waitForTimeout(150);
  const after=await page.evaluate(()=>{
    const cw=document.documentElement.clientWidth;
    const panel=document.querySelector('.twins-brand-drawer-panel');
    const rows=[...document.querySelectorAll('.twins-brand-drawer-metro-body a')];
    const clipped=rows.filter(a=>a.getBoundingClientRect().right>cw+1||a.scrollWidth>a.clientWidth+1).map(a=>a.textContent.trim());
    return {scrollWidth:document.documentElement.scrollWidth,clientWidth:cw,panelW:Math.round(panel.getBoundingClientRect().width),panelScrollable:panel.scrollHeight>panel.clientHeight,rows:rows.length,clipped,minRowH:Math.min(...rows.map(a=>Math.round(a.getBoundingClientRect().height))),summaryH:Math.round(document.querySelector('.twins-brand-drawer-metro > summary').getBoundingClientRect().height)};
  });
  R['mobile'+w]={before,after};
  await ctx.close();
}
await browser.close(); server.close();
console.log(JSON.stringify(R,null,1));
