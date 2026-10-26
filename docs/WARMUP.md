# Warm-up task

**Pre-work task 13.** About 20 minutes. Do this before Session 1.

The change itself is trivial on purpose. What you are practising is the loop:
ask, review, verify, commit. Not the change.

---

## A note on names

The tool is called **Claude Code**. The command you type is **`claude`**, in
lower case. There is no `claude-code` command. Typing `claude` in a terminal
inside the repo folder starts Claude Code.

This is a different thing from the Claude website and from Cowork. Claude Code
runs on your machine and edits the files in this repo directly.

---

## Steps

### 1. Open the repo in VS Code

File → Open Folder → the repo folder. Then open a terminal inside VS Code:
**Terminal → New Terminal**. Check the prompt shows the repo folder.

### 2. Make a branch

```
git checkout -b warmup/<yourname>
```

For example: `git checkout -b warmup/fung`

### 3. Start Claude Code

```
claude
```

The first thing it does is read `CLAUDE.md` from the repo root. You do not
paste it in and you do not mention it. That happens every time, in every
session, on every machine.

### 4. Give it this task

Type this, replacing the name and role with your own:

```
Add a row to the table in docs/team.md for Fung, role "Developer, templates".
Keep the existing rows and the column order unchanged.
```

### 5. Read what it changed

Before you accept anything, look at the diff. In the VS Code source control
panel, or:

```
git diff
```

Check three things:

- Did it change only `docs/team.md`?
- Is your row correct?
- Did it leave the other rows alone?

If it changed anything else, that is worth knowing. Write it down — see step 8.

### 6. Run the checks

```
npm run verify
```

This is the harness. It checks the source rules, assembles the page, and opens
it in a browser to compare against the stored baseline. Your change does not
touch the template, so it should pass.

If it fails, read the message before doing anything else. Most failures name
the problem clearly.

### 7. Commit and push

```
git add docs/team.md
git commit -m "Add <yourname> to team list"
git push -u origin warmup/<yourname>
```

Then open a pull request on GitHub. You do not need a review for this one — the
point is that you have been through the whole loop once.

### 8. Note anything surprising

Add two lines at the bottom of your pull request:

- What the agent did that you did not expect
- Anything in `CLAUDE.md` that was unclear

Both are genuinely useful. The failures teach more than the successes, and
`CLAUDE.md` gets better because people say what was confusing.

---

## If something goes wrong

| Problem | What to do |
|---|---|
| `claude` is not recognised | Close the terminal and open a new one. If it still fails, the install folder is not on your PATH — run `claude doctor`. |
| `claude doctor` reports problems | It usually names the fix. If not, send the output to Kushal. |
| `npm run verify` fails | Read the message. If it mentions a missing browser, run `npx playwright install chromium`. |
| The agent did something strange | Good — that is data. Note it in the pull request. Do not spend an hour fighting it. |

Raise anything unresolved with Kushal **by Wednesday 28 October**. An hour of
setup trouble on the day costs four people an hour each.
