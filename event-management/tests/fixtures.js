// Synthetic fixtures. Every value is obviously fake: example.test emails,
// 203.0.113.x / 2001:db8:: documentation IPs, "Testa" names. Never put real
// input data in tests.

import { writeCsv } from '../src/lib/csv.js';

export const HEADER = [
  'PURL', 'email', 'firstname', 'isseed', 'lastname', 'address1', 'address2', 'city', 'phone',
  'state', 'zip', '_id', 'event_code', 'event_date', 'event_path', 'event_element', 'app_code',
  'Browser', 'Device', 'OS', 'Platform', 'IP', 'audio_transcript', 'audio_recording',
  'tokens_used', 'audio_time', 'data', 'acctID',
];

export const ACCT = '99999';
export const HOST = 'agent.example.test';

export const CONTACTS_HEADER = ['_id', 'purl', 'firstname', 'lastname', 'email'];
export const CONTACTS = Array.from({ length: 20 }, (_, i) => ({
  _id: `00000000000000000000${String(i).padStart(4, '0')}`,
  purl: `testa${i}`,
  firstname: `Testa${i}`,
  lastname: 'Example',
  email: i % 4 === 0 ? `testa${i}@example.test` : '',
}));

const uuid = (n) => `00000000-0000-4000-8000-${String(n).padStart(12, '0')}`;

export function tokens(inText = 1000, inAudio = 200, outText = 50, outAudio = 150) {
  return JSON.stringify({
    input_tokens: inText + inAudio,
    output_tokens: outText + outAudio,
    input_token_details: {
      text_tokens: inText, audio_tokens: inAudio, image_tokens: 0, cached_tokens: 0,
      cached_tokens_details: { text_tokens: 0, audio_tokens: 0, image_tokens: 0 },
    },
    output_token_details: {
      text_tokens: outText, audio_tokens: outAudio, image_tokens: 0, cached_tokens: 0,
      cached_tokens_details: { text_tokens: 0, audio_tokens: 0, image_tokens: 0 },
    },
  });
}

export const DATA = JSON.stringify({
  phone: '555-0100', email: '', lastname: 'example', firstname: 'testa', requestCallback: 'Y',
  conversation_summary: [
    { role: 'assistant', message: 'The assistant greeted the user.' },
    { role: 'user', message: 'The user asked about pricing.' },
    { role: 'assistant', message: 'The assistant explained the options.' },
  ],
});

function blankRow(contactId, code, date) {
  const r = Object.fromEntries(HEADER.map((h) => [h, '']));
  return Object.assign(r, {
    _id: contactId, event_code: code, app_code: code.slice(0, 3), event_date: date,
    event_path: HOST, event_element: code === '30500' ? '' : 'Agent', acctID: ACCT,
  });
}

const visitFp = { Browser: 'Firefox', Device: 'desktop', OS: 'Windows 10.0', Platform: 'Microsoft Windows', IP: '2001:db8::1, 203.0.113.7' };
const requestFp = { Browser: 'Firefox', Device: 'desktop', OS: 'Windows 10.0', Platform: 'Microsoft Windows', IP: '' };

// Raw source events in the shape of a real export (pre-blanking, so 505xx rows
// carry `unknown`/internal IPs as the real export does).
export function sourceEventsCsv() {
  const rows = [];
  const add = (id, code, date, extra = {}) => rows.push(Object.assign(blankRow(id, code, date), extra));
  const srcFpReq = { ...requestFp, IP: '::ffff:10.0.0.1' };
  const srcFpSrv = { Browser: 'unknown', Device: 'desktop', OS: 'unknown', Platform: 'unknown', IP: '::ffff:10.0.0.2' };
  const conv = (id, t0, n, audio, withData) => {
    const t = Date.parse(t0);
    add(id, '30500', new Date(Math.floor((t - 90_000) / 1000) * 1000).toISOString(), visitFp);
    add(id, '50531', new Date(t).toISOString(), srcFpReq);
    const t2 = t + Math.round((audio + 8) * 1000);
    add(id, '50532', new Date(t2).toISOString(), {
      ...srcFpSrv,
      audio_transcript: `https://files.example.test/voiceai/${uuid(n)}.txt`,
      audio_recording: `https://files.example.test/voiceai/${uuid(n)}.mp4`,
      tokens_used: tokens(), audio_time: String(audio),
    });
    add(id, '50533', new Date(t2 + 350).toISOString(), { ...srcFpSrv, data: withData ? DATA : '' });
  };
  const src = ['aaaaaaaaaaaaaaaaaaaa0001', 'aaaaaaaaaaaaaaaaaaaa0002', 'aaaaaaaaaaaaaaaaaaaa0003'];
  conv(src[0], '2026-08-01T10:00:00.123Z', 1, 35.5, true);
  conv(src[0], '2026-08-02T11:00:00.456Z', 2, 120.25, false);
  conv(src[1], '2026-08-03T12:00:00.789Z', 3, 12.75, true);
  add(src[1], '50531', '2026-08-04T09:00:00.321Z', srcFpReq); // abandoned
  add(src[1], '30500', '2026-08-04T08:58:30.000Z', { ...visitFp, event_path: `${HOST}/srcpurl` });
  for (let k = 0; k < 4; k++) add(src[2], '30500', `2026-08-0${k + 1}T15:00:0${k}.000Z`, visitFp);
  add(src[2], '50101', '2026-08-05T15:00:00.000Z', visitFp); // other campaign code: must never be emitted
  for (const r of rows) Object.assign(r, { PURL: 'srcpurl', firstname: 'Src', lastname: 'Person' });
  return writeCsv(HEADER, rows);
}

export function contactsCsv() {
  return writeCsv(CONTACTS_HEADER, CONTACTS);
}

// A small, valid generated output for `now`, used to test the validator.
export function validOutput(now) {
  const c = CONTACTS[0];
  const contactBlock = { PURL: c.purl, email: c.email, firstname: c.firstname, lastname: c.lastname, _id: c._id };
  const base = now - 2 * 86400_000;
  const iso = (ms) => new Date(ms).toISOString();
  const rows = [
    Object.assign(blankRow(c._id, '30500', iso(Math.floor((base - 90_000) / 1000) * 1000)), visitFp),
    Object.assign(blankRow(c._id, '50531', iso(base + 123)), requestFp),
    Object.assign(blankRow(c._id, '50532', iso(base + 123 + 43_500)), {
      audio_transcript: `https://files.example.test/voiceai/${uuid(9)}.txt`,
      audio_recording: `https://files.example.test/voiceai/${uuid(9)}.wav`,
      tokens_used: tokens(), audio_time: '35.5',
    }),
    Object.assign(blankRow(c._id, '50533', iso(base + 123 + 43_500 + 400)), { data: DATA }),
  ];
  for (const r of rows) Object.assign(r, contactBlock);
  return rows;
}
