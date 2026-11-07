# Event Generator for Demo Dashboard

Generates randomized-but-realistic MindFire voice-AI event records for a given list of contacts,
so the client's dashboard and downstream pipelines can be exercised 
without waiting on live campaign traffic.

## What this project does

Reads `input/contacts.csv` and `input/agentConversationEvents_nnnnn.csv` where nnnnn is the account number.
During every run, the system will produces event rows for those contacts, matching the schema of the real event export exactly. 
The output CSV is a browser download (the **Download CSV** button), both locally
(`npm start`) and hosted (Cloudflare Pages). Nothing is written to disk by the app.

In both cases the generator runs **in the browser**. The input files are chosen in the
page, processed there, and never uploaded anywhere. The run summary is shown on
screen. `input/` is where the files
are kept locally.

**Weekly scheduled run:** an AWS Lambda, triggered by EventBridge every Sunday 10 PM
Pacific, fetches the two input files from links, generates with the same `src/lib` code,
and uploads the CSV to one SFTP folder. Its settings (input links, SFTP host, port,
username, password, folder) are edited in the UI's Settings screen and stored in AWS
Secrets Manager. Spec: `docs/001-scheduled-sftp-run.md`. Deploy: `docs/lambda-deploy.md`.

The contacts are fictitious demo people. This tool is never used for a real production site.

**Output target:** CSV in the same format as `input/agentConversationEvents_nnnnn.csv`

## Reference material (read these before writing any code)

| File | What it is |
|---|---|
| `input/agentConversationEvents_nnnnn.csv` | **Real export** — AI conversations, where `nnnnn` is the account number (`acctID`). Example: acct 38525, Aug 2026, 59 rows, 5 contacts. The schema here is authoritative. |
| `reference/event_codes.md` | Codes, journey model, field rules, JSON payload shapes — all derived from that export. |
| `input/agentConversationEvents_nnnnn.csv` | Design reference for any preview UI. |

If `reference/event_codes.md` disagrees with the export about field names, types, or formats, **the export is correct** 
Tell me about the conflict rather than picking one silently.

## The schema in one paragraph

The export is a **contact ⋈ event join**, where the contact block
(`PURL`, `_id`, `firstname`, `lastname`, `email`, address fields, `acctID`) 
repeats identically on every one of that contact's event rows, and the event block
(`app_code`, `event_code`, `event_date`, `event_path`, `event_element`, `app_code`)
and other columns listed in the file `input/agentConversationEvents_nnnnn.csv`.
The output header is that file's header — same columns, same order — with every column
name in lowercase (`PURL` → `purl`, `acctID` → `acctid`, `Browser` → `browser`).
`purl` and `_id` uniquely identify a **contact** with its personalized URL and database objectId.

## Generation rules

- Every output row maps to a contact that exists in `input/contacts.csv`, and merges the event records from a record in the file `input/agentConversationEvents_nnnnn.csv`. 
- Never invent a contact that does not exist in the file `input/contacts.csv`.
- Never invent event detais that does not exist in the file `input/agentConversationEvents_nnnnn.csv`.
- During every run, randomly select 50% to 75% of contacts from the file `input/contacts.csv`
- Contact-block columns are copied verbatim from the contacts file onto every row
  for that contact. Columns absent from the contacts file are emitted empty.
- Select event rows from `input/agentConversationEvents_nnnnn.csv`, and append the following columns to each row of the output file.
event_date, event_path, event_element, app_code, Browser, Device, OS, Platform, IP, audio_transcript, audio_recording, tokens_used, audio_time, data
- The acctID column has the value copied from the column acctID in the `input/agentConversationEvents_nnnnn.csv` file.
  It is the final column in the real export; the output keeps whatever column order the events file has.
- The order of columns in either input file does not matter. Columns are matched by name
  (case-insensitively between `contacts.csv` and the events file, e.g. `purl` → `PURL`).
- Only the four codes in `reference/event_codes.md` §1: `30500`, `50531`, `50532`, `50533`. 
  Never emit any other codes — they belong to different campaign types and will pollute the dashboard.
- `app_code` is always the first three digits of `event_code`.
- During every run, use a randomly selected number of contacts, each with a sequence of
  event records following the `Journey model` below. Funnel rates (configurable constants):
  - 20–25% of contacts that have a `30500` also have a `50531`. The rest have `30500` only.
  - At least 95% of `50531` rows have a matching `50532`. The rest are abandoned, which
    leaves a valid orphan `50531`.
  - Every `50532` has exactly one matching `50533`.
- Please make sure to copy event records from matching contact _ids for the same contact in the output file.
- The contact block in the output file may change from the `input/agentConversationEvents_nnnnn.csv` as long as it matches with a contact row in the file `input/contacts.csv`, the input file only has the contact block to identify which event rows go together with the same contact.

### Journey model — this is the core of the generator

```
30500 (visit, repeatable)   20–25% of visiting contacts go on to request
   └─> 50531 (requested)    ≤5% abandon here, producing a valid orphan row
          └─> 50532 (completed)  ──> 50533 (evaluated, always paired)
```

Model each contact as a sequence of sessions over the campaign window, not as
independent random rows. Make the drop-off ratios configurable constants, not
magic numbers buried in the code.

Timing constraints that must hold:

- Events are created asynchronously. The gap between `50531` and `50532` need not match
  `audio_time`, and the order of `event_date` within a conversation is not checked.
- `30500` timestamps are second-precision ending `.000Z`; all `505xx` timestamps
  carry milliseconds. **Do not use one format for both.**
- Every `event_date` falls between today minus 7 days and now. Source dates are
  re-timed into that window; gaps inside a conversation are kept as in the source.

### Fingerprint rules

- `30500` and `50531` carry a real browser/device/OS fingerprint.
- `50532` and `50533` are server-generated: `Browser`/`OS`/`Platform` and `Device` should always be blank or null, regardless of the visitor's real device.
- `30500` IP is `{real client IP}, {CDN hop IP}`. All `505xx` IPs should be blank or null.

### Payload rules

- `audio_transcript`, `audio_recording`, `tokens_used`, `audio_time` appear **only**
  on `50532`. Transcript and recording share the same UUID per conversation.
- If a source `50532` has no `audio_time` (blank or 0), skip that conversation's `50532` and
  `50533` and move on. Do not fail the run. Its `50531` may still be used as an abandoned request.
- `data` appears **only** on `50533`. An empty `data` on an evaluated row can occur.
- `tokens_used` must be internally consistent — `input_tokens` equals the sum of its
  detail fields, and counts scale with `audio_time`. See `reference/event_codes.md` §4.
- `conversation_summary` entries are third-person past tense, start with assistant, and mostly
  alternate assistant/user. The same speaker twice in a row occurs in real exports and is valid.

## Hard rules

- **The only write allowed is the scheduled job's upload to the one SFTP folder in
  Settings.** Never write to a database, S3, or any other endpoint, and never delete
  anything on the SFTP server (the consuming system removes old files). The UI's own
  output is a browser download. If a task seems to need any other write, stop and ask.
- **The hosted page must never send input or output data off the user's machine.**
  No upload endpoint, no analytics, no third-party scripts. `connect-src` in `_headers`
  is `'none'`, or exactly one origin, the Settings API, when `SETTINGS_API_URL` is set.
  The page sends only settings, Test SFTP and Run now requests there; files chosen in the
  page and the generated CSV are never sent.
- **Every Settings API request needs the admin key** (`x-admin-key`). `GET /settings`
  never returns the SFTP password.
- Never commit anything from `input/` or `output/`, and never commit
  `reference/event_table_sample.csv` — all contain real client contact data.
- No credentials, account codes, or endpoint URLs in source. Read them from the
  environment and fail loudly if absent.
- If generated output doesn't match the headers of `input/agentConversationEvents_nnnnn.csv` — same
  columns, same order, all names lowercase — fail the run. Don't coerce or silently drop columns.

## Stack and commands

NodeJS for the backend logic, and VueJS/HTML, when creating UI. The current UI is
plain HTML and vanilla JS, styled on `reference/Zoltar_AI_Dashboard_Intelligence_Demo.html`.
No npm dependencies for the page or `src/lib`. The Lambda (`lambda/`) has one,
`ssh2-sftp-client` (agreed 2026-11-05), in `lambda/package.json` only.

```
npm start              # UI on http://127.0.0.1:4310 (PORT, SETTINGS_API_URL optional)
npm test               # validator, generator and Lambda tests (node:test, fakes only)
npm run build          # dist/ for Cloudflare Pages
npm run build:lambda   # lambda-dist/mf-event-generator-lambda.zip for AWS
```

Tunable ratios and ranges live in `src/lib/config.js`. Everything in `src/lib/` must
stay browser-safe: no Node built-ins, because the same files are deployed as-is.

## Deployment

Cloudflare Pages, public link (anyone with the URL; `noindex` keeps it out of search).

- Root directory `event-management`, build command `npm run build`, output `dist`.
- `scripts/build.js` copies `src/ui/index.html`, `src/ui/favicon.jpg` and `src/lib/*.js` and writes
  `_headers` (CSP, noindex, no framing). No bundler, no dependencies.
- `dist/` is a build artifact and is not committed.
- Optional Pages environment variable `SETTINGS_API_URL` (the Lambda Function URL) turns on
  the Settings screen and adds that origin to `connect-src`.

AWS (weekly run): one Lambda (Node.js 22, handler `lambda/index.handler`), a Function URL
for the Settings API, one Secrets Manager secret, and an EventBridge Scheduler schedule.
Lambda environment variables: `SETTINGS_SECRET_ID`, `ADMIN_KEY`, `ALLOWED_ORIGIN`.
Deployed by hand from the zip, following `docs/lambda-deploy.md`.

## Run summary (print this every time)

Row count, per-event-code distribution, contacts touched vs. skipped, conversation
funnel (requested / completed / evaluated, with the abandon count), `audio_time`
min/median/max, date range produced, and the seed.

Sanity check against the sample's own shape: visits should dominate, completed
should be ≥95% of requested, and evaluated should equal completed exactly.

## Working style for this repo

- Plan before implementing anything that touches the schema.
- **Write the validator first.** It diffs generated output against the sample
  export's headers, dtypes, timestamp formats, and per-code field population rules.
  It is what catches schema drift, and it is more valuable than the generator itself.
