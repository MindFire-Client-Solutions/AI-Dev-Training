/**
 * Tier 2 — simulated assembly.
 *
 * The DV platform appends Standard.html to a template at publish time: the
 * <head> part before </head>, the <body> part before </body>. This reproduces
 * that locally so we can test the page that will actually be published, not
 * just the template on its own.
 *
 * It is an approximation. Validate it once against a real published page
 * before trusting it (see docs/001, section 8).
 *
 * Tokens are replaced with values from tokens/local.json purely so the page
 * renders sensibly for screenshots. Real substitution only happens at publish.
 */
import { readFileSync, writeFileSync, mkdirSync, existsSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const read = (p) => readFileSync(join(root, p), 'utf8');

const TEMPLATE = process.env.MF_TEMPLATE || 'src/ATR21-Template-Bootstrap5.html';
const STANDARD = process.env.MF_STANDARD || 'src/Standard.html';

// --- pull the two halves out of Standard.html -------------------------------
function splitStandard(html) {
  const head = html.match(/<head[^>]*>([\s\S]*?)<\/head>/i);
  const body = html.match(/<body[^>]*>([\s\S]*?)<\/body>/i);
  if (!head || !body) {
    throw new Error('Standard.html: expected both a <head> and a <body> block');
  }
  return { head: head[1], body: body[1] };
}

// --- token substitution, local only -----------------------------------------
function substitute(html, tokens) {
  let unresolved = new Set();

  // ##name default='value'##   and   ##name##
  html = html.replace(/##([A-Za-z0-9_]+)(?:\s+default='([^']*)')?##/g, (m, name, def) => {
    if (name in tokens) return tokens[name];
    if (def !== undefined) return def;
    unresolved.add(name);
    return '';
  });

  // {{name}} and {{RAW name}}
  html = html.replace(/\{\{\s*(?:RAW\s+)?([A-Za-z0-9_]+)\s*\}\}/g, (m, name) => {
    if (name in tokens) return tokens[name];
    unresolved.add(name);
    return '';
  });

  return { html, unresolved: [...unresolved].sort() };
}

const template = read(TEMPLATE);
const standard = read(STANDARD);
const tokens = existsSync(join(root, 'tokens/local.json'))
  ? JSON.parse(read('tokens/local.json'))
  : {};

const { head, body } = splitStandard(standard);

let out = template;
if (!/<\/head>/i.test(out)) throw new Error(`${TEMPLATE}: no </head> found`);
if (!/<\/body>/i.test(out)) throw new Error(`${TEMPLATE}: no </body> found`);

out = out.replace(/<\/head>/i, `${head}\n</head>`);
out = out.replace(/<\/body>/i, `${body}\n</body>`);

const { html, unresolved } = substitute(out, tokens);

mkdirSync(join(root, 'dist'), { recursive: true });
writeFileSync(join(root, 'dist/assembled.html'), html);

console.log(`assembled  ${TEMPLATE} + ${STANDARD}  ->  dist/assembled.html`);
if (unresolved.length) {
  console.log(`\n${unresolved.length} token(s) with no local value (blank in the render):`);
  for (const t of unresolved) console.log(`  ${t}`);
  console.log('\nAdd them to tokens/local.json if they affect how the page looks.');
}
