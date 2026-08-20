import { createServer } from 'node:http';
import { createReadStream, existsSync, statSync } from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';
const ROOT=process.argv[2], PAGE=process.argv[3];
const mime=new Map([['.html','text/html; charset=utf-8'],['.css','text/css; charset=utf-8'],['.js','text/javascript; charset=utf-8'],['.png','image/png'],['.jpg','image/jpeg'],['.jpeg','image/jpeg'],['.webp','image/webp'],['.woff2','font/woff2'],['.json','application/json']]);
const server=createServer((req,res)=>{const p=path.join(ROOT,decodeURIComponent(req.url.split('?')[0]));if(!p.startsWith(ROOT)||!existsSync(p)||!statSync(p).isFile()){res.writeHead(404);res.end();return;}res.writeHead(200,{'Content-Type':mime.get(path.extname(p))||'application/octet-stream'});createReadStream(p).pipe(res);});
await new Promise(r=>server.listen(41994,'127.0.0.1',r));
const browser=await chromium.launch();
for(const w of [1201,1366,1440,1920]){
  const ctx=await browser.newContext({viewport:{width:w,height:900}});
  const page=await ctx.newPage();
  await page.goto(`http://127.0.0.1:41994/${PAGE}`,{waitUntil:'load'});
  const out=await page.evaluate((w)=>{
    const rect=el=>{const b=el.getBoundingClientRect();return {l:Math.round(b.left),t:Math.round(b.top),r:Math.round(b.right),w:Math.round(b.width),h:Math.round(b.height)};};
    const hdr=document.querySelector('.twins-brand-header');
    const bar=document.querySelector('.twins-brand-mainbar');
    const strip=document.querySelector('.twins-brand-market-strip');
    const nav=document.querySelector('.twins-brand-primary-nav');
    const grp=document.querySelector('.twins-brand-nav-group--areas');
    const trig=grp.querySelector('.twins-brand-nav-trigger');
    // open the FIRST (non-areas) group and read its panel
    const g0=document.querySelector('.twins-brand-nav-group:not(.twins-brand-nav-group--areas)');
    g0.querySelector('.twins-brand-nav-trigger').setAttribute('aria-expanded','true');
    const p0=g0.querySelector('.twins-brand-nav-panel');
    return {w, header:rect(hdr), mainbar:rect(bar), strip:rect(strip), nav:rect(nav), areasGroup:rect(grp), areasTrigger:rect(trig), firstPanel:rect(p0)};
  }, w);
  console.log(JSON.stringify(out));
  await ctx.close();
}
await browser.close(); server.close();
