# Event Generator for Demo Dashboard

Generates randomized-but-realistic MindFire voice-AI event records for a given list of contacts,
so the client's dashboard and downstream pipelines can be exercised 
without waiting on live campaign traffic.

## What this project does

Reads `input/contacts.csv` and `input/agentConversationEvents_nnnnn.csv` where nnnnn is the account number.
During every run, the system will produces event rows for those contacts, matching the schema of the real event export exactly. 
Output goes to `output/` and nowhere else.

**Output target:** CSV in the same format as `reference/Sample_Conversation_Export.csv`

## Reference material (read these before writing any code)

| File | What it is |
|---|---|
| `reference/Sample_Conversation_Export.csv` | **Real export** — AI conversations, acct 38525, Aug 2026. 59 rows, 5 contacts. The schema here is authoritative. |
| `reference/event_codes.md` | Codes, journey model, field rules, JSON payload shapes — all derived from that export. |
| `reference/Sample_Conversation_Export.html` | Design reference for any preview UI. |

If `reference/event_codes.md` disagrees with the export about field names, types, or formats, **the export is correct** 
Tell me about the conflict rather than picking one silently.

## The schema in one paragraph

The export is a **contact ⋈ event join**: 32 columns, where the contact block
(`PURL`, `_id`, `firstname`, `lastname`, `email`, address fields, `acctID`) 
repeats identically on every one of that contact's event rows, and the event block
(`app_code`, `event_code`, `event_date`, `event_path`, `event_element`, `app_code`)
and other columns listed in the file `reference/Sample_Conversation_Export.csv`
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
- The final column of the output file will be acctID, and it will have the value copied from the column acctID in the `input/agentConversationEvents_nnnnn.csv` file.
- Only the four codes in `reference/event_codes.md` §1: `30500`, `50531`, `50532`, `50533`. 
  Never emit any other codes — they belong to different campaign types and will pollute the dashboard.
- `app_code` is always the first three digits of `event_code`.
- During every run, use a randomly selected number of contacts which will have a distribution of event records associated with each of them, following the `Journey model` described below. To clarify, 70-80% of the contacts selected should have 30500 only. Of the rest, all will have 50531, and about 95% of more will have 50532 and 50533. Any contact that has a 50532, must have a cirresponding 50533.
- Please make sure to copy event records from matching contact _ids for the same contact in the output file.
- The contact block in the output file may change from the `input/agentConversationEvents_nnnnn.csv` as long as it matches with a contact row in the file `input/contacts.csv`, the input file only has the contact block to identify which event rows go together with the same contact.

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
- `50532` and `50533` are server-generated: `Browser`/`OS`/`Platform` and `Device` should always be blank or null, regardless of the visitor's real device.
- `30500` IP is `{real client IP}, {CDN hop IP}`. All `505xx` IPs should be blank or null.

### Payload rules

- `audio_transcript`, `audio_recording`, `tokens_used`, `audio_time` appear **only**
  on `50532`. Transcript and recording share the same UUID per conversation.
- `data` appears **only** on `50533`. An empty `data` on an evaluated row can occur.
- `tokens_used` must be internally consistent — `input_tokens` equals the sum of its
  detail fields, and counts scale with `audio_time`. See `reference/event_codes.md` §4.
- `conversation_summary` entries are third-person past tense, alternate assistant/user, and start with assistant.

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

NodeJS for the backend logic, and VueJS/HTML, when creating UI.

## Run summary (print this every time)

Row count, per-event-code distribution, contacts touched vs. skipped, conversation
funnel (requested / completed / evaluated, with the abandon count), `audio_time`
min/median/max, date range produced, and the seed.

Sanity check against the sample's own shape: visits should dominate, completed
should trail requested by ~10%, and evaluated should equal completed exactly.

## Working style for this repo

- Plan before implementing anything that touches the schema.
- **Write the validator first.** It diffs generated output against the sample
  export's headers, dtypes, timestamp formats, and per-code field population rules.
  It is what catches schema drift, and it is more valuable than the generator itself.
