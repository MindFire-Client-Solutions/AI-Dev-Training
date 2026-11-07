# Demo Event Generator

Builds a realistic MindFire voice-AI event export (`30500`, `50531`, `50532`,
`50533`) for a list of contacts, so the dashboard and downstream pipelines can be
exercised without live campaign traffic.

Rules and schema: [`CLAUDE.md`](CLAUDE.md). Design and decisions:
[`docs/event-generator-plan.md`](docs/event-generator-plan.md).

## Requirements

- Node.js 20 or later. No `npm install` is needed: there are no dependencies.

## Run it locally

From the `event-management` folder:

```
npm start
```

Then open **http://127.0.0.1:4310** in your browser. To use another port:
`PORT=5000 npm start` (macOS/Linux) or `$env:PORT=5000; npm start` (PowerShell).

Stop the server with `Ctrl+C`.

> **Don't double-click `src/ui/index.html`.** The page will display, but the
> Generate button stays disabled. Browsers block the page's JavaScript modules
> when it is opened from disk (`file://`), and the module paths only resolve
> through the server. Always use `npm start`, or the hosted link.

## Use it

1. **Contacts:** choose `contacts.csv` (needs an `_id` column; `purl`, `firstname`,
   `lastname`, `email` are copied when present).
2. **Events:** choose `agentConversationEvents_nnnnn.csv`, where `nnnnn` is the
   account number. It must match the file's `acctID` column.
3. **Seed (optional):** leave it blank for a random run, or enter a whole number from 0 to
   4,294,967,295. The same seed repeats which contacts, conversations and source rows
   are used; dates move with the time of the run.
4. Click **Generate**.

The page shows the run summary (rows, events per code, contacts touched and skipped,
the funnel, `audio_time`, date range, seed), every validation rule, the funnel
targets, and a preview of the first 50 rows.

- **Download CSV** saves the file from the browser. It is the only output.
- If any validation rule fails, no file is produced; the failing rows are listed.

Input files are processed in the browser and never uploaded.

## Test

```
npm test
```

Runs the validator and generator tests (`node:test`, synthetic fixtures only).

## Deploy (Cloudflare Pages)

```
npm run build
```

Creates `dist/` (the page, its modules, and `_headers`). Then either:

Live at **https://mfi-event-generator.pages.dev/**, connected to GitHub: every merge to
`main` redeploys. To set up a new one:

- **Connect to Git** (Workers & Pages → Create application → "Continue to Pages" →
  Import an existing Git repository):
  root directory `event-management`, build command `npm run build`, output
  directory `dist`. Every push to the production branch redeploys.
- **Upload assets:** drag the `dist` folder into a new Pages project.
- **CLI:** `npx wrangler pages deploy dist` (after `wrangler login`).

The hosted page is kept out of search engines (`noindex`) and can talk to nothing
(`connect-src 'none'`), except the Settings API once `SETTINGS_API_URL` is set. As
locally, the CSV is a browser download only.

## Weekly SFTP run (AWS)

Every Sunday at 10 PM Pacific, an AWS Lambda fetches the two input files from links,
generates a new CSV with the same rules as the UI, and uploads it to an SFTP folder. Change
the links and the SFTP folder from the **Settings** button in the UI (needs the admin key).

```
npm run build:lambda
```

Creates `lambda-dist/mf-event-generator-lambda.zip`. Deploy it with the console steps in
[`docs/lambda-deploy.md`](docs/lambda-deploy.md). Spec:
[`docs/001-scheduled-sftp-run.md`](docs/001-scheduled-sftp-run.md).

## Tuning

Ratios and ranges (selection share, request and abandon rates, sessions and visits
per contact, the 7-day window) live in [`src/lib/config.js`](src/lib/config.js).

## Data handling

The contacts are fictitious demo people. `input/` and `output/` are still gitignored;
never commit them. The SFTP password is stored only in AWS Secrets Manager and is never
shown in the UI.
