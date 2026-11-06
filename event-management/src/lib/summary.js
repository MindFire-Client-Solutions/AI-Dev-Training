// The run summary CLAUDE.md asks to print on every run.

import { EVENT_CODES } from './config.js';

function median(sorted) {
  if (!sorted.length) return null;
  const m = Math.floor(sorted.length / 2);
  return sorted.length % 2 ? sorted[m] : (sorted[m - 1] + sorted[m]) / 2;
}

export function summarize(rows, { contactsTotal, seed }) {
  const byCode = Object.fromEntries(EVENT_CODES.map((c) => [c, 0]));
  const touched = new Set();
  const visitors = new Set();
  const requesters = new Set();
  const audio = [];
  let first = null;
  let last = null;

  for (const r of rows) {
    byCode[r.event_code] = (byCode[r.event_code] ?? 0) + 1;
    touched.add(r._id);
    if (r.event_code === '30500') visitors.add(r._id);
    if (r.event_code === '50531') requesters.add(r._id);
    if (r.event_code === '50532' && r.audio_time !== '') audio.push(Number(r.audio_time));
    if (!first || r.event_date < first) first = r.event_date;
    if (!last || r.event_date > last) last = r.event_date;
  }
  audio.sort((a, b) => a - b);

  const requested = byCode['50531'];
  const completed = byCode['50532'];
  return {
    rowCount: rows.length,
    byCode,
    contactsTotal,
    contactsTouched: touched.size,
    contactsSkipped: contactsTotal - touched.size,
    funnel: {
      visitors: visitors.size,
      requesters: requesters.size,
      requested,
      completed,
      evaluated: byCode['50533'],
      abandoned: requested - completed,
    },
    rates: {
      requestRate: visitors.size ? requesters.size / visitors.size : null,
      completionRate: requested ? completed / requested : null,
    },
    audioTime: { min: audio[0] ?? null, median: median(audio), max: audio[audio.length - 1] ?? null },
    dateRange: { first, last },
    seed,
  };
}
