import { test } from 'node:test';
import assert from 'node:assert/strict';
import { generate, outputFileName, parseSeed } from '../src/lib/generator.js';
import { parseCsv, writeCsv } from '../src/lib/csv.js';
import { CONFIG } from '../src/lib/config.js';
import { CONTACTS_HEADER, HEADER, HOST, contactsCsv, sourceEventsCsv } from './fixtures.js';

const NOW = Date.parse('2026-11-04T12:00:00.000Z');
const EVENTS_NAME = 'agentConversationEvents_99999.csv';

const run = (opts = {}) => generate({
  contactsCsv: contactsCsv(), eventsCsv: sourceEventsCsv(), eventsFileName: EVENTS_NAME, now: NOW, seed: 7, ...opts,
});

function manyContactsCsv(n) {
  const rows = Array.from({ length: n }, (_, i) => ({
    _id: `bbbbbbbbbbbbbbbb${String(i).padStart(8, '0')}`, purl: `fake${i}`, firstname: `Fake${i}`, lastname: 'Example', email: '',
  }));
  return writeCsv(CONTACTS_HEADER, rows);
}

test('output passes the validator across many seeds', () => {
  for (let seed = 1; seed <= 50; seed++) {
    const res = run({ seed });
    const failed = res.validation.checks.filter((c) => !c.ok);
    assert.deepEqual(failed.map((c) => `${c.rule}: ${c.examples[0]?.message}`), [], `seed ${seed}`);
  }
});

test('same seed and time give the same file', () => {
  assert.equal(run({ seed: 123 }).csv, run({ seed: 123 }).csv);
  assert.notEqual(run({ seed: 123 }).csv, run({ seed: 124 }).csv);
});

test('header is the events file header, same order, all lowercase', () => {
  const { header, records } = parseCsv(run().csv);
  assert.deepEqual(header, HEADER.map((c) => c.toLowerCase()));
  assert.ok(header.every((c) => c === c.toLowerCase()));
  assert.ok(records.length && records.every((r) => r.acctid && r.purl !== undefined));
});

test('rejects an events file with two columns that differ only by case', () => {
  const bad = sourceEventsCsv().replace('isseed', 'EMAIL');
  assert.notEqual(bad, sourceEventsCsv());
  assert.throws(() => run({ eventsCsv: bad }), /differ only by case/);
});

test('never emits codes outside the four', () => {
  const res = run();
  assert.ok(res.rows.every((r) => ['30500', '50531', '50532', '50533'].includes(r.event_code)));
  assert.ok(res.notes.some((n) => n.includes('Ignored 1 source row')));
});

test('selects 50-75% of contacts, all from the contacts file', () => {
  const contacts = parseCsv(manyContactsCsv(200)).records;
  const ids = new Set(contacts.map((c) => c._id));
  for (let seed = 1; seed <= 20; seed++) {
    const res = run({ seed, contactsCsv: manyContactsCsv(200) });
    const touched = new Set(res.rows.map((r) => r._id));
    assert.ok([...touched].every((id) => ids.has(id)));
    assert.ok(touched.size >= 100 && touched.size <= 150, `seed ${seed}: ${touched.size}`);
  }
});

test('funnel rates hold on a realistic contact count', () => {
  for (let seed = 1; seed <= 20; seed++) {
    const res = run({ seed, contactsCsv: manyContactsCsv(400) });
    for (const w of res.validation.warnings) assert.ok(w.ok, `seed ${seed} ${w.rule}: ${w.detail}`);
  }
});

test('abandoned requests appear at the configured rate and never exceed 5%', () => {
  const config = { ...CONFIG, abandonRate: { min: 0.05, max: 0.05 } };
  const res = run({ seed: 3, contactsCsv: manyContactsCsv(400), config });
  const { requested, completed, abandoned } = res.summary.funnel;
  assert.ok(abandoned > 0);
  assert.ok(completed / requested >= 0.95);
  assert.equal(res.validation.ok, true);
});

test('event_path carrying a PURL gets the new contact PURL', () => {
  for (let seed = 1; seed <= 30; seed++) {
    for (const r of run({ seed }).rows) {
      assert.ok(r.event_path === HOST || r.event_path === `${HOST}/${r.PURL}`, r.event_path);
    }
  }
});

test('events file name must agree with its acctID', () => {
  assert.throws(() => run({ eventsFileName: 'agentConversationEvents_12345.csv' }), /account 12345/);
  assert.ok(run({ eventsFileName: 'whatever.csv' }).notes.some((n) => n.includes('does not follow')));
});

test('rejects an events file without the required columns', () => {
  const bad = sourceEventsCsv().replace('event_path', 'path');
  assert.throws(() => run({ eventsCsv: bad }), /missing columns: event_path/);
});

test('seed: blank means random, otherwise a whole number 0-4294967295', () => {
  assert.equal(parseSeed(''), undefined);
  assert.equal(parseSeed('  '), undefined);
  assert.equal(parseSeed(' 42 '), 42);
  assert.equal(parseSeed('4294967295'), 4294967295);
  for (const bad of ['-1', '4294967296', '1.5', 'abc']) assert.throws(() => parseSeed(bad), /whole number/);
});

test('output file name follows the agreed pattern', () => {
  const name = outputFileName('38525', new Date(2026, 10, 4, 9, 5, 7));
  assert.equal(name, 'agentConversationEvents_38525_generated_20261104-090507.csv');
});

test('a source conversation without audio_time is skipped, not a failure', () => {
  for (const missing of ['', '0']) {
    const eventsCsv = sourceEventsCsv().replace(',120.25,', `,${missing},`);
    assert.notEqual(eventsCsv, sourceEventsCsv());
    for (let seed = 1; seed <= 30; seed++) {
      const res = run({ seed, eventsCsv, contactsCsv: manyContactsCsv(100) });
      assert.equal(res.validation.ok, true, `seed ${seed}`);
      assert.ok(res.rows.every((r) => r.event_code !== '50532' || Number(r.audio_time) > 0));
      assert.ok(res.notes.some((n) => n.includes('Skipped 1 source conversation')));
    }
  }
});

test('column order in either input file does not matter', () => {
  const reorder = (csv, seed) => {
    const { header, records } = parseCsv(csv);
    const shuffled = [...header].sort((a, b) => ((a.length * 31 + seed) % 7) - ((b.length * 31 + seed) % 7) || b.localeCompare(a));
    return { header: shuffled, csv: writeCsv(shuffled, records) };
  };
  const base = run({ seed: 11 });
  for (let k = 1; k <= 5; k++) {
    const events = reorder(sourceEventsCsv(), k);
    const contacts = reorder(contactsCsv(), k + 3);
    assert.notDeepEqual(events.header, HEADER);
    const res = run({ seed: 11, eventsCsv: events.csv, contactsCsv: contacts.csv });
    assert.equal(res.validation.ok, true, `order ${k}`);
    assert.deepEqual(res.header, events.header.map((c) => c.toLowerCase()), 'output follows the events file column order');
    assert.deepEqual(res.rows, base.rows, 'same rows, whatever the column order');
  }
});
