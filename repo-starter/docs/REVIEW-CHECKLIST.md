# Review checklist

Every pull request. The first two sections are the gate — a PR that fails them
is not reviewed, it is returned.

The point of a checklist is that it works when nobody is paying close attention.
Do not skip items because the change looks small.

---

## 1. The gate (mechanical)

- [ ] **A spec exists** in `docs/` and is linked from the PR description.
- [ ] **`npm run verify` passes**, and the run is attached or linked.
      (Runs the source checks, assembles template + Standard.html, then the browser tests.)
- [ ] **The forbidden list is clean.** From the repo root:

```
npm run check:forbidden
```

It must exit 0. It reads the rules from `scripts/check-forbidden.mjs`, which
mirrors the "Never" list in `CLAUDE.md`, and runs the same on Windows and Mac.

> **About the baseline.** `.forbidden-baseline.json` records violations that
> already existed when the gate was switched on, so the check fails only on
> **new** ones. That is how we run a strict gate against legacy code without
> stopping everything to fix it first.
>
> The baseline shrinks and never grows. When your change fixes baselined
> violations, the check says so — run `npm run check:forbidden -- --update` and
> commit the smaller file as part of your PR.
>
> **Adding to the baseline to make a check pass is not allowed.** If a rule is
> genuinely wrong, that is a conversation before the PR and a change to
> `CLAUDE.md` — a decision, not an exception.

---

## 2. The gate (human, two minutes)

- [ ] **The diff was read.** All of it. A diff nobody read is generated code,
      not reviewed code.
- [ ] **The change matches the spec.** Nothing extra. An agent that helpfully
      reorganised an unrelated file has failed, even if the result is better.
- [ ] **Acceptance criteria are ticked** in the spec, by someone who checked.

---

## 3. Correctness

- [ ] Every selector in new code matches something. A selector that matches
      nothing is a bug, not a no-op.
- [ ] No duplicate `id` attributes anywhere in the rendered page.
- [ ] Every function added is called. Every function removed had no callers.
- [ ] Every CSS class added is used. Every class removed was unused.
- [ ] Every font and library loaded is actually applied or called.
- [ ] Paired values still agree — `--mf-primary` and `--mf-primary-rgb` in
      particular.

---

## 4. Structure

- [ ] One agreed method per task, per CLAUDE.md. No second way of doing a thing
      the repo already does.
- [ ] No inline `style` for colour or layout.
- [ ] No table markup outside a `<table>`.
- [ ] Nothing was added to `standard.html` without something being removed.
- [ ] Nothing in `reference/` was modified.

---

## 5. Afterwards

- [ ] **Section 11 of the spec is filled in** — what went right, what went
      wrong, what the spec was missing.
- [ ] If the work hit something CLAUDE.md does not cover, a line was added to
      it, or an issue was opened to add one.

---

## Notes

**Who reviews.** Anyone on the team other than the author. The point is a second
pair of eyes, not seniority.

**What gets returned rather than fixed in review.** A missing spec, a failing
verify run, or a forbidden-list hit. Those are the author's to resolve. Reviewer
comments on everything else are normal review.

**When a deadline is tight.** The gate still applies. If it genuinely cannot,
that is an escalation to Kushal, recorded in the PR — not a quiet skip. The
first time this is skipped quietly, the process stops being a process.
