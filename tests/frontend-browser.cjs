/* Run with PLAYWRIGHT_MODULE pointing to a local Playwright installation.
   Start php artisan serve --host=127.0.0.1 --port=8011 first. */
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const base=process.env.FRONTEND_URL||'http://127.0.0.1:8011';
const artifacts=path.resolve('storage/app/private/ui-checks');
fs.mkdirSync(artifacts,{recursive:true});
function pdf(){
 const objects=['<< /Type /Catalog /Pages 2 0 R >>','<< /Type /Pages /Kids [3 0 R] /Count 1 >>','<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 300] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>','<< /Length 44 >>\nstream\nBT /F1 16 Tf 30 250 Td (CV demo VIEJOB) Tj ET\nendstream','<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'];
 let output='%PDF-1.4\n',offsets=[0];objects.forEach((o,i)=>{offsets.push(Buffer.byteLength(output));output+=`${i+1} 0 obj\n${o}\nendobj\n`;});const xref=Buffer.byteLength(output);output+=`xref\n0 6\n0000000000 65535 f \n`;offsets.slice(1).forEach(n=>output+=`${String(n).padStart(10,'0')} 00000 n \n`);output+=`trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n${xref}\n%%EOF`;return Buffer.from(output);
}
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.EDGE_PATH||'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',headless:true});
 const context=await browser.newContext({viewport:{width:1440,height:1000},locale:'vi-VN'});
 const page=await context.newPage();const errors=[];const failures=[];
 page.on('pageerror',e=>errors.push(e.message));page.on('console',m=>{if(m.type()==='error')errors.push(m.text());});page.on('requestfailed',r=>failures.push(r.url()));
 const pages=['/','/viec-lam','/viec-lam/1','/cong-ty','/gioi-thieu','/nha-tuyen-dung','/dang-nhap','/dang-ky','/quen-mat-khau','/dat-lai-mat-khau','/ung-vien/tong-quan','/ung-vien/cv','/ung-vien/ky-nang','/ung-vien/goi-y-ai','/ung-vien/da-luu','/ung-vien/ung-tuyen','/ung-vien/thong-bao','/ung-vien/ho-so','/ung-vien/cai-dat'];
 const localLinks=new Set();
 for(const width of [1440,768,390]){
   await page.setViewportSize({width,height:1000});
   for(const route of pages){
     const response=await page.goto(base+route);assert.equal(response.status(),200,route);await page.evaluate(()=>document.fonts.ready);
     const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>window.innerWidth+1);assert.equal(overflow,false,`Horizontal overflow: ${route} at ${width}`);
     if(width===1440){const links=await page.locator('a[href]').evaluateAll(els=>els.map(e=>e.getAttribute('href')));links.filter(h=>h?.startsWith(base)||h?.startsWith('/')).forEach(h=>localLinks.add(h));}
     if(['/', '/ung-vien/tong-quan','/ung-vien/cv','/viec-lam'].includes(route))await page.screenshot({path:path.join(artifacts,`${route.replace(/\//g,'-')||'home'}-${width}.png`),fullPage:true});
   }
 }
 for(const link of localLinks){const result=await context.request.get(new URL(link,base).href);assert.equal(result.status(),200,`Broken link: ${link}`);}
 await page.goto(base+'/');await page.locator('.menu-toggle').click();assert.equal(await page.locator('.menu-toggle').getAttribute('aria-expanded'),'true');await page.keyboard.press('Escape');assert.equal(await page.locator('.menu-toggle').getAttribute('aria-expanded'),'false');
 await page.setViewportSize({width:1440,height:1000});
 await page.goto(base+'/dang-ky');await page.locator('[data-demo-form] button[type=submit]').click();assert.match(await page.locator('#name-error').innerText(),/bắt buộc/);await page.locator('#name').fill('Ứng viên Demo');await page.locator('#email').fill('demo@example.com');await page.locator('#password').fill('abcdefgh');await page.locator('#password_confirmation').fill('different');await page.locator('[name=consent]').check();await page.locator('[data-demo-form] button[type=submit]').click();assert.match(await page.locator('#password_confirmation-error').innerText(),/chưa khớp/);await page.locator('#password_confirmation').fill('abcdefgh');await page.locator('[data-demo-form] button[type=submit]').click();assert.match(await page.locator('.form-feedback').innerText(),/chưa/);assert.equal(await page.locator('#password').inputValue(),'');
 await page.goto(base+'/ung-vien/cv');
 await page.locator('#resume-upload').setInputFiles({name:'not.pdf',mimeType:'application/pdf',buffer:Buffer.from('not a pdf')});await page.locator('#upload-error:not([hidden])').waitFor();
 await page.locator('#resume-upload').setInputFiles({name:'cv-backend.pdf',mimeType:'application/pdf',buffer:pdf()});await page.locator('.resume-card').waitFor();assert.match(await page.locator('#resume-count').innerText(),/1 \/ 10/);assert.equal(await page.locator('.main-badge').count(),1);
 await page.locator('.resume-actions a').click();await page.locator('[data-simulate-skills]').click();await page.locator('#skill-input').fill(' php ');await page.locator('#skill-form button').click();assert.equal(await page.locator('#skill-tags .skill-tag').count(),4);await page.locator('[data-confirm-skills]').click();
 await page.goto(base+'/ung-vien/goi-y-ai');assert.ok(await page.locator('.match-card').count()>0);await page.screenshot({path:path.join(artifacts,'recommendations.png'),fullPage:true});
 await page.goto(base+'/viec-lam/1');await page.locator('[data-save="1"]').click();await page.locator('[data-apply="1"]').click();await page.locator('#apply-form input[type=checkbox]').check();await page.locator('#apply-form button[type=submit]').click();await page.locator('#apply-modal').waitFor({state:'hidden'});
 await page.goto(base+'/ung-vien/ung-tuyen');assert.equal(await page.locator('tbody tr').count(),1);await page.locator('[data-withdraw]').click();await page.locator('#confirm-modal button[value=confirm]').click();await page.locator('[data-apply="1"]').waitFor();await page.locator('[data-apply="1"]').click();await page.locator('#apply-form input[type=checkbox]').check();await page.locator('#apply-form button[type=submit]').click();await page.locator('#apply-modal').waitFor({state:'hidden'});assert.equal(await page.locator('tbody tr').count(),2);await page.locator('[data-withdraw]').click();await page.locator('#confirm-modal button[value=confirm]').click();await page.waitForFunction(()=>!document.querySelector('[data-apply="1"]'));await page.screenshot({path:path.join(artifacts,'applications.png'),fullPage:true});
 await page.goto(base+'/ung-vien/thong-bao');assert.equal(await page.locator('.notification').count(),4);await page.locator('[data-read-all]').click();assert.equal(await page.locator('.notification.unread').count(),0);
 await page.goto(base+'/ung-vien/cv');await page.locator('#resume-upload').setInputFiles({name:'cv-second.pdf',mimeType:'application/pdf',buffer:pdf()});await page.waitForFunction(()=>document.querySelectorAll('.resume-card').length===2);
 await page.locator('[data-cv-delete]').first().click();assert.equal(await page.locator('#confirm-value option').count(),2);await page.locator('#confirm-value').selectOption({index:1});await page.locator('#confirm-modal button[value=confirm]').click();await page.waitForFunction(()=>document.querySelectorAll('.resume-card').length===1);assert.equal(await page.locator('.main-badge').count(),1);
 await page.locator('[data-cv-delete]').click();await page.locator('#confirm-modal button[value=confirm]').click();await page.locator('#resume-list .empty-state').waitFor();await page.goto(base+'/ung-vien/goi-y-ai');assert.match(await page.locator('#recommendation-list').innerText(),/tạm dừng/);
 await page.goto(base+'/ung-vien/da-luu');await page.locator('summary').click();await page.locator('[data-demo-saved]').click();assert.match(await page.locator('#saved-list').innerText(),/không khả dụng/);assert.equal(await page.locator('#saved-list a[href="/viec-lam/11"]').count(),0);
 await page.goto(base+'/ung-vien/ho-so');await page.locator('#profile-name').fill('Nguyễn Minh Anh');await page.locator('#profile-form button[type=submit]').click();await page.reload();assert.equal(await page.locator('#profile-name').inputValue(),'Nguyễn Minh Anh');
 await page.goto(base+'/ung-vien/cv');
 await page.locator('#resume-upload').setInputFiles({name:'too-large.pdf',mimeType:'application/pdf',buffer:Buffer.alloc(5*1024*1024+1)});await page.locator('#upload-error:not([hidden])').waitFor();assert.match(await page.locator('#upload-error').innerText(),/5 MB/);
 await page.locator('#resume-upload').setInputFiles(Array.from({length:10},(_,i)=>({name:`cv-${i}.pdf`,mimeType:'application/pdf',buffer:pdf()})));await page.waitForFunction(()=>document.querySelectorAll('.resume-card').length===10);
 await page.locator('#resume-upload').setInputFiles({name:'eleventh.pdf',mimeType:'application/pdf',buffer:pdf()});await page.locator('#upload-error:not([hidden])').waitFor();assert.match(await page.locator('#upload-error').innerText(),/10 CV/);
 await page.locator('[data-cv-rename]').first().click();await page.locator('#confirm-value').fill('CV đã đổi tên');await page.locator('#confirm-modal button[value=confirm]').click();await page.waitForFunction(()=>document.querySelector('.resume-card h3').textContent==='CV đã đổi tên');
 const downloadPromise=page.waitForEvent('download');await page.locator('[data-cv-download]').first().click();const download=await downloadPromise;assert.equal(download.suggestedFilename(),'cv-0.pdf');
 const chooserPromise=page.waitForEvent('filechooser');await page.locator('[data-cv-replace]').first().click();const chooser=await chooserPromise;await chooser.setFiles({name:'cv-replaced.pdf',mimeType:'application/pdf',buffer:pdf()});await page.waitForFunction(()=>document.querySelector('.resume-card').textContent.includes('cv-replaced.pdf'));assert.match(await page.locator('.resume-card').first().innerText(),/Chờ phân tích/);
 await page.locator('.resume-card summary').first().click();await page.locator('[data-cv-state]').first().selectOption('failed');await page.locator('.resume-actions a').first().click();assert.match(await page.locator('#skills-content').innerText(),/Phân tích thất bại/);
 await page.goto(base+'/ung-vien/ung-tuyen');await page.locator('summary').click();await page.locator('[data-demo-applications]').click();assert.ok(await page.locator('tbody tr').count()>=6);
 await page.goto(base+'/ung-vien/tong-quan');await page.screenshot({path:path.join(artifacts,'dashboard-populated.png'),fullPage:true});await page.setViewportSize({width:390,height:844});await page.goto(base+'/ung-vien/cv');assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1),false);await page.screenshot({path:path.join(artifacts,'cv-populated-mobile.png'),fullPage:true});
 assert.deepEqual(errors,[],'Browser console errors');assert.deepEqual(failures,[],'Network failures');
 fs.writeFileSync(path.join(artifacts,'result.json'),JSON.stringify({routes:pages.length,viewports:[1440,768,390],links:localLinks.size,errors,failures,flows:['validation','PDF signature','skills normalization','AI empty and ready','save','apply','withdraw','reapply','third attempt blocked','notifications','delete primary with replacement','delete last CV','hidden job','profile persistence']},null,2));
 console.log('PASS: 19 pages x 3 viewports; links; CV, skills, AI, application and profile flows.');await browser.close();
})().catch(e=>{console.error(e);process.exit(1);});
