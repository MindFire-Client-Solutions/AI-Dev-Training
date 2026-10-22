# Review checklist

Every pull request. The first two sections are the gate — a PR that fails them
is not reviewed, it is returned.

The point of a checklist is that it works when nobody is paying close attention.
Do not skip items because the change looks small.

---

## 1. The gate (mechanical)

- [ ] **A spec exists** in `docs/` and is linked from the PR description.
- [ ] **`npm run verify` passes**, and the run is attached or linked.
- [ ] **The forbidden list is clean.** Run this from the repo root:

```bash
npm run check:forbidden
```

Which is this, and it must return nothing from `src/`:

```bash
grep -rniE "jquery|dvtracking|console\.log|!important|form-group|btn-block|input-group-addon" src/
grep -rn "\$(" src/
```

> If something on the forbidden list is genuinely needed, that is a conversation
> before the PR, not a note inside it. The answer may well be yes — but it
> changes CLAUDE.md, and that is a decision, not an exception.

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
