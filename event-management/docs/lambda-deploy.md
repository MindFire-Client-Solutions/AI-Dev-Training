# Deploying the weekly SFTP run (AWS console)

What gets built: one Lambda function that EventBridge runs every Sunday at 10 PM Pacific,
plus a Settings screen in the existing UI. Spec: [`001-scheduled-sftp-run.md`](001-scheduled-sftp-run.md).

```
EventBridge Scheduler ──(Sun 22:00 Pacific)──> Lambda ──> fetch 2 input links
                                                  │   ──> generate + validate
                                                  └─> upload CSV to SFTP folder
UI Settings screen ──(admin key)──> Lambda Function URL ──> Secrets Manager (settings)
```

Use one AWS region for everything (for example **US West (Oregon) us-west-2**).

---

## 0. Before you start

- **Build the zip.** From `event-management`:
  ```
  npm run build:lambda
  ```
  This creates `lambda-dist/mf-event-generator-lambda.zip` (about 0.6 MB).
- **Make the admin key**, a long random password for the Settings screen. Keep it
  somewhere safe, such as your password manager:
  ```
  node -e "console.log(require('crypto').randomBytes(24).toString('base64url'))"
  ```
- **Host the two input files** at `https://` links that open without signing in: the
  contacts file and `agentConversationEvents_nnnnn.csv`. Keep the events file's real name
  at the end of the link, because the account number is checked against it.
- **SFTP firewall.** If the SFTP server only accepts connections from listed IP addresses,
  tell the server's owner now. A plain Lambda has no fixed IP address. Getting one needs a
  VPC with a NAT gateway and an Elastic IP, which has a monthly cost. Skip this if the
  server accepts connections from anywhere.

---

## 1. Secret for the settings

1. **Secrets Manager → Store a new secret.**
2. Secret type: **Other type of secret**. Choose the **Plaintext** tab and replace the
   contents with:
   ```
   {}
   ```
3. Encryption key: leave the default (`aws/secretsmanager`). **Next.**
4. Secret name: `mf-event-generator/settings`. **Next.**
5. Rotation: leave it off. **Next → Store.**

You will fill in the settings from the UI in step 6.

---

## 2. Lambda function

1. **Lambda → Create function → Author from scratch.**
   - Function name: `mf-event-generator`
   - Runtime: **Node.js 22.x**
   - Architecture: **arm64** (cheaper; x86_64 also works)
   - Permissions: leave **Create a new role with basic Lambda permissions**.
   - **Create function.**
2. **Code tab → Upload from → .zip file**, and choose
   `lambda-dist/mf-event-generator-lambda.zip`. **Save.**
3. Still on the Code tab, under **Runtime settings → Edit**, set Handler to
   `lambda/index.handler`. **Save.**
4. **Configuration → General configuration → Edit:** Memory **512 MB**, Timeout
   **2 min 0 sec**. **Save.**
5. **Configuration → Environment variables → Edit → Add**:

   | Key | Value |
   |---|---|
   | `SETTINGS_SECRET_ID` | `mf-event-generator/settings` |
   | `ADMIN_KEY` | the admin key from step 0 |
   | `ALLOWED_ORIGIN` | `https://mfi-event-generator.pages.dev` (no trailing slash) |

   **Save.**
6. **Configuration → Permissions →** click the **Role name** (this opens IAM) **→ Add
   permissions → Create inline policy → JSON**, and paste the following. Replace
   `REGION` and `ACCOUNT_ID` with yours, and keep the `-*` at the end:
   ```json
   {
     "Version": "2012-10-17",
     "Statement": [{
       "Effect": "Allow",
       "Action": ["secretsmanager:GetSecretValue", "secretsmanager:PutSecretValue"],
       "Resource": "arn:aws:secretsmanager:REGION:ACCOUNT_ID:secret:mf-event-generator/settings-*"
     }]
   }
   ```
   **Next**, name it `mf-event-generator-settings`, then **Create policy**.

---

## 3. Function URL (the Settings API)

1. Back in the Lambda: **Configuration → Function URL → Create function URL.**
2. Auth type: **NONE**. The admin key is checked inside the function.
3. **Leave "Configure cross-origin resource sharing (CORS)" unticked.** The function sends
   its own CORS headers, and ticking it as well makes browsers reject the duplicate
   headers.
4. **Save**, then copy the **Function URL**. It looks like
   `https://abc123….lambda-url.us-west-2.on.aws/`.

---

## 4. Turn on the Settings screen (Cloudflare)

1. **Cloudflare → Workers & Pages → mfi-event-generator → Settings → Variables and
   Secrets → Add** (Production):
   - Variable name: `SETTINGS_API_URL`
   - Value: the Function URL from step 3
2. Redeploy so the build picks it up: **Deployments →** the latest production
   deployment **→ ⋯ → Retry deployment**. Any later merge to `main` also redeploys.

The page now shows a **Settings** button. Without this variable it does not, and the page
can talk to nothing.

---

## 5. Weekly schedule (EventBridge Scheduler)

1. **Amazon EventBridge → Scheduler → Schedules → Create schedule.**
   - Name: `mf-event-generator-weekly`
   - Schedule pattern: **Recurring schedule**, **Cron-based schedule**, then set the cron
     fields like this:
     `0 22 ? * SUN *` (minutes `0`, hours `22`, day of month `?`, month `*`, day of week
     `SUN`, year `*`)
   - Time zone: **America/Los_Angeles**. This keeps it at 10 PM through daylight saving.
   - Flexible time window: **Off**.
   - **Next.**
2. Target: **AWS Lambda → Invoke**, choose `mf-event-generator`. Payload:
   ```json
   {"source": "weekly-schedule"}
   ```
   **Next.**
3. Settings: Retry policy **on**, maximum retries **2**, maximum age of event **1 hour**.
   Execution role: **Create new role for this schedule.** **Next → Create schedule.**

---

## 6. Fill in the settings and test

1. Open https://mfi-event-generator.pages.dev/, press `Ctrl+F5`, and click **Settings**.
2. Enter the admin key and click **Load settings**. With an empty secret, every field is
   blank.
3. Fill in the contacts link, the events link, and the SFTP host, port, username,
   password and folder. Click **Save**.
4. Click **Test SFTP**. It should say "Connected. Folder … has N item(s)." Nothing is
   uploaded.
5. Click **Run now**. It should say "Uploaded /your/folder/agentConversationEvents_…csv"
   and show the run summary. Check that the file is on the SFTP server.
6. Optional: in the Lambda's **Test** tab, create a test event with
   `{"source": "weekly-schedule"}` and click **Test**. This is exactly what Sunday's run
   does.

Every run's summary, or its error, is in **CloudWatch → Log groups →
/aws/lambda/mf-event-generator**.

---

## 7. Optional: email me if Sunday's run fails

**CloudWatch → Alarms → Create alarm → Select metric → Lambda → By Function Name →
`mf-event-generator` → Errors.** Set Statistic to **Sum**, Period to **1 hour**, and the
threshold to **Greater/Equal 1**. Under notification, create an SNS topic with your email,
then confirm the email AWS sends you.

---

## Updating later

- **Generator rules or Lambda code changed:** run `npm run build:lambda` again, then
  **Lambda → Code → Upload from → .zip file**. Settings and schedule stay as they are.
- **UI changed:** merge to `main`. Cloudflare redeploys automatically.
- **New admin key:** change `ADMIN_KEY` in the Lambda's environment variables, then enter
  the new key in the Settings screen.

## Troubleshooting

| You see | Cause |
|---|---|
| "Wrong or missing admin key" | The key in Settings differs from the Lambda's `ADMIN_KEY`. The key must be at least 12 characters. |
| "Failed to fetch" in Settings | `ALLOWED_ORIGIN` does not exactly match the site address, or CORS was ticked on the Function URL (step 3.3). |
| Settings button missing | `SETTINGS_API_URL` is not set, or the site was not redeployed after setting it (step 4). |
| "Could not download … HTTP 403/404" | An input link needs a sign-in or is wrong. Open it in a private browser window to check. |
| "All configured authentication methods failed" | Wrong SFTP username or password. |
| "Timed out while waiting for handshake" | Wrong host or port, or the SFTP firewall blocks AWS (step 0). |
| "Validation failed, nothing uploaded" | The input files break a generator rule. The log lists which rule and which rows. |
| "AccessDeniedException … secretsmanager" | The inline policy in step 2.6 is missing, or the secret name or region in it is wrong. |
