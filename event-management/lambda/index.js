// AWS Lambda entry point: wires app.js to Secrets Manager, fetch and SFTP.
// Handler setting in the Lambda console: lambda/index.handler
//
// Environment variables (Lambda -> Configuration -> Environment variables):
//   SETTINGS_SECRET_ID  name or ARN of the Secrets Manager secret holding the settings JSON
//   ADMIN_KEY           the Settings screen's admin key (at least 12 characters)
//   ALLOWED_ORIGIN      the Pages site, e.g. https://mfi-event-generator.pages.dev
//
// @aws-sdk/client-secrets-manager is built into the Lambda Node.js runtime, so it is not
// bundled. It is loaded with require() because the runtime finds it through NODE_PATH,
// which ES module imports do not search.

import { createRequire } from 'node:module';
import SftpClient from 'ssh2-sftp-client';
import { createApp } from './app.js';

const { GetSecretValueCommand, PutSecretValueCommand, SecretsManagerClient } =
  createRequire(import.meta.url)('@aws-sdk/client-secrets-manager');

function requireEnv(name) {
  const v = process.env[name];
  if (!v) throw new Error(`Missing environment variable ${name}`);
  return v;
}

const env = {
  SETTINGS_SECRET_ID: requireEnv('SETTINGS_SECRET_ID'),
  ADMIN_KEY: requireEnv('ADMIN_KEY'),
  ALLOWED_ORIGIN: requireEnv('ALLOWED_ORIGIN'),
};
const secrets = new SecretsManagerClient({});

const app = createApp({
  env,
  async loadSettings() {
    const out = await secrets.send(new GetSecretValueCommand({ SecretId: env.SETTINGS_SECRET_ID }));
    try { return JSON.parse(out.SecretString || '{}'); } catch { return {}; }
  },
  async saveSettings(settings) {
    await secrets.send(new PutSecretValueCommand({ SecretId: env.SETTINGS_SECRET_ID, SecretString: JSON.stringify(settings) }));
  },
  async fetchText(url) {
    const res = await fetch(url, { redirect: 'follow', signal: AbortSignal.timeout(30000) });
    if (!res.ok) throw new Error(`Could not download ${url}: HTTP ${res.status}`);
    return res.text();
  },
  async sftpConnect(config) {
    const client = new SftpClient();
    await client.connect(config);
    return {
      put: (text, path) => client.put(Buffer.from(text, 'utf8'), path),
      list: (folder) => client.list(folder),
      end: () => client.end(),
    };
  },
});

export const handler = app.handler;
