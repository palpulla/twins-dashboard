import { createServer } from 'node:http';
import { createReadStream, existsSync, statSync } from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';
const ROOT=process.argv[2], PAGE=process.argv[3], SEL=process.argv[4], TRIG=process.argv[5];
const mime=new Map([['.html','text/html; charset=utf-8'],['.css','text/css; charset=utf-8'],['.js','text/javascript; charset=utf-8'],['.png','image/png'],['.jpg','image/jpeg'],['.jpeg','image/jpeg'],['.webp','image/webp'],['.woff2','font/woff2'],['.json','application/json']]);
const server=createServer((req,res)=>{const p=path.join(ROOT,decodeURIComponent(req.url.split('?')[0]));if(!p.startsWith(ROOT)||!existsSync(p)||!statSync(p).isFile()){res.writeHead(404);res.end();return;}res.writeHead(200,{'Content-Type':mime.get(path.extname(p))||'application/octet-stream'});createReadStream(p).pipe(res);});
await new Promise(r=>server.listen(41993,'127.0.0.1',r));
const browser=await chromium.launch();
for(const w of [1201,1280,1366,1440,1600,1920]){
  const ctx=await browser.newContext({viewport:{width:w,height:900}});
  const page=await ctx.newPage();
  await page.goto(`http://127.0.0.1:41993/${PAGE}`,{waitUntil:'load'});
  const t=page.locator(TRIG);
  await t.click(); await page.waitForTimeout(200);
  const r=await page.evaluate(([sel,w])=>{const p=document.querySelector(sel);const b=p.getBoundingClientRect();return {w,left:Math.round(b.left),right:Math.round(b.right),width:Math.round(b.width),clippedLeft:Math.max(0,Math.round(-b.left)),clippedRight:Math.max(0,Math.round(b.right-document.documentElement.clientWidth)),h:Math.round(b.height),scrollable:p.scrollHeight>p.clientHeight};},[SEL,w]);
  console.log(JSON.stringify(r));
  await ctx.close();
}
await browser.close(); server.close();
