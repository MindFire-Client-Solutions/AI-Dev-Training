import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createApp, publicSettings } from '../lambda/app.js';
import { parseCsv } from '../src/lib/csv.js';
import { contactsCsv, sourceEventsCsv } from './fixtures.js';

const NOW = Date.parse('2026-11-09T06:00:00.000Z');
const KEY = 'test-admin-key-123456';
const ORIGIN = 'https://demo.example.test';
const SETTINGS = {
  contactsUrl: 'https://files.example.test/demo/contacts.csv',
  eventsUrl: 'https://files.example.test/demo/agentConversationEvents_99999.csv',
  sftp: { host: 'sftp.example.test', port: 22, username: 'demo', password: 'secret-pw', folder: '/incoming/demo/' },
};

function setup({ settings = SETTINGS, files, sftpFails = false } = {}) {
  const state = { settings: structuredClone(settings), puts: [], connects: [], ended: 0, logs: [] };
  const body = files ?? { [SETTINGS.contactsUrl]: contactsCsv(), [SETTINGS.eventsUrl]: sourceEventsCsv() };
  const app = createApp({
    env: { ADMIN_KEY: KEY, ALLOWED_ORIGIN: ORIGIN },
    now: () => NOW,
    log: (l) => state.logs.push(l),
    loadSettings: async () => structuredClone(state.settings),
    saveSettings: async (s) => { state.settings = structuredClone(s); },
    fetchText: async (url) => {
      if (!(url in body)) throw new Error(`Could not download ${url}: HTTP 404`);
      return body[url];
    },
    sftpConnect: async (cfg) => {
      state.connects.push(cfg);
      if (sftpFails) throw new Error('All configured authentication methods failed');
      return {
        put: async (text, path) => { state.puts.push({ text, path }); },
        list: async () => [{ name: 'a.csv' }, { name: 'b.csv' }],
        end: async () => { state.ended += 1; },
      };
    },
  });
  return { app, state };
}

const http = (method, path, { key = KEY, body } = {}) => ({
  rawPath: path,
  requestContext: { http: { method } },
  headers: key == null ? {} : { 'X-Admin-Key': key },
  body: body === undefined ? undefined : JSON.stringify(body),
});
const json = (res) => JSON.parse(res.body);

test('scheduled run uploads one valid CSV with a lowercase header and dates in the last 7 days', async () => {
  const { app, state } = setup();
  const out = await app.handler({ source: 'aws.scheduler' });
  assert.equal(state.puts.length, 1);
  assert.equal(out.uploaded, '/incoming/demo/agentConversationEvents_99999_generated_20261109-060000.csv');
  assert.equal(state.puts[0].path, out.uploaded);
  assert.deepEqual(state.connects[0], { host: 'sftp.example.test', port: 22, username: 'demo', password: 'secret-pw', readyTimeout: 20000 });
  assert.equal(state.ended, 1);

  const { header, records } = parseCsv(state.puts[0].text);
  assert.ok(header.every((c) => c === c.toLowerCase()));
  for (const r of records) {
    const t = Date.parse(r.event_date);
    assert.ok(t >= NOW - 7 * 86400_000 && t <= NOW, r.event_date);
  }
  assert.ok(state.logs.some((l) => l.startsWith('Uploaded /incoming/demo/') && l.includes('Rows:')));
});

test('a broken input link uploads nothing and fails the invocation', async () => {
  const { app, state } = setup({ files: { [SETTINGS.contactsUrl]: contactsCsv() } });
  await assert.rejects(app.handler({}), /HTTP 404/);
  assert.equal(state.puts.length, 0);
  assert.equal(state.connects.length, 0);
});

test('a failed validation rule uploads nothing', async () => {
  // Events file name says account 12345, but its acctID column is 99999.
  const settings = { ...SETTINGS, eventsUrl: 'https://files.example.test/demo/agentConversationEvents_12345.csv' };
  const files = { [SETTINGS.contactsUrl]: contactsCsv(), [settings.eventsUrl]: sourceEventsCsv() };
  const { app, state } = setup({ settings, files });
  await assert.rejects(app.handler({}), /account 12345/);
  assert.equal(state.puts.length, 0);
});

test('wrong SFTP credentials fail the invocation and upload nothing', async () => {
  const { app, state } = setup({ sftpFails: true });
  await assert.rejects(app.handler({}), /authentication/);
  assert.equal(state.puts.length, 0);
});

test('incomplete settings fail with the missing fields named', async () => {
  const { app } = setup({ settings: { contactsUrl: 'http://insecure.test/c.csv' } });
  await assert.rejects(app.handler({}), /contactsUrl must be an https:\/\/ link.*sftp\.host is required/s);
});

test('every Settings API request needs the admin key', async () => {
  const { app } = setup();
  for (const [m, p] of [['GET', '/settings'], ['PUT', '/settings'], ['POST', '/run'], ['POST', '/test-sftp']]) {
    assert.equal((await app.handler(http(m, p, { key: null }))).statusCode, 401, `${m} ${p} without key`);
    assert.equal((await app.handler(http(m, p, { key: 'wrong-key-wrong-key-1' }))).statusCode, 401, `${m} ${p} wrong key`);
  }
});

test('a short ADMIN_KEY is never accepted', async () => {
  const app = createApp({ env: { ADMIN_KEY: 'short', ALLOWED_ORIGIN: ORIGIN }, loadSettings: async () => SETTINGS });
  assert.equal((await app.handler(http('GET', '/settings', { key: 'short' }))).statusCode, 401);
});

test('GET /settings never returns the password', async () => {
  const { app } = setup();
  const res = await app.handler(http('GET', '/settings'));
  assert.equal(res.statusCode, 200);
  assert.ok(!res.body.includes('secret-pw'));
  assert.equal(json(res).passwordSet, true);
  assert.equal(res.headers['Access-Control-Allow-Origin'], ORIGIN);
});

test('saving with a blank password keeps the current password', async () => {
  const { app, state } = setup();
  const body = { ...publicSettings(SETTINGS), sftp: { ...publicSettings(SETTINGS).sftp, folder: '/new', password: '' } };
  const res = await app.handler(http('PUT', '/settings', { body }));
  assert.equal(res.statusCode, 200);
  assert.equal(state.settings.sftp.password, 'secret-pw');
  assert.equal(state.settings.sftp.folder, '/new');
  assert.ok(!res.body.includes('secret-pw'));

  await app.handler(http('PUT', '/settings', { body: { ...body, sftp: { ...body.sftp, password: 'new-pw' } } }));
  assert.equal(state.settings.sftp.password, 'new-pw');
});

test('saving invalid settings is refused and changes nothing', async () => {
  const { app, state } = setup();
  const res = await app.handler(http('PUT', '/settings', { body: { contactsUrl: 'ftp://x', eventsUrl: '', sftp: { port: 99999 } } }));
  assert.equal(res.statusCode, 400);
  assert.match(json(res).error, /contactsUrl.*port/s);
  assert.deepEqual(state.settings, SETTINGS);
});

test('Run now and Test SFTP work through the API', async () => {
  const { app, state } = setup();
  const run = await app.handler(http('POST', '/run'));
  assert.equal(run.statusCode, 200);
  assert.match(json(run).fileName, /^agentConversationEvents_99999_generated_\d{8}-\d{6}\.csv$/);
  const t = await app.handler(http('POST', '/test-sftp'));
  assert.deepEqual(json(t), { ok: true, folder: '/incoming/demo/', entries: 2 });
  assert.equal(state.puts.length, 1, 'test-sftp uploads nothing');

  const bad = setup({ sftpFails: true });
  const failed = await bad.app.handler(http('POST', '/test-sftp'));
  assert.equal(failed.statusCode, 502);
  assert.match(json(failed).error, /authentication/);
});

test('browser preflight gets CORS headers for the Pages origin only', async () => {
  const { app } = setup();
  const res = await app.handler(http('OPTIONS', '/settings', { key: null }));
  assert.equal(res.statusCode, 204);
  assert.equal(res.headers['Access-Control-Allow-Origin'], ORIGIN);
  assert.match(res.headers['Access-Control-Allow-Headers'], /x-admin-key/);
});
