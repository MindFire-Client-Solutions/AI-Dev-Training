// Local-only server for the generator UI. Node built-ins only.
//
//   npm start            -> http://127.0.0.1:4310
//   PORT=5000 npm start  -> another port
//
// The page runs the generator in the browser (the same files are deployed to
// Cloudflare Pages). Locally, this server also accepts the generated CSV and
// saves a copy to output/ — and nowhere else (CLAUDE.md). It binds to
// 127.0.0.1 so nothing is reachable from other machines.

import { createServer } from 'node:http';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { OUTPUT_FILE_PATTERN } from './lib/generator.js';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const OUTPUT_DIR = join(ROOT, 'output');
const HOST = '127.0.0.1';
const PORT = Number(process.env.PORT ?? 4310);
const MAX_BODY = 50 * 1024 * 1024;

// The only files served: the page and the browser modules it imports.
const STATIC = new Map([
  ['/', ['src/ui/index.html', 'text/html; charset=utf-8']],
  ['/index.html', ['src/ui/index.html', 'text/html; charset=utf-8']],
  ...['config', 'csv', 'generator', 'schema', 'summary', 'validator'].map((m) => [
    `/lib/${m}.js`, [`src/lib/${m}.js`, 'text/javascript; charset=utf-8'],
  ]),
]);

function sendJson(res, status, body) {
  res.writeHead(status, { 'Content-Type': 'application/json; charset=utf-8', 'Cache-Control': 'no-store' });
  res.end(JSON.stringify(body));
}

async function readJson(req) {
  const chunks = [];
  let size = 0;
  for await (const chunk of req) {
    size += chunk.length;
    if (size > MAX_BODY) throw Object.assign(new Error('File is larger than 50 MB'), { status: 413 });
    chunks.push(chunk);
  }
  try { return JSON.parse(Buffer.concat(chunks).toString('utf8')); } catch {
    throw Object.assign(new Error('Request body is not valid JSON'), { status: 400 });
  }
}

async function handleSave(req, res) {
  const { fileName, csv, summaryText } = await readJson(req);
  if (typeof fileName !== 'string' || !OUTPUT_FILE_PATTERN.test(fileName)) {
    return sendJson(res, 400, { error: 'Bad file name' });
  }
  if (typeof csv !== 'string' || !csv) return sendJson(res, 400, { error: 'Empty CSV' });
  await mkdir(OUTPUT_DIR, { recursive: true });
  await writeFile(join(OUTPUT_DIR, fileName), csv, 'utf8');
  process.stdout.write(`\n[${new Date().toISOString()}] run\n${typeof summaryText === 'string' ? summaryText : ''}\nWrote output/${fileName}\n`);
  sendJson(res, 200, { path: `output/${fileName}` });
}

const server = createServer(async (req, res) => {
  const url = new URL(req.url, `http://${HOST}`);
  try {
    const file = req.method === 'GET' && STATIC.get(url.pathname);
    if (file) {
      res.writeHead(200, { 'Content-Type': file[1], 'Cache-Control': 'no-store' });
      return res.end(await readFile(join(ROOT, file[0])));
    }
    if (req.method === 'POST' && url.pathname === '/api/save') return await handleSave(req, res);
    sendJson(res, 404, { error: 'Not found' });
  } catch (err) {
    sendJson(res, err.status ?? 500, { error: err.status ? err.message : 'Internal error' });
    if (!err.status) process.stderr.write(`${err.stack}\n`);
  }
});

server.listen(PORT, HOST, () => {
  process.stdout.write(`Event generator UI: http://${HOST}:${PORT}\nOutput folder:      ${OUTPUT_DIR}\n`);
});
