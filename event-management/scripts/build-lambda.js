// Packages the weekly SFTP run Lambda as a zip to upload in the AWS console.
//
//   npm run build:lambda   -> lambda-dist/mf-event-generator-lambda.zip
//
// Zip layout (Lambda handler setting: lambda/index.handler):
//   package.json        from lambda/package.json ("type": "module")
//   lambda/*.js         handler
//   src/lib/*.js        the same generator and validator the UI uses
//   node_modules/       ssh2-sftp-client and its dependencies (from lambda/package-lock.json)
//
// Native add-ons are skipped (--ignore-scripts, --omit=optional): ssh2 falls back to pure
// JavaScript, so a zip built on Windows runs on Lambda's Linux.

import { execSync } from 'node:child_process';
import { copyFile, mkdir, readdir, rm, stat } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const OUT = join(ROOT, 'lambda-dist');
const PKG = join(OUT, 'pkg');
const ZIP = join(OUT, 'mf-event-generator-lambda.zip');

await rm(OUT, { recursive: true, force: true });
await mkdir(join(PKG, 'lambda'), { recursive: true });
await mkdir(join(PKG, 'src', 'lib'), { recursive: true });

for (const f of ['app.js', 'index.js']) await copyFile(join(ROOT, 'lambda', f), join(PKG, 'lambda', f));
for (const f of ['package.json', 'package-lock.json']) await copyFile(join(ROOT, 'lambda', f), join(PKG, f));
for (const f of (await readdir(join(ROOT, 'src', 'lib'))).filter((n) => n.endsWith('.js'))) {
  await copyFile(join(ROOT, 'src', 'lib', f), join(PKG, 'src', 'lib', f));
}

const run = (cmd) => execSync(cmd, { cwd: PKG, stdio: 'inherit', shell: true });
run('npm ci --omit=dev --omit=optional --ignore-scripts --no-audit --no-fund');

const entries = 'package.json lambda src node_modules';
// Windows: use its built-in bsdtar (writes zip with -a), not Git's GNU tar, which may be first on PATH.
// The zip path is relative so "C:" is never read as a remote host.
if (process.platform === 'win32') {
  run(`"${join(process.env.SystemRoot ?? 'C:\\Windows', 'System32', 'tar.exe')}" -a -c -f ../mf-event-generator-lambda.zip ${entries}`);
} else {
  run(`zip -qr ../mf-event-generator-lambda.zip ${entries}`);
}

const { size } = await stat(ZIP);
process.stdout.write(`\nBuilt ${ZIP} (${(size / 1024 / 1024).toFixed(1)} MB)\nLambda handler: lambda/index.handler\n`);
