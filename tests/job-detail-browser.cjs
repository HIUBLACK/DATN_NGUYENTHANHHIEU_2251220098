const { chromium } = require(process.env.TEMP + '/viejob-browser-tools/node_modules/playwright');
const assert = require('node:assert/strict');
(async function () {
    const browser = await chromium.launch({ executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', headless: true });
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('response', response => {
        if (response.status() >= 400) errors.push(response.url());
    });
    for (const width of [1440, 768, 390]) {
        await page.setViewportSize({ width, height: 1000 });
        await page.goto('http://127.0.0.1:8012/viec-lam/1');
        await page.locator('#overlayer').waitFor({ state: 'hidden' });
        assert(await page.locator('h1').count() === 1);
        assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
        await page.screenshot({ path: 'storage/app/private/jobboard-checks/job-detail-' + width + '.png', fullPage: true });
        await page.locator('[data-target="#apply-modal"]').click();
        await page.locator('#apply-modal').waitFor({ state: 'visible' });
        assert(await page.locator('#apply-modal').isVisible());
        await page.locator('#apply-modal [data-dismiss="modal"]').first().click();
    }
    for (const id of [9, 10]) {
        await page.goto('http://127.0.0.1:8012/viec-lam/' + id);
        assert(await page.locator('[data-target="#apply-modal"]').isDisabled());
    }
    assert.deepEqual(errors, []);
    console.log('PASS: desktop/tablet/mobile, assets, modal, closed and expired jobs.');
    await browser.close();
})().catch(error => {
    console.error(error);
    process.exit(1);
});
