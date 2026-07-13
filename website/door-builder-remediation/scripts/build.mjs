import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, '..');
const repo = path.resolve(root, '../..');
const dist = path.join(root, 'dist');
const check = process.argv.includes('--check');
const expectedProductCount = 23;
const harnessListUrl = 'https://www.clopaydoor.com/api/v2/GetProductsList/GetProducts?productType=Residential';
const harnessDetailUrl = 'https://www.clopaydoor.com/api/v2/GetProductDetails/GetProductData?productId=';
const harnessLeadPath = '/__harness__/lead';
const verificationImagePath = 'verification-image.svg';
const verificationImageProvenance = 'repository-generated deterministic local verification fixture';

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

export function sameUtf8Bytes(current, value) {
  return Buffer.isBuffer(current) && current.equals(Buffer.from(value, 'utf8'));
}

export function validateCatalogFixtures(listFixture, detailFixtures) {
  if (!Array.isArray(listFixture) || listFixture.length !== expectedProductCount) {
    throw new Error('expected exactly 23 catalog entries');
  }
  if (!Array.isArray(detailFixtures) || detailFixtures.length !== expectedProductCount) {
    throw new Error('expected exactly 23 detail files');
  }

  const records = detailFixtures.map(function (fixture) {
    const match = /^product-([0-9]+)\.json$/.exec(fixture.name);
    if (!match) throw new Error('invalid detail fixture filename: ' + fixture.name);
    return {
      name: fixture.name,
      fileId: match[1],
      bodyId: String(fixture.detail.ProductId),
      detail: fixture.detail
    };
  });
  const detailIds = records.map(function (record) { return record.bodyId; });
  if (new Set(detailIds).size !== expectedProductCount) {
    throw new Error('expected exactly 23 unique detail body IDs');
  }
  records.forEach(function (record) {
    if (record.fileId !== record.bodyId) {
      throw new Error('detail filename/body ProductId mismatch: ' + record.name);
    }
  });

  const listIds = listFixture.map(function (item) { return String(item.ProductId); }).sort();
  detailIds.sort();
  if (JSON.stringify(listIds) !== JSON.stringify(detailIds)) {
    throw new Error('catalog/detail fixture ID mismatch');
  }

  const details = {};
  records.forEach(function (record) { details[record.bodyId] = record.detail; });
  return { details: details, listIds: listIds, detailIds: detailIds };
}

function writeOrCheck(relative, value) {
  const target = path.join(dist, relative);
  if (check) {
    const current = fs.existsSync(target) ? fs.readFileSync(target) : null;
    if (current === null || !sameUtf8Bytes(current, value)) {
      console.error('generated artifact differs: ' + relative);
      process.exitCode = 1;
    }
    return;
  }
  fs.mkdirSync(dist, { recursive: true });
  fs.writeFileSync(target, value, 'utf8');
}

export function validateDistEntries(entries, expectedNames) {
  const expected = new Set(expectedNames);
  const byName = new Map(entries.map(function (entry) { return [entry.name, entry]; }));
  const problems = [];
  expectedNames.slice().sort().forEach(function (name) {
    if (byName.has(name) && !byName.get(name).isFile()) {
      problems.push('generated artifact is not a regular file: ' + name);
    }
  });
  entries.map(function (entry) { return entry.name; }).sort().forEach(function (name) {
    if (!expected.has(name)) problems.push('unexpected generated entry: ' + name);
  });
  return problems;
}

function enforceExactDistEntries(expectedNames) {
  if (!fs.existsSync(dist)) return;
  const entries = fs.readdirSync(dist, { withFileTypes: true });
  const problems = validateDistEntries(entries, expectedNames);
  problems.forEach(function (problem) {
    if (check) {
      console.error(problem);
      process.exitCode = 1;
    } else {
      const stalePrefix = 'unexpected generated entry: ';
      const name = problem.startsWith(stalePrefix) ? problem.slice(stalePrefix.length) : '';
      const entry = entries.find(function (candidate) { return candidate.name === name; });
      if (!entry || !entry.isFile()) throw new Error(problem);
      fs.unlinkSync(path.join(dist, name));
    }
  });
}

function build() {
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
const detailFixtures = detailFiles.map(function (name) {
  return {
    name: name,
    detail: JSON.parse(fs.readFileSync(path.join(fixtureRoot, name), 'utf8'))
  };
});
const details = validateCatalogFixtures(listFixture, detailFixtures).details;
const fixturesScript = 'window.TwinsDoorBuilderFixtures = '
  + JSON.stringify({ list: listFixture, details: details }) + ';';
const verificationImageScript = 'window.TwinsDoorBuilderVerificationImage = '
  + JSON.stringify({
    path: './' + verificationImagePath,
    provenance: verificationImageProvenance
  }) + ';';
const verificationImage = [
  '<svg xmlns="http://www.w3.org/2000/svg" width="960" height="540" viewBox="0 0 960 540" role="img" aria-labelledby="title description">',
  '<title id="title">Local door-builder verification fixture</title>',
  '<desc id="description">Repository-generated placeholder used only to verify local image loading and responsive intrinsic-size caps.</desc>',
  '<rect width="960" height="540" fill="#f2f5f7"/>',
  '<rect x="90" y="70" width="780" height="400" rx="18" fill="#ffffff" stroke="#022751" stroke-width="12"/>',
  '<path d="M110 180h740M110 290h740M110 400h740" stroke="#022751" stroke-width="8"/>',
  '<path d="M285 80v380M480 80v380M675 80v380" stroke="#022751" stroke-width="6" opacity=".45"/>',
  '<rect x="345" y="18" width="270" height="58" rx="29" fill="#fbbd04"/>',
  '<text x="480" y="54" text-anchor="middle" font-family="Arial,sans-serif" font-size="22" font-weight="700" fill="#022751">LOCAL VERIFICATION FIXTURE</text>',
  '<text x="480" y="515" text-anchor="middle" font-family="Arial,sans-serif" font-size="18" fill="#022751">Generated offline • not a product photograph • never used in production</text>',
  '</svg>'
].join('\n') + '\n';

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
  '<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; script-src \'unsafe-inline\'; style-src \'unsafe-inline\'; img-src \'self\'; frame-src \'self\'; connect-src \'none\'; form-action \'none\'; base-uri \'none\'; object-src \'none\'">',
  '<!-- Same-origin fixture pixels load locally; CSP blocks original external image URLs, all connections and form actions. -->',
  '<title>Twins door builder repository harness</title>',
  '<link rel="icon" href="./verification-image.svg" type="image/svg+xml">',
  '<style>:root{--tw-navy:#022751;--tw-yellow:#FBBD04}body{font-family:Arial,sans-serif;margin:0;padding:24px}.twxdb-verification-note{max-width:1140px;margin:0 auto 18px;padding:10px 12px;border-left:4px solid var(--tw-yellow);background:#F2F5F7;color:var(--tw-navy);font-size:13px;line-height:1.5}</style>',
  '</head><body>',
  '<p class="twxdb-verification-note">Local verification mode: every rendered image is the repository-generated deterministic fixture. Original HTTPS Clopay source URLs remain preserved in each image\'s <code>data-source-url</code> attribute and are blocked by CSP.</p>',
  '<div id="twxdb" class="twxdb" data-region="main" data-endpoint="/__harness__/lead"></div>',
  '<style id="twxdb-css">' + css + '</style>',
  '<script>' + verificationImageScript + '</script>',
  '<script>' + fixturesScript + '</script>',
  '<script>',
  '(function(){',
  'window.__twxdbPosts=[];',
  'var LIST_URL=' + JSON.stringify(harnessListUrl) + ';',
  'var DETAIL_URL=' + JSON.stringify(harnessDetailUrl) + ';',
  'var LEAD_PATH=' + JSON.stringify(harnessLeadPath) + ';',
  'window.fetch=function(url,options){',
  'var href=String(url);',
  'var method=options&&options.method!==undefined?String(options.method):"GET";',
  'if(method==="POST"&&href===LEAD_PATH){',
  'var payload=JSON.parse(options.body||"{}");',
  'window.__twxdbPosts.push(payload);',
  'var fail=new URLSearchParams(location.search).get("leadFail")==="1";',
  'return Promise.resolve(new Response(JSON.stringify({ok:!fail}),{status:fail?500:200,headers:{"Content-Type":"application/json"}}));',
  '}',
  'if(method==="GET"&&href===LIST_URL){',
  'var catalogFail=new URLSearchParams(location.search).get("twxdbfail")==="1";',
  'if(catalogFail)return Promise.resolve(new Response(JSON.stringify({error:"forced catalog failure"}),{status:503,headers:{"Content-Type":"application/json"}}));',
  'return Promise.resolve(new Response(JSON.stringify(window.TwinsDoorBuilderFixtures.list),{status:200,headers:{"Content-Type":"application/json"}}));',
  '}',
  'if(method==="GET"&&href.indexOf(DETAIL_URL)===0){',
  'var productId=href.slice(DETAIL_URL.length);',
  'if(/^[0-9]+$/.test(productId)&&Object.prototype.hasOwnProperty.call(window.TwinsDoorBuilderFixtures.details,productId)){',
  'return Promise.resolve(new Response(JSON.stringify(window.TwinsDoorBuilderFixtures.details[productId]),{status:200,headers:{"Content-Type":"application/json"}}));',
  '}',
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
  'twins-door-builder-wpcode.php': php.trimEnd() + '\n',
  [verificationImagePath]: verificationImage
};
const expectedDistNames = Object.keys(outputs).concat('artifact-manifest.json').sort();

enforceExactDistEntries(expectedDistNames);

Object.keys(outputs).sort().forEach(function (name) {
  writeOrCheck(name, outputs[name]);
});

const records = Object.keys(outputs).sort().map(function (name) {
  return { path: name, sha256: sha256(outputs[name]) };
});
const verificationImageHash = sha256(verificationImage);
writeOrCheck('artifact-manifest.json', stableJson({
  schemaVersion: 1,
  artifacts: records,
  verificationFixtures: [{
    path: verificationImagePath,
    sha256: verificationImageHash,
    mediaType: 'image/svg+xml',
    width: 960,
    height: 540,
    provenance: verificationImageProvenance,
    purpose: 'Local-only image loading and intrinsic-size verification without external traffic.',
    productionUse: false
  }]
}));

enforceExactDistEntries(expectedDistNames);

if (!process.exitCode) {
  console.log(check ? 'generated artifacts match' : 'generated five artifacts');
}
}

const isMain = process.argv[1]
  && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url);
if (isMain) build();
