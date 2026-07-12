import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, '..');
const repo = path.resolve(root, '../..');
const dist = path.join(root, 'dist');
const check = process.argv.includes('--check');

function read(relative) {
  return fs.readFileSync(path.join(root, relative), 'utf8');
}

function replaceOnce(source, token, value) {
  if (source.split(token).length !== 2) throw new Error('token count: ' + token);
  return source.replace(token, value);
}

function sha256(value) {
  return crypto.createHash('sha256').update(value).digest('hex');
}

function stableJson(value) {
  return JSON.stringify(value, null, 2) + '\n';
}

function writeOrCheck(relative, value) {
  const target = path.join(dist, relative);
  if (check) {
    const current = fs.existsSync(target) ? fs.readFileSync(target, 'utf8') : '';
    if (current !== value) {
      console.error('generated artifact differs: ' + relative);
      process.exitCode = 1;
    }
    return;
  }
  fs.mkdirSync(dist, { recursive: true });
  fs.writeFileSync(target, value, 'utf8');
}

const css = read('src/styles.css');
const core = read('src/core.js');
const transport = read('src/transport.js');
const app = read('src/app.js');
const funnel = read('src/funnel-submit.js');
const referenceManifest = JSON.parse(read('assets/reference-manifest.json'));
const manifestScript = 'window.TwinsDoorBuilderReferenceManifest = '
  + JSON.stringify(referenceManifest) + ';';

let php = read('src/wpcode-wrapper.php.tmpl');
php = replaceOnce(php, '/*__TWXDB_CSS__*/', css);
php = replaceOnce(php, '/*__TWXDB_MANIFEST__*/', manifestScript);
php = replaceOnce(php, '/*__TWXDB_CORE__*/', core);
php = replaceOnce(php, '/*__TWXDB_TRANSPORT__*/', transport);
php = replaceOnce(php, '/*__TWXDB_FUNNEL__*/', funnel);
php = replaceOnce(php, '/*__TWXDB_APP__*/', app);
if (/\/\*__TWXDB_[A-Z_]+__\*\//.test(php)) {
  throw new Error('unreplaced WPCode token');
}

const fixtureRoot = path.join(
  repo,
  'docs/superpowers/backups/2026-07-09-phase4-catalog/clopay-api-snapshot'
);
const listFixture = JSON.parse(fs.readFileSync(path.join(fixtureRoot, 'products-list.json'), 'utf8'));
const detailFiles = fs.readdirSync(fixtureRoot)
  .filter(function (name) { return /^product-[0-9]+\.json$/.test(name); })
  .sort(function (left, right) {
    return Number(left.match(/[0-9]+/)[0]) - Number(right.match(/[0-9]+/)[0]);
  });
const details = {};
detailFiles.forEach(function (name) {
  const detail = JSON.parse(fs.readFileSync(path.join(fixtureRoot, name), 'utf8'));
  details[String(detail.ProductId)] = detail;
});
const listIds = listFixture.map(function (item) { return String(item.ProductId); }).sort();
const detailIds = Object.keys(details).sort();
if (JSON.stringify(listIds) !== JSON.stringify(detailIds)) {
  throw new Error('catalog/detail fixture ID mismatch');
}
const fixturesScript = 'window.TwinsDoorBuilderFixtures = '
  + JSON.stringify({ list: listFixture, details: details }) + ';';

const funnelBoot = [
  '(function(){',
  'function start(){',
  'var form=document.querySelector(".twx-db");',
  'if(!form)return;',
  'var error=form.querySelector("[data-door-builder-error]");',
  'if(!error){error=document.createElement("p");error.hidden=true;error.setAttribute("data-door-builder-error","");form.appendChild(error);}',
  'TwinsDoorBuilderFunnel.bindFunnel(form,{',
  'fetchImpl:fetch,',
  'endpoint:"https://twinsgaragedoors.com/wp-json/twins/v1/door-builder",',
  'successUrl:"/door-builder/",',
  'errorMessage:"Something went wrong. Call Twins at (833) 833-2010."',
  '});',
  '}',
  'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",start);}else{start();}',
  '}());'
].join('\n');
const funnelCandidate = funnel.trimEnd() + '\n' + funnelBoot + '\n';

const harness = [
  '<!doctype html>',
  '<html lang="en"><head>',
  '<meta charset="utf-8">',
  '<meta name="viewport" content="width=device-width,initial-scale=1">',
  '<title>Twins door builder repository harness</title>',
  '<style>:root{--tw-navy:#022751;--tw-yellow:#FBBD04}body{font-family:Arial,sans-serif;margin:0;padding:24px}</style>',
  '</head><body>',
  '<div id="twxdb" class="twxdb" data-region="main" data-endpoint="/__harness__/lead"></div>',
  '<style id="twxdb-css">' + css + '</style>',
  '<script>' + fixturesScript + '</script>',
  '<script>',
  '(function(){',
  'window.__twxdbPosts=[];',
  'window.fetch=function(url,options){',
  'var href=String(url);',
  'if(options&&options.method==="POST"){',
  'var payload=JSON.parse(options.body||"{}");',
  'window.__twxdbPosts.push(payload);',
  'var fail=new URLSearchParams(location.search).get("leadFail")==="1";',
  'return Promise.resolve(new Response(JSON.stringify({ok:!fail}),{status:fail?500:200,headers:{"Content-Type":"application/json"}}));',
  '}',
  'if(href.indexOf("GetProductsList/GetProducts")>=0){',
  'var catalogFail=new URLSearchParams(location.search).get("twxdbfail")==="1";',
  'if(catalogFail)return Promise.resolve(new Response(JSON.stringify({error:"forced catalog failure"}),{status:503,headers:{"Content-Type":"application/json"}}));',
  'return Promise.resolve(new Response(JSON.stringify(window.TwinsDoorBuilderFixtures.list),{status:200,headers:{"Content-Type":"application/json"}}));',
  '}',
  'var match=href.match(/productId=([0-9]+)/);',
  'if(match&&window.TwinsDoorBuilderFixtures.details[match[1]]){',
  'return Promise.resolve(new Response(JSON.stringify(window.TwinsDoorBuilderFixtures.details[match[1]]),{status:200,headers:{"Content-Type":"application/json"}}));',
  '}',
  'return Promise.reject(new Error("harness blocked network"));',
  '};',
  '}());',
  '</script>',
  '<script>' + manifestScript + '</script>',
  '<script>' + core + '</script>',
  '<script>' + transport + '</script>',
  '<script>' + funnel + '</script>',
  '<script>' + app + '</script>',
  '</body></html>'
].join('\n') + '\n';

const outputs = {
  'design-your-door-funnel.js': funnelCandidate,
  'local-harness.html': harness,
  'twins-door-builder-wpcode.php': php.trimEnd() + '\n'
};

Object.keys(outputs).sort().forEach(function (name) {
  writeOrCheck(name, outputs[name]);
});

const records = Object.keys(outputs).sort().map(function (name) {
  return { path: name, sha256: sha256(outputs[name]) };
});
writeOrCheck('artifact-manifest.json', stableJson({
  schemaVersion: 1,
  artifacts: records
}));

if (!process.exitCode) {
  console.log(check ? 'generated artifacts match' : 'generated four artifacts');
}
