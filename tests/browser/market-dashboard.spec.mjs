import {test,expect} from '@playwright/test';
test.skip(!process.env.CHANDI_APACHE_URL,'Uses imported local archive');
test('all country charts, units, history, and PSL work',async({page})=>{
 await page.goto('./');
 await expect(page.locator('[data-chart-empty]')).toBeHidden();
 for(const [currency,unit] of [['USD','oz'],['PKR','tola'],['INR','kg']]){
  await page.locator(`[data-currency="${currency}"]`).click();
  await expect(page.locator('[data-chart-unit-select]')).toHaveValue(unit);
  await page.locator('[data-period="1y"]').click();
  await expect(page.locator('[data-chart-unit]')).toContainText(currency);
  await expect(page.locator('[data-market-chart]')).not.toHaveAttribute('aria-busy','true');
  expect(Number(await page.locator('[data-chart-count]').textContent())).toBeGreaterThan(350);
  await expect(page.locator('[data-chart-empty]')).toBeHidden();
  await page.locator('[data-chart-unit-select]').selectOption('g');
  await expect(page.locator('[data-chart-unit]')).toContainText('gram');
 }
 await page.locator('[data-currency="PSL"]').click();
 await expect(page.locator('[data-chart-unit-select]')).toBeDisabled();
 await expect(page.locator('[data-chart-note]')).toContainText('unit is unconfirmed');
 await expect(page.locator('[data-chart-value]')).toContainText('PKR');
 await page.locator('[data-select-market="PKR"]').click();
 await expect(page.locator('[data-currency="PKR"]')).toHaveAttribute('aria-pressed','true');
 await expect(page.getByRole('link',{name:'Become a dealer'})).toHaveCount(0);
 for(const path of ['register','dealers','products','become-a-dealer'])expect((await page.request.get('./'+path)).status()).toBe(404);
});
