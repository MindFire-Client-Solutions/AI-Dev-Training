// Local-only server for the generator UI. Node built-ins only.
//
//   npm start            -> http://127.0.0.1:4310
//   PORT=5000 npm start  -> another port
//
// The page runs the generator in the browser (the same files are deployed to
// Cloudflare Pages); the CSV is a browser download. This server only serves the
// page and its modules. It binds to 127.0.0.1 so nothing is reachable from other machines.

import { createServer } from 'node:http';
import { readFile } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const HOST = '127.0.0.1';
const PORT = Number(process.env.PORT ?? 4310);
// Same as scripts/build.js: SETTINGS_API_URL turns on the Settings screen.
const SETTINGS_API = (process.env.SETTINGS_API_URL ?? '').trim().replace(/\/+$/, '').replace(/"/g, '');
const META = '<meta name="mf-settings-api" content="">';

// The only files served: the page and the browser modules it imports.
const STATIC = new Map([
  ['/', ['src/ui/index.html', 'text/html; charset=utf-8']],
  ['/index.html', ['src/ui/index.html', 'text/html; charset=utf-8']],
  ['/favicon.jpg', ['src/ui/favicon.jpg', 'image/jpeg']],
  ...['config', 'csv', 'generator', 'schema', 'summary', 'validator'].map((m) => [
    `/lib/${m}.js`, [`src/lib/${m}.js`, 'text/javascript; charset=utf-8'],
  ]),
]);

function sendJson(res, status, body) {
  res.writeHead(status, { 'Content-Type': 'application/json; charset=utf-8', 'Cache-Control': 'no-store' });
  res.end(JSON.stringify(body));
}

const server = createServer(async (req, res) => {
  const url = new URL(req.url, `http://${HOST}`);
  try {
    const file = req.method === 'GET' && STATIC.get(url.pathname);
    if (file) {
      res.writeHead(200, { 'Content-Type': file[1], 'Cache-Control': 'no-store' });
      const body = await readFile(join(ROOT, file[0]));
      if (SETTINGS_API && file[0].endsWith('.html')) {
        return res.end(body.toString('utf8').replace(META, `<meta name="mf-settings-api" content="${SETTINGS_API}">`));
      }
      return res.end(body);
    }
    sendJson(res, 404, { error: 'Not found' });
  } catch (err) {
    sendJson(res, err.status ?? 500, { error: err.status ? err.message : 'Internal error' });
    if (!err.status) process.stderr.write(`${err.stack}\n`);
  }
});

server.listen(PORT, HOST, () => {
  process.stdout.write(`Event generator UI: http://${HOST}:${PORT}\n`);
});
