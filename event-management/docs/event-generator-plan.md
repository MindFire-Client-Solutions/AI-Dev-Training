# Event generator, validator and UI: plan

Issue: #8 (stays open). Implemented in PR #9, merged to `main` on 2026-11-05.
This document records the plan as agreed and as built, including decisions made along the way.

## Context

The dashboard and downstream pipelines need realistic voice-AI event data without
waiting on live campaign traffic. The generator takes a contacts file and a real
events export (`agentConversationEvents_nnnnn.csv`, where `nnnnn` is the account
number). It produces a new export for those contacts that matches the source schema
exactly. `CLAUDE.md` is the authority. Where it disagrees with the sample export,
`CLAUDE.md` wins.

## Decisions

| Topic | Decision |
|---|---|
| Header | Output header = events file header exactly (28 columns for acct 38525). No 32-column schema. |
| Funnel | 20–25% of visiting contacts (30500) request (50531). At least 95% of requests complete (50532). Every 50532 has exactly one 50533. |
| Contact selection | 50–75% of contacts in `contacts.csv` per run. |
| Event details | Copied from real source rows only, never invented. A contact never repeats the same conversation. |
| Dates | Every `event_date` falls between today − 7 days and now. Each conversation is placed at a random time in that window, with its internal gaps (visit → request, call length, sub-second evaluation) kept exactly. 30500 dates end `.000Z`; 505xx carry milliseconds. |
| Fingerprint | 50532/50533 `Browser`/`Device`/`OS`/`Platform` blank. All 505xx `IP` blank. 30500 keeps `client, CDN hop`. |
| `event_path` | A path carrying the source contact's PURL is rewritten to the new contact's PURL. |
| Real values | No synthetic-value rule. Rows are copied verbatim apart from the changes above. |
| Rate checks | Hard rules fail the run. Funnel rates are warnings, because small inputs can't hit them exactly. |
| UI stack | Plain HTML and vanilla JS, styled on `reference/Zoltar_AI_Dashboard_Intelligence_Demo.html`. No npm dependencies. |
| Where it runs | In the browser. Input files are never uploaded. |
| Output | Local (`npm start`): saved to `output/` and offered for download. Hosted (Cloudflare Pages): browser download only. |
| Hosting | Cloudflare Pages, public link (anyone with the URL), `noindex`. |
| Seed | Optional, 0–4294967295. The same seed repeats contact, conversation and row choices. Dates move with the time of the run. |

## Components

| File | Role |
|---|---|
| `src/lib/config.js` | All tunable ratios and ranges. |
| `src/lib/csv.js` | RFC 4180 parse/write: minimal quoting, CRLF line endings. |
| `src/lib/schema.js` | Column roles derived from the events header; mapping from contacts columns (`purl` → `PURL`). |
| `src/lib/validator.js` | Hard rules and funnel warnings. Written first. |
| `src/lib/generator.js` | Seeded generator: `parseSeed`, `outputFileName`. |
| `src/lib/summary.js` | Run summary (CLAUDE.md). |
| `src/ui/index.html` | Upload, generate, summary, validation, preview, download. |
| `src/server.js` | Local only (127.0.0.1): serves the page and modules; `/api/save` writes to `output/`. |
| `scripts/build.js` | Builds `dist/` (page, `lib/`, `_headers`) for Cloudflare Pages. |
| `tests/` | `node:test` suites on synthetic fixtures only. |

Everything in `src/lib/` must stay browser-safe (no Node built-ins).

## Generation

1. Parse both files, check required columns, and require exactly one `acctID`. If the
   file name follows `agentConversationEvents_nnnnn.csv`, `nnnnn` must equal that
   `acctID`. Drop any source row whose code is not 30500/50531/50532/50533.
2. Split the source into personas, one per source contact: their visits, their
   complete conversations (50531 → 50532 → 50533, with the visit before each), and
   their abandoned 50531s.
3. Select 50–75% of contacts. 20–25% of those become requesters; at least one when
   2 or more contacts are selected.
4. Each requester gets 1–3 conversations drawn without replacement from one persona.
   Up to 5% of all conversation slots become abandoned requests.
5. Place each conversation in the 7-day window, avoiding overlaps for the same
   contact. Add a lead visit 60–120 s before each request, extra visits for
   requesters, and 1–6 visits for visit-only contacts.
6. Copy the contact block from `contacts.csv`, blank the fingerprint and IP fields,
   and rewrite `event_path`. Sort by contact, then by time.
7. Validate, then summarize. Nothing is produced if a hard rule fails.

## Validator: hard rules

Each of these fails the run:

1. Header is exact.
2. Only the four event codes.
3. `app_code` is the code prefix.
4. Every contact is in `contacts.csv`, with its contact block copied verbatim.
5. `acctID` matches the events file.
6. Timestamp precision is right for the code.
7. Every date is in the window.
8. The 50532/50533 fingerprint is blank.
9. The 505xx IP is blank, and the 30500 IP is `client, CDN hop`.
10. Payloads are only on their own codes.
11. Transcript and recording share a UUID.
12. `tokens_used` totals equal the sum of their detail fields.
13. `data` is valid JSON, and `conversation_summary` alternates starting with assistant.
14. Pairing: every 50532 follows a 50531 with a gap of `audio_time` plus 0–30 s, and has exactly one 50533 within 1 s.

Warnings: request rate (20–25%), completion rate (at least 95%), and every requester also has a visit.

## Deployment

- Cloudflare Pages: root `event-management`, build `npm run build`, output `dist`.
- `_headers`: CSP with `connect-src 'none'` (the page cannot send data anywhere),
  `X-Robots-Tag: noindex`, no framing, `no-referrer`.
- `dist/` is not committed.

## Verification

- `npm test`: 30 tests covering every rule, 50 seeds, determinism, selection
  share, funnel rates at 400 contacts, PURL rewrite, seed parsing and file naming.
- Browser end-to-end with the account 38525 files:
  - **Local:** download is byte-identical to the copy in `output/`.
  - **Hosted simulation:** `dist/` served with the Pages headers. All rules pass, there are no CSP errors, and no data requests leave the page.
- Checked at desktop width, phone width and in dark mode.

## Known limitations

- A 50533 `data` payload holds what the agent captured on the original call, so after
  reassignment it can show another contact's name or email.
- Few source conversations (7 for acct 38525) means different contacts can share
  transcript/recording URLs.
- With very small contact lists, the 20–25% request rate can't be reached; it shows as a warning.

## Open items

- Connect the repo to Cloudflare Pages and confirm the public URL.
- The root `CLAUDE.md` asks for a person to review every change; PR #9 was merged
  without a recorded review.
