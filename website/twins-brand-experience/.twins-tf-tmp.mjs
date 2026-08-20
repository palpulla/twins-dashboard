import { createServer } from 'node:http';
import { createReadStream, existsSync, statSync } from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';
const ROOT=process.argv[2], PAGE=process.argv[3];
const mime=new Map([['.html','text/html; charset=utf-8'],['.css','text/css; charset=utf-8'],['.js','text/javascript; charset=utf-8'],['.png','image/png'],['.jpg','image/jpeg'],['.jpeg','image/jpeg'],['.webp','image/webp'],['.woff2','font/woff2'],['.json','application/json']]);
const server=createServer((req,res)=>{const p=path.join(ROOT,decodeURIComponent(req.url.split('?')[0]));if(!p.startsWith(ROOT)||!existsSync(p)||!statSync(p).isFile()){res.writeHead(404);res.end();return;}res.writeHead(200,{'Content-Type':mime.get(path.extname(p))||'application/octet-stream'});createReadStream(p).pipe(res);});
await new Promise(r=>server.listen(41997,'127.0.0.1',r));
const browser=await chromium.launch();
const ctx=await browser.newContext({viewport:{width:1440,height:900}});
const page=await ctx.newPage();
await page.goto(`http://127.0.0.1:41997/${PAGE}`,{waitUntil:'load'});
await page.locator('.twins-brand-nav-group--areas .twins-brand-nav-trigger').click();
await page.waitForTimeout(250);
await page.locator('.twins-brand-nav-group--areas .twins-brand-nav-trigger').focus();
const snap=()=>page.evaluate(()=>{const el=document.activeElement;const s=getComputedStyle(el);return {text:el.textContent.trim().slice(0,26),cls:String(el.className)||'(no class)',fv:el.matches(':focus-visible'),color:s.color,bg:s.backgroundColor,outline:`${s.outlineWidth} ${s.outlineStyle} ${s.outlineColor}`,offset:s.outlineOffset,shadow:s.boxShadow};});
const seq=[];
for(let i=0;i<6;i++){ await page.keyboard.press('Tab'); await page.waitForTimeout(40); seq.push(await snap()); }
// tab all the way to the hub pill
for(let i=0;i<40;i++){ const hit=await page.evaluate(()=>document.activeElement.classList.contains('twins-brand-area-hub')); if(hit) break; await page.keyboard.press('Tab'); await page.waitForTimeout(20); }
seq.push(await snap());
console.log(JSON.stringify(seq,null,1));
await browser.close(); server.close();
