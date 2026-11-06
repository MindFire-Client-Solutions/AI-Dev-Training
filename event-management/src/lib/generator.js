// Builds a demo event export from a contacts file and a real events file.
// Pure: no file system, no network. Runs in Node and in the browser.
//
// Every output row is a copy of a real source event row (CLAUDE.md: never
// invent event details), re-assigned to a selected contact and re-timed into
// the last `windowDays` days. Gaps inside a conversation are kept exactly.

import { CONFIG, EVENT_CODES } from './config.js';
import { parseCsv, writeCsv } from './csv.js';
import {
  FINGERPRINT_COLUMNS, accountFromFileName, assertContactsHeader, assertEventHeader,
  contactBlockFor, contactColumnMap, outputHeader,
} from './schema.js';
import { summarize } from './summary.js';
import { validate } from './validator.js';

// --- seeded randomness ------------------------------------------------------

function mulberry32(seed) {
  let a = seed >>> 0;
  return function next() {
    a = (a + 0x6d2b79f5) >>> 0;
    let t = a;
    t = Math.imul(t ^ (t >>> 15), t | 1);
    t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

function makeRandom(seed) {
  const rand = mulberry32(seed);
  const api = {
    float: (min, max) => min + rand() * (max - min),
    int: (min, max) => min + Math.floor(rand() * (max - min + 1)),
    pick: (arr) => arr[Math.floor(rand() * arr.length)],
    shuffle: (arr) => {
      const a = [...arr];
      for (let i = a.length - 1; i > 0; i--) {
        const j = Math.floor(rand() * (i + 1));
        [a[i], a[j]] = [a[j], a[i]];
      }
      return a;
    },
  };
  return api;
}

export function randomSeed() {
  return globalThis.crypto.getRandomValues(new Uint32Array(1))[0];
}

// '' / null / undefined -> undefined (random). Otherwise a whole number 0..2^32-1.
export function parseSeed(v) {
  if (v === undefined || v === null || String(v).trim() === '') return undefined;
  const n = Number(String(v).trim());
  if (!Number.isInteger(n) || n < 0 || n > 0xffffffff) {
    throw new Error('Seed must be a whole number from 0 to 4294967295');
  }
  return n;
}

// agentConversationEvents_<acct>_generated_<YYYYMMDD-HHMMSS>.csv (local time)
export function outputFileName(acctID, date = new Date()) {
  const p = (n) => String(n).padStart(2, '0');
  const stamp = `${date.getFullYear()}${p(date.getMonth() + 1)}${p(date.getDate())}-${p(date.getHours())}${p(date.getMinutes())}${p(date.getSeconds())}`;
  return `agentConversationEvents_${acctID}_generated_${stamp}.csv`;
}

// --- source analysis --------------------------------------------------------

// Split the source events into per-contact "personas": their visits, their
// complete conversations (50531 -> 50532 -> 50533) and their abandoned requests.
// A conversation whose 50532 has no audio_time (blank, 0 or not a number) is
// skipped: its 50532 and 50533 are dropped and its 50531 counts as abandoned.
const hasAudioTime = (row) => Number(row.audio_time) > 0;

function buildPersonas(records) {
  let skippedNoAudio = 0;
  const byId = new Map();
  for (const r of records) {
    if (!byId.has(r._id)) byId.set(r._id, []);
    byId.get(r._id).push(r);
  }
  const personas = [];
  for (const [id, rows] of byId) {
    rows.sort((a, b) => Date.parse(a.event_date) - Date.parse(b.event_date));
    const visits = rows.filter((r) => r.event_code === '30500');
    const sessions = [];
    const pendingRequests = [];
    const orphans = [];
    let open = null;
    for (const r of rows) {
      if (r.event_code === '50531') pendingRequests.push(r);
      else if (r.event_code === '50532') {
        const req = pendingRequests.pop();
        orphans.push(...pendingRequests.splice(0));
        open = req ? { req, comp: r } : null;
      } else if (r.event_code === '50533' && open) {
        if (!hasAudioTime(open.comp)) {
          skippedNoAudio += 1;
          orphans.push(open.req);
          open = null;
          continue;
        }
        const t = (x) => Date.parse(x.event_date);
        sessions.push({
          req: open.req, comp: open.comp, evalRow: r,
          reqToComp: t(open.comp) - t(open.req),
          compToEval: t(r) - t(open.comp),
          leadVisit: [...visits].reverse().find((v) => t(v) <= t(open.req)) ?? null,
        });
        open = null;
      }
    }
    orphans.push(...pendingRequests);
    const leadVisitFor = (req) => [...visits].reverse().find((v) => Date.parse(v.event_date) <= Date.parse(req.event_date)) ?? null;
    personas.push({ id, visits, sessions, orphans: orphans.map((req) => ({ req, leadVisit: leadVisitFor(req) })) });
  }
  return { personas, skippedNoAudio };
}

// --- generation -------------------------------------------------------------

export function generate({
  contactsCsv, eventsCsv, eventsFileName = '', seed = randomSeed(), now = Date.now(), config = CONFIG,
}) {
  const notes = [];
  const contactsParsed = parseCsv(contactsCsv);
  const events = parseCsv(eventsCsv);
  assertContactsHeader(contactsParsed.header);
  assertEventHeader(events.header);
  const contacts = contactsParsed.records.filter((c) => c._id);
  if (!contacts.length) throw new Error('Contacts file has no contacts');

  const accts = [...new Set(events.records.map((r) => r.acctID).filter(Boolean))];
  if (accts.length !== 1) throw new Error(`Events file must have exactly one acctID, found: ${accts.join(', ') || 'none'}`);
  const acctID = accts[0];
  const nameAcct = accountFromFileName(eventsFileName);
  if (eventsFileName && nameAcct === null) {
    notes.push(`Events file name "${eventsFileName}" does not follow agentConversationEvents_nnnnn.csv.`);
  } else if (nameAcct !== null && nameAcct !== acctID) {
    throw new Error(`Events file name says account ${nameAcct}, but its acctID column is ${acctID}`);
  }

  const usable = events.records.filter((r) => EVENT_CODES.includes(r.event_code));
  const skipped = events.records.length - usable.length;
  if (skipped) notes.push(`Ignored ${skipped} source row(s) with event codes outside ${EVENT_CODES.join(', ')}.`);

  const { personas, skippedNoAudio } = buildPersonas(usable);
  if (skippedNoAudio) notes.push(`Skipped ${skippedNoAudio} source conversation(s) with no audio_time: their 50532 and 50533 were not used.`);
  const talkers = personas.filter((p) => p.sessions.length);
  const visitors = personas.filter((p) => p.visits.length);
  const allVisits = visitors.flatMap((p) => p.visits);
  if (!talkers.length) throw new Error('Events file has no complete conversation (50531 -> 50532 -> 50533)');
  if (!allVisits.length) throw new Error('Events file has no 30500 visits');

  const rng = makeRandom(seed);
  const columnMap = contactColumnMap(events.header, contactsParsed.header);
  const windowStart = now - config.windowDays * 86400_000;

  // 1. Select 50-75% of contacts, then 20-25% of those as requesters.
  const nSelected = Math.max(1, Math.round(contacts.length * rng.float(config.contactSelection.min, config.contactSelection.max)));
  const selected = rng.shuffle(contacts).slice(0, nSelected);
  let nRequesters = Math.round(nSelected * rng.float(config.requestRate.min, config.requestRate.max));
  if (nRequesters === 0 && nSelected >= config.minRequestingContactsFrom) {
    nRequesters = 1;
    notes.push(`Only ${nSelected} contacts selected: 20-25% rounds to 0, so 1 contact was given a conversation.`);
  }
  const requesters = selected.slice(0, nRequesters);
  const visitOnly = selected.slice(nRequesters);

  // 2. Session slots for requesters; mark up to abandonRate of them abandoned.
  const slots = [];
  for (const c of requesters) {
    const persona = rng.pick(talkers);
    // Draw without replacement so one contact never repeats the same conversation.
    const n = Math.min(persona.sessions.length, rng.int(config.sessionsPerRequester.min, config.sessionsPerRequester.max));
    for (const session of rng.shuffle(persona.sessions).slice(0, n)) slots.push({ contact: c, persona, session, abandoned: false });
  }
  const nAbandoned = Math.floor(slots.length * rng.float(config.abandonRate.min, config.abandonRate.max));
  for (const s of rng.shuffle(slots).slice(0, nAbandoned)) s.abandoned = true;

  // 3. Build rows.
  const out = [];
  const busy = new Map(); // contact _id -> [[start, end]] so one contact's calls do not overlap
  const emit = (src, contact, ms) => out.push(transformRow(src, contact, ms, columnMap));
  const secondInWindow = () => rng.int(Math.ceil(windowStart / 1000), Math.floor(now / 1000)) * 1000;

  for (const slot of slots) {
    const { contact, persona, session: s } = slot;
    const orphan = slot.abandoned && persona.orphans.length ? rng.pick(persona.orphans) : null;
    const reqRow = orphan ? orphan.req : s.req;
    const leadVisit = (orphan ? orphan.leadVisit : s.leadVisit) ?? rng.pick(persona.visits.length ? persona.visits : allVisits);
    const tail = slot.abandoned ? 0 : s.reqToComp + s.compToEval;
    const leadMax = config.leadVisitGapSeconds.max * 1000;
    const lo = windowStart + leadMax + 1000;
    const hi = now - tail - 1;
    if (hi < lo) throw new Error('A conversation is longer than the date window');

    let tReq = 0;
    const spans = busy.get(contact._id) ?? [];
    for (let attempt = 0; attempt < 50; attempt++) {
      tReq = Math.floor(rng.float(lo, hi));
      const start = tReq - leadMax;
      const end = tReq + tail;
      if (!spans.some(([a, b]) => start < b + 60_000 && end > a - 60_000)) break;
    }
    spans.push([tReq - leadMax, tReq + tail]);
    busy.set(contact._id, spans);

    const leadGap = rng.int(config.leadVisitGapSeconds.min, config.leadVisitGapSeconds.max) * 1000;
    emit(leadVisit, contact, Math.floor((tReq - leadGap) / 1000) * 1000);
    emit(reqRow, contact, tReq);
    if (!slot.abandoned) {
      emit(s.comp, contact, tReq + s.reqToComp);
      emit(s.evalRow, contact, tReq + s.reqToComp + s.compToEval);
    }
  }

  for (const c of requesters) {
    const persona = slots.find((s) => s.contact === c).persona;
    const pool = persona.visits.length ? persona.visits : allVisits;
    const n = rng.int(config.extraVisitsPerRequester.min, config.extraVisitsPerRequester.max);
    for (let k = 0; k < n; k++) emit(rng.pick(pool), c, secondInWindow());
  }
  for (const c of visitOnly) {
    const pool = rng.pick(visitors).visits;
    const n = rng.int(config.visitsPerVisitOnlyContact.min, config.visitsPerVisitOnlyContact.max);
    for (let k = 0; k < n; k++) emit(rng.pick(pool), c, secondInWindow());
  }

  // 4. Order like the export: grouped by contact, then by time.
  const order = new Map(contacts.map((c, i) => [c._id, i]));
  out.sort((a, b) => order.get(a._id) - order.get(b._id) || Date.parse(a.event_date) - Date.parse(b.event_date));

  const header = outputHeader(events.header);
  const validation = validate({
    header, rows: out, expectedHeader: events.header,
    contactsHeader: contactsParsed.header, contacts, acctID, now, config,
  });
  const summary = summarize(out, { contactsTotal: contacts.length, seed });

  return {
    // header: output header (lowercase). columns: the matching keys of each row.
    acctID, seed, header, columns: events.header, rows: out,
    csv: writeCsv(events.header, out, header), summary, validation, notes,
    window: { start: new Date(windowStart).toISOString(), end: new Date(now).toISOString() },
  };
}

function transformRow(src, contact, ms, columnMap) {
  const row = { ...src, ...contactBlockFor(contact, columnMap) };
  row.event_date = new Date(ms).toISOString();

  // A path that carries the source contact's PURL gets the new contact's PURL.
  const slash = row.event_path.indexOf('/');
  if (slash !== -1) {
    const purl = row.PURL ?? '';
    row.event_path = purl ? `${row.event_path.slice(0, slash)}/${purl}` : row.event_path.slice(0, slash);
  }

  // CLAUDE.md fingerprint rules: server-generated rows carry no fingerprint, 505xx no IP.
  if (row.event_code === '50532' || row.event_code === '50533') {
    for (const col of FINGERPRINT_COLUMNS) row[col] = '';
  }
  if (row.event_code.startsWith('505')) row.IP = '';
  return row;
}
