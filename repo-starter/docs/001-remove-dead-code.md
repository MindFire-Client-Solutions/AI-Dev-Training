# Spec: Remove dead code from the Attract 2.1 template

> **Worked example.** This is the pilot spec and the model for the ones the team
> writes. Items marked `[TBC]` need the real template before this can be built.

| | |
|---|---|
| **ID** | 001 |
| **Author** | Kushal Dutta |
| **Date** | 20 October 2026 |
| **Status** | Draft — awaiting template source |
| **Target file(s)** | `src/[TBC].html`, `reference/standard.html` |

---

## 1. Goal

Remove code and assets that nothing on the page uses, with the rendered page
behaving and looking exactly as it does today.

---

## 2. Why

`standard.html` is copied into every published page and has only ever grown.
Appendix A lists fourteen items that are dead on both pages reviewed, including
four font files downloaded on every page and never applied to anything.

This is the pilot for the Claude Code workflow. It was chosen because the
correct result is *identical output* — which gives an objective answer to
whether the agent broke something, and makes it a good first exercise.

---

## 3. Scope

**In scope**

- Dead items D1–D13 from Appendix A, where they appear in this template
- Debug code: unused `FormData` object, the timezone block that calculates and
  discards, `console.log` calls
- Duplicated Email and Phone validation blocks

**Out of scope**

- `.well` (D14). Still used by generated markup. Removed with the form
  generator, not here.
- sweetalert2. `dvtracking.js` is ruled out as a consumer, but `lead2.js` and
  `pixel-v030.js` have not been checked. Leave it.
- The retired `dvtracking` script. Separate change, separate spec — it needs the
  endpoint and double-counting checks first.
- Restructuring, renaming, reformatting, or improving anything. Deletion only.
- Anything in `reference/`.

---

## 4. Current behaviour

From the two pages reviewed (Appendix A, F5):

| Item | Evidence |
|---|---|
| `$('#rzForm option').each(...)` | Neither page has a `<select>` or `<option>`. Count of each: 0 |
| `validateZip()` | Defined. Never called |
| `validateZipUS()` | Defined. Never called |
| `.linearTab`, `.linearHeader`, `.linearRow`, `.linearCell` | Four CSS rules. No element uses them |
| `.rz-readonly` (3 rules) | No element uses the class |
| `/*#1942*/` | Ticket number shipped in CSS |
| Mission Gothic Bold, Mission Gothic Regular, Avenir Black | `@font-face` + WebFont. No rule applies them |
| Nunito Sans | Requested from Google Fonts. No rule uses it |
| `background-color: https://...png` | A URL is not a colour. Ignored by every browser |
| `var form_data = new FormData(...)` | Built, never read |
| Timezone block | Calculates, logs, returns nothing |
| `console.log` × 5 | Debug output in a published page |
| Email, Phone validation | Each block written twice, verbatim |

`validateEmail()` and `validatePhone()` **are** called on a page with a form.
Keep them.

---

## 5. Required behaviour

The rendered page is **identical** to the baseline in appearance and behaviour.

Specifically unchanged: layout, colours, fonts actually in use, form submission,
validation messages, tracking calls, and every personalisation token.

Changed: the page no longer downloads four unused font files, and the listed
dead code is gone.

---

## 6. Constraints

From CLAUDE.md:

- No new library, no replacement library
- Nothing in `reference/` is edited
- A selector that matches nothing is a bug — if removing something reveals one,
  raise it, do not fix it quietly in this change
- Nothing is added to `standard.html` in this change

---

## 7. Acceptance criteria

- [ ] Items D1–D13 are absent from the rendered page, where present today
- [ ] `validateEmail` and `validatePhone` still present and still called
- [ ] `.well` untouched
- [ ] sweetalert2 untouched
- [ ] `dvtracking` untouched — separate change
- [ ] Email and Phone each validated exactly once per submission
- [ ] No `console.log` in the rendered page
- [ ] Zero font files downloaded that no rule applies
- [ ] Nothing in `reference/` modified
- [ ] Diff is deletions only, apart from the de-duplicated validation

---

## 8. Verification

- [ ] No console errors
- [ ] Network: the four unused font files are no longer requested
- [ ] Network: every other request identical to baseline
- [ ] DOM matches baseline except for the removed `<style>` and `<script>` content
- [ ] Screenshot matches baseline within tolerance at 1280px and 390px wide
- [ ] Form submits; validation messages appear as before
- [ ] Submitted payload identical to baseline

---

## 9. Reference files

- `reference/standard.html`
- `reference/global.js` `[TBC]`
- `reference/[published-page].html` `[TBC]` — shows what the platform injects
- `docs/appendix-a-findings.md` — F5 and the D1–D14 table

---

## 10. Open questions

- Which Attract 2.1 template is the pilot? `[TBC]`
- Does the template render locally, or only once published? Decides whether the
  baseline is captured from a local render or a published stage page.
- Are the four fonts unused on *every* template, or only these two pages? If a
  sibling template uses Mission Gothic, it stays in `standard.html` and is
  removed per-template instead.

---

## 11. Notes after the work

*To be filled in during Session 1.*

- What the agent got right:
- What it got wrong:
- What this spec was missing:
- Changes to CLAUDE.md:
