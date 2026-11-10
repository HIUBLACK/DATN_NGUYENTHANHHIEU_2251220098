const { chromium } = require(process.env.TEMP + '/viejob-browser-tools/node_modules/playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const base = 'http://127.0.0.1:8012/admin/danh-muc';

(async function () {
    const browser = await chromium.launch({ executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', headless: true });
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('response', response => {
        if (response.url().startsWith('http://127.0.0.1') && response.status() >= 400) errors.push(response.status() + ' ' + response.url());
    });
    page.on('request', request => {
        if (request.method() !== 'GET') errors.push('Unexpected write request');
    });
    fs.mkdirSync('storage/app/private/admin-category-checks', { recursive: true });
    for (const width of [1440, 768, 390]) {
        await page.setViewportSize({ width, height: 1000 });
        for (const path of ['/nhom-nghe', '/chuyen-mon']) {
            await page.goto(base + path);
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 2), false);
            assert.equal(await page.locator('#admin-category-menu .active').count(), 1);
            await page.screenshot({ path: 'storage/app/private/admin-category-checks/' + path.slice(1) + '-' + width + '.png', fullPage: true });
        }
    }
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.goto(base + '/nhom-nghe');
    await page.evaluate(() => {
        const seed = JSON.parse(document.getElementById('admin-seed').textContent);
        delete seed.occupations;
        delete seed.specialties;
        seed.users[2].status = 'blocked';
        sessionStorage.setItem('viejob-admin-preview-v1', JSON.stringify(seed));
    });
    await page.reload();
    await page.locator('[data-page="2"]').click();
    assert((await page.locator('#admin-table-body').innerText()).includes('Hỗ trợ CNTT'));
    await page.locator('#admin-search').fill('PHÁT TRIỂN');
    assert.equal(await page.locator('#admin-table-body tr').count(), 1);
    await page.locator('[data-category-toggle="NN-001"]').click();
    await page.locator('#admin-confirm-submit').click();
    assert((await page.locator('#admin-confirm-error').innerText()).includes('còn chuyên môn'));
    await page.locator('#admin-confirm button[data-bs-dismiss="modal"]').last().click();
    await page.waitForTimeout(350);
    await page.locator('a[href*="occupationId=NN-001"]').click();
    assert.equal(await page.locator('#filter-occupationId').inputValue(), 'NN-001');
    assert.equal(await page.locator('#admin-table-body tr').count(), 3);
    await page.locator('[data-category-edit=""]').click();
    assert.equal(await page.locator('#category-parent option[value="NN-007"]').isDisabled(), true);
    await page.locator('#category-name').fill(' BACKEND   DEVELOPER ');
    await page.locator('#admin-category-form button[type="submit"]').click();
    assert((await page.locator('#category-error').innerText()).includes('đã tồn tại'));
    await page.locator('#category-name').fill('Mobile Developer');
    await page.locator('#admin-category-form button[type="submit"]').click();
    await page.waitForTimeout(350);
    assert.equal(await page.locator('#admin-table-body tr').count(), 4);
    const state = await page.evaluate(() => JSON.parse(sessionStorage.getItem('viejob-admin-preview-v1')));
    assert.equal(state.users[2].status, 'blocked');
    assert.equal(state.specialties.length, 10);
    await page.goto(base + '/nhom-nghe');
    await page.locator('[data-category-edit="NN-005"]').click();
    await page.locator('#category-status').selectOption('inactive');
    await page.locator('#admin-category-form button[type="submit"]').click();
    await page.locator('#admin-confirm').waitFor({ state: 'visible' });
    await page.locator('#admin-confirm button[data-bs-dismiss="modal"]').last().click();
    await page.locator('#admin-category-modal').waitFor({ state: 'visible' });
    assert.equal(await page.locator('#category-status').inputValue(), 'inactive');
    await page.waitForTimeout(350);
    await page.locator('#admin-category-form button[type="submit"]').click();
    await page.locator('#admin-confirm-submit').click();
    await page.waitForTimeout(350);
    await page.locator('#filter-status').selectOption('inactive');
    assert.equal(await page.locator('#admin-table-body tr').count(), 2);
    await page.goto(base + '/chuyen-mon?occupationId=NN-007');
    await page.locator('[data-category-toggle="CM-009"]').click();
    await page.locator('#admin-confirm-submit').click();
    assert((await page.locator('#admin-confirm-error').innerText()).includes('ẩn'));
    assert.deepEqual(errors, []);
    console.log('PASS: two pages x three widths; pagination/search/filter; legacy session; duplicate validation; parent conditions; form confirmation cancellation; no mutation requests.');
    await browser.close();
})().catch(error => {
    console.error(error);
    process.exit(1);
});
