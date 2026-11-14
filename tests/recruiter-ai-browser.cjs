const { chromium } = require(process.env.TEMP + '/viejob-browser-tools/node_modules/playwright');
const assert = require('node:assert/strict');

(async function () {
    const browser = await chromium.launch({
        executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
        headless: true
    });
    const page = await browser.newPage();
    const errors = [];
    const writes = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('request', request => {
        if (request.method() !== 'GET') {
            writes.push(request.url());
        }
    });
    const base = 'http://127.0.0.1:8012/nha-tuyen-dung/quan-ly/ho-so';
    await page.goto(base);
    await page.locator('#application-ai-status').selectOption('completed');
    await page.locator('#application-sort').selectOption('ai-desc');
    assert.equal(await page.locator('#application-table-body tr').count(), 3);
    const scores = await page.locator('#application-table-body td:nth-child(5) .fw-bold').allTextContents();
    assert.deepEqual(scores, ['85/100', '68/100', '46/100']);
    await page.locator('#application-job').selectOption('1');
    assert.equal(await page.locator('#application-table-body tr').count(), 1);
    await page.getByRole('link', { name: 'Xem đánh giá AI' }).click();
    await page.locator('#ai-evaluation').waitFor();
    assert((await page.locator('#ai-evaluation').innerText()).includes('85/100'));
    assert((await page.locator('#ai-evaluation').innerText()).includes('Docker'));
    for (const width of [1440, 768, 390]) {
        await page.setViewportSize({ width, height: 1000 });
        await page.waitForTimeout(350);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 2), false, "Overflow at " + width);
    }
    await page.screenshot({ path: 'storage/app/private/recruiter-checks/ai-detail-mobile.png', fullPage: true });
    await page.goto(base + '/chi-tiet?id=5');
    await page.getByRole('button', { name: 'Thử lại (demo)' }).click();
    assert((await page.locator('#ai-evaluation').innerText()).includes('Chưa đánh giá'));
    await page.reload();
    assert((await page.locator('#ai-evaluation').innerText()).includes('Chưa đánh giá'));
    const stored = await page.evaluate(() => JSON.parse(sessionStorage.getItem('viejob-recruiter-preview-v1')));
    assert.equal(stored.applications.find(item => item.id === '5').status, 'rejected');
    await page.goto(base);
    await page.locator('#application-ai-status').selectOption('unrated');
    assert.equal(await page.locator('#application-table-body tr').count(), 3);
    assert.deepEqual(errors, []);
    assert.deepEqual(writes, []);
    await browser.close();
    console.log('PASS: AI scores, combined filters, sorting, details, responsive layout, retry persistence without changing recruitment status or making server writes.');
})().catch(function (error) {
    console.error(error);
    process.exit(1);
});
