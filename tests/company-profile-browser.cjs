const { chromium } = require(process.env.TEMP + '/viejob-browser-tools/node_modules/playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
(async function () {
    const browser = await chromium.launch({ executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', headless: true });
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('response', response => {
        if (response.url().startsWith('http://127.0.0.1') && response.status() >= 400) errors.push(response.status() + ' ' + response.url());
    });
    const base = 'http://127.0.0.1:8012';
    const edit = base + '/nha-tuyen-dung/quan-ly/doanh-nghiep/chinh-sua';
    const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1cAAAAASUVORK5CYII=', 'base64');
    fs.mkdirSync('storage/app/private/company-checks', { recursive: true });
    for (const width of [1440, 768, 390]) {
        await page.setViewportSize({ width, height: 1000 });
        for (const [name, url] of [['edit', edit], ['public', base + '/cong-ty/mau/1']]) {
            await page.goto(url);
            if (name === 'public') {
                await page.locator('#overlayer').waitFor({ state: 'hidden' });
            }
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 2), false);
            await page.screenshot({ path: 'storage/app/private/company-checks/' + name + '-' + width + '.png', fullPage: true });
        }
    }
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.goto(edit);
    await page.locator('#name').fill('Doanh nghiệp tiếng Việt thử nghiệm');
    await page.locator('#summary').fill('Mô tả ngắn được công khai');
    await page.locator('#email').fill('private-office@example.com');
    await page.locator('#company-logo').setInputFiles({ name: 'bad.svg', mimeType: 'image/svg+xml', buffer: Buffer.from('<svg></svg>') });
    await page.locator('#company-image-error').waitFor({ state: 'visible' });
    for (const field of ['logo', 'cover', 'office_images']) {
        await page.locator('#company-' + field).setInputFiles({ name: field + '.png', mimeType: 'image/png', buffer: png });
        await page.locator('#preview-' + field + ' img').waitFor();
    }
    await page.locator('#company-form button[type="submit"]').click();
    await page.waitForURL(edit);
    await page.getByText('Đã cập nhật hồ sơ trong phiên demo, chưa cập nhật database.').waitFor();
    assert.equal(await page.locator('#name').inputValue(), 'Doanh nghiệp tiếng Việt thử nghiệm');
    const publicLink = page.getByRole('link', { name: 'Xem trang công khai' });
    const publicUrl = await publicLink.getAttribute('href');
    await publicLink.click();
    await page.locator('#overlayer').waitFor({ state: 'hidden' });
    const content = await page.locator('#main-content').innerText();
    assert(content.includes('Mô tả ngắn được công khai'));
    assert(!content.includes('private-office@example.com'));
    assert.equal(await page.locator('.company-office-gallery img').count(), 1);
    await page.locator('.company-cover').evaluate(image => image.decode());
    assert.equal(await page.locator('.company-cover').evaluate(image => image.naturalWidth), 1);
    await page.screenshot({ path: 'storage/app/private/company-checks/saved-public.png', fullPage: true });
    const other = await browser.newContext();
    const otherPage = await other.newPage();
    assert.equal((await otherPage.goto(publicUrl)).status(), 404);
    await other.close();
    await page.goto(edit);
    await page.locator('input[name="remove_cover"]').check();
    await page.locator('input[name="remove_office_images[]"]').check();
    await page.locator('#company-form button[type="submit"]').click();
    await page.getByText('Đã cập nhật hồ sơ trong phiên demo, chưa cập nhật database.').waitFor();
    await page.goto(publicUrl);
    assert.equal(await page.locator('.company-office-gallery img').count(), 0);
    assert.equal(await page.locator('.company-cover-placeholder').count(), 1);
    assert.deepEqual(errors, []);
    console.log('PASS: 3 widths, image validation/previews, save/reload, public projection, separate-session denial, remove images; no page/HTTP errors.');
    await browser.close();
})().catch(error => { console.error(error); process.exit(1); });
