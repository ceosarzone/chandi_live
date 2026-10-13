import {test,expect} from '@playwright/test';
const live=process.env.CHANDI_LIVE_URL;
test.skip(!live,'Set CHANDI_LIVE_URL for explicit live deployment verification.');
test.use({baseURL:(live||'http://localhost/chandilive').replace(/\/$/,'')+'/'});
test('production HTTPS, historical feeds, and private files are configured',async({page,request})=>{
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 const response=await page.goto('./');expect(response.status()).toBe(200);
 expect(new URL(page.url()).protocol).toBe('https:');
 expect(response.headers()['strict-transport-security']).toContain('max-age=');
 await expect(page.locator('html')).toHaveAttribute('data-theme','light');
 await expect(page.locator('[data-market-chart]')).not.toHaveAttribute('aria-busy','true');
 await expect(page.locator('[data-chart-empty]')).toBeHidden();
 expect((await request.get('./health')).ok()).toBe(true);
 expect((await (await request.get('./health')).json()).status).toBe('ok');
 for(const currency of ['USD','PKR','INR']){
  const data=await (await request.get(`./market/chart?period=max&currency=${currency}`)).json();
  expect(data.points.filter(p=>p.value!==null).length).toBeGreaterThan(350);
 }
 for(const path of ['.env','.git/config','composer.json','vendor/autoload.php','storage/logs/laravel.log','app/Models/User.php','register','dealers','become-a-dealer'])expect([403,404]).toContain((await request.get('./'+path)).status());
 const assets=await page.locator('script[src],link[rel="stylesheet"],link[rel="icon"]').evaluateAll(nodes=>nodes.map(n=>n.src||n.href));
 for(const asset of assets){expect(asset).toMatch(/^https:\/\/chandi\.live\//);expect((await request.get(asset)).status()).toBe(200);}
 const robots=await (await request.get('./robots.txt')).text();expect(robots).toContain('https://chandi.live/sitemap.xml');
 const sitemap=await (await request.get('./sitemap.xml')).text();expect(sitemap).toContain('https://chandi.live/silver-rate-in-india');expect(sitemap).not.toContain('become-a-dealer');
 expect(errors).toEqual([]);
 await page.screenshot({path:'tests/browser/artifacts/production-desktop.png',fullPage:true});
});
