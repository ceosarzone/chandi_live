import {test,expect} from '@playwright/test';
test('light is default even with a dark system preference; choice persists and charts stay usable',async({page})=>{
 await page.emulateMedia({colorScheme:'dark'});
 await page.goto('./');
 await expect(page.locator('html')).toHaveAttribute('data-theme','light');
 expect(await page.locator('body').evaluate(el=>getComputedStyle(el).backgroundColor)).toBe('rgb(245, 247, 250)');
 await expect(page.locator('[data-market-chart]')).not.toHaveAttribute('aria-busy','true');
 await page.getByRole('button',{name:'Switch to dark theme'}).click();
 await expect(page.locator('html')).toHaveAttribute('data-theme','dark');
 await page.reload();
 await expect(page.locator('html')).toHaveAttribute('data-theme','dark');
 await page.getByRole('button',{name:'Switch to light theme'}).click();
 await page.locator('[data-currency="PKR"]').click();
 await expect(page.locator('[data-chart-unit]')).toContainText('PKR');
 await page.goto('./silver-calculator');
 await expect(page.locator('html')).toHaveAttribute('data-theme','light');
});
for(const width of [390,1440]){
 test(`both themes remain readable at ${width}`,async({page})=>{
  await page.setViewportSize({width,height:1000});await page.goto('./');
  await expect(page.locator('[data-market-chart]')).not.toHaveAttribute('aria-busy','true');
  for(const theme of ['light','dark']){
   if(theme==='dark')await page.getByRole('button',{name:'Switch to dark theme'}).click();
   expect(await page.evaluate(()=>document.documentElement.scrollWidth<=document.documentElement.clientWidth)).toBe(true);
   await page.screenshot({path:`tests/browser/artifacts/theme-${theme}-${width}.png`,fullPage:true});
  }
 });
}
