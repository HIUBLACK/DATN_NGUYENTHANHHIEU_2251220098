const { chromium } = require(process.env.TEMP + '/viejob-browser-tools/node_modules/playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const base = 'http://127.0.0.1:8012/admin';

(async function () {
    const browser = await chromium.launch({ executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', headless: true });
    const page = await browser.newPage();
    const errors = [];
    const failures = [];
    const writes = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('response', response => {
        if (response.status() >= 400 && response.url().startsWith('http://127.0.0.1')) failures.push(response.status() + ' ' + response.url());
    });
    page.on('request', request => {
        if (request.method() !== 'GET') writes.push(request.method() + ' ' + request.url());
    });
    const paths = ['', '/nguoi-dung', '/nguoi-dung/chi-tiet?id=3', '/nguoi-dung/lich-su?id=10', '/doanh-nghiep', '/doanh-nghiep/chi-tiet?id=1', '/tin-tuyen-dung', '/kiem-duyet', '/tin-tuyen-dung/chi-tiet?id=1', '/tin-tuyen-dung/lich-su?id=1', '/ung-tuyen', '/ung-tuyen/chi-tiet?id=1', '/ky-nang', '/ky-nang/ten-dong-nghia', '/tai-khoan'];
    fs.mkdirSync('storage/app/private/admin-checks', { recursive: true });
    for (const width of [1440, 768, 390]) {
        await page.setViewportSize({ width, height: 1000 });
        for (const path of paths) {
            await page.goto(base + path);
            await page.waitForTimeout(120);
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 2), false, 'Overflow ' + width + ' ' + path);
            if (['', '/kiem-duyet', '/tin-tuyen-dung/chi-tiet?id=1'].includes(path)) {
                await page.screenshot({ path: 'storage/app/private/admin-checks/' + (path.replace(/[^a-z0-9]/gi, '-') || 'dashboard') + '-' + width + '.png', fullPage: true });
            }
        }
    }
    console.log('Responsive pages:', { errors, failures });
    assert.deepEqual(errors, []);
    assert.deepEqual(failures, []);
    await page.goto(base);
    await page.locator('.sidebar-toggle-btn').click();
    assert(await page.locator('.sidebar-wrapper').evaluate(element => element.classList.contains('show')));
    await page.locator('.sidebar-overlay').click({ position: { x: 380, y: 900 } });
    await page.setViewportSize({ width: 1440, height: 1000 });
    const seed = await page.locator('#admin-seed').textContent().then(JSON.parse);
    assert((await page.locator('#admin-range-summary').innerText()).includes(seed.jobs.length + ' tin mới'));
    assert((await page.locator('#admin-range-summary').innerText()).includes(seed.applications.length + ' lượt ứng tuyển'));
    await page.locator('#admin-date-start').fill('2000-01-01');
    await page.locator('#admin-date-end').fill('2000-01-02');
    await page.locator('#admin-date-filter button').click();
    assert((await page.locator('#admin-range-summary').innerText()).includes('Chưa có dữ liệu'));
    await page.goto(base + '/nguoi-dung');
    await page.locator('#admin-search').fill('CANDIDATE');
    await page.locator('#filter-status').selectOption('blocked');
    assert.equal(await page.locator('#admin-table-body tr').count(), 1);
    await page.locator('#admin-search').fill('not-present');
    assert((await page.locator('#admin-table-body').innerText()).includes('Không tìm thấy'));
    await page.goto(base + '/nguoi-dung/chi-tiet?id=1');
    assert.equal(await page.locator('[data-action]').count(), 0);
    await page.goto(base + '/nguoi-dung/chi-tiet?id=3');
    await page.locator('[data-action="lock"]').click();
    await page.locator('#admin-reason').fill('   ');
    await page.locator('#admin-confirm-submit').click();
    assert(await page.locator('#admin-confirm-error').isVisible());
    await page.locator('#admin-reason').fill('Kiểm tra demo');
    await page.locator('#admin-confirm-submit').click();
    await page.waitForTimeout(350);
    assert.equal(await page.locator('[data-action="unlock"]').count(), 1);
    await page.goto(base + '/doanh-nghiep/chi-tiet?id=1');
    await page.locator('[data-action="lock"]').click();
    await page.locator('#admin-reason').fill('Kiểm tra doanh nghiệp');
    await page.locator('#admin-confirm-submit').click();
    await page.waitForTimeout(350);
    await page.goto(base + '/tin-tuyen-dung/chi-tiet?id=1');
    assert((await page.locator('#admin-detail').innerText()).includes('không đủ điều kiện'));
    assert.equal(await page.locator('.admin-changed').count(), 3);
    await page.locator('[data-action="reject"]').click();
    await page.locator('#admin-reason').fill('Yêu cầu bổ sung mô tả');
    await page.locator('#admin-confirm-submit').click();
    await page.waitForTimeout(350);
    let state = await page.evaluate(() => JSON.parse(sessionStorage.getItem('viejob-admin-preview-v1')));
    assert.equal(state.jobs[0].moderation, 'rejected');
    assert.equal(state.jobs[0].published.salary, seed.jobs[0].published.salary);
    assert.equal(await page.locator('[data-action="approve"]').count(), 0);
    await page.goto(base + '/tin-tuyen-dung/chi-tiet?id=3');
    await page.locator('[data-action="approve"]').click();
    await page.locator('#admin-confirm-submit').click();
    await page.waitForTimeout(350);
    state = await page.evaluate(() => JSON.parse(sessionStorage.getItem('viejob-admin-preview-v1')));
    assert.equal(state.jobs[2].moderation, 'approved');
    assert.equal(state.jobs[2].activity, 'paused');
    await page.goto(base + '/tin-tuyen-dung/chi-tiet?id=8');
    await page.locator('[data-action="unlock"]').click();
    await page.locator('#admin-confirm-submit').click();
    await page.waitForTimeout(350);
    state = await page.evaluate(() => JSON.parse(sessionStorage.getItem('viejob-admin-preview-v1')));
    assert.equal(state.jobs[7].activity, 'closed');
    await page.goto(base + '/ung-tuyen');
    await page.locator('#filter-companyId').selectOption('1');
    await page.locator('#filter-status').selectOption('applied');
    assert.equal(await page.locator('#admin-table-body tr').count(), 3);
    await page.goto(base + '/ung-tuyen/chi-tiet?id=1');
    assert.equal(await page.locator('[data-action], iframe, a[download]').count(), 0);
    await page.goto(base + '/ky-nang/ten-dong-nghia');
    await page.locator('[data-skill-edit=""]').click();
    await page.locator('#skill-name').fill(' javascript ');
    await page.locator('#admin-skill-form button[type="submit"]').click();
    assert(await page.locator('#skill-error').isVisible());
    await page.locator('#skill-name').fill('ECMAScript demo');
    await page.locator('#skill-target').selectOption('1');
    assert((await page.locator('#skill-mapping-preview').innerText()).includes('JavaScript'));
    await page.locator('#admin-skill-form button[type="submit"]').click();
    await page.waitForTimeout(350);
    await page.locator('#admin-search').fill('ECMAScript');
    assert.equal(await page.locator('#admin-table-body tr').count(), 1);
    await page.locator('[data-alias-toggle]').click();
    await page.locator('#admin-confirm-submit').click();
    await page.waitForTimeout(350);
    assert((await page.locator('#admin-table-body').innerText()).includes('Vô hiệu hóa'));
    await page.goto(base + '/ky-nang');
    await page.locator('[data-skill-edit=""]').click();
    await page.locator('#skill-name').fill('Kỹ năng thử');
    await page.locator('#skill-normalized').fill('ky-nang-thu');
    await page.locator('#admin-skill-form button[type="submit"]').click();
    await page.waitForTimeout(350);
    await page.locator('#admin-search').fill('Kỹ năng thử');
    assert.equal(await page.locator('#admin-table-body tr').count(), 1);
    for (const path of ['/nguoi-dung/chi-tiet?id=missing', '/tin-tuyen-dung/chi-tiet?id=missing', '/ung-tuyen/chi-tiet?id=missing']) {
        await page.goto(base + path);
        assert((await page.locator('#admin-detail').innerText()).includes('Không tìm thấy'));
    }
    assert.deepEqual(errors, []);
    assert.deepEqual(writes, []);
    console.log('PASS: Admin 15 pages x 3 widths; filters; date chart totals; protected accounts; moderation; version comparison; company lock; unlock closed; read-only applications; skills/aliases validation; no mutation requests.');
    await browser.close();
})().catch(error => {
    console.error(error);
    process.exit(1);
});
