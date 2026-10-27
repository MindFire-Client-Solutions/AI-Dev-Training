# MindFire Blueprint Templates

Attract 2.1 Bootstrap 5 templates, with verification.

Read **[CLAUDE.md](CLAUDE.md)** before writing anything. It is short.

## Setup

```
npm install
npx playwright install chromium
npm run smoke
```

`npm run smoke` checks this machine can run everything. It must pass before
Session 1 — fix problems there, not during the session.

## Working on a change

1. Write a spec in `docs/`, from `docs/SPEC-TEMPLATE.md`. No code first.
2. Capture the baseline if there isn't one: `npm run baseline`, and commit it.
   Without a before state, verification has nothing to compare against.
3. Make the change with the agent, in the editor, against this repo.
4. `npm run verify`
5. Read the whole diff.
6. Open a PR. `docs/REVIEW-CHECKLIST.md` is the gate.

### Handing a spec to the agent

`CLAUDE.md` is read automatically every time. **A spec is not** — you have to
point the agent at it. Start `claude` in the repo folder and give it this:

```
Read docs/001-remove-jquery-and-sweetalert2.md.
Implement only what is in the Scope section.
Do not change anything listed under "Out of scope".
Stop and ask if something is unclear rather than guessing.
```

Name the file, bind it to Scope, and give it permission not to guess. The
out-of-scope list exists because the agent will otherwise notice other problems
and fix those too — which is how a small change becomes an unreviewable one.

Use the same shape every time, so everyone's work is comparable.

## Commands

| | |
|---|---|
| `npm run smoke` | Is this machine set up correctly? |
| `npm run check:forbidden` | Source rules. Instant. |
| `npm run assemble` | Build `dist/assembled.html` from template + `Standard.html` |
| `npm run verify` | Everything: source rules, assemble, browser tests |
| `npm run baseline` | Record new screenshot baselines (only when a visual change is intended) |

## How verification works

A published page is the template **plus** `Standard.html`, merged by the
platform at publish time, with `##tokens##` substituted. So three tiers:

| Tier | What | Speed |
|---|---|---|
| 1 | Source rules on template and `Standard.html` | instant |
| 2 | The two assembled locally, in a browser | seconds |
| 3 | A real published stage page | minutes |

Tier 2 is an approximation of what the platform produces. Validate it once
against a real published page before trusting it. Tier 3 is the only place
token substitution is actually proven.

## Layout

```
CLAUDE.md       the rules
docs/           specs, one per change
src/            template source — what we edit
reference/      read-only context. Never edit.
scripts/        assemble, checks, smoke test
tests/          Playwright
tokens/         dummy values for local rendering only
```
