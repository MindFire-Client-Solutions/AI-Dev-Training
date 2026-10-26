/**
 * Tier 1 — source checks. Instant, no browser.
 *
 * Node rather than grep, so it behaves the same on Windows and Mac.
 * Every rule here is in CLAUDE.md. Change one, change both.
 *
 *   npm run check:forbidden            fail on anything not in the baseline
 *   npm run check:forbidden -- --update   record current state as the baseline
 *
 * The baseline exists so we can switch the gate on today, against code that
 * does not pass yet. Known problems are recorded; the check then fails only on
 * NEW ones. The baseline is meant to shrink and never to grow.
 */
import { readdirSync, readFileSync, writeFileSync, statSync, existsSync } from 'node:fs';
import { join, dirname, relative, extname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const BASELINE = join(root, '.forbidden-baseline.json');
const UPDATE = process.argv.includes('--update');
const SCAN = ['src'];
const EXT = new Set(['.html', '.js', '.mjs', '.css']);

const RULES = [
  { id: 'jquery',      re: /jquery/i,            msg: 'jQuery. CLAUDE.md: not in this repo.' },
  { id: 'jq-selector', re: /\$\(/,               msg: 'jQuery-style $( selector.' },
  { id: 'sweetalert',  re: /sweetalert2|swal2-/, msg: 'sweetalert2. Not used by these templates.' },
  { id: 'dvtracking',  re: /dvtracking/i,        msg: 'Retired tracking script.' },
  { id: 'console',     re: /console\.log\s*\(/,  msg: 'console.log in a published page.' },
  { id: 'important',   re: /!important/,         msg: '!important with no explanation on the same line.',
                       skip: (l) => /\/\*[\s\S]*\*\/|\/\//.test(l) },
  { id: 'bs3-class',   re: /\b(form-group|btn-block|input-group-addon)\b/, msg: 'Bootstrap 3 class.' },
  { id: 'orphan-row',  re: /<(tr|td)\b/i,        msg: '<tr>/<td> — email markup is converted, not pasted.',
                       fileCheck: (t) => !/<table\b/i.test(t) },
];

const walk = (dir, out = []) => {
  let entries; try { entries = readdirSync(dir); } catch { return out; }
  for (const e of entries) {
    const p = join(dir, e);
    statSync(p).isDirectory() ? walk(p, out) : EXT.has(extname(p)) && out.push(p);
  }
  return out;
};

const files = SCAN.flatMap((d) => walk(join(root, d)));
if (!files.length) { console.error(`No files under ${SCAN.join(', ')}/`); process.exit(1); }

// Key on file + rule + the line's text, not its number — line numbers move.
const found = [];
for (const file of files) {
  const text = readFileSync(file, 'utf8');
  const lines = text.split(/\r?\n/);
  for (const rule of RULES) {
    if (rule.fileCheck && !rule.fileCheck(text)) continue;
    lines.forEach((line, i) => {
      if (!rule.re.test(line) || (rule.skip && rule.skip(line))) return;
      found.push({
        file: relative(root, file).replace(/\\/g, '/'),
        rule: rule.id,
        msg: rule.msg,
        line: i + 1,
        text: line.trim(),
      });
    });
  }
}

const key = (h) => `${h.file}::${h.rule}::${h.text}`;

if (UPDATE) {
  const entries = found.map(key).sort();
  writeFileSync(BASELINE, JSON.stringify({
    note: 'Known violations, recorded so the gate can run on legacy code. This file should only ever shrink.',
    recorded: new Date().toISOString().slice(0, 10),
    entries,
  }, null, 2) + '\n');
  console.log(`Baseline written: ${entries.length} known violation(s).`);
  process.exit(0);
}

const baseline = existsSync(BASELINE)
  ? new Set(JSON.parse(readFileSync(BASELINE, 'utf8')).entries)
  : new Set();

const fresh = found.filter((h) => !baseline.has(key(h)));
const stillThere = new Set(found.map(key));
const fixed = [...baseline].filter((k) => !stillThere.has(k));

if (fixed.length) {
  console.log(`${fixed.length} baselined violation(s) now fixed:`);
  for (const k of fixed) console.log(`  - ${k.split('::')[0]}  [${k.split('::')[1]}]`);
  console.log('Run  npm run check:forbidden -- --update  to shrink the baseline.\n');
}

if (fresh.length) {
  for (const h of fresh) {
    console.log(`${h.file}:${h.line}  [${h.rule}]  ${h.msg}`);
    console.log(`    ${h.text.slice(0, 100)}`);
  }
  console.error(`\nFAIL  ${fresh.length} new violation(s).`);
  console.error('Each is a rule in CLAUDE.md. If the rule is wrong, change the rule — do not work around it.');
  process.exit(1);
}

console.log(`PASS  ${files.length} file(s), ${baseline.size} known violation(s) still baselined.`);
