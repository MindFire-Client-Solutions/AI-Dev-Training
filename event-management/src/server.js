// Local-only server for the generator UI. Node built-ins only.
//
//   npm start            -> http://127.0.0.1:4310
//   PORT=5000 npm start  -> another port
//
// It writes to output/ and nowhere else (CLAUDE.md). It binds to 127.0.0.1 so
// uploaded client data never leaves this machine.

import { createServer } from 'node:http';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { dirname, join, resolve, sep } from 'node:path';
import { fileURLToPath } from 'node:url';
import { generate } from './lib/generator.js';
import { formatSummary } from './lib/summary.js';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const OUTPUT_DIR = join(ROOT, 'output');
const UI_FILE = join(ROOT, 'src', 'ui', 'index.html');
const HOST = '127.0.0.1';
const PORT = Number(process.env.PORT ?? 4310);
const MAX_BODY = 25 * 1024 * 1024;
const PREVIEW_ROWS = 50;

function send(res, status, body, headers = {}) {
  const isJson = typeof body !== 'string' && !Buffer.isBuffer(body);
  res.writeHead(status, {
    'Content-Type': isJson ? 'application/json; charset=utf-8' : 'text/html; charset=utf-8',
    'Cache-Control': 'no-store',
    ...headers,
  });
  res.end(isJson ? JSON.stringify(body) : body);
}

async function readJson(req) {
  const chunks = [];
  let size = 0;
  for await (const chunk of req) {
    size += chunk.length;
    if (size > MAX_BODY) throw Object.assign(new Error('Upload is larger than 25 MB'), { status: 413 });
    chunks.push(chunk);
  }
  try { return JSON.parse(Buffer.concat(chunks).toString('utf8')); } catch {
    throw Object.assign(new Error('Request body is not valid JSON'), { status: 400 });
  }
}

function parseSeed(v) {
  if (v === undefined || v === null || v === '') return undefined;
  const n = Number(v);
  if (!Number.isInteger(n) || n < 0 || n > 0xffffffff) {
    throw Object.assign(new Error('Seed must be a whole number from 0 to 4294967295'), { status: 400 });
  }
  return n;
}

function stamp(d) {
  const p = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}${p(d.getMonth() + 1)}${p(d.getDate())}-${p(d.getHours())}${p(d.getMinutes())}${p(d.getSeconds())}`;
}

async function handleGenerate(req, res) {
  const body = await readJson(req);
  if (typeof body.contactsCsv !== 'string' || typeof body.eventsCsv !== 'string') {
    return send(res, 400, { error: 'Upload both a contacts file and an events file.' });
  }
  const seed = parseSeed(body.seed);
  let result;
  try {
    result = generate({
      contactsCsv: body.contactsCsv, eventsCsv: body.eventsCsv,
      eventsFileName: String(body.eventsName ?? ''), ...(seed === undefined ? {} : { seed }),
    });
  } catch (err) {
    return send(res, 400, { error: err.message });
  }

  process.stdout.write(`\n[${new Date().toISOString()}] run for account ${result.acctID}\n${formatSummary(result.summary)}\n`);

  let fileName = null;
  if (result.validation.ok) {
    fileName = `agentConversationEvents_${result.acctID}_generated_${stamp(new Date())}_seed${result.seed}.csv`;
    await mkdir(OUTPUT_DIR, { recursive: true });
    await writeFile(join(OUTPUT_DIR, fileName), result.csv, 'utf8');
    process.stdout.write(`Wrote output/${fileName}\n`);
  } else {
    process.stdout.write('Validation FAILED, nothing written.\n');
  }

  send(res, result.validation.ok ? 200 : 422, {
    ok: result.validation.ok,
    fileName,
    acctID: result.acctID,
    summary: result.summary,
    validation: result.validation,
    notes: result.notes,
    window: result.window,
    header: result.header,
    preview: result.rows.slice(0, PREVIEW_ROWS),
  });
}

async function handleDownload(url, res) {
  const name = url.searchParams.get('file') ?? '';
  if (!/^[\w.-]+\.csv$/.test(name)) return send(res, 400, { error: 'Bad file name' });
  const path = resolve(OUTPUT_DIR, name);
  if (!path.startsWith(OUTPUT_DIR + sep)) return send(res, 400, { error: 'Bad file name' });
  let data;
  try { data = await readFile(path); } catch { return send(res, 404, { error: 'File not found in output/' }); }
  send(res, 200, data, {
    'Content-Type': 'text/csv; charset=utf-8',
    'Content-Disposition': `attachment; filename="${name}"`,
  });
}

const server = createServer(async (req, res) => {
  const url = new URL(req.url, `http://${HOST}`);
  try {
    if (req.method === 'GET' && (url.pathname === '/' || url.pathname === '/index.html')) {
      return send(res, 200, await readFile(UI_FILE, 'utf8'));
    }
    if (req.method === 'POST' && url.pathname === '/api/generate') return await handleGenerate(req, res);
    if (req.method === 'GET' && url.pathname === '/api/download') return await handleDownload(url, res);
    send(res, 404, { error: 'Not found' });
  } catch (err) {
    send(res, err.status ?? 500, { error: err.status ? err.message : 'Internal error' });
    if (!err.status) process.stderr.write(`${err.stack}\n`);
  }
});

server.listen(PORT, HOST, () => {
  process.stdout.write(`Event generator UI: http://${HOST}:${PORT}\nOutput folder:      ${OUTPUT_DIR}\n`);
});
