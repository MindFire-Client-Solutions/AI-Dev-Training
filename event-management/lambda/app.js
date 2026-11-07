// Weekly scheduled run and Settings API (spec: docs/001-scheduled-sftp-run.md).
//
// Everything that talks to the outside world is passed in, so tests run with fakes:
//   loadSettings()        -> settings object from Secrets Manager
//   saveSettings(obj)     -> writes it back
//   fetchText(url)        -> body text (throws on non-200)
//   sftpConnect(cfg)      -> { put(text, remotePath), list(folder), end() }
//   env                   -> { ADMIN_KEY, ALLOWED_ORIGIN }
//   now()                 -> ms since epoch
//   log(line)             -> CloudWatch

import { timingSafeEqual } from 'node:crypto';
import { generate } from '../src/lib/generator.js';
import { formatSummary } from '../src/lib/summary.js';

const OUTPUT_NAME = (acctID, ms) => {
  const d = new Date(ms).toISOString();
  return `agentConversationEvents_${acctID}_generated_${d.slice(0, 10).replaceAll('-', '')}-${d.slice(11, 19).replaceAll(':', '')}.csv`;
};

export class SettingsError extends Error {}

// Checks settings before a run or a save. Returns the list of problems.
export function settingsProblems(s, { needPassword = true } = {}) {
  const problems = [];
  const https = (v) => { try { return new URL(v).protocol === 'https:'; } catch { return false; } };
  if (!https(s?.contactsUrl)) problems.push('contactsUrl must be an https:// link');
  if (!https(s?.eventsUrl)) problems.push('eventsUrl must be an https:// link');
  const f = s?.sftp ?? {};
  if (!String(f.host ?? '').trim()) problems.push('sftp.host is required');
  const port = Number(f.port);
  if (!Number.isInteger(port) || port < 1 || port > 65535) problems.push('sftp.port must be 1-65535');
  if (!String(f.username ?? '').trim()) problems.push('sftp.username is required');
  if (needPassword && !f.password) problems.push('sftp.password is required');
  if (!String(f.folder ?? '').trim()) problems.push('sftp.folder is required');
  return problems;
}

// Settings as the UI may see them: never the password.
export function publicSettings(s) {
  const f = s?.sftp ?? {};
  return {
    contactsUrl: s?.contactsUrl ?? '',
    eventsUrl: s?.eventsUrl ?? '',
    sftp: { host: f.host ?? '', port: f.port ?? 22, username: f.username ?? '', folder: f.folder ?? '' },
    passwordSet: Boolean(f.password),
  };
}

function sftpConfig(f) {
  return { host: f.host.trim(), port: Number(f.port), username: f.username.trim(), password: f.password, readyTimeout: 20000 };
}

function remotePath(folder, name) {
  return `${folder.trim().replace(/\/+$/, '')}/${name}`;
}

export function createApp({ loadSettings, saveSettings, fetchText, sftpConnect, env, now = Date.now, log = console.log }) {
  // §5.2: fetch inputs, generate, validate, upload. Throws on any failure; uploads nothing then.
  async function run() {
    const settings = await loadSettings();
    const problems = settingsProblems(settings);
    if (problems.length) throw new SettingsError(`Settings incomplete: ${problems.join('; ')}`);

    const [contactsCsv, eventsCsv] = await Promise.all([fetchText(settings.contactsUrl), fetchText(settings.eventsUrl)]);
    const eventsFileName = decodeURIComponent(new URL(settings.eventsUrl).pathname.split('/').pop() || '');
    const at = now();
    const result = generate({ contactsCsv, eventsCsv, eventsFileName, now: at });
    const summaryText = formatSummary(result.summary);

    if (!result.validation.ok) {
      const failed = result.validation.checks.filter((c) => !c.ok)
        .map((c) => `${c.label}: ${c.examples.map((e) => (e.row ? `row ${e.row}: ` : '') + e.message).join(' | ')}`);
      throw new Error(`Validation failed, nothing uploaded.\n${failed.join('\n')}`);
    }

    const fileName = OUTPUT_NAME(result.acctID, at);
    const path = remotePath(settings.sftp.folder, fileName);
    const sftp = await sftpConnect(sftpConfig(settings.sftp));
    try {
      await sftp.put(result.csv, path);
    } finally {
      await sftp.end();
    }
    log(`Uploaded ${path}\n${summaryText}${result.notes.length ? `\nNotes: ${result.notes.join(' ')}` : ''}`);
    return { uploaded: path, fileName, summary: result.summary, summaryText, notes: result.notes };
  }

  async function testSftp() {
    const settings = await loadSettings();
    const problems = settingsProblems(settings).filter((p) => p.startsWith('sftp'));
    if (problems.length) throw new SettingsError(`Settings incomplete: ${problems.join('; ')}`);
    const sftp = await sftpConnect(sftpConfig(settings.sftp));
    try {
      const entries = await sftp.list(settings.sftp.folder.trim());
      return { ok: true, folder: settings.sftp.folder.trim(), entries: entries.length };
    } finally {
      await sftp.end();
    }
  }

  async function putSettings(body) {
    const current = (await loadSettings()) ?? {};
    const incoming = body ?? {};
    const sftp = { ...(incoming.sftp ?? {}) };
    // A blank or missing password keeps the current one.
    sftp.password = sftp.password ? String(sftp.password) : current.sftp?.password ?? '';
    const next = {
      contactsUrl: String(incoming.contactsUrl ?? '').trim(),
      eventsUrl: String(incoming.eventsUrl ?? '').trim(),
      sftp: {
        host: String(sftp.host ?? '').trim(), port: Number(sftp.port ?? 22),
        username: String(sftp.username ?? '').trim(), password: sftp.password,
        folder: String(sftp.folder ?? '').trim(),
      },
    };
    const problems = settingsProblems(next, { needPassword: false });
    if (problems.length) throw new SettingsError(problems.join('; '));
    await saveSettings(next);
    return publicSettings(next);
  }

  // ---- HTTP (Lambda Function URL) -------------------------------------------
  const cors = () => ({
    'Access-Control-Allow-Origin': env.ALLOWED_ORIGIN,
    'Access-Control-Allow-Methods': 'GET, PUT, POST, OPTIONS',
    'Access-Control-Allow-Headers': 'content-type, x-admin-key',
    'Access-Control-Max-Age': '600',
    Vary: 'Origin',
  });
  const reply = (statusCode, body) => ({
    statusCode,
    headers: { 'Content-Type': 'application/json; charset=utf-8', 'Cache-Control': 'no-store', ...cors() },
    body: JSON.stringify(body),
  });

  function keyMatches(given) {
    const want = Buffer.from(String(env.ADMIN_KEY ?? ''));
    const got = Buffer.from(String(given ?? ''));
    return want.length >= 12 && got.length === want.length && timingSafeEqual(got, want);
  }

  async function http(event) {
    const method = event.requestContext.http.method;
    const path = event.rawPath.replace(/\/+$/, '') || '/';
    if (method === 'OPTIONS') return { statusCode: 204, headers: cors() };

    const headers = Object.fromEntries(Object.entries(event.headers ?? {}).map(([k, v]) => [k.toLowerCase(), v]));
    if (!keyMatches(headers['x-admin-key'])) return reply(401, { error: 'Wrong or missing admin key' });

    let body = {};
    if (event.body) {
      try {
        body = JSON.parse(event.isBase64Encoded ? Buffer.from(event.body, 'base64').toString('utf8') : event.body);
      } catch {
        return reply(400, { error: 'Body is not valid JSON' });
      }
    }

    try {
      if (method === 'GET' && path === '/settings') return reply(200, publicSettings(await loadSettings()));
      if (method === 'PUT' && path === '/settings') return reply(200, await putSettings(body));
      if (method === 'POST' && path === '/test-sftp') return reply(200, await testSftp());
      if (method === 'POST' && path === '/run') return reply(200, await run());
      return reply(404, { error: 'Not found' });
    } catch (err) {
      log(`ERROR ${method} ${path}: ${err.message}`);
      return reply(err instanceof SettingsError ? 400 : 502, { error: err.message });
    }
  }

  // EventBridge Scheduler invokes with its own payload (no requestContext): a scheduled run.
  // Errors are re-thrown so the invocation fails and CloudWatch / retries can act on it.
  async function handler(event) {
    if (event?.requestContext?.http) return http(event);
    try {
      return await run();
    } catch (err) {
      log(`ERROR scheduled run: ${err.message}`);
      throw err;
    }
  }

  return { handler, run, testSftp, putSettings };
}
