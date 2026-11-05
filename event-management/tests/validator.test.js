import { test } from 'node:test';
import assert from 'node:assert/strict';
import { validate } from '../src/lib/validator.js';
import { ACCT, CONTACTS, CONTACTS_HEADER, HEADER, validOutput } from './fixtures.js';

const NOW = Date.parse('2026-11-04T12:00:00.000Z');

function run(rows, header = HEADER) {
  return validate({
    header, rows, expectedHeader: HEADER, contactsHeader: CONTACTS_HEADER,
    contacts: CONTACTS, acctID: ACCT, now: NOW,
  });
}
const failed = (res) => res.checks.filter((c) => !c.ok).map((c) => c.rule);

// Apply `mutate` to the row with `code` in a fresh valid output.
function withRow(code, mutate) {
  const rows = validOutput(NOW);
  mutate(rows.find((r) => r.event_code === code), rows);
  return rows;
}

test('a valid output passes every hard rule', () => {
  const res = run(validOutput(NOW));
  assert.deepEqual(failed(res), []);
  assert.equal(res.ok, true);
});

test('header must match exactly: same columns, same order', () => {
  const swapped = [...HEADER];
  [swapped[1], swapped[2]] = [swapped[2], swapped[1]];
  assert.deepEqual(failed(run(validOutput(NOW), swapped)), ['header']);
  assert.deepEqual(failed(run(validOutput(NOW), HEADER.slice(0, -1))), ['header']);
  assert.deepEqual(failed(run(validOutput(NOW), [...HEADER, 'event_id'])), ['header']);
});

test('only the four event codes are allowed', () => {
  const rows = withRow('30500', (r) => { r.event_code = '50101'; r.app_code = '501'; });
  assert.ok(failed(run(rows)).includes('codes'));
});

test('app_code must be the code prefix', () => {
  assert.deepEqual(failed(run(withRow('50531', (r) => { r.app_code = '305'; }))), ['appCode']);
});

test('contacts must exist and carry their contacts.csv values', () => {
  assert.ok(failed(run(withRow('30500', (r) => { r._id = 'ffffffffffffffffffffffff'; }))).includes('contact'));
  assert.deepEqual(failed(run(withRow('30500', (r) => { r.firstname = 'Changed'; }))), ['contact']);
  assert.deepEqual(failed(run(withRow('30500', (r) => { r.city = 'Nowhere'; }))), ['contact']);
});

test('acctID must match the events file', () => {
  assert.deepEqual(failed(run(withRow('50533', (r) => { r.acctID = '11111'; }))), ['acctID']);
});

test('timestamp precision differs by code', () => {
  assert.deepEqual(failed(run(withRow('30500', (r) => { r.event_date = r.event_date.replace('.000Z', '.120Z'); }))), ['dateFormat']);
  assert.ok(failed(run(withRow('50531', (r) => { r.event_date = r.event_date.slice(0, 19) + 'Z'; }))).includes('dateFormat'));
});

test('dates must fall within the last 7 days, up to now', () => {
  const old = (r) => { r.event_date = '2026-10-01T00:00:00.000Z'; };
  assert.ok(failed(run(withRow('30500', old))).includes('dateWindow'));
  const future = (r) => { r.event_date = '2026-11-05T00:00:00.000Z'; };
  assert.ok(failed(run(withRow('30500', future))).includes('dateWindow'));
});

test('50532/50533 fingerprints must be blank', () => {
  assert.deepEqual(failed(run(withRow('50532', (r) => { r.Browser = 'unknown'; }))), ['fingerprint']);
  assert.deepEqual(failed(run(withRow('50533', (r) => { r.Device = 'desktop'; }))), ['fingerprint']);
});

test('505xx IP must be blank and 30500 IP must be client + CDN hop', () => {
  assert.deepEqual(failed(run(withRow('50531', (r) => { r.IP = '::ffff:10.0.0.1'; }))), ['ip']);
  assert.deepEqual(failed(run(withRow('30500', (r) => { r.IP = '203.0.113.7'; }))), ['ip']);
});

test('payloads only on their own codes', () => {
  assert.deepEqual(failed(run(withRow('50531', (r) => { r.audio_time = '12'; }))), ['payloadPlacement']);
  assert.deepEqual(failed(run(withRow('50532', (r) => { r.data = '{}'; }))), ['payloadPlacement']);
  assert.ok(failed(run(withRow('50532', (r) => { r.tokens_used = ''; }))).includes('payloadPlacement'));
});

test('transcript and recording share a UUID', () => {
  const rows = withRow('50532', (r) => { r.audio_recording = r.audio_recording.replace('000000000009', '000000000008'); });
  assert.deepEqual(failed(run(rows)), ['audioUuid']);
});

test('tokens_used totals must add up', () => {
  const rows = withRow('50532', (r) => {
    const t = JSON.parse(r.tokens_used); t.input_tokens += 1; r.tokens_used = JSON.stringify(t);
  });
  assert.deepEqual(failed(run(rows)), ['tokens']);
});

test('conversation_summary must alternate, starting with assistant', () => {
  const rows = withRow('50533', (r) => {
    const d = JSON.parse(r.data); d.conversation_summary[0].role = 'user'; r.data = JSON.stringify(d);
  });
  assert.deepEqual(failed(run(rows)), ['dataPayload']);
});

test('every 50532 needs a 50531 before it and exactly one 50533 after it', () => {
  const noEval = validOutput(NOW).filter((r) => r.event_code !== '50533');
  assert.deepEqual(failed(run(noEval)), ['pairing']);
  const noReq = validOutput(NOW).filter((r) => r.event_code !== '50531');
  assert.deepEqual(failed(run(noReq)), ['pairing']);
  const slowEval = withRow('50533', (r) => { r.event_date = new Date(Date.parse(r.event_date) + 5000).toISOString(); });
  assert.deepEqual(failed(run(slowEval)), ['pairing']);
});

test('50531 -> 50532 gap must track audio_time', () => {
  assert.deepEqual(failed(run(withRow('50532', (r) => { r.audio_time = '200'; }))), ['pairing']);
});

test('an abandoned 50531 is valid, but drags the completion warning', () => {
  const rows = validOutput(NOW).filter((r) => r.event_code === '30500' || r.event_code === '50531');
  const res = run(rows);
  assert.equal(res.ok, true);
  assert.equal(res.warnings.find((w) => w.rule === 'completionRate').ok, false);
});

test('rates are warnings, not failures', () => {
  const res = run(validOutput(NOW)); // 1 requester of 1 visitor = 100%
  assert.equal(res.ok, true);
  assert.equal(res.warnings.find((w) => w.rule === 'requestRate').ok, false);
});
