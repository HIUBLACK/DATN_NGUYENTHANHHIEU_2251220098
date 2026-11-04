const { chromium } = require(process.env.TEMP + '/viejob-browser-tools/node_modules/playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const base = 'http://127.0.0.1:8012/nha-tuyen-dung/quan-ly';

(async function () {
    const browser = await chromium.launch({ executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', headless: true });
    const page = await browser.newPage();
    const errors = [];
    const failures = [];
    const writes = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('response', response => {
        if (response.status() >= 400) failures.push(response.status() + ' ' + response.url());
    });
    page.on('request', request => {
        if (request.method() !== 'GET') writes.push(request.method() + ' ' + request.url());
    });
    const paths = ['', '/doanh-nghiep', '/doanh-nghiep/tao', '/doanh-nghiep/chinh-sua', '/tin-tuyen-dung', '/tin-tuyen-dung/tao', '/tin-tuyen-dung/chinh-sua?id=1', '/tin-tuyen-dung/chi-tiet?id=1', '/ho-so', '/ho-so/chi-tiet?id=1', '/thong-bao', '/tai-khoan'];
    fs.mkdirSync('storage/app/private/recruiter-checks', { recursive: true });
    for (const width of [1440, 768, 390]) {
        await page.setViewportSize({ width, height: 1000 });
        for (const path of paths) {
            await page.goto(base + path);
            await page.waitForTimeout(200);
            const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 2);
            if (overflow) errors.push('Overflow ' + width + ' ' + path);
            if (path === '' || path === '/tin-tuyen-dung' || path === '/ho-so') {
                await page.screenshot({ path: 'storage/app/private/recruiter-checks/' + (path.replaceAll('/', '-') || 'dashboard') + '-' + width + '.png', fullPage: true });
            }
        }
    }
    console.log(JSON.stringify({ errors, failures }, null, 2));
    assert.deepEqual(errors, []);
    assert.deepEqual(failures, []);
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.goto(base + '/doanh-nghiep/chinh-sua');
    await page.locator('#name').fill('Doanh nghiệp thử nghiệm tiếng Việt');
    await page.locator('#company-logo').setInputFiles({
        name: 'logo.png',
        mimeType: 'image/png',
        buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1cAAAAASUVORK5CYII=', 'base64')
    });
    await page.waitForFunction(() => !document.getElementById('company-logo-preview').hidden);
    await page.locator('#company-form button[type="submit"]').click();
    await page.goto(base + '/doanh-nghiep');
    assert((await page.locator('main').innerText()).includes('Doanh nghiệp thử nghiệm tiếng Việt'));
    await page.goto(base + '/tin-tuyen-dung/tao');
    await page.locator('#title').fill('Bản nháp tối thiểu');
    await page.locator('button[value="draft"]').click();
    const draft = await page.evaluate(() => JSON.parse(sessionStorage.getItem('viejob-recruiter-preview-v1')).jobs[0]);
    assert.equal(draft.moderation, 'draft');
    assert.equal(draft.published, null);
    await page.goto(base + '/tin-tuyen-dung/chinh-sua?id=1');
    await page.locator('#salary_min').fill('30');
    await page.locator('#salary_max').fill('10');
    await page.locator('button[value="submit"]').click();
    assert(await page.locator('#salary_max').evaluate(element => !element.validity.valid));
    assert.equal(await page.locator('#recruiter-confirm').isVisible(), false);
    await page.goto(base + '/tin-tuyen-dung/chinh-sua?id=1');
    await page.locator('#title').fill('Bản sửa Laravel chưa công khai');
    await page.locator('#preview-job').click();
    assert(await page.locator('#job-preview').isVisible());
    assert((await page.locator('#job-preview-content').innerText()).includes('Bản sửa Laravel chưa công khai'));
    await page.locator('#back-to-editor').click();
    assert.equal(await page.locator('#title').inputValue(), 'Bản sửa Laravel chưa công khai');
    await page.locator('button[value="submit"]').click();
    await page.locator('#recruiter-confirm-submit').click();
    await page.waitForTimeout(300);
    const storedJob = await page.evaluate(() => JSON.parse(sessionStorage.getItem('viejob-recruiter-preview-v1')).jobs.find(job => job.id === '1'));
    assert.equal(storedJob.moderation, 'pending');
    assert.equal(storedJob.published.title, 'Backend Laravel Developer');
    assert.equal(storedJob.content.title, 'Bản sửa Laravel chưa công khai');
    await page.goto(base + '/tin-tuyen-dung/chi-tiet?id=1');
    assert(await page.locator('details').isVisible());
    await page.goto(base + '/tin-tuyen-dung/chi-tiet?id=8');
    assert.equal(await page.locator('[data-job-action="reopen"]').count(), 0);
    for (const id of [6, 7]) {
        await page.goto(base + '/tin-tuyen-dung/chi-tiet?id=' + id);
        assert.equal(await page.locator('[data-job-action]').count(), 0);
    }
    await page.goto(base + '/ho-so');
    await page.locator('#candidate-search').fill('NGUYỄN MINH AN');
    assert.equal(await page.locator('#application-table-body tr').count(), 1);
    await page.locator('#application-status').selectOption('hired');
    assert((await page.locator('#application-table-body').innerText()).includes('Không có ứng viên'));
    await page.getByRole('button', { name: 'Xóa tìm kiếm' }).click();
    assert.equal(await page.locator('#application-table-body tr').count(), 6);
    await page.goto(base + '/ho-so/chi-tiet?id=1');
    const download = page.waitForEvent('download');
    await page.getByRole('link', { name: 'Tải CV demo' }).click();
    assert.equal((await download).suggestedFilename(), 'cv-demo-01.pdf');
    for (const status of ['reviewing', 'interview', 'hired']) {
        await page.locator('[data-application-status="' + status + '"]').click();
        await page.locator('#recruiter-confirm-submit').click();
        await page.waitForTimeout(350);
    }
    assert.equal(await page.locator('[data-application-status]').count(), 0);
    await page.locator('#application-note').fill('Ghi chú kiểm tra riêng — không đổi timeline.');
    await page.locator('#application-note-form button').click();
    assert((await page.locator('#application-notes').innerText()).includes('Ghi chú kiểm tra'));
    assert.equal(await page.locator('.recruiter-timeline li').count(), 4);
    await page.goto(base + '/thong-bao');
    await page.locator('#read-all-notifications').click();
    await page.locator('#notification-filter').selectOption('unread');
    assert.equal(await page.locator('.recruiter-notification').count(), 0);
    await page.goto(base + '/tai-khoan');
    await page.locator('#reset-recruiter-demo').click();
    await page.locator('#recruiter-confirm-submit').click();
    assert.deepEqual(writes, []);
    assert.deepEqual(errors, []);
    console.log('PASS: 12 pages x 3 widths; unsaved preview; published version preserved; restrictions; search/filter; PDF; transitions; notes; notifications. No mutation requests.');
    await browser.close();
})().catch(error => {
    console.error(error);
    process.exit(1);
});
