# Spec: [short title]

> Copy this file to `docs/NNN-short-name.md` and fill it in. Delete the
> quoted guidance lines as you go.
>
> The test of a finished spec: **could someone who has never seen this page
> build it correctly from the spec alone?** If not, it is not finished. The
> agent is exactly that person.

| | |
|---|---|
| **ID** | NNN |
| **Author** | |
| **Date** | |
| **Status** | Draft / In review / Approved / Done |
| **Target file(s)** | |

---

## 1. Goal

> One sentence. What is true after this change that is not true now?

---

## 2. Why

> Two or three sentences of background. Why are we doing this, and what happens
> if we do not? Link the finding in Appendix A if there is one.

---

## 3. Scope

**In scope**

-

**Out of scope**

> Be explicit. This is the section that stops an agent from helpfully
> reorganising things nobody asked it to touch.

-

---

## 4. Current behaviour

> What the page does today. For a conversion, this is the baseline the result
> will be compared against. Be concrete — file, line, what it does.
>
> Include the evidence: a count, a grep result, a screenshot. "It loads jQuery"
> is weaker than the line number and the version.

---

## 5. Required behaviour

> What it must do after the change. Where behaviour must be identical to today,
> say so explicitly — "identical" is a requirement, not an absence of one.

---

## 6. Constraints

> Rules from CLAUDE.md that bear directly on this change. Do not restate the
> whole file; name the ones that matter here, so they are in front of the agent
> at the right moment.

-

---

## 7. Acceptance criteria

> Testable statements. Each one must be answerable yes or no by a person or by
> the harness. If a criterion cannot be checked, it is a wish, not a criterion.

- [ ]
- [ ]
- [ ]

---

## 8. Verification

> What `npm run verify` must show. Name the specific assertions, not "tests
> pass".

- [ ] No console errors
- [ ] Expected scripts load; retired scripts absent
- [ ] DOM matches baseline except for the documented changes
- [ ] Screenshot matches baseline within tolerance
- [ ]

---

## 9. Reference files

> What the agent should read before starting. Paths in `reference/` or `src/`.

-

---

## 10. Open questions

> Anything unresolved. An open question here is fine — an open question
> discovered halfway through the build is not.

-

---

## 11. Notes after the work

> Filled in afterwards. What the agent got right, what it got wrong, what was
> missing from the spec, and whether anything should change in CLAUDE.md.
>
> This section is the point of the exercise. Do not skip it.
