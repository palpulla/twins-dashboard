import { createServer } from 'node:http';
import { createReadStream, existsSync, statSync } from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';
const ROOT=process.argv[2], PAGE=process.argv[3], OUT=process.argv[4];
const mime=new Map([['.html','text/html; charset=utf-8'],['.css','text/css; charset=utf-8'],['.js','text/javascript; charset=utf-8'],['.png','image/png'],['.jpg','image/jpeg'],['.jpeg','image/jpeg'],['.webp','image/webp'],['.woff2','font/woff2'],['.json','application/json']]);
const server=createServer((req,res)=>{const p=path.join(ROOT,decodeURIComponent(req.url.split('?')[0]));if(!p.startsWith(ROOT)||!existsSync(p)||!statSync(p).isFile()){res.writeHead(404);res.end();return;}res.writeHead(200,{'Content-Type':mime.get(path.extname(p))||'application/octet-stream'});createReadStream(p).pipe(res);});
await new Promise(r=>server.listen(41998,'127.0.0.1',r));
const browser=await chromium.launch();
for (const [w,h,tag] of [[1366,820,'1366'],[1440,900,'1440']]) {
  const ctx=await browser.newContext({viewport:{width:w,height:h},deviceScaleFactor:1});
  const page=await ctx.newPage();
  await page.goto(`http://127.0.0.1:41998/${PAGE}`,{waitUntil:'load'});
  await page.locator('.twins-brand-nav-group--areas .twins-brand-nav-trigger').click();
  await page.waitForTimeout(400);
  await page.screenshot({path:`${OUT}/panel-${tag}.png`, clip:{x:0,y:0,width:w,height:Math.min(h,720)}});
  await ctx.close();
}
// mobile drawer
{
  const ctx=await browser.newContext({viewport:{width:390,height:844}});
  const page=await ctx.newPage();
  await page.goto(`http://127.0.0.1:41998/${PAGE}`,{waitUntil:'load'});
  await page.locator('.twins-brand-menu-trigger').click();
  await page.waitForTimeout(250);
  const s=page.locator('.twins-brand-drawer-metro > summary');
  await s.nth(0).click(); await page.waitForTimeout(200);
  await page.screenshot({path:`${OUT}/drawer-390.png`});
  await ctx.close();
}
await browser.close(); server.close();
