// Validates generated output against CLAUDE.md. Hard rules fail the run;
// funnel rates are reported as warnings because small inputs cannot hit them
// exactly. Runs in Node and in the browser.

import { CONFIG, EVENT_CODES } from './config.js';
import {
  COMPLETION_PAYLOAD_COLUMNS, FINGERPRINT_COLUMNS, contactBlockFor, contactColumnMap,
} from './schema.js';

const SEC_PRECISION = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.000Z$/;
const MS_PRECISION = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/;
const UUID = /\/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})\.[a-z0-9]+$/i;

// Every rule the validator knows, in display order.
export const RULES = {
  header: 'Header matches the events file exactly (same columns, same order)',
  codes: 'Only event codes 30500, 50531, 50532, 50533',
  appCode: 'app_code is the first three digits of event_code',
  contact: 'Every row belongs to a contact in contacts.csv, contact block copied verbatim',
  acctID: 'acctID matches the events file',
  dateFormat: '30500 dates end .000Z; 505xx dates carry milliseconds',
  dateWindow: 'Every event_date is within the date window (last 7 days by default, up to now)',
  fingerprint: '50532/50533 Browser, Device, OS, Platform are blank',
  ip: '505xx IP is blank; 30500 IP is "client, CDN hop"',
  payloadPlacement: 'Transcript, recording, tokens, audio_time only on 50532; data only on 50533',
  audioUuid: 'Transcript and recording share one UUID',
  tokens: 'tokens_used totals equal the sum of their detail fields',
  dataPayload: 'data is valid JSON; conversation_summary alternates, starting with assistant',
  pairing: 'Every 50532 follows a 50531 (gap ~ audio_time) and has exactly one 50533 within 1s',
};

export const WARNINGS = {
  requestRate: 'Requesting contacts are 20-25% of visiting contacts',
  completionRate: 'At least 95% of 50531 requests complete (50532)',
  requesterVisited: 'Every requesting contact also has a 30500 visit',
};

const MAX_EXAMPLES = 20;

export function validate({ header, rows, expectedHeader, contactsHeader, contacts, acctID, now, config = CONFIG }) {
  const failures = Object.fromEntries(Object.keys(RULES).map((k) => [k, { count: 0, examples: [] }]));
  const fail = (rule, rowIndex, message) => {
    const f = failures[rule];
    f.count++;
    if (f.examples.length < MAX_EXAMPLES) f.examples.push({ row: rowIndex == null ? null : rowIndex + 2, message });
  };

  // --- header ---------------------------------------------------------------
  if (header.length !== expectedHeader.length || header.some((h, i) => h !== expectedHeader[i])) {
    const diff = expectedHeader.map((h, i) => (header[i] === h ? null : `col ${i + 1}: expected "${h}", got "${header[i] ?? '(none)'}"`)).filter(Boolean);
    if (header.length > expectedHeader.length) diff.push(`${header.length - expectedHeader.length} extra column(s)`);
    for (const d of diff) fail('header', null, d);
  }

  const contactById = new Map(contacts.map((c) => [c._id, c]));
  const columnMap = contactColumnMap(expectedHeader, contactsHeader);
  const windowStart = now - config.windowDays * 86400_000;

  // --- per-row rules --------------------------------------------------------
  rows.forEach((r, i) => {
    const code = r.event_code;
    const is505 = code.startsWith('505');

    if (!EVENT_CODES.includes(code)) fail('codes', i, `event_code "${code}"`);
    if (r.app_code !== code.slice(0, 3)) fail('appCode', i, `app_code "${r.app_code}" for ${code}`);

    const contact = contactById.get(r._id);
    if (!contact) fail('contact', i, `_id ${r._id} is not in contacts.csv`);
    else {
      const expected = contactBlockFor(contact, columnMap);
      for (const [col, v] of Object.entries(expected)) {
        if ((r[col] ?? '') !== v) fail('contact', i, `${col} "${r[col]}" differs from contacts.csv "${v}"`);
      }
    }

    if (r.acctID !== acctID) fail('acctID', i, `acctID "${r.acctID}", expected "${acctID}"`);

    const re = code === '30500' ? SEC_PRECISION : MS_PRECISION;
    const t = Date.parse(r.event_date);
    if (!re.test(r.event_date) || Number.isNaN(t)) fail('dateFormat', i, `${code} event_date "${r.event_date}"`);
    else if (t < windowStart || t > now) fail('dateWindow', i, `${r.event_date} is outside the ${config.windowDays}-day window`);

    if (code === '50532' || code === '50533') {
      for (const col of FINGERPRINT_COLUMNS) if (r[col] !== '') fail('fingerprint', i, `${code} ${col} = "${r[col]}"`);
    }
    if (is505 && r.IP !== '') fail('ip', i, `${code} IP = "${r.IP}"`);
    if (code === '30500' && !/^[^,]+, [^,]+$/.test(r.IP)) fail('ip', i, `30500 IP "${r.IP}" is not "client, CDN hop"`);

    for (const col of COMPLETION_PAYLOAD_COLUMNS) {
      if (code === '50532' && r[col] === '') fail('payloadPlacement', i, `50532 has empty ${col}`);
      if (code !== '50532' && r[col] !== '') fail('payloadPlacement', i, `${code} has ${col}`);
    }
    if (code !== '50533' && r.data !== '') fail('payloadPlacement', i, `${code} has data`);

    if (code === '50532') {
      const a = UUID.exec(r.audio_transcript)?.[1];
      const b = UUID.exec(r.audio_recording)?.[1];
      if (!a || a !== b) fail('audioUuid', i, `transcript ${a ?? '(no uuid)'} vs recording ${b ?? '(no uuid)'}`);
      if (!(Number(r.audio_time) > 0)) fail('payloadPlacement', i, `audio_time "${r.audio_time}" is not a positive number`);
      checkTokens(r.tokens_used, (m) => fail('tokens', i, m));
    }
    if (code === '50533' && r.data !== '') checkData(r.data, (m) => fail('dataPayload', i, m));
  });

  // --- journey pairing, per contact ----------------------------------------
  const byContact = new Map();
  rows.forEach((r, i) => {
    if (!byContact.has(r._id)) byContact.set(r._id, []);
    byContact.get(r._id).push({ r, i, t: Date.parse(r.event_date) });
  });
  const slack = config.requestToCompleteSlackSeconds;
  for (const events of byContact.values()) {
    events.sort((a, b) => a.t - b.t);
    const openRequests = [];
    let openCompletion = null;
    for (const e of events) {
      const code = e.r.event_code;
      if (code === '50531') openRequests.push(e);
      else if (code === '50532') {
        if (openCompletion) fail('pairing', openCompletion.i, '50532 has no 50533');
        const req = openRequests.pop();
        if (!req) fail('pairing', e.i, '50532 has no preceding 50531');
        else {
          const gap = (e.t - req.t) / 1000 - Number(e.r.audio_time);
          if (gap < slack.min || gap > slack.max) {
            fail('pairing', e.i, `50531->50532 gap exceeds audio_time by ${gap.toFixed(1)}s (allowed ${slack.min}-${slack.max}s)`);
          }
        }
        openCompletion = e;
      } else if (code === '50533') {
        if (!openCompletion) fail('pairing', e.i, '50533 has no preceding 50532');
        else {
          const gap = (e.t - openCompletion.t) / 1000;
          if (gap < 0 || gap > config.completeToEvaluateMaxSeconds) fail('pairing', e.i, `50532->50533 gap ${gap.toFixed(3)}s`);
        }
        openCompletion = null;
      }
    }
    if (openCompletion) fail('pairing', openCompletion.i, '50532 has no 50533');
  }

  // --- funnel rates (warnings) ---------------------------------------------
  const visitors = new Set(rows.filter((r) => r.event_code === '30500').map((r) => r._id));
  const requesters = new Set(rows.filter((r) => r.event_code === '50531').map((r) => r._id));
  const requested = rows.filter((r) => r.event_code === '50531').length;
  const completed = rows.filter((r) => r.event_code === '50532').length;
  const warnings = [];
  const rr = visitors.size ? requesters.size / visitors.size : 0;
  warnings.push({
    rule: 'requestRate', label: WARNINGS.requestRate,
    ok: rr >= config.requestRate.min && rr <= config.requestRate.max,
    detail: `${requesters.size}/${visitors.size} = ${(rr * 100).toFixed(1)}%`,
  });
  const cr = requested ? completed / requested : 1;
  warnings.push({
    rule: 'completionRate', label: WARNINGS.completionRate,
    ok: cr >= 1 - config.abandonRate.max,
    detail: `${completed}/${requested} = ${(cr * 100).toFixed(1)}%`,
  });
  const unvisited = [...requesters].filter((id) => !visitors.has(id));
  warnings.push({
    rule: 'requesterVisited', label: WARNINGS.requesterVisited,
    ok: unvisited.length === 0,
    detail: unvisited.length ? `${unvisited.length} requester(s) without a visit` : 'all requesters visited',
  });

  const checks = Object.entries(RULES).map(([rule, label]) => ({ rule, label, ...failures[rule], ok: failures[rule].count === 0 }));
  return { ok: checks.every((c) => c.ok), checks, warnings };
}

function checkTokens(json, fail) {
  let t;
  try { t = JSON.parse(json); } catch { fail('tokens_used is not valid JSON'); return; }
  for (const side of ['input', 'output']) {
    const d = t[`${side}_token_details`];
    if (!d) { fail(`missing ${side}_token_details`); continue; }
    const sum = (d.text_tokens ?? 0) + (d.audio_tokens ?? 0) + (d.image_tokens ?? 0);
    if (sum !== t[`${side}_tokens`]) fail(`${side}_tokens ${t[`${side}_tokens`]} != detail sum ${sum}`);
  }
}

function checkData(json, fail) {
  let d;
  try { d = JSON.parse(json); } catch { fail('data is not valid JSON'); return; }
  const s = d.conversation_summary;
  if (!Array.isArray(s)) return;
  s.forEach((turn, k) => {
    const expected = k % 2 === 0 ? 'assistant' : 'user';
    if (turn.role !== expected) fail(`conversation_summary[${k}] role "${turn.role}", expected "${expected}"`);
  });
}
