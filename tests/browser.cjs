const {chromium}=require('playwright');
const assert=require('node:assert/strict');
(async()=>{const browser=await chromium.launch({headless:true,executablePath:process.env.CHROMIUM_PATH||'/usr/bin/chromium',args:['--no-sandbox']});let checks=0;
for(const width of [360,768,1440]){
 const page=await browser.newPage({viewport:{width,height:950}});
 await page.goto('http://localhost:8080/');
 assert.equal(await page.locator('[name=consent]').isChecked(),false);
 await page.keyboard.press('Tab');assert(await page.evaluate(()=>document.activeElement.classList.contains('skip')));
 assert.equal(await page.locator('h1').count(),1);assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));checks++;
 assert.equal(await page.locator('a[href="/raboty/"]').count(),0);
 for(const url of ['/izgotovlenie-bannerov/','/montazh-bannerov/','/kontakty/','/politika/','/soglasie/']){await page.goto('http://localhost:8080'+url);assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));checks++;}
 await page.goto('http://localhost:8080/');
 await page.locator('[name=phone]').fill('123');await page.locator('[name=consent]').check();await page.locator('button[type=submit]').click();assert.equal(await page.locator('[name=phone]').evaluate(el=>el.validity.valid),false);
 await page.locator('[name=phone]').fill('8 (999) 123-45-67');await page.locator('[name=comment]').fill('Тестовый комментарий');await page.locator('button[type=submit]').click();await page.getByRole('status').filter({hasText:'Тестовая отправка'}).waitFor();assert.equal(await page.locator('[name=comment]').inputValue(),'Тестовый комментарий');checks++;
 await page.screenshot({path:`/tmp/banner-${width}.png`,fullPage:true});await page.close();
}
await browser.close();console.log(`${checks} browser layout and form checks passed`);
})().catch(e=>{console.error(e);process.exit(1)});
