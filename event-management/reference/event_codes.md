# Zoltar Voice-AI Event Reference

Derived from `input/agentConversationEvents_nnnnn.csv`, where `nnnnn` is the
account number (example: acct 38525, Aug 2026 — 59 rows, 5 contacts),
cross-checked against the JS SDK Event Dev Spec and the Matchback Requirements.

Where this document and `CLAUDE.md` disagree, **`CLAUDE.md` wins.** Where this
document and the export disagree about field names, types or formats, the export wins.

---

## 1. Codes in this stream

| Code | app_code | Name | Fires when |
|---|---|---|---|
| `30500` | `305` | Microsite / page visit | Visitor loads the Zoltar page. Written server-side by Studio. |
| `50531` | `505` | Conversation requested | Visitor initiates a voice session with the agent. |
| `50532` | `505` | Conversation completed | Session ends. Carries the recording, transcript, token usage and duration. |
| `50533` | `505` | Conversation evaluated | Post-call analysis finishes. Carries the structured summary and captured contact info. |

`app_code` is the first three digits of the event code. Never mix them.

These four map 1:1 onto the dashboard's "Requested → Completed → Evaluated"
grouped bars, plus visits in the funnel's top row.

### Codes NOT in this stream

Do not generate these unless explicitly asked — they belong to other campaign
types and will pollute the dashboard:

- `50101 / 50102 / 50103` — email, PURL, QR pass-through entry (microsite campaigns)
- `50124 / 50125 / 50126` — microsite page 1, page 2, form submit
- `50601 / 50602 / 50603` — matchback offline visit, cart add, purchase (proposed, not yet allocated)
- `3xxxx` other than `30500` — USPS mail-scan tracking

---

## 2. Journey model

```
30500 (visit, repeatable)
   └─> 50531 (requested)
          └─> 50532 (completed)  ──> 50533 (evaluated)
```

Generation targets (from `CLAUDE.md`):

- **Visits are frequent and standalone.** A contact accumulates visits over days
  or weeks. **20–25% of contacts with a `30500` go on to a `50531`**; the rest
  have `30500` only.
- **`50531` does not guarantee `50532`.** At least 95% of `50531` rows have a
  matching `50532`; 5% or fewer are abandoned. An orphan `50531` with no
  completion is valid data and must be generatable.
- **`50532` and `50533` are always paired** — every `50532` has exactly one `50533`.
- A contact can hold several complete conversations.

Observed in the acct 38525 example: 36 of 59 rows are `30500`; 9 requested,
7 completed, 7 evaluated.

### Timing

| Transition | Observed | Use |
|---|---|---|
| `50531` → `50532` | 17s – 5m 0s | Must approximately equal `audio_time` (it is the call duration) |
| `50532` → `50533` | 0.18s – 0.67s | Sub-second. Always same-second or next-second. |
| visit → `50531` | ~1–2 min | When a visit leads to a conversation at all |

`audio_time` observed range: **10.5 – 278.7 seconds**, median ~57s. Skew the
distribution short — most calls are under a minute.

### Timestamp formats — these differ by code

- `30500`: second precision, `.000Z` always — `2026-08-11T18:14:35.000Z`
- `50531/50532/50533`: millisecond precision — `2026-08-21T18:52:05.395Z`

Getting this wrong is the fastest way to make generated rows obviously synthetic.

---

## 3. Schema

The output header must match the header of `input/agentConversationEvents_nnnnn.csv`
exactly — same columns, same order. In the acct 38525 example:

```
PURL, email, firstname, isseed, lastname, address1, address2, city, phone,
state, zip, _id, event_code, event_date, event_path, event_element, app_code,
Browser, Device, OS, Platform, IP, audio_transcript, audio_recording,
tokens_used, audio_time, data, acctID
```

The export is a **contact ⋈ event join** — contact columns are denormalized onto
every event row and repeat identically for all of that contact's events.

### Contact block (identical across a contact's rows)

| Column | Notes |
|---|---|
| `PURL` | lowercase, no separators (`bethjackson`, `jordyncrosby`) |
| `_id` | 24-char hex Mongo ObjectId. **The contact ID, not the event ID.** |
| `firstname`, `lastname` | Populated |
| `email` | Sparse — present for ~1 of 5 contacts |
| `address1`, `address2`, `city`, `state`, `zip`, `phone` | **Empty in this export.** Leave empty. |
| `isseed` | Empty in this export. Flag for client test records. |
| `acctID` | `38525` — constant for the account |

### Event block

| Column | Notes |
|---|---|
| `event_code` | See §1 |
| `event_date` | See timestamp formats above |
| `event_path` | Bare host: `zoltar.secure.mdl.io`. Occasionally `zoltar.secure.mdl.io/{purl}`. **Not a full URL, no query string.** |
| `event_element` | Empty on `30500`. Agent name (`Sylvia`) on all 505xx. |
| `app_code` | `305` or `505`, matching the code prefix |

### Client fingerprint — varies by code

| Code | Browser | Device | OS / Platform | IP |
|---|---|---|---|---|
| `30500` | Firefox / Chrome / Edge / Safari | desktop, mobile | `Windows 10.0`/`Microsoft Windows`, `OS X`/`iPhone`, `Linux 64`/`Linux`, `Linux`/`Android`, `OS X`/`Apple Mac` | Real client IP + CDN hop, comma-separated: `2600:1700:...:a0b4, 18.68.47.239` |
| `50531` | Real browser (inherited from visitor) | desktop, mobile | Real | Blank |
| `50532` | Blank | Blank | Blank / Blank | Blank |
| `50533` | Blank | Blank | Blank / Blank | Blank |

`50532`/`50533` are server-generated, so `Browser`, `Device`, `OS` and `Platform`
are always blank regardless of what the visitor used. All `505xx` IPs are blank.
(The source export carries `unknown`/`desktop` and internal `::ffff:10.23.x.x`
IPs on these rows; per `CLAUDE.md`, output blanks them.)

### Payload columns — populated only on specific codes

| Column | Only on | Content |
|---|---|---|
| `audio_transcript` | `50532` | `https://mfdavinci.s3.us-west-1.amazonaws.com/file/voiceai/{uuid4}.txt` |
| `audio_recording` | `50532` | Same path, `.mp4` (5 of 7) or `.wav` (2 of 7) |
| `tokens_used` | `50532` | JSON, see below |
| `audio_time` | `50532` | Float seconds |
| `data` | `50533` only, and **only ~43% of them** | JSON, see below |

Everything else is empty string, not null.

> The `{uuid4}` in transcript and recording URLs is the **same UUID** for both
> columns on a given row. Generate one per conversation.

---

## 4. JSON payloads

### `tokens_used` — on `50532`

```json
{
  "input_tokens": 18647,
  "output_tokens": 656,
  "input_token_details": {
    "text_tokens": 18260, "audio_tokens": 387, "image_tokens": 0,
    "cached_tokens": 9472,
    "cached_tokens_details": {"text_tokens": 9152, "audio_tokens": 320, "image_tokens": 0}
  },
  "output_token_details": {
    "text_tokens": 144, "audio_tokens": 512, "image_tokens": 0,
    "cached_tokens": 0,
    "cached_tokens_details": {"text_tokens": 0, "audio_tokens": 0, "image_tokens": 0}
  }
}
```

Internal consistency to preserve when generating:

- `input_tokens` = `input_token_details.text_tokens` + `.audio_tokens` + `.image_tokens`
- `output_tokens` = same arithmetic on `output_token_details`
- `output_token_details.cached_tokens` is always `0` and its details are all `0`
- Token counts scale with `audio_time`. Observed: 10.5s → 18k input; 278.7s → 118k input.
  Roughly 350–450 input tokens per second of call, with wide variance.
- `image_tokens` is `0` everywhere.

### `data` — on `50533`, ~43% of rows

```json
{
  "phone": "949-395-3131",
  "email": "",
  "lastname": "jackson",
  "firstname": "beth",
  "requestCallback": "Y",
  "conversation_summary": [
    {"role": "assistant", "message": "..."},
    {"role": "user", "message": "..."}
  ]
}
```

- Contact fields here are **what the agent captured during the call**, not the
  contact record. They are inconsistently cased (`beth`, `bethjackson`) and
  frequently partial or wrong — the agent heard them spoken. Reproduce that
  messiness; do not clean it up.
- `requestCallback` is `"Y"` or `"N"`.
- `conversation_summary` alternates `assistant` / `user`, starts with `assistant`,
  and is written in third-person past tense ("The assistant introduced itself as...",
  "The user asked where the company is located."). Observed length: 3–11 turns,
  scaling with `audio_time`.
- When `data` is empty the `50533` row still exists. This is the normal case for
  more than half of evaluations.

---

## 5. PII handling

The JS SDK `event_data.query_params` blocklist **does not apply to this stream** —
there is no `event_data` column and no query-string capture here.

What does apply:

- The contact block carries real client PII (`email`, and potentially
  `phone`/address fields in other accounts). It is client data: never commit it,
  never post it anywhere outside `output/`.
- The `data` payload on `50533` is *supposed* to contain name, email and phone —
  that is the agent's captured lead info and the whole point of the field.

---

## 6. Open items

- [ ] `isseed` — confirm the values used to flag client test records.
- [ ] Whether `event_path` ever carries a full URL with query string in this stream.
