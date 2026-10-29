/**
 * Tier 2 — the assembled page in a browser.
 *
 * Run `npm run assemble` first (npm run verify does it for you).
 * Baselines: `npm run baseline`.
 *
 * These assertions come from docs/001 section 8. When a spec needs a different
 * assertion, add it here rather than checking by eye.
 */
import { test, expect } from '@playwright/test';
import { pathToFileURL } from 'node:url';
import { join } from 'node:path';
import { readFileSync } from 'node:fs';

const PAGE = pathToFileURL(join(process.cwd(), 'dist/assembled.html')).href;

/**
 * Scripts and styles that must never be requested -- once the spec that removes
 * them has run.
 *
 * Legacy code still loads some of these, so the gate reads
 * `.forbidden-baseline.json` the same way `scripts/check-forbidden.mjs` does: a
 * violation is tolerated only while the baseline still admits it, and reported
 * so it is never silent. The baseline only ever shrinks, so this only ever
 * tightens -- when spec 001 drops `jquery` from the baseline, this test starts
 * enforcing it with no edit here.
 *
 * `kinds` are the category field of a baseline entry: src/file::KIND::line.
 */
const baselinedKinds = new Set(
  JSON.parse(readFileSync(join(process.cwd(), '.forbidden-baseline.json'), 'utf8'))
    .entries.map((e) => e.split('::')[1]),
);

const FORBIDDEN_REQUESTS = [
  { pattern: /jquery/i, kinds: ['jquery', 'jq-selector'] },
  { pattern: /sweetalert2/i, kinds: ['sweetalert'] },
  // The URL is built from a token, so the endpoint filename is not dependable.
  // Match the host too.
  { pattern: /dvtracking|track\.my-dv\.com/i, kinds: ['dvtracking'] },
];

/** Requests we expect to see. */
const EXPECTED_REQUESTS = [/bootstrap@5/i, /lead2\.js/i, /pixel-v030\.js/i];

async function load(page) {
  const requests = [];
  const consoleErrors = [];
  const pageErrors = [];

  page.on('request', (r) => requests.push(r.url()));
  page.on('console', (m) => { if (m.type() === 'error') consoleErrors.push(m.text()); });
  page.on('pageerror', (e) => pageErrors.push(e.message));

  await page.goto(PAGE, { waitUntil: 'load' });
  // Give deferred and module scripts a moment to run and fail.
  await page.waitForTimeout(500);

  return { requests, consoleErrors, pageErrors };
}

test('no console errors and no uncaught exceptions', async ({ page }) => {
  const { consoleErrors, pageErrors } = await load(page);
  expect(pageErrors, `uncaught: ${pageErrors.join(' | ')}`).toEqual([]);
  // External scripts may 404 locally; only fail on errors the page itself threw.
  const ours = consoleErrors.filter((e) => !/Failed to load resource/i.test(e));
  expect(ours, `console: ${ours.join(' | ')}`).toEqual([]);
});

test('forbidden scripts are not requested', async ({ page }) => {
  const { requests } = await load(page);
  const stillBaselined = [];

  for (const { pattern, kinds } of FORBIDDEN_REQUESTS) {
    const hit = requests.filter((u) => pattern.test(u));
    if (hit.length === 0) continue;

    if (kinds.some((k) => baselinedKinds.has(k))) {
      stillBaselined.push(`${pattern} (${hit.length} request(s))`);
      continue;
    }
    expect(hit, `${pattern} should not be requested, got: ${hit.join(', ')}`).toEqual([]);
  }

  if (stillBaselined.length) {
    console.log(
      `  known legacy, still in .forbidden-baseline.json: ${stillBaselined.join(', ')}`,
    );
  }
});

test('expected scripts are still requested', async ({ page }) => {
  const { requests } = await load(page);
  for (const pattern of EXPECTED_REQUESTS) {
    expect(requests.some((u) => pattern.test(u)), `${pattern} missing`).toBe(true);
  }
});

test('no duplicate element ids', async ({ page }) => {
  await load(page);
  const dupes = await page.evaluate(() => {
    const seen = new Map();
    for (const el of document.querySelectorAll('[id]')) {
      seen.set(el.id, (seen.get(el.id) || 0) + 1);
    }
    return [...seen].filter(([, n]) => n > 1).map(([id, n]) => `${id} x${n}`);
  });
  expect(dupes, `duplicate ids: ${dupes.join(', ')}`).toEqual([]);
});

test('no table rows outside a table', async ({ page }) => {
  await load(page);
  const orphans = await page.evaluate(() =>
    [...document.querySelectorAll('tr, td')].filter((el) => !el.closest('table')).length
  );
  expect(orphans, 'table markup must be converted, not pasted').toBe(0);
});

test('tracking entry point still fires', async ({ page }) => {
  // This test checks one thing: does the template still call
  // AddEventOnLoadPage?
  //
  // We cannot watch the real function. It sends data to a server and
  // leaves nothing on the page for us to check.
  //
  // We cannot install our own copy first. lead2.js loads after us and
  // replaces it.
  //
  // We cannot block lead2.js. It also defines clearifyPage, and the
  // template calls that one first.
  //
  // So we replace lead2.js with a small test version. It defines both
  // functions. Ours records each call in window.__mfCalls, and nothing
  // is sent to the server.
  await page.route(/lead2\.js/, (route) => route.fulfill({
    contentType: 'application/javascript',
    body: `window.__mfCalls = [];
           function clearifyPage(page, purl) { return page; }
           function AddEventOnLoadPage(...args) { window.__mfCalls.push(args); }`,
  }));

  await load(page);

  const calls = await page.evaluate(() => window.__mfCalls || []);
  expect(calls.length, 'AddEventOnLoadPage was not called').toBeGreaterThan(0);
});

test('page matches baseline', async ({ page }) => {
  await load(page);
  await expect(page).toHaveScreenshot('assembled.png', { fullPage: true });
});
