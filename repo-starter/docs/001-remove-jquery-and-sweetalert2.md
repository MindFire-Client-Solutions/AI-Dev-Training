# Spec: Remove jQuery and sweetalert2 from published pages

> **Pilot.** This is the worked example for Session 1 and the model for the
> specs the team writes.

| | |
|---|---|
| **ID** | 001 |
| **Author** | Kushal Dutta |
| **Date** | 22 October 2026 |
| **Status** | Ready |
| **Target files** | `src/ATR21-Template-Bootstrap5.html`, `src/Standard.html` |

---

## 1. Goal

Remove jQuery and sweetalert2 from every page published from the Attract 2.1
Bootstrap 5 template, with the rendered page behaving and looking exactly as it
does today.

---

## 2. Why

The template loads **jQuery 2.2.4, unminified**. That version has publicly known
security vulnerabilities, and the unminified build is roughly three times the
bytes of the minified one. This is the current template, so every new page
published today carries it.

The fix already exists — Fung moved a page to 3.7.1 and recorded the reason in a
comment — but it never reached the template source. So the problem is ongoing
rather than ageing out.

jQuery turns out to have **two uses in the entire stack**, one of which is dead.
Removing both removes the library rather than upgrading it.

sweetalert2 is loaded on every page by `Standard.html` and is never called. It
was needed by the Social Selling Assistant, which has since become a separate
app with its own code. What is left here is the dependency, a mismatched
stylesheet, and three `!important` rules patching the result.

---

## 3. Scope

**In scope — `ATR21-Template-Bootstrap5.html`**

- Line 25: remove the jQuery `<script>` tag
- Line 240: replace `$(document).ready(...)` with `DOMContentLoaded`

**In scope — `Standard.html`**

- Lines 5–6: remove the sweetalert2 stylesheet and script
- The three `.swal2-*` CSS rules in the `<style>` block
- Lines 29–33: remove the `$('#rzForm option').each(...)` script
- `validateZip()` and `validateZipUS()`

**Out of scope**

- `validateEmail()` and `validatePhone()`. Dead on this template, but called by
  generated forms on templates that have one. They go with the form generator.
- The `linearTab` / `linearHeader` / `linearRow` / `linearCell` CSS and the
  `/*#1942*/` comment. Unused on the pages examined, but `Standard.html` is
  shared across six templates and the other five have not been checked.
- The `dvtracking` script. Separate change — needs the endpoint, consumer and
  double-counting checks first.
- `global.js`. Not referenced by this template or by any page reviewed.
- Fonts, colours, layout, formatting, naming. Not this change.
- Anything in `reference/`.

---

## 4. Current behaviour

### jQuery — every use, both files

```
ATR21-Template-Bootstrap5.html:25
  <script src=".../jquery/2.2.4/jquery.js"></script>

ATR21-Template-Bootstrap5.html:240
  $(document).ready(function() {
      let inpurl = "##purl##";
      let page = window.location.pathname.split("/").pop().toLowerCase();
      page = clearifyPage(page, inpurl);
      const comefromWelcome = ["welcome.html", "qr.html", "email.html", "id.html"];
      ...
      AddEventOnLoadPage(inpurl, page);
  });

Standard.html:29-33
  $('#rzForm option').each(function(){
      var text=$(this).text()
      if (text.length>56)
          $(this).val(text).text(text.substr(0,55)+'…')
  });
```

Nothing else uses it. `lead2.js`, `event-persistent-script-v100.js` and
`dvtracking.js` contain zero jQuery references.

The `Standard.html` loop is **dead**: the Bootstrap 5 template has no `<form>`,
no `<select>` and no `<option>`.

### sweetalert2 — `Standard.html`

```html
<link href=".../limonte-sweetalert2/6.7.0/sweetalert2.css" rel="stylesheet">
<script src=".../limonte-sweetalert2/7.11.0/sweetalert2.all.min.js"></script>
```

```css
.swal2-container { z-index: 2051 !important; }
/*Fix sweet alert very small */
.swal2-popup { font-size: 1.55rem !important; }
/* Set calendar display with swal*/
.swal2-container, .swal2-popup { overflow: visible !important; }
```

Stylesheet from version 6, script from version 7. Confirmed unused: not called
by either published page, nor by `dvtracking.js`, `lead2.js`,
`event-persistent-script-v100.js` or `pixel-v030.js`.

### Unused validators — `Standard.html`

`validateZip()` and `validateZipUS()` are defined on every page and called by
nothing.

---

## 5. Required behaviour

The rendered page is **identical** to baseline in appearance and behaviour.

Unchanged: layout, colours, fonts, every `##token##`, tracking calls, the
`AddEventOnLoadPage` call and its arguments, and the order in which scripts run.

Changed: the page no longer downloads jQuery or sweetalert2.

**The replacement for line 240:**

```js
document.addEventListener('DOMContentLoaded', function () {
    // body unchanged
});
```

`$(document).ready` and `DOMContentLoaded` are not identical — jQuery fires its
callback immediately if the document is already parsed, `DOMContentLoaded` does
not fire at all if registered late. Here the script tag is inline near the end of
`<body>`, so the listener is registered before parsing completes and the
behaviour matches. **Confirm this in verification; do not assume it.**

---

## 6. Constraints

From `CLAUDE.md`:

- No library may be added, and none substituted for jQuery
- `DOMContentLoaded`, never `$(document).ready`
- A selector that matches nothing is a bug, not a no-op
- Nothing is added to `Standard.html` in this change
- Nothing in `reference/` is edited

---

## 7. Acceptance criteria

- [ ] No jQuery `<script>` tag in the template
- [ ] No `$(` anywhere in either file
- [ ] `AddEventOnLoadPage` still called, with the same two arguments
- [ ] No sweetalert2 stylesheet or script in `Standard.html`
- [ ] The three `.swal2-*` rules removed
- [ ] `validateZip` and `validateZipUS` removed
- [ ] `validateEmail` and `validatePhone` still present and unchanged
- [ ] `linearTab` CSS untouched
- [ ] `dvtracking` script untouched
- [ ] Every `##token##` present and unaltered
- [ ] Diff is deletions, plus the single rewritten line

---

## 8. Verification

- [ ] No console errors
- [ ] Network: jQuery not requested
- [ ] Network: sweetalert2 CSS and JS not requested
- [ ] Network: every other request identical to baseline
- [ ] `AddEventOnLoadPage` fires, with the same arguments as baseline
- [ ] DOM matches baseline except for the removed tags and rules
- [ ] Screenshot matches baseline at 1280px and 390px
- [ ] Tokens render identically on a published stage page

> Tiers 1 and 2 run against template + `Standard.html` assembled locally. Tier 3
> runs against a published stage page and is the only place token substitution
> is proven.

---

## 9. Reference files

- `reference/ATR21-Template-Bootstrap5.html`
- `reference/Standard.html`
- `reference/lead2.js` — defines `AddEventOnLoadPage` and `clearifyPage`
- `reference/event-persistent-script-v100.js`
- `docs/appendix-a-findings.md` — F1, F5, F7

---

## 10. Open questions

- Do the other five templates use the `linearTab` classes? Decides whether that
  CSS can go in a follow-up.
- Is `global.js` loaded on any live landing page, or is it admin-only? It uses
  `.success()` and `.error()`, both removed in jQuery 3.0, so if it shares a
  page with an upgraded jQuery it fails silently.
- Which stage account do we publish to for Tier 3?

---

## 11. Notes after the work

*Filled in during Session 1.*

- What the agent got right:
- What it got wrong:
- What this spec was missing:
- Changes to `CLAUDE.md`:
