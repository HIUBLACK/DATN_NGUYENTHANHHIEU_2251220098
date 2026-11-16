const { chromium } = require(process.env.TEMP + '/viejob-browser-tools/node_modules/playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');

(async function () {
    const browser = await chromium.launch({ executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', headless: true });
    const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const base = 'http://127.0.0.1:8012';
    fs.mkdirSync('storage/app/private/menu-checks', { recursive: true });
    await page.goto(base);
    await page.locator('#overlayer').waitFor({ state: 'hidden' });
    const menu = page.locator('.site-navigation .job-mega');
    await menu.locator('.job-mega-link').hover();
    await menu.locator('[data-job-category="skill"]').hover();
    assert.equal(await menu.locator('.job-mega-options a').count(), 36);
    assert(await menu.locator('.job-mega-options').evaluate(element => element.scrollHeight > element.clientHeight));
    await page.screenshot({ path: 'storage/app/private/menu-checks/desktop.png' });
    await menu.locator('.job-mega-options a').filter({ hasText: /^PHP$/ }).click();
    assert.equal(new URL(page.url()).searchParams.get('skill'), 'PHP');
    assert.equal(await page.locator('#filter-skill').inputValue(), 'PHP');
    assert((await page.locator('.jobs-results-heading').first().innerText()).includes('2'));
    for (const field of ['industry', 'specialtyId', 'level', 'company', 'location']) {
        await page.locator('#overlayer').waitFor({ state: 'hidden' });
        await menu.locator('.job-mega-link').hover();
        await menu.locator('[data-job-category="' + field + '"]').hover();
        const link = menu.locator('.job-mega-options a').first();
        const value = new URL(await link.getAttribute('href')).searchParams.get(field);
        await link.click();
        assert.equal(await page.locator('#filter-' + field).inputValue(), value);
    }
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(base);
    await page.locator('#overlayer').waitFor({ state: 'hidden' });
    await page.locator('.site-menu-toggle').click();
    const mobile = page.locator('.site-mobile-menu .job-mega');
    await mobile.locator('.job-mega-toggle').click();
    await mobile.locator('[data-job-expand="skill"]').click();
    await mobile.locator('.job-mega-options').evaluate(element => element.scrollTop = element.scrollHeight);
    await page.screenshot({ path: 'storage/app/private/menu-checks/mobile.png' });
    await mobile.locator('.job-mega-options a').last().click();
    assert.equal(new URL(page.url()).searchParams.get('skill'), 'MongoDB');
    for (const path of ['linh-vuc', 'ky-nang', 'cap-bac']) {
        await page.goto(base + '/admin/danh-muc/' + path);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 2), false);
        await page.locator('[data-category-edit=""]').click();
        await page.locator('#category-name').fill('Danh mục thử ' + path);
        await page.locator('#admin-category-form button[type="submit"]').click();
        await page.locator('#admin-category-modal').waitFor({ state: 'hidden' });
        await page.locator('#admin-search').fill('Danh mục thử ' + path);
        assert.equal(await page.locator('#admin-table-body tr').count(), 1);
        await page.locator('[data-category-toggle]').click();
        await page.locator('#admin-confirm-submit').click();
        await page.locator('#admin-confirm').waitFor({ state: 'hidden' });
        assert((await page.locator('#admin-table-body').innerText()).includes('Đã ẩn'));
    }
    await page.goto(base);
    await page.locator('#overlayer').waitFor({ state: 'hidden' });
    await page.locator('.site-menu-toggle').click();
    await mobile.locator('.job-mega-toggle').click();
    await mobile.locator('[data-job-expand="skill"]').click();
    assert(!(await mobile.locator('.job-mega-options').innerText()).includes('Danh mục thử'));
    assert.deepEqual(errors, []);
    await browser.close();
    console.log('PASS: six menu filters, scrolling, mobile, admin create/hide and demo category visibility.');
})().catch(function (error) {
    console.error(error);
    process.exit(1);
});

