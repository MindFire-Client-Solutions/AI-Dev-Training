// Builds dist/ for Cloudflare Pages: the page, the browser modules it imports,
// the favicon and a _headers file. No dependencies, no bundling: files are copied as-is.
//
//   npm run build   -> dist/
//
// Cloudflare Pages settings: root directory `event-management`,
// build command `npm run build`, output directory `dist`.
//
// Optional: SETTINGS_API_URL (Cloudflare Pages -> Settings -> Environment variables)
// is the Lambda Function URL for the weekly SFTP run (docs/001-scheduled-sftp-run.md).
// When set, the page shows a Settings button and may connect to that one origin.
// When not set, there is no Settings button and connect-src stays 'none'.

import { copyFile, mkdir, readFile, readdir, rm, writeFile } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const DIST = join(ROOT, 'dist');
const LIB = join(ROOT, 'src', 'lib');
const META = '<meta name="mf-settings-api" content="">';

const api = (process.env.SETTINGS_API_URL ?? '').trim().replace(/\/+$/, '');
let apiOrigin = '';
if (api) {
  const u = new URL(api);
  if (u.protocol !== 'https:') throw new Error('SETTINGS_API_URL must be an https:// URL');
  apiOrigin = u.origin;
}

// Public link, but keep it out of search engines and lock the page down: it loads only
// its own scripts and Google Fonts, and talks to nothing except the Settings API when one
// is configured. Files chosen in the page are never sent anywhere.
const HEADERS = `/*
  X-Robots-Tag: noindex, nofollow
  X-Content-Type-Options: nosniff
  X-Frame-Options: DENY
  Referrer-Policy: no-referrer
  Permissions-Policy: camera=(), microphone=(), geolocation=()
  Content-Security-Policy: default-src 'none'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data:; connect-src ${apiOrigin || "'none'"}; base-uri 'none'; form-action 'none'; frame-ancestors 'none'
`;

await rm(DIST, { recursive: true, force: true });
await mkdir(join(DIST, 'lib'), { recursive: true });

const page = await readFile(join(ROOT, 'src', 'ui', 'index.html'), 'utf8');
if (!page.includes(META)) throw new Error('index.html: mf-settings-api meta missing');
await writeFile(join(DIST, 'index.html'), page.replace(META, `<meta name="mf-settings-api" content="${apiOrigin ? api : ''}">`), 'utf8');

await copyFile(join(ROOT, 'src', 'ui', 'favicon.jpg'), join(DIST, 'favicon.jpg'));
const modules = (await readdir(LIB)).filter((f) => f.endsWith('.js'));
for (const f of modules) await copyFile(join(LIB, f), join(DIST, 'lib', f));
await writeFile(join(DIST, '_headers'), HEADERS, 'utf8');

process.stdout.write(`Built dist/: index.html, favicon.jpg, _headers, lib/${modules.join(', lib/')}\n`);
process.stdout.write(api ? `Settings API: ${apiOrigin}\n` : 'Settings API: not set (no Settings button; connect-src none)\n');
