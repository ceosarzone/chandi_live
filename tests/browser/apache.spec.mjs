import { test, expect } from '@playwright/test';

// These checks exercise Apache's real filesystem rules, rather than Laravel's test router.
const apacheUrl = process.env.CHANDI_APACHE_URL;
test.skip(!apacheUrl, 'Set CHANDI_APACHE_URL to the local Apache application URL.');
test.use({ baseURL: (apacheUrl || 'http://localhost/chandilive').replace(/\/$/, '') + '/' });

test('Apache denies application files and private storage', async ({ request }) => {
  for (const path of ['.env', '.env.testing', '.git/config', 'composer.json', 'vendor/autoload.php',
    'app/Models/User.php', 'bootstrap/cache/config.php', 'storage/logs/laravel.log',
    'public/index.php', 'public/.env', 'scripts/browser-fixtures.php', 'README.md']) {
    expect((await request.get('./' + path)).status(), path).toBe(403);
  }
  expect((await request.get('./storage/app/private/secret.jpg')).status()).toBe(404);
  expect((await request.post('./enquiries', { data: {} })).status()).toBe(404);
});

test('Apache serves the branded icons and application assets', async ({ page, request }) => {
  const failures = [];
  page.on('response', response => { if (response.status() >= 400) failures.push(response.url()); });
  await page.goto('./');
  await expect(page.getByRole('link', { name: 'CHANDI.LIVE home' })).toHaveAttribute('href', apacheUrl.replace(/\/$/, ''));
  for (const icon of await page.locator('link[rel="icon"], link[rel="apple-touch-icon"]').evaluateAll(nodes => nodes.map(node => node.href))) {
    expect(icon).toContain(apacheUrl.replace(/\/$/, '') + '/');
    expect((await request.get(icon)).status()).toBe(200);
  }
  const response = await request.get('./favicon.ico');
  expect(response.headers()['content-type']).toContain('image/x-icon');
  const ico = await response.body();
  expect(ico.readUInt16LE(2)).toBe(1);
  expect(ico.readUInt16LE(4)).toBe(4);
  expect(failures).toEqual([]);
});
