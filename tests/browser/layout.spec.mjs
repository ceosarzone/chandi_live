import { test, expect } from '@playwright/test';
for(const width of [360,390,768,1024,1440,1920]){
 test(`public layouts at ${width}px`,async({page})=>{
 await page.setViewportSize({width,height:1000});const errors=[];page.on('pageerror',e=>errors.push(e.message));
 for(const path of ['/','/live-silver-rates','/silver-rate-in-usa','/silver-rate-in-india','/silver-rate-in-pakistan','/silver-calculator','/contact','/login']){
 const response=await page.goto('.'+path);expect(response.status(),path).toBe(200);await page.waitForTimeout(150);
 const size=await page.evaluate(()=>({scroll:document.documentElement.scrollWidth,client:document.documentElement.clientWidth}));expect(size.scroll,`overflow ${path} at ${width}`).toBeLessThanOrEqual(size.client);
 if(path==='/'&&[390,1440].includes(width))await page.screenshot({path:`tests/browser/artifacts/home-${width}.png`,fullPage:true});
 }
 expect(errors).toEqual([]);
 });
}
test('mobile navigation opens and routes',async({page})=>{await page.setViewportSize({width:390,height:844});await page.goto('./');await page.getByRole('button',{name:'Menu'}).click();await expect(page.locator('#mobile-nav')).toBeVisible();await page.locator('#mobile-nav').getByRole('link',{name:'Pakistan'}).click();await expect(page).toHaveURL(/\/silver-rate-in-pakistan$/);});
test('calculator computes, validates, persists share inputs',async({page})=>{await page.goto('./silver-calculator');await page.getByLabel('Weight',{exact:true}).fill('10');await page.getByLabel('Unit',{exact:true}).selectOption('g');await page.getByLabel('Purity %').fill('92.5');await page.getByLabel('Quantity',{exact:true}).fill('2');await page.getByLabel('Benchmark · PKR per pure gram').fill('100');await page.getByLabel('Dealer premium / discount %').fill('10');await page.getByLabel('Making / processing charges per item (PKR)').fill('50');await page.getByLabel('Additional fee / tax %').fill('5');await page.getByRole('button',{name:'Calculate value'}).click();await expect(page.locator('.calculator-result')).toContainText('2,241.75');await expect(page).toHaveURL(/purity=92.5/);await page.screenshot({path:'tests/browser/artifacts/calculator.png',fullPage:true});});
test('recorded chart handles limited data and period controls work',async({page})=>{await page.goto('./silver-price-history');await page.getByRole('button',{name:'7D',exact:true}).click();await expect(page.getByRole('button',{name:'7D',exact:true})).toHaveAttribute('aria-pressed','true');const data=await (await page.request.get('./market/chart?period=7d')).json();if(data.points.length<2){await expect(page.locator('[data-chart-empty]')).toBeVisible();await expect(page.locator('[data-chart-change]')).toContainText('unavailable');}else{await expect(page.locator('[data-chart-empty]')).toBeHidden();await expect(page.locator('[data-chart-change]')).not.toContainText('unavailable');}});
