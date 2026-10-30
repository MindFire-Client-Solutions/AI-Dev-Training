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

const PAGE = pathToFileURL(join(process.cwd(), 'dist/assembled.html')).href;

/** Scripts and styles that must never be requested. */
const FORBIDDEN_REQUESTS = [/jquery/i, /sweetalert2/i, /dvtracking/i];

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
  for (const pattern of FORBIDDEN_REQUESTS) {
    const hit = requests.filter((u) => pattern.test(u));
    expect(hit, `${pattern} should not be requested, got: ${hit.join(', ')}`).toEqual([]);
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
  // AddEventOnLoadPage is the call the template makes on load. Removing jQuery
  // must not change whether it runs, or its arguments.
  await page.addInitScript(() => {
    window.__mfCalls = [];
    Object.defineProperty(window, 'AddEventOnLoadPage', {
      configurable: true,
      writable: true,
      value: (...args) => window.__mfCalls.push(args),
    });
  });
  await load(page);
  const calls = await page.evaluate(() => window.__mfCalls || []);
  expect(calls.length, 'AddEventOnLoadPage was not called').toBeGreaterThan(0);
});

test('page matches baseline', async ({ page }) => {
  await load(page);
  await expect(page).toHaveScreenshot('assembled.png', { fullPage: true });
});
