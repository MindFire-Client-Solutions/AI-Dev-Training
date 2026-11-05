// Builds dist/ for Cloudflare Pages: the page, the browser modules it imports,
// and a _headers file. No dependencies, no bundling: the files are copied as-is.
//
//   npm run build   -> dist/
//
// Cloudflare Pages settings: root directory `event-management`,
// build command `npm run build`, output directory `dist`.

import { copyFile, mkdir, readdir, rm, writeFile } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const DIST = join(ROOT, 'dist');
const LIB = join(ROOT, 'src', 'lib');

// Public link, but keep it out of search engines and lock the page down:
// it loads only its own scripts and Google Fonts, and sends data nowhere.
const HEADERS = `/*
  X-Robots-Tag: noindex, nofollow
  X-Content-Type-Options: nosniff
  X-Frame-Options: DENY
  Referrer-Policy: no-referrer
  Permissions-Policy: camera=(), microphone=(), geolocation=()
  Content-Security-Policy: default-src 'none'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data:; connect-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'
`;

await rm(DIST, { recursive: true, force: true });
await mkdir(join(DIST, 'lib'), { recursive: true });
await copyFile(join(ROOT, 'src', 'ui', 'index.html'), join(DIST, 'index.html'));
const modules = (await readdir(LIB)).filter((f) => f.endsWith('.js'));
for (const f of modules) await copyFile(join(LIB, f), join(DIST, 'lib', f));
await writeFile(join(DIST, '_headers'), HEADERS, 'utf8');

process.stdout.write(`Built dist/: index.html, _headers, lib/${modules.join(', lib/')}\n`);
