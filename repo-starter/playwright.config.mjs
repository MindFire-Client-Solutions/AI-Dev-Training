import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests',
  // Screenshots differ slightly between machines. Allow a small drift so the
  // harness reports real changes, not font rendering.
  expect: { toHaveScreenshot: { maxDiffPixelRatio: 0.02 } },
  reporter: [['list']],
  use: { trace: 'retain-on-failure' },
  projects: [
    { name: 'desktop', use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 900 } } },
    { name: 'mobile',  use: { ...devices['Pixel 5'] } },
  ],
});
