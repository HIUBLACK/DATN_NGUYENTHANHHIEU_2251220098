const { chromium } = require(process.env.TEMP + '/viejob-browser-tools/node_modules/playwright');
const fs = require('fs');
const assert = require('node:assert/strict');

(async function () {
    const browser = await chromium.launch({ executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', headless: true });
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('response', response => {
        if (response.status() >= 400) errors.push(response.status() + ' ' + response.url());
    });
    const paths = ['/', '/viec-lam', '/viec-lam/1', '/cong-ty', '/gioi-thieu', '/nha-tuyen-dung', '/dang-nhap', '/dang-ky', '/quen-mat-khau', '/dat-lai-mat-khau', '/ung-vien/cv', '/ung-vien/ky-nang', '/ung-vien/goi-y-ai', '/ung-vien/da-luu', '/ung-vien/ung-tuyen', '/ung-vien/thong-bao', '/ung-vien/ho-so', '/ung-vien/cai-dat', '/ung-vien/tin-nhan'];
    const overflow = [];
    fs.mkdirSync('storage/app/private/jobboard-checks', { recursive: true });
    for (const width of [1440, 768, 390]) {
        await page.setViewportSize({ width, height: 900 });
        for (const path of paths) {
            await page.goto('http://127.0.0.1:8012' + path);
            await page.waitForTimeout(350);
            const dimensions = await page.evaluate(() => ({ scroll: document.documentElement.scrollWidth, client: document.documentElement.clientWidth }));
            if (dimensions.scroll > dimensions.client + 2) overflow.push({ width, path, ...dimensions });
            if (['/', '/viec-lam', '/ung-vien/cv'].includes(path)) {
                await page.waitForTimeout(1000);
                await page.screenshot({ path: 'storage/app/private/jobboard-checks/' + (path === '/' ? 'home' : path.replaceAll('/', '-')) + '-' + width + '.png', fullPage: true });
            }
        }
    }
    console.log(JSON.stringify({ errors, overflow }, null, 2));
    await browser.close();
    assert.deepEqual(errors, []);
    assert.deepEqual(overflow, []);
})();
