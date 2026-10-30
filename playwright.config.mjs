import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests',
  // Screenshots differ slightly between machines. Allow a small drift so the
  // harness reports real changes, not font rendering.
  expect: { toHaveScreenshot: { maxDiffPixelRatio: 0.02 } },

  // A missing baseline must FAIL, not quietly become the baseline. Playwright's
  // default ('missing') writes whatever it sees on a first run and passes ever
  // after -- so a first run made *after* a change records the changed page as
  // the reference and certifies the bug. `npm run baseline` passes
  // --update-snapshots on the command line, which overrides this.
  updateSnapshots: 'none',

  // Keep baselines where CLAUDE.md says they live. Playwright's default is
  // tests/<file>-snapshots/. {platform} keeps Windows and Mac from colliding.
  snapshotPathTemplate: 'tests/baseline/{testFileName}/{arg}{-projectName}-{platform}{ext}',
  reporter: [['list']],
  use: { trace: 'retain-on-failure' },
  projects: [
    { name: 'desktop', use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 900 } } },
    { name: 'mobile',  use: { ...devices['Pixel 5'] } },
  ],
});
