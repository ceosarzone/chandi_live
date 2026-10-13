import { test, expect } from '@playwright/test';

test.skip(!process.env.CHANDI_APACHE_URL, 'Requires the configured local Apache application with real observations.');
test('three currencies show real observations and refresh through the local cached endpoint', async ({ page }) => {
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await page.setViewportSize({width:1440,height:1000});
  await page.clock.install();
  await page.goto('./live-silver-rates');
  for (const currency of ['USD','PKR','INR']) {
    const card = page.locator(`[data-country="${currency}"]`);
    await expect(card).toBeVisible();
    await expect(card.locator('.country-headline')).toContainText(currency);
    await expect(card.locator('.country-headline')).not.toContainText('—');
    await expect(card.locator('time')).toHaveAttribute('datetime', /T/);
    await expect(card).not.toContainText('Simulated development fixture');
  }
  await expect(page.getByText('Currency-converted spot benchmark')).toHaveCount(2);
  await expect(page.getByRole('link', { name:'Rates By Exchange Rate API' })).toBeVisible();
  const response = page.waitForResponse(response => response.url().includes('/market/rates') && response.status()===200);
  await page.clock.fastForward(61000);
  await response;
  await expect(page.locator('[data-rates-refresh-status]')).toContainText('Display checked at');
  await page.screenshot({path:'tests/browser/artifacts/live-rates-1440.png',fullPage:true});
  expect(errors).toEqual([]);
});
