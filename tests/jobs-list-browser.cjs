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
        await page.goto('http://127.0.0.1:8012/viec-lam');
        await page.locator('#overlayer').waitFor({ state: 'hidden' });
        assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
        assert.equal(await page.locator('.search-job-card').count(), 6);
        const columns = width >= 992 ? 3 : (width >= 576 ? 2 : 1);
        const positions = await page.locator('.search-job-card').evaluateAll(cards => cards.map(card => card.getBoundingClientRect().top));
        for (let index = 1; index < columns; index++) {
            assert.equal(positions[index], positions[0]);
        }
        await page.screenshot({ path: 'storage/app/private/jobboard-checks/jobs-grid-' + width + '.png', fullPage: true });
    }
    await page.locator('#job-keyword').fill('Laravel');
    await page.locator('.jobs-search-submit').click();
    await page.waitForLoadState('networkidle');
    assert.equal(await page.locator('.search-job-card').count(), 2);
    await page.locator('#filter-location').selectOption({ label: 'Hà Nội' });
    await page.getByRole('button', { name: 'Áp dụng bộ lọc' }).click();
    await page.waitForLoadState('networkidle');
    assert.equal(await page.locator('.search-job-card').count(), 1);
    await page.locator('.search-job-save').click();
    await page.waitForLoadState('networkidle');
    assert.equal(await page.locator('.search-job-save').getAttribute('aria-pressed'), 'true');
    await page.locator('.search-job-save').click();
    await page.waitForLoadState('networkidle');
    await page.locator('#job-keyword').fill('no-matching-result');
    await page.locator('.jobs-search-submit').click();
    await page.waitForLoadState('networkidle');
    assert(await page.getByText('Không tìm thấy kết quả', { exact: true }).isVisible());
    await page.goto('http://127.0.0.1:8012/viec-lam');
    await page.getByRole('link', { name: 'Trang 2', exact: true }).click();
    await page.waitForLoadState('networkidle');
    assert.equal(await page.locator('.search-job-card').count(), 4);
    assert(await page.getByText('Đã đóng', { exact: true }).isVisible());
    assert(await page.getByText('Hết hạn', { exact: true }).isVisible());
    assert.deepEqual(errors, []);
    console.log('PASS: responsive, search, filters, save/unsave, empty state, pagination, closed/expired labels, no asset or JS errors.');
    await browser.close();
})().catch(error => {
    console.error(error);
    process.exit(1);
});
