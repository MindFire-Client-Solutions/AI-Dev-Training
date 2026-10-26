/**
 * Pre-work item 5. Proves this machine can run everything before Session 1.
 * Run:  npm run smoke
 */
import { execSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const checks = [];
const ok   = (n, d) => checks.push({ n, pass: true,  d });
const fail = (n, d) => checks.push({ n, pass: false, d });

function run(cmd) { return execSync(cmd, { cwd: root, stdio: 'pipe' }).toString().trim(); }

// 1. Node
const major = Number(process.versions.node.split('.')[0]);
major >= 18 ? ok('Node', `v${process.versions.node}`)
            : fail('Node', `v${process.versions.node} — need 18 or newer`);

// 2. git
try { ok('git', run('git --version')); }
catch { fail('git', 'not found on PATH'); }

// 3. dependencies
existsSync(join(root, 'node_modules'))
  ? ok('Dependencies', 'node_modules present')
  : fail('Dependencies', 'run: npm install');

// 4. Playwright browser
try { run('npx playwright --version'); ok('Playwright', 'installed'); }
catch { fail('Playwright', 'run: npx playwright install chromium'); }

// 5. assembly
try {
  run('node scripts/assemble.mjs');
  existsSync(join(root, 'dist/assembled.html'))
    ? ok('Assembly', 'dist/assembled.html built')
    : fail('Assembly', 'ran but produced no output');
} catch (e) {
  fail('Assembly', String(e.stderr || e.message).split('\n')[0]);
}

// 6. forbidden-pattern check runs (pass or fail is fine — it just has to work)
try { run('node scripts/check-forbidden.mjs'); ok('Source checks', 'clean'); }
catch (e) {
  const out = String(e.stdout || '');
  out.includes('forbidden pattern')
    ? ok('Source checks', 'ran, found issues — expected before the pilot')
    : fail('Source checks', 'did not run');
}

const width = Math.max(...checks.map((c) => c.n.length));
console.log('');
for (const c of checks) {
  console.log(`  ${c.pass ? 'ok  ' : 'FAIL'}  ${c.n.padEnd(width)}   ${c.d}`);
}

const failed = checks.filter((c) => !c.pass);
console.log('');
if (failed.length) {
  console.error(`${failed.length} check(s) failed. Fix these before Session 1 — not during it.`);
  process.exit(1);
}
console.log('All checks passed. You are ready for Session 1.');
