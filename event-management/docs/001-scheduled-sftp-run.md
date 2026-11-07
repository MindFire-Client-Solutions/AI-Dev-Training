# Spec: Weekly scheduled run to SFTP, with a settings screen

| | |
|---|---|
| **ID** | 001 (event-management) |
| **Author** | kdutta, drafted with Claude |
| **Date** | 2026-11-05 |
| **Status** | Approved |
| **Target file(s)** | `lambda/` (new), `src/ui/index.html`, `scripts/build.js`, `CLAUDE.md`, `docs/lambda-deploy.md` (new) |

---

## 1. Goal

Once a week, without anyone opening the UI, a new demo events CSV is generated from two
fixed input files and uploaded to a configured SFTP folder. The SFTP folder and input
links can be changed from a Settings screen in the existing UI.

---

## 2. Why

The demo dashboard needs fresh events every week, because every `event_date` must fall in
the last 7 days. Doing that by hand (open UI, generate, download, upload to SFTP) is easy
to forget. If it is forgotten, the dashboard goes stale.

---

## 3. Scope

**In scope**

- One AWS Lambda function (Node.js 22) that does two jobs:
  - **Scheduled run**, invoked weekly by EventBridge Scheduler: fetch the two input files
    from their links, generate, validate, and upload the CSV to the SFTP folder.
  - **Settings API**, through a Lambda Function URL, used by the Settings screen:
    read settings, save settings, and "Run now".
- Settings stored as one JSON secret in AWS Secrets Manager.
- A Settings screen in the existing UI, shown only when the build is given the
  Settings API URL.
- A build script that packages the Lambda as a zip for manual upload.
- Step-by-step deployment instructions for the AWS console (`docs/lambda-deploy.md`).
- Tests for the Lambda handler, with fake HTTP and fake SFTP (no network).
- CLAUDE.md rule changes listed in §6.

**Out of scope**

- Any change to how the generator, validator or download work. The Lambda reuses
  `src/lib/*.js` exactly as the UI does.
- Writing to a database, S3, or any destination other than the one SFTP folder.
- Editing the schedule from the UI. The day and time are set in EventBridge.
- User accounts or logins beyond the admin key in §5.4.
- Infrastructure-as-code (CDK, SAM, Terraform). Deployment is manual, from the console.
- Multiple accounts or multiple SFTP destinations.

---

## 4. Current behaviour

- The generator runs only in the browser (`src/ui/index.html` imports `src/lib/*.js`).
  The output is a browser download. Nothing is scheduled.
- `scripts/build.js` writes `_headers` with `connect-src 'none'`, so the hosted page
  cannot call any server.
- CLAUDE.md hard rule: "Never write to a production endpoint, database, SFTP path, or S3
  bucket."
- The project has no npm dependencies.

---

## 5. Required behaviour

### 5.1 Settings (one Secrets Manager secret, JSON)

```json
{
  "contactsUrl": "https://…/contacts.csv",
  "eventsUrl":   "https://…/agentConversationEvents_38525.csv",
  "sftp": {
    "host": "sftp.example.com",
    "port": 22,
    "username": "demo-upload",
    "password": "…",
    "folder": "/incoming/demo-events"
  }
}
```

- The events file name used for the account check is the last path segment of
  `eventsUrl`. For example, `agentConversationEvents_38525.csv` must match `acctID`
  38525, exactly as in the UI.
- The secret name comes from the Lambda environment variable `SETTINGS_SECRET_ID`.

### 5.2 Scheduled run (EventBridge → Lambda)

1. Read settings from the secret. If any required field is missing, fail with a message
   that names the field.
2. Fetch both URLs. If either is not HTTP 200, fail.
3. Call `generate()` from `src/lib/generator.js` with `now` set to the run time and a
   random seed.
4. If any hard rule fails, upload nothing and fail. The failed rules and examples go to
   the log.
5. Connect to SFTP with username and password, and upload the CSV to `folder` as
   `agentConversationEvents_<acct>_generated_<YYYYMMDD-HHMMSS>.csv` (UTC). Never
   overwrite: names are unique per second.
6. Log the run summary (the same fields as the UI) and the uploaded path to CloudWatch.
7. On any failure, the Lambda invocation ends in an error, so a CloudWatch alarm or
   EventBridge retry can act on it.

### 5.3 Settings API (Lambda Function URL)

| Method and path | Does |
|---|---|
| `GET /settings` | Returns the settings. **The password is never returned**; the response says only `"passwordSet": true/false`. |
| `PUT /settings` | Saves the settings. A blank or missing password keeps the current one. Validates: URLs are `https://`, port is 1–65535, host and folder are not blank. |
| `POST /run` | Runs §5.2 now and returns the summary, the uploaded file name, or the error. |
| `POST /test-sftp` | Connects and lists the folder, without uploading. Returns ok or the error. |

- Every request must send the header `x-admin-key` matching the Lambda environment
  variable `ADMIN_KEY`. Otherwise it gets 401.
- CORS allows only the origin in the environment variable `ALLOWED_ORIGIN` (the Pages URL).

### 5.4 Why an admin key, when the data is fictitious

Without it, anyone with the Function URL could do three things:

- point the Lambda at any web address or server, which makes AWS fetch and connect to
  things on their behalf, at your cost;
- press Run now in a loop, also at your cost;
- replace the SFTP password and break the weekly job.

The key is entered once in the Settings screen and remembered in that browser.

### 5.5 Settings screen (UI)

- A **Settings** button in the top bar, shown only when the page was built with
  `SETTINGS_API_URL`. Without it, the page is identical to today.
- The panel has these fields: admin key, contacts link, events link, SFTP host, port,
  username, password (write-only, placeholder "unchanged" when one is set) and folder.
- Buttons: **Save**, **Test SFTP**, **Run now**. Each shows its result inline. Run now
  shows the same run summary as Generate.
- The existing upload, generate and download flow is **identical** to today. Files
  chosen in the page are still never sent anywhere.

### 5.6 Build

- `npm run build`: if the environment variable `SETTINGS_API_URL` is set, it is written
  into the page and into the CSP as the only `connect-src`. If not set, the CSP stays
  `connect-src 'none'`. Set it in Cloudflare Pages → Settings → Environment variables.
- `npm run build:lambda`: produces `lambda-dist/mf-event-generator-lambda.zip`
  containing the handler, `lib/*.js` (copied from `src/lib`) and `node_modules` from
  `lambda/package.json`.

---

## 6. Constraints (CLAUDE.md, and the changes this spec makes to it)

- **Rule change (agreed 2026-11-05):** "Never write to … SFTP path" becomes "The only
  write allowed is the scheduled job's upload to the one SFTP folder in Settings. Never
  write to a database, S3, or any other endpoint."
- **Rule change:** "The hosted page must never send input or output data off the user's
  machine" stays. The CSP may allow exactly one `connect-src`, the Settings API, and
  only for settings, test and run-now requests. Uploaded files and the generated CSV are
  never sent from the page.
- **Dependency (agreed 2026-11-05):** `ssh2-sftp-client`, in `lambda/package.json`
  only. `src/lib` and the page stay dependency-free and browser-safe.
- **No credentials or endpoint URLs in source.** The SFTP password lives in Secrets
  Manager. `ADMIN_KEY`, `SETTINGS_SECRET_ID` and `ALLOWED_ORIGIN` are Lambda environment
  variables. `SETTINGS_API_URL` is a Pages build variable.
- **Data note:** the contacts are fictitious demo people (owner's confirmation,
  2026-11-05). `input/` and `output/` are still never committed.

---

## 7. Acceptance criteria

- [ ] An EventBridge test invoke uploads one CSV to the SFTP folder. Its header is
      lowercase and every `event_date` is within the last 7 days of the run.
- [ ] A run with a broken input link, a failed rule, or wrong SFTP credentials uploads
      nothing and ends the invocation in an error that names the cause.
- [ ] `GET /settings` never contains the password.
- [ ] Saving with a blank password keeps the existing password.
- [ ] Any Settings API request without the right `x-admin-key` gets 401.
- [ ] A page built without `SETTINGS_API_URL` is byte-identical in behaviour to today and
      has `connect-src 'none'`.
- [ ] A page built with it shows Settings and its CSP allows only that one origin.
- [ ] The upload/generate/download flow works exactly as before on the hosted page.

---

## 8. Verification

- [ ] `npm test` passes. New tests cover the handler with fake fetch and fake SFTP:
      the success path, each failure path, the admin key, password masking, and keeping
      the password on a blank save.
- [ ] `npm run build` without the variable gives the same `_headers` as today.
- [ ] `npm run build:lambda` produces the zip, and the zip runs locally against a fake
      event.
- [ ] After deployment: Test SFTP, Run now and one EventBridge test invoke all succeed.

---

## 9. Reference files

- `CLAUDE.md` (this project) and the root `CLAUDE.md`.
- `src/lib/generator.js`: `generate()` and `outputFileName()`.
- `scripts/build.js`: `_headers` and the CSP.
- `src/ui/index.html`: top bar and run flow.

---

## 10. Open questions

- **Schedule (decided):** Sunday 22:00 Pacific time (`America/Los_Angeles`, so it stays
  at 10 PM local through daylight saving).
- **SFTP host key (decided):** not checked. The data is fictitious demo data.
- **Old files (decided):** the job only adds files. The consuming system picks them up
  and removes old ones; the generator never deletes anything on the SFTP server.
- **Admin key (decided):** keep it (§5.4).

---

## 11. Notes after the work

**Built** (2026-11-05):
- `lambda/app.js`: the run, the Settings API and the admin key check. Everything external
  (Secrets Manager, fetch, SFTP, clock) is passed in, so tests use fakes.
- `lambda/index.js`: the AWS wiring.
- `scripts/build-lambda.js`: the zip.
- Settings panel in `src/ui/index.html`.
- `SETTINGS_API_URL` handling in `scripts/build.js` and `src/server.js`.
- `docs/lambda-deploy.md`.

**Verified:**
- `npm test`: 47 passing, 12 of them for the Lambda.
- `npm run build` without the variable gives the same `_headers` as the live site
  (`connect-src 'none'`). With it, exactly one origin. `http://` is refused.
- The packaged zip was run against a throwaway local SFTP server built on the same `ssh2`
  library:
  - it signed in with a password and uploaded a CSV with a lowercase header;
  - Test SFTP listed the folder;
  - a wrong password failed and nothing was uploaded.
- `lambda/index.js` loaded from the zip with the AWS SDK found through `NODE_PATH`, as
  on Lambda.

**Not verified yet:**
- Against real AWS and the real SFTP server. That happens during deployment (§8, last item).
- The Settings panel in a browser against a deployed Function URL.

**What the spec missed:**
- **Not every write was covered.** The spec never said the job must never delete files
  on the SFTP server. It does not delete any; this is now in CLAUDE.md.
- **SFTP firewall.** A plain Lambda has no fixed IP, which matters if the SFTP server
  allow-lists addresses (noted in `docs/lambda-deploy.md`, step 0).
- **The AWS SDK and ES modules.** ES module imports cannot find the AWS SDK that Lambda
  provides, so `lambda/index.js` loads it with `require()`.
- **Windows packaging.** A zip built on Windows has to skip native add-ons, so `ssh2` runs
  in pure JavaScript on Lambda's Linux.
