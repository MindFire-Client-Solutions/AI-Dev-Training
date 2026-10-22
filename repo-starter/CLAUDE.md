# CLAUDE.md — MindFire Blueprint Templates

Rules for this repo. Read this before writing anything. If a rule here conflicts
with something you find in the existing code, this file wins — the existing code
is what we are moving away from.

Keep this file short. If it grows past two screens, something belongs in `docs/`
instead.

---

## What this repo is

Bootstrap 5 landing page and microsite templates for the Attract blueprint.
Pages are published through the DV platform, which appends `standard.html` at
publish time.

The Bootstrap 3 templates and the form editor are **not** part of this repo and
are not being carried forward. Forms here are written as HTML.

---

## How we work

1. **Specification first.** No code until there is a spec in `docs/`. Use
   `docs/SPEC-TEMPLATE.md`. Fixing code that was written without a spec takes
   far longer than writing the spec.
2. **Generate against the spec**, with the agent running in the editor against
   this repo — not pasted into a browser chat.
3. **Verify.** `npm run verify` must pass before review. A change that cannot be
   verified is not finished.
4. **A person reviews every change.** See `docs/REVIEW-CHECKLIST.md`. The agent
   does the work; it does not approve the work.

---

## Stack

| | |
|---|---|
| CSS | Bootstrap 5.3 |
| JavaScript | Vanilla. ES2020+ |
| Tests | Playwright |

**No new third-party library** goes in without agreement. If a task seems to
need one, stop and ask rather than adding it.

---

## One agreed way to do each thing

These exist because we found the same job done two different ways across two
pages. Pick the version below every time.

### Page load

```js
// yes
document.addEventListener('DOMContentLoaded', function () { ... });

// no — jQuery is not available in this repo
$(document).ready(function () { ... });
```

### Event binding

Always delegate from a stable ancestor. Never bind directly to an element at
load time.

```js
// yes — survives re-renders, works on static pages and on framework pages
document.addEventListener('click', function (e) {
    var el = e.target.closest('[data-mf-track]');
    if (!el) return;
    recordEvent(el.dataset.mfTrack);
});

// no — destroyed the moment the page re-renders itself
document.querySelectorAll('[data-mf-track]').forEach(function (el) {
    el.addEventListener('click', ...);
});
```

This matters most on customer-supplied pages. When a customer gives us markup
built with Vue or React, **we do not adopt their framework**. We attach to the
DOM with delegation. A page that redraws itself will destroy directly-bound
listeners; a document-level listener survives.

### Validation

One function per field type, defined once, called per field. Do not repeat a
check, and do not define validators that nothing calls.

```js
// yes
const validators = {
    email: (v) => /.../.test(v),
    phone: (v) => /.../.test(v),
};
```

### Colour

Define a colour once and derive everything from it. Never maintain the same
colour as two separate values that can drift apart.

```css
/* yes */
:root {
    --mf-primary: #ff6633;
    --mf-primary-rgb: 255, 102, 51;  /* must match --mf-primary */
}

/* no — inline colour on an element */
<a style="background-color:#38b6ff">
```

If you change one, change both in the same edit. A mismatch here renders the
wrong colour on hover and nobody notices for months.

### Selectors

A selector that matches nothing is a bug, not a no-op. Before writing to an
element, confirm it exists in the markup you are editing.

```js
// yes
const el = document.getElementById('pageSubmitDate');
if (!el) throw new Error('pageSubmitDate missing');
el.value = submitTime;
```

Silent failure is how we lost submit-time and lead-level data on existing pages.

---

## Never

Each of these is in the repo's forbidden list and is checked at review:

- `jquery` — any version, any form
- `$(` as a selector
- `dvtracking` — retired tracking, replaced by the current stack
- `console.log` in a published page
- Duplicate `id` attributes
- `<tr>` or `<td>` without a `<table>` ancestor — email markup does not get
  pasted into a page, it gets converted
- `!important`, unless a comment on the same line explains why
- Inline `style` for colour or layout
- `@font-face` or a webfont request for a font no rule applies
- Bootstrap 3 class names: `form-group`, `btn-block`, `input-group-addon`, `well`

---

## File layout

```
CLAUDE.md             this file
docs/                 specifications, one per change
  SPEC-TEMPLATE.md
  REVIEW-CHECKLIST.md
src/                  template source
tests/                Playwright specs
tests/baseline/       captured baselines
reference/            read-only: current standard.html, global JS, sample pages
```

`reference/` is context for the agent. **Never edit anything in `reference/`.**

---

## Tokens

Personalisation tokens use the `##token##` form and are substituted by the
platform at publish time. Leave them exactly as written — do not reformat,
re-case, or "tidy" them. They are not placeholders to fill in.

*(Confirm the exact token syntax against a published page before relying on
this.)*

---

## Tracking

The current stack is `pixel-v030.js`, `lead2.js`, and
`event-persistent-script-v100.js` where the voice component is present.

`dvtracking` is retired. It must not appear in any page produced from this repo.

---

## When you are not sure

Stop and ask. Do not guess at:

- whether a library may be added
- whether an existing behaviour is intentional or a bug
- what a page is supposed to do when the spec does not say

A question costs a few minutes. A wrong assumption built on for a week costs
considerably more.

---

## Changing this file

When real work hits something these rules do not cover, add a line. Keep it
specific, and keep an example. Owner: Kwang.
