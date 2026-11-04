# Zoltar Event Generator

Generates randomized-but-realistic MindFire voice-AI event records for a supplied
list of contacts, so the Zoltar dashboard and downstream pipelines can be
exercised without waiting on live campaign traffic.

## What this project does

Reads `input/contacts.csv` and produces event rows for those contacts, matching
the schema of the real event export exactly. Output goes to `output/` and nowhere else.

**Output target:** <!-- PICK ONE AND DELETE THE REST -->
- [ ] CSV in the same 32-column shape as `reference/event_table_sample.csv`
- [ ] JSON batch posted to the NexGen event_post Lambda endpoint
- [ ] Direct insert into the event store

## Reference material (read these before writing any code)

| File | What it is |
|---|---|
| `reference/event_table_sample.csv` | **Real export** — Zoltar conversations, acct 38525, Aug 2026. 59 rows, 5 contacts. The schema here is authoritative. |
| `reference/event_codes.md` | Codes, journey model, field rules, JSON payload shapes — all derived from that export. |
| `reference/Zoltar_AI_Dashboard_Intelligence_Demo.html` | Design reference for any preview UI. |

If `reference/event_codes.md` disagrees with the export about field names, types,
or formats, **the export is correct** — tell me about the conflict rather than
picking one silently.

## The schema in one paragraph

The export is a **contact ⋈ event join**: 32 columns, where the contact block
(`PURL`, `_id`, `firstname`, `lastname`, `email`, address fields, `acctID`) repeats
identically on every one of that contact's event rows, and the event block
(`event_id`, `event_code`, `event_date`, `event_path`, `event_element`, `app_code`,
client fingerprint, payload columns) varies per row. `_id` is the **contact**
ObjectId; `event_id` is the per-row ObjectId. Empty cells are empty strings, not nulls.

## Generation rules

- Every row maps to a contact that exists in `input/contacts.csv`. Never invent one.
- Contact-block columns are copied verbatim from the contacts file onto every row
  for that contact. Columns absent from the contacts file are emitted empty.
- Only the four codes in `reference/event_codes.md` §1: `30500`, `50531`, `50532`,
  `50533`. Never emit `50101/50102/50103`, `50124/50125/50126`, `506xx`, or other
  `3xxxx` codes — they belong to different campaign types and will pollute the dashboard.
- `app_code` is always the first three digits of `event_code`.
- Randomization is seeded. The seed is a CLI argument, defaults to a fixed value,
  and is printed in the run summary. Same seed ⇒ identical output.
- `event_id` is a fresh 24-char hex ObjectId per row, never repeated.

### Journey model — this is the core of the generator

```
30500 (visit, repeatable, most contacts never go further)
   └─> 50531 (requested)   ~20-25% abandon here, producing a valid orphan row
          └─> 50532 (completed)  ──> 50533 (evaluated, always paired)
```

Model each contact as a sequence of sessions over the campaign window, not as
independent random rows. Make the drop-off ratios configurable constants, not
magic numbers buried in the code.

Timing constraints that must hold:

- `50531` → `50532` gap ≈ `audio_time` (it *is* the call duration): 17s–5m observed
- `50532` → `50533` gap is sub-second: 0.18–0.67s observed
- `30500` timestamps are second-precision ending `.000Z`; all `505xx` timestamps
  carry milliseconds. **Do not use one format for both.**

### Fingerprint rules

- `30500` and `50531` carry a real browser/device/OS fingerprint.
- `50532` and `50533` are server-generated: `Browser`/`OS`/`Platform` are literally
  `unknown` and `Device` is always `desktop`, regardless of the visitor's real device.
- `30500` IP is `{real client IP}, {CDN hop IP}`. All `505xx` IPs are internal
  `::ffff:10.23.x.x`.

### Payload rules

- `audio_transcript`, `audio_recording`, `tokens_used`, `audio_time` appear **only**
  on `50532`. Transcript and recording share the same UUID per conversation.
- `data` appears **only** on `50533`, and only on roughly 43% of them. An empty
  `data` on an evaluated row is the normal case.
- `tokens_used` must be internally consistent — `input_tokens` equals the sum of its
  detail fields, and counts scale with `audio_time`. See `reference/event_codes.md` §4.
- `conversation_summary` entries are third-person past tense, alternate
  assistant/user, and start with assistant.

## Hard rules

- **Never write to a production endpoint, database, SFTP path, or S3 bucket.**
  Output to `output/` only. If a task seems to require a live write, stop and ask.
- Never commit anything from `input/` or `output/`, and never commit
  `reference/event_table_sample.csv` — all contain real client contact data.
- Generated emails, phones and names must be obviously synthetic. **Never reuse a
  real value from the sample export** in generated output.
- No credentials, account codes, or endpoint URLs in source. Read them from the
  environment and fail loudly if absent.
- If generated output doesn't match the sample export's headers exactly — same
  columns, same order — fail the run. Don't coerce or silently drop columns.

## Stack and commands

Python 3.11. Standard library plus `pandas` only; ask before adding a dependency.

```bash
python -m src.generate \
  --contacts input/contacts.csv \
  --acct-id 38525 \
  --host zoltar.secure.mdl.io \
  --agent Sylvia \
  --start 2026-10-01 --end 2026-10-31 \
  --seed 42 \
  --out output/events_20261101.csv

pytest
```

## Run summary (print this every time)

Row count, per-event-code distribution, contacts touched vs. skipped, conversation
funnel (requested / completed / evaluated, with the abandon count), `audio_time`
min/median/max, date range produced, and the seed.

Sanity check against the sample's own shape: visits should dominate, completed
should trail requested by ~20%, and evaluated should equal completed exactly.

## Working style for this repo

- Plan before implementing anything that touches the schema.
- One small module per concern: `contacts.py` (load/validate), `journey.py`
  (session sequencing), `events.py` (row construction), `payloads.py`
  (tokens_used / data JSON), `writers.py` (output format), `generate.py` (CLI).
- **Write the validator first.** It diffs generated output against the sample
  export's headers, dtypes, timestamp formats, and per-code field population rules.
  It is what catches schema drift, and it is more valuable than the generator itself.
