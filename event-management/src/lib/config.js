// Every tunable ratio and range lives here. See CLAUDE.md "Generation rules"
// and "Journey model". Change a number here, not inside the generator.

export const EVENT_CODES = ['30500', '50531', '50532', '50533'];

export const CONFIG = {
  // Share of input contacts selected on each run.
  contactSelection: { min: 0.50, max: 0.75 },

  // Share of visiting contacts (30500) that go on to request (50531).
  requestRate: { min: 0.20, max: 0.25 },
  // When the rounded count would be 0 but at least this many contacts are
  // selected, still give one contact a conversation so the funnel is not empty.
  minRequestingContactsFrom: 2,

  // Share of 50531 requests that are abandoned (no 50532). CLAUDE.md: >= 95% complete.
  abandonRate: { min: 0.00, max: 0.05 },

  // Requests (sessions) per requesting contact.
  sessionsPerRequester: { min: 1, max: 3 },

  // Standalone visits per contact (on top of the visit that leads to each request).
  visitsPerVisitOnlyContact: { min: 1, max: 6 },
  extraVisitsPerRequester: { min: 0, max: 3 },

  // The visit that leads to a request comes this many seconds before it.
  leadVisitGapSeconds: { min: 60, max: 120 },

  // All event_date values fall in [now - windowDays, now].
  windowDays: 7,

  // Validator tolerances, from the observed source export.
  // 50531 -> 50532 gap is audio_time plus connect/teardown time.
  requestToCompleteSlackSeconds: { min: 0, max: 30 },
  // 50532 -> 50533 gap.
  completeToEvaluateMaxSeconds: 1,
};
