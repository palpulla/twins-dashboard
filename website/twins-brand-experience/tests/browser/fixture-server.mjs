import { createReadStream } from 'node:fs';
import { realpath, stat } from 'node:fs/promises';
import { createServer } from 'node:http';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const portableRoot = await realpath(fileURLToPath(new URL('../..', import.meta.url)));
const stagingAssetsRoot = await realpath(path.resolve(
  portableRoot,
  '../staging-safety/mu-plugins/twins-staging-assets',
));
const args = process.argv.slice(2);
const portFlag = args.indexOf('--port');
const port = portFlag === -1 ? 41739 : Number(args[portFlag + 1]);
if (!Number.isInteger(port) || port < 1024 || port > 65535) throw new Error('A safe fixture port is required.');

const ledger = [];
const mime = new Map([
  ['.html', 'text/html; charset=utf-8'],
  ['.css', 'text/css; charset=utf-8'],
  ['.js', 'text/javascript; charset=utf-8'],
  ['.png', 'image/png'],
  ['.jpg', 'image/jpeg'],
  ['.jpeg', 'image/jpeg'],
  ['.webp', 'image/webp'],
  ['.woff2', 'font/woff2'],
  ['.json', 'application/json; charset=utf-8'],
]);

function reply(response, status, type, body = '') {
  response.writeHead(status, {
    'Content-Type': type,
    'Cache-Control': 'no-store',
    'X-Content-Type-Options': 'nosniff',
  });
  response.end(body);
}

const server = createServer(async (request, response) => {
  const method = request.method || '';
  const requestUrl = new URL(request.url || '/', 'http://127.0.0.1');
  ledger.push({ method, path: requestUrl.pathname });

  if (method !== 'GET' && method !== 'HEAD') {
    reply(response, 405, 'text/plain; charset=utf-8', 'Method Not Allowed');
    return;
  }

  if (requestUrl.pathname === '/__fixture-ledger') {
    const body = JSON.stringify(ledger);
    reply(response, 200, 'application/json; charset=utf-8', method === 'HEAD' ? '' : body);
    return;
  }

  let decoded;
  try {
    decoded = decodeURIComponent(requestUrl.pathname);
  } catch {
    reply(response, 400, 'text/plain; charset=utf-8', 'Bad Request');
    return;
  }

  const assetPrefix = '/wp-content/mu-plugins/twins-staging-assets/';
  const selectedRoot = decoded.startsWith(assetPrefix) ? stagingAssetsRoot : portableRoot;
  const relative = decoded.startsWith(assetPrefix)
    ? decoded.slice(assetPrefix.length)
    : decoded === '/'
      ? 'tests/browser/fixtures/brand-home.html'
      : decoded.replace(/^\/+/, '');
  const candidate = path.resolve(selectedRoot, relative);
  if (candidate !== selectedRoot && !candidate.startsWith(`${selectedRoot}${path.sep}`)) {
    reply(response, 403, 'text/plain; charset=utf-8', 'Forbidden');
    return;
  }

  try {
    const resolved = await realpath(candidate);
    const details = await stat(resolved);
    if (!details.isFile() || (resolved !== selectedRoot && !resolved.startsWith(`${selectedRoot}${path.sep}`))) {
      reply(response, 403, 'text/plain; charset=utf-8', 'Forbidden');
      return;
    }
    response.writeHead(200, {
      'Content-Type': mime.get(path.extname(resolved).toLowerCase()) || 'application/octet-stream',
      'Content-Length': details.size,
      'Cache-Control': 'no-store',
      'X-Content-Type-Options': 'nosniff',
    });
    if (method === 'HEAD') response.end();
    else createReadStream(resolved).pipe(response);
  } catch {
    reply(response, 404, 'text/plain; charset=utf-8', 'Not Found');
  }
});

server.listen(port, '127.0.0.1');
for (const signal of ['SIGINT', 'SIGTERM']) process.on(signal, () => server.close(() => process.exit(0)));
