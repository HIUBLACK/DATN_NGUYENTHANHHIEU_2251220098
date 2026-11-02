(function () {
    'use strict';
    const R = window.ViejobRules;
    const $ = (s, root=document) => root.querySelector(s);
    const $$ = (s, root=document) => [...root.querySelectorAll(s)];
    const esc = value => String(value ?? '').replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const jobs = JSON.parse($('#job-data')?.textContent || '[]');
    const key = 'viejob-ui-demo-v1';
    const initial = () => ({resumes:[],saved:[],applications:[],notifications:[],profile:{name:'Minh Anh',email:'minhanh@example.com',phone:'',location:'TP. Hồ Chí Minh',title:'Lập trình viên Backend',experience:'1–3 năm',bio:''},member:false});
    let state;
    try { state = JSON.parse(localStorage.getItem(key)) || initial(); } catch { state=initial(); }
    if (!Array.isArray(state.resumes) || !Array.isArray(state.saved) || !Array.isArray(state.applications) || !Array.isArray(state.notifications) || !state.profile) state=initial();
    let toastTimer, dbPromise, uploadBusy=false, applyBusy=false, replaceId=null, applyId=null, savedFilter='all', notificationFilter='all', draftSkills=[];
    const stamp = () => new Date().toISOString();
    const uid = () => crypto.randomUUID();
    const date = value => new Date(value).toLocaleString('vi-VN',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'});
    const findJob = id => jobs.find(j=>j.id===Number(id));
    const badge = status => `<span class="status status-${esc(status)}">${esc(R.statuses[status]||'Chưa xác định')}</span>`;
    const empty = (title, desc, href='/viec-lam', action='Khám phá việc làm') => `<div class="empty-state"><span class="empty-icon"><i class="far fa-folder-open" aria-hidden="true"></i></span><h3>${esc(title)}</h3><p>${esc(desc)}</p>${href ? `<a class="button secondary" href="${href}">${esc(action)}</a>` : ''}</div>`;
    function toast(message) { const el=$('#toast'); if(!el)return; el.textContent=message; el.hidden=false; clearTimeout(toastTimer); toastTimer=setTimeout(()=>el.hidden=true,5500); }
    function persist() { try {localStorage.setItem(key,JSON.stringify(state)); return true;} catch {toast('Không thể lưu dữ liệu demo. Hãy kiểm tra dung lượng hoặc quyền lưu trữ của trình duyệt.'); return false;} }
    function database() {
        if (!dbPromise) dbPromise = new Promise((resolve,reject)=>{ const req=indexedDB.open('viejob-demo-files',1); req.onupgradeneeded=()=>req.result.createObjectStore('files');req.onsuccess=()=>resolve(req.result);req.onerror=()=>reject(req.error); });
        return dbPromise;
    }
    async function files(action, id, value) {
        const db=await database();
        return new Promise((resolve,reject)=>{const tx=db.transaction('files',action==='get'?'readonly':'readwrite');const store=tx.objectStore('files');const req=action==='put'?store.put(value,id):action==='clear'?store.clear():store[action](id);tx.oncomplete=()=>resolve(req.result);tx.onerror=()=>reject(tx.error);tx.onabort=()=>reject(tx.error);});
    }
    function confirmAction(title, description, extra='') {
        return new Promise(resolve=>{const d=$('#confirm-modal');$('#confirm-title').textContent=title;$('#confirm-description').textContent=description;$('#confirm-extra').innerHTML=extra;d.returnValue='cancel';d.addEventListener('close',()=>resolve({confirmed:d.returnValue==='confirm',value:$('#confirm-value')?.value}),{once:true});d.showModal();});
    }
    function notify(status, application) {
        state.notifications.unshift({id:uid(),applicationId:application.id,status,at:stamp(),read:false,title:R.statuses[status],body:`${findJob(application.jobId)?.title || 'Công việc mẫu'} · ${status==='reviewing'?'Cập nhật nội bộ':'Sự kiện email minh họa, chưa gửi thư'}`});
    }
    function syncHeader() {
        const member=state.member || location.pathname.startsWith('/ung-vien/');
        $$('[data-member]').forEach(e=>e.hidden=!member);$$('[data-guest]').forEach(e=>e.hidden=member);
        $$('[data-profile-name]').forEach(e=>e.textContent=state.profile.name||'Bạn');
        $$('[data-save]').forEach(e=>{const saved=state.saved.includes(Number(e.dataset.save));e.setAttribute('aria-pressed',String(saved));const icon=$('i',e);if(icon)icon.className=(saved?'fas':'far')+' fa-bookmark';});
    }
    function jobMarkup(job) {
        if (!job) return `<article class="panel"><h3>Công việc không khả dụng</h3><p class="small">Tin đã được ẩn. Nội dung chi tiết không được công khai.</p><button class="button secondary compact" data-save="11">Bỏ lưu</button></article>`;
        return `<article class="job-card"><div class="job-top"><span class="company-logo ${esc(job.company.color)}">${esc(job.company.initials)}</span><div><span class="company-name">${esc(job.company.name)}</span><h3><a href="/viec-lam/${job.id}">${esc(job.title)}</a></h3></div><button class="icon-button save-button" data-save="${job.id}" aria-label="Lưu hoặc bỏ lưu việc làm" aria-pressed="${state.saved.includes(job.id)}"><i class="far fa-bookmark" aria-hidden="true"></i></button></div><div class="job-meta"><span>${esc(job.location)}</span><span>${esc(job.mode)}</span><span>${esc(job.level)}</span></div><div class="skill-list">${job.skills.map(s=>`<span class="skill-tag">${esc(s)}</span>`).join('')}</div><div class="job-bottom"><strong class="salary">${job.min}–${job.max} triệu / tháng</strong>${badge(job.status)}</div><div class="job-footnote"><span>Hạn: ${esc(job.deadline)}</span><a href="/viec-lam/${job.id}">Xem chi tiết ↗</a></div><div class="actions">${job.status==='open'?`<button class="button compact" data-apply="${job.id}">Ứng tuyển ↗</button>`:'<button class="button compact" disabled>Không nhận ứng tuyển</button>'}<button class="text-button small" data-save="${job.id}">Bỏ lưu</button></div></article>`;
    }
    function renderResumes() {
        const container=$('#resume-list');if(!container)return;
        $('#resume-count').textContent=`${state.resumes.length} / 10 CV`;
        container.innerHTML=state.resumes.length?state.resumes.map(cv=>`<article class="resume-card"><div class="resume-head"><span class="pdf-icon"><i class="far fa-file-pdf" aria-hidden="true"></i></span><div><h3>${esc(cv.name)}</h3><p>${esc(cv.filename)} · ${esc(date(cv.createdAt))}</p></div>${cv.main?'<span class="main-badge">CV chính</span>':''}${badge(cv.status)}</div><div class="resume-actions"><button data-cv-view="${cv.id}">Xem PDF</button><button data-cv-download="${cv.id}">Tải xuống</button><button data-cv-rename="${cv.id}">Đổi tên</button><button data-cv-replace="${cv.id}">Thay thế PDF</button>${!cv.main?`<button data-cv-main="${cv.id}">Đặt làm CV chính</button>`:''}<a href="/ung-vien/ky-nang?cv=${cv.id}">Xác nhận kỹ năng ↗</a><button class="danger-text" data-cv-delete="${cv.id}">Xóa</button></div><details class="mt-3"><summary class="small muted">Trạng thái phân tích demo</summary><label for="state-${cv.id}">Chọn trạng thái để kiểm tra giao diện</label><select id="state-${cv.id}" data-cv-state="${cv.id}">${['pending','processing','needs_review','completed','failed'].map(s=>`<option value="${s}" ${s===cv.status?'selected':''}>${esc(R.statuses[s])}</option>`).join('')}</select><p class="small mt-2 mb-0">Không có mô hình AI xử lý file. Trạng thái hoàn tất chỉ được đặt sau khi bạn xác nhận kỹ năng.</p></details></article>`).join(''):empty('Chưa có CV nào','Tải lên PDF đầu tiên để chuẩn bị ứng tuyển. Chưa có CV chính, gợi ý AI đang tạm dừng.',null);
    }
    async function upload(fileList, replacing=null) {
        if(uploadBusy)return; uploadBusy=true;const loading=$('#upload-loading');if(loading)loading.hidden=false;const error=$('#upload-error');if(error)error.hidden=true;
        try {
            for(const file of fileList) {
                const validation=R.pdfError(file,state.resumes.length,Boolean(replacing));if(validation)throw Error(validation);
                const header=new TextDecoder().decode(await file.slice(0,5).arrayBuffer());const tail=new TextDecoder().decode(await file.slice(-2048).arrayBuffer());
                if(header!=='%PDF-' || !tail.includes('%%EOF'))throw Error('Tệp không có cấu trúc PDF hợp lệ. Hãy xuất lại CV thành PDF.');
                const id=replacing || uid();await files('put',id,file);
                if(replacing) { const cv=state.resumes.find(c=>c.id===id);if(!cv)throw Error('CV không còn tồn tại.');cv.filename=file.name;cv.status='pending';cv.skills=[];cv.recognized=[];cv.updatedAt=stamp(); }
                else state.resumes.push({id,name:file.name.replace(/\.pdf$/i,''),filename:file.name,createdAt:stamp(),status:'pending',main:state.resumes.length===0,skills:[],recognized:[]});
                persist();
            }
            toast(replacing?'Đã thay PDF cục bộ. CV đang chờ phân tích lại (demo).':'Đã lưu PDF cục bộ. Chưa chạy phân tích AI.');
        } catch(e) {if(error){error.textContent=e.message;error.hidden=false;}else toast(e.message);}
        finally {uploadBusy=false;if(loading)loading.hidden=true;renderAll();}
    }
    function selectedCv() {const id=new URLSearchParams(location.search).get('cv');return state.resumes.find(c=>c.id===id) || (!id && (state.resumes.find(c=>c.main)||state.resumes[0]));}
    function renderSkills() {
        const el=$('#skills-content');if(!el)return;const cv=selectedCv();
        if(!cv){el.innerHTML=empty('Chưa chọn CV','Tải lên một CV PDF để bắt đầu.','/ung-vien/cv','Quản lý CV');return;}
        draftSkills=[...(cv.skills||[])];
        el.innerHTML=`<div class="resume-head"><span class="pdf-icon"><i class="far fa-file-pdf" aria-hidden="true"></i></span><div><h2 class="h4">${esc(cv.name)}</h2><p>${esc(cv.filename)}</p></div>${badge(cv.status)}</div>${cv.status==='failed'?'<div class="notice notice-error">Phân tích thất bại (minh họa). Thử lại hoặc nhập kỹ năng thủ công.</div>':''}${cv.status==='processing'?'<div class="loading-state"><span class="spinner"></span>Trạng thái đang phân tích minh họa. Chưa có dịch vụ AI kết nối.</div>':''}<div class="actions"><button class="button secondary compact" data-simulate-skills="${cv.id}">Nạp kỹ năng mẫu (không đọc PDF)</button><button class="text-button small" data-cv-view="${cv.id}">Xem PDF</button></div><h3 class="mt-4">Kỹ năng nhận diện — minh họa</h3><p class="small">${cv.recognized?.length?esc(cv.recognized.join(', ')):'Chưa có kết quả. Nạp dữ liệu mẫu hoặc nhập kỹ năng bên dưới.'}</p><h3 class="mt-4">Kỹ năng đã chuẩn hóa</h3><p class="small">Có thể xóa rồi thêm lại để chỉnh sửa. Các kỹ năng trùng nhau sẽ được gộp.</p><div id="skill-tags" class="skill-list"></div><form id="skill-form" class="skill-editor"><label for="skill-input" class="visually-hidden">Thêm kỹ năng</label><input id="skill-input" maxlength="50" placeholder="Ví dụ: Laravel" required><button class="button secondary compact">Thêm</button></form><p class="form-error" id="skill-error"></p><div class="actions"><button class="button" data-confirm-skills="${cv.id}">Xác nhận dữ liệu demo</button></div>`;
        renderSkillTags();
        $('#skill-form').addEventListener('submit',e=>{e.preventDefault();const value=$('#skill-input').value.trim();if(!value)return;draftSkills=R.normalizeSkills([...draftSkills,value]);$('#skill-input').value='';renderSkillTags();});
    }
    function renderSkillTags() {const el=$('#skill-tags');if(el)el.innerHTML=draftSkills.length?draftSkills.map((s,i)=>`<span class="skill-tag match">${esc(s)}<button data-remove-skill="${i}" aria-label="Xóa kỹ năng ${esc(s)}">×</button></span>`).join(''):'<p class="small muted">Chưa có kỹ năng được xác nhận.</p>';}
    function renderRecommendations() {
        const el=$('#recommendation-list');if(!el)return;const cv=state.resumes.find(c=>c.main);const ready=cv?.status==='completed' && cv.skills?.length;
        $('#recommendation-resume').innerHTML=cv?`<span class="eyebrow">CV CHÍNH ĐANG SỬ DỤNG</span><h3>${esc(cv.name)}</h3>${badge(cv.status)}<a class="back-link ml-3" href="/ung-vien/ky-nang?cv=${cv.id}">Kiểm tra kỹ năng ↗</a>`:'<h3>Chưa có CV chính</h3><p class="small">Tải CV lên để bắt đầu hành trình.</p>';
        if(!ready){el.innerHTML=empty('Gợi ý đang tạm dừng','Cần một CV chính hợp lệ đã xác nhận kỹ năng. Hãy tải PDF và xác nhận trước khi tiếp tục.','/ung-vien/cv','Hoàn thiện CV');return;}
        const filtered=jobs.filter(j=>j.status==='open' && (!$('#recommend-location').value||j.location===$('#recommend-location').value) && (!$('#recommend-mode').value||j.mode===$('#recommend-mode').value));
        el.innerHTML=filtered.length?filtered.slice(0,6).map((j,i)=>{const normalized=cv.skills.map(s=>s.toLowerCase());const matched=j.skills.filter(s=>normalized.includes(s.toLowerCase()));const missing=j.skills.filter(s=>!normalized.includes(s.toLowerCase()));const score=[86,79,74,68,62,58][i];return `<article class="match-card"><div class="match-heading"><div><span class="company-name">${esc(j.company.name)} · ${esc(j.location)}</span><h3 class="mt-2"><a href="/viec-lam/${j.id}">${esc(j.title)}</a></h3></div><span class="match-score">${score}%<small class="d-block muted small">minh họa</small></span></div><div class="match-progress"><progress value="${score}" max="100" aria-label="Điểm phù hợp minh họa ${score}%"></progress></div><p class="small mb-2">Kỹ năng đáp ứng</p><div class="skill-list">${matched.length?matched.map(s=>`<span class="skill-tag match">${esc(s)}</span>`).join(''):'<span class="small muted">Chưa có kỹ năng trùng khớp.</span>'}</div><p class="small mt-3 mb-2">Kỹ năng cần bổ sung</p><div class="skill-list">${missing.map(s=>`<span class="skill-tag missing">${esc(s)}</span>`).join('')||'<span class="small muted">Đã đáp ứng danh sách kỹ năng mẫu.</span>'}</div><div class="actions"><button class="button compact" data-apply="${j.id}">Ứng tuyển ↗</button><a class="button secondary compact" href="/viec-lam/${j.id}">Chi tiết</a><button class="icon-button" data-save="${j.id}" aria-label="Lưu việc làm" aria-pressed="false"><i class="far fa-bookmark" aria-hidden="true"></i></button></div></article>`;}).join(''):empty('Chưa có gợi ý theo bộ lọc','Hãy thử chọn địa điểm hoặc hình thức làm việc khác.',null);
    }
    function renderSaved() {const el=$('#saved-list');if(!el)return;const list=state.saved.filter(id=>savedFilter==='all'||(savedFilter==='open'?findJob(id)?.status==='open':findJob(id)?.status!=='open'));el.innerHTML=list.length?`<div class="job-grid">${list.map(id=>jobMarkup(findJob(id))).join('')}</div>`:empty('Chưa có việc làm đã lưu','Nhấn biểu tượng dấu trang trên công việc bạn quan tâm.');}
    function renderApplications() {
        const el=$('#application-list');if(!el)return;const filter=$('#application-filter').value;const rows=state.applications.filter(a=>filter==='all'||a.status===filter);
        el.innerHTML=rows.length?`<div class="panel p-0 table-wrap"><table><caption class="visually-hidden">Danh sách lượt ứng tuyển demo</caption><thead><tr><th>Công việc / Công ty</th><th>CV / Ngày nộp</th><th>Lượt</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>${rows.map(a=>{const j=findJob(a.jobId);const eligible=R.applicationEligibility(j,state.applications);return `<tr><td><strong>${esc(j?.title||'Công việc không khả dụng')}</strong><p>${esc(j?.company.name||'')}</p></td><td>${esc(a.cvName)}<p>${esc(date(a.at))}</p></td><td>${a.attempt}/2</td><td>${badge(a.status)}</td><td><button class="text-button" data-application-detail="${a.id}">Chi tiết</button>${R.canWithdraw(a.status)?`<button class="text-button d-block" data-withdraw="${a.id}">Rút hồ sơ</button>`:''}${a.status==='withdrawn' && eligible.allowed?`<button class="text-button d-block" data-apply="${a.jobId}">Ứng tuyển lại</button>`:''}</td></tr>`;}).join('')}</tbody></table></div>`:empty('Chưa có hồ sơ ứng tuyển','Hãy tìm công việc phù hợp và chọn CV để thử luồng ứng tuyển.');
    }
    function renderApplicationDetail(id) {
        const el=$('#application-detail');const a=state.applications.find(x=>x.id===id);if(!el||!a)return;el.hidden=false;
        el.innerHTML=`<span class="eyebrow">LỊCH SỬ HỒ SƠ · LƯỢT ${a.attempt}</span><h2 class="h4">${esc(findJob(a.jobId)?.title||'Công việc không khả dụng')}</h2><p class="small">CV đã dùng: ${esc(a.cvName)} · ${esc(a.cvFilename||'Tệp mẫu')}</p>${a.letter?`<p class="small">Thư giới thiệu: ${esc(a.letter)}</p>`:''}<ol class="timeline">${a.timeline.map(t=>`<li><strong>${esc(R.statuses[t.status])}</strong><small>${esc(date(t.at))}</small></li>`).join('')}</ol>${a.sample?'<span class="preview-label">Hồ sơ mẫu phục vụ kiểm tra giao diện.</span>':''}`;el.scrollIntoView({behavior:'smooth',block:'nearest'});
    }
    function renderNotifications() {
        const el=$('#notification-list');if(!el)return;const list=state.notifications.filter(n=>notificationFilter==='all'||(notificationFilter==='read'?n.read:!n.read));
        el.innerHTML=list.length?list.map(n=>`<article class="notification ${n.read?'':'unread'}"><span class="avatar"><i class="far fa-bell" aria-hidden="true"></i></span><div><h3>${esc(n.title)}</h3><p>${esc(n.body)}</p><span class="muted small">${esc(date(n.at))} · ${n.read?'Đã đọc':'Chưa đọc'}</span><a class="back-link d-block mt-2" href="/ung-vien/ung-tuyen?application=${n.applicationId}">Xem hồ sơ ứng tuyển ↗</a></div>${!n.read?`<button class="icon-button" data-read="${n.id}" aria-label="Đánh dấu đã đọc">✓</button>`:''}</article>`).join(''):empty('Bạn đã xem hết cập nhật','Thông báo ứng tuyển mới sẽ xuất hiện tại đây.',null);
    }
    function renderDashboard() {
        if(!$('#recent-applications'))return;
        const values={total:state.applications.length,reviewing:state.applications.filter(a=>a.status==='reviewing').length,interview:state.applications.filter(a=>a.status==='interview').length,saved:state.saved.length};
        $$('[data-stat]').forEach(el=>el.textContent=values[el.dataset.stat]);
        $('#recent-applications').innerHTML=state.applications.length?state.applications.slice(-3).reverse().map(a=>`<div class="mini-item"><div><a href="/ung-vien/ung-tuyen?application=${a.id}">${esc(findJob(a.jobId)?.title||'Công việc không khả dụng')}</a><p>${esc(date(a.at))}</p></div>${badge(a.status)}</div>`).join(''):empty('Hành trình đang chờ bạn','Chưa có lượt ứng tuyển demo nào.');
        $('#recent-notifications').innerHTML=state.notifications.length?state.notifications.slice(0,3).map(n=>`<div class="mini-item"><a href="/ung-vien/thong-bao">${esc(n.title)}</a><span class="muted small">${n.read?'Đã đọc':'Mới'}</span></div>`).join(''):'<p class="small">Chưa có thông báo mới.</p>';
        const cv=state.resumes.find(c=>c.main);$('#dashboard-resume').innerHTML=cv?`<h3>${esc(cv.name)}</h3><p class="small">${esc(cv.filename)}</p>${badge(cv.status)}`:'<p class="small">Chưa có CV chính. Tải lên PDF để sẵn sàng ứng tuyển.</p>';
        const recommendations=$('#dashboard-recommendations');
        if(recommendations) recommendations.innerHTML=cv?.status==='completed' && cv.skills?.length
            ? jobs.filter(j=>j.status==='open').slice(0,2).map((j,i)=>`<div class="mini-item"><div><a href="/viec-lam/${j.id}"><strong>${esc(j.title)}</strong></a><p>${esc(j.company.name)} · ${esc(j.location)}</p></div><span class="status status-open">${i===0?86:79}% · minh họa</span></div>`).join('')
            : '<p class="small">Gợi ý đang tạm dừng. Tải CV chính và xác nhận kỹ năng để tiếp tục.</p>';
    }
    function renderAll() {renderResumes();renderSaved();renderApplications();renderNotifications();renderDashboard();renderRecommendations();syncHeader();}
    function openApplication(id) {
        const job=findJob(id);const eligibility=R.applicationEligibility(job,state.applications);if(!eligibility.allowed){toast(eligibility.reason);return;}
        applyId=job.id;$('#apply-title').textContent=eligibility.attempt===2?'Ứng tuyển lại — lượt 2':'Ứng tuyển công việc';$('#apply-job').textContent=job.title+' · '+job.company.name;
        $('#apply-form').reset();$('#apply-cv').innerHTML=state.resumes.length?state.resumes.map(cv=>`<option value="${cv.id}" ${cv.main?'selected':''}>${esc(cv.name)} · ${esc(R.statuses[cv.status])}</option>`).join(''):'<option value="">Chưa có CV PDF — vui lòng tải lên trước</option>';
        $('#apply-error').textContent=state.resumes.length?'':'Bạn cần tải lên CV PDF trước khi ứng tuyển.';$('#apply-modal').showModal();
    }
    function validateForm(form) {
        let valid=true;let first=null;$$('.field-error',form).forEach(e=>e.textContent='');
        $$('input,select,textarea',form).forEach(input=>{let message='';if(input.required && (input.type==='checkbox'?!input.checked:!input.value.trim()))message='Vui lòng điền thông tin bắt buộc.';else if(input.type==='email' && input.value && !input.validity.valid)message='Vui lòng nhập địa chỉ email hợp lệ.';else if(input.minLength>0 && input.value.length<input.minLength)message=`Vui lòng nhập ít nhất ${input.minLength} ký tự.`;else if(input.name==='password_confirmation' && input.value!==$('[name="password"]',form)?.value)message='Mật khẩu xác nhận chưa khớp.';else if(input.type==='tel' && input.value && !/^[+\d\s().-]{9,20}$/.test(input.value))message='Vui lòng nhập số điện thoại hợp lệ.';input.setAttribute('aria-invalid',String(Boolean(message)));let error=$(`#${input.id}-error`,form);if(message){valid=false;first??=input;if(!error){error=document.createElement('span');error.className='field-error';input.parentElement.append(error);}error.textContent=message;}});
        first?.focus();return valid;
    }
    $$('.menu-toggle').forEach(button=>button.addEventListener('click',()=>{const nav=$('#site-nav');nav.classList.toggle('is-open');button.setAttribute('aria-expanded',String(nav.classList.contains('is-open')));}));
    document.addEventListener('keydown',e=>{if(e.key==='Escape'){ $('#site-nav')?.classList.remove('is-open');$('.menu-toggle')?.setAttribute('aria-expanded','false'); }});
    document.addEventListener('click', async e=>{
        const target=e.target.closest('button,a');if(!target)return;
        try {
            if(target.matches('[data-toggle-password]')){const input=document.getElementById(target.dataset.togglePassword);const reveal=input.type==='password';input.type=reveal?'text':'password';target.setAttribute('aria-pressed',String(reveal));target.setAttribute('aria-label',reveal?'Ẩn mật khẩu':'Hiển thị mật khẩu');}
            if(target.matches('[data-enter-demo]')){state.member=true;persist();}
            if(target.matches('[data-logout]')){state.member=false;persist();location.href='/';}
            if(target.matches('[data-save]')){const id=Number(target.dataset.save);state.saved=state.saved.includes(id)?state.saved.filter(x=>x!==id):[...state.saved,id];persist();renderAll();toast(state.saved.includes(id)?'Đã lưu vào danh sách demo.':'Đã bỏ lưu công việc.');}
            if(target.matches('[data-apply]'))openApplication(target.dataset.apply);
            if(target.matches('[data-close-dialog]'))target.closest('dialog').close();
            if(target.matches('[data-cv-main]')){state.resumes.forEach(c=>c.main=c.id===target.dataset.cvMain);persist();renderAll();toast('Đã cập nhật CV chính.');}
            if(target.matches('[data-cv-rename]')){const cv=state.resumes.find(c=>c.id===target.dataset.cvRename);const answer=await confirmAction('Đổi tên CV','Tên mới giúp bạn chọn đúng CV khi ứng tuyển.',`<label for="confirm-value">Tên CV</label><input id="confirm-value" required maxlength="100" value="${esc(cv.name)}">`);if(answer.confirmed && answer.value.trim()){cv.name=answer.value.trim();persist();renderAll();}}
            if(target.matches('[data-cv-delete]')){const cv=state.resumes.find(c=>c.id===target.dataset.cvDelete);const others=state.resumes.filter(c=>c.id!==cv.id);const needs=cv.main && others.length;const answer=await confirmAction('Xóa CV này?',needs?'Hãy chọn CV chính thay thế trước khi xóa.':'Thao tác xóa PDF cục bộ. Nếu đây là CV cuối cùng, gợi ý AI sẽ tạm dừng.',needs?`<label for="confirm-value">CV chính thay thế</label><select id="confirm-value" required><option value="">Chọn CV thay thế</option>${others.map(c=>`<option value="${c.id}">${esc(c.name)}</option>`).join('')}</select>`:'');if(answer.confirmed){if(needs&&!answer.value){toast('Cần chọn CV chính thay thế.');return;}await files('delete',cv.id);state.resumes=others;if(needs)state.resumes.forEach(c=>c.main=c.id===answer.value);persist();renderAll();toast('Đã xóa CV cục bộ.');}}
            if(target.matches('[data-cv-replace]')){replaceId=target.dataset.cvReplace;$('#resume-replace').click();}
            if(target.matches('[data-cv-view],[data-cv-download]')){const id=target.dataset.cvView||target.dataset.cvDownload;const file=await files('get',id);if(!file){toast('Không tìm thấy PDF cục bộ. Vui lòng thay thế hoặc tải lại CV.');return;}const url=URL.createObjectURL(file);if(target.dataset.cvDownload){const link=document.createElement('a');link.href=url;link.download=file.name||'cv.pdf';link.click();}else{const tab=window.open(url,'_blank');if(tab)tab.opener=null;else toast('Trình duyệt chặn cửa sổ PDF. Hãy cho phép mở cửa sổ hoặc chọn tải xuống.');}setTimeout(()=>URL.revokeObjectURL(url),60000);}
            if(target.matches('[data-simulate-skills]')){const cv=selectedCv();cv.status='needs_review';cv.recognized=['php','Laravel','mysql','REST API'];cv.skills=R.normalizeSkills(cv.recognized);persist();renderSkills();}
            if(target.matches('[data-remove-skill]')){draftSkills.splice(Number(target.dataset.removeSkill),1);renderSkillTags();}
            if(target.matches('[data-confirm-skills]')){if(!draftSkills.length){$('#skill-error').textContent='Thêm ít nhất một kỹ năng trước khi xác nhận.';return;}const cv=selectedCv();cv.skills=R.normalizeSkills(draftSkills);cv.status='completed';persist();renderSkills();toast('Đã xác nhận kỹ năng demo. PDF gốc không thay đổi.');}
            if(target.matches('[data-demo-saved]')){state.saved=[...new Set([...state.saved,9,10,11])];persist();renderAll();}
            if(target.matches('[data-application-detail]'))renderApplicationDetail(target.dataset.applicationDetail);
            if(target.matches('[data-withdraw]')){const a=state.applications.find(x=>x.id===target.dataset.withdraw);if(!a||!R.canWithdraw(a.status)){toast('Không thể rút hồ sơ ở trạng thái hiện tại.');return;}const answer=await confirmAction('Rút hồ sơ ứng tuyển?',a.attempt===1?'Bạn có thể ứng tuyển lại một lần nếu công việc vẫn đang tuyển.':'Đây là lượt thứ hai. Bạn không thể ứng tuyển lần thứ ba.');if(answer.confirmed){a.status='withdrawn';a.timeline.push({status:'withdrawn',at:stamp()});notify('withdrawn',a);persist();renderAll();renderApplicationDetail(a.id);toast('Đã rút hồ sơ demo. Không gửi email thật.');}}
            if(target.matches('[data-demo-applications]')){if(state.applications.some(a=>a.sample)){toast('Đã có các hồ sơ minh họa.');return;}['applied','reviewing','interview','hired','rejected','withdrawn'].forEach((status,i)=>{const j=jobs[i+1];if(state.applications.some(a=>a.jobId===j.id))return;const at=stamp();const a={id:uid(),jobId:j.id,cvName:'CV minh họa',cvFilename:'cv-mau.pdf',at,status,attempt:1,sample:true,timeline:[{status:'applied',at}]};if(status!=='applied')a.timeline.push({status,at});state.applications.push(a);notify(status,a);});persist();renderAll();toast('Đã tạo hồ sơ mẫu trên trình duyệt.');}
            if(target.matches('[data-read]')){const n=state.notifications.find(n=>n.id===target.dataset.read);if(n)n.read=true;persist();renderAll();}
            if(target.matches('[data-read-all]')){state.notifications.forEach(n=>n.read=true);persist();renderAll();toast('Đã đánh dấu tất cả thông báo đã đọc.');}
            if(target.matches('[data-reset-demo]')){const answer=await confirmAction('Xóa toàn bộ dữ liệu demo?','CV PDF, ảnh đại diện và dữ liệu demo trong trình duyệt này sẽ bị xóa.');if(answer.confirmed){await files('clear');state=initial();persist();location.href='/ung-vien/tong-quan';}}
        } catch(error){toast('Không thể hoàn tất thao tác. Vui lòng kiểm tra quyền lưu trữ và thử lại.');console.error(error);}
    });
    $('#resume-upload')?.addEventListener('change',async e=>{await upload([...e.target.files]);e.target.value='';});
    $('#resume-replace')?.addEventListener('change',async e=>{if(e.target.files[0])await upload([e.target.files[0]],replaceId);e.target.value='';});
    const zone=$('#upload-zone');if(zone){['dragenter','dragover'].forEach(type=>zone.addEventListener(type,e=>{e.preventDefault();zone.classList.add('drag-over');}));['dragleave','drop'].forEach(type=>zone.addEventListener(type,e=>{e.preventDefault();zone.classList.remove('drag-over');}));zone.addEventListener('drop',e=>upload([...e.dataTransfer.files]));}
    document.addEventListener('change',e=>{if(e.target.matches('[data-cv-state]')){const cv=state.resumes.find(c=>c.id===e.target.dataset.cvState);if(e.target.value==='completed'){location.href='/ung-vien/ky-nang?cv='+cv.id;return;}cv.status=e.target.value;persist();renderAll();}});
    $('#apply-form')?.addEventListener('submit',async e=>{
        e.preventDefault();if(applyBusy)return;
        const eligible=R.applicationEligibility(findJob(applyId),state.applications);
        const cv=state.resumes.find(c=>c.id===$('#apply-cv').value);
        if(!eligible.allowed||!cv){$('#apply-error').textContent=eligible.reason||'Vui lòng chọn CV PDF hợp lệ.';return;}
        const submit=$('#apply-form button[type="submit"]');applyBusy=true;submit.disabled=true;
        try {
            if(!await files('get',cv.id)){$('#apply-error').textContent='PDF cục bộ không còn tồn tại. Vui lòng tải lại CV.';return;}
            const at=stamp();
            const a={id:uid(),jobId:applyId,cvName:cv.name,cvFilename:cv.filename,at,status:'applied',attempt:eligible.attempt,letter:$('#cover-letter').value.trim(),timeline:[{status:'applied',at}]};
            state.applications.push(a);notify('applied',a);
            if(!persist()){state.applications.pop();state.notifications.shift();return;}
            $('#apply-modal').close();renderAll();toast('Ứng tuyển demo thành công. Chưa gửi hồ sơ hoặc email thật.');
        } catch {$('#apply-error').textContent='Không đọc được PDF cục bộ. Hãy thử lại.';}
        finally {applyBusy=false;submit.disabled=false;}
    });
    function filterButtons(id,set,render){const parent=$(id);parent?.addEventListener('click',e=>{const button=e.target.closest('[data-status]');if(!button)return;$$('button',parent).forEach(b=>b.classList.toggle('active',b===button));set(button.dataset.status);render();syncHeader();});}
    filterButtons('#saved-filter',v=>savedFilter=v,renderSaved);filterButtons('#notification-filter',v=>notificationFilter=v,renderNotifications);
    $('#application-filter')?.addEventListener('change',renderApplications);['#recommend-location','#recommend-mode'].forEach(s=>$(s)?.addEventListener('change',()=>{renderRecommendations();syncHeader();}));
    $$('[data-demo-form]').forEach(form=>form.addEventListener('submit',e=>{e.preventDefault();if(!validateForm(form))return;$('.form-feedback',form).innerHTML='<div class="notice notice-success">Biểu mẫu hợp lệ. Backend xác thực chưa kết nối nên tài khoản, mật khẩu và email chưa được cập nhật hoặc gửi đi.</div>';$$('input[type="password"],input[name="password"]',form).forEach(i=>i.value='');}));
    const profile=$('#profile-form');if(profile){['name','email','phone','location','title','experience','bio'].forEach(k=>$('#profile-'+k).value=state.profile[k]||'');profile.addEventListener('submit',e=>{e.preventDefault();if(!validateForm(profile))return;['name','email','phone','location','title','experience','bio'].forEach(k=>state.profile[k]=$('#profile-'+k).value.trim());if(persist())$('.form-feedback',profile).innerHTML='<div class="notice notice-success">Đã lưu hồ sơ demo trên trình duyệt.</div>';syncHeader();});files('get','avatar').then(file=>{if(file)showAvatar(file);}).catch(()=>{});}
    function showAvatar(file){const el=$('#avatar-preview');if(!el)return;const url=URL.createObjectURL(file);el.onload=()=>URL.revokeObjectURL(url);el.src=url;el.hidden=false;$('#profile-initials').hidden=true;}
    $('#avatar-upload')?.addEventListener('change',async e=>{const file=e.target.files[0];if(!file)return;if(!['image/jpeg','image/png','image/webp'].includes(file.type)||file.size>2*1024*1024){toast('Chọn ảnh JPG, PNG hoặc WebP tối đa 2 MB.');return;}try{await createImageBitmap(file);await files('put','avatar',file);showAvatar(file);toast('Đã lưu ảnh cục bộ.');}catch{toast('Ảnh không hợp lệ hoặc không thể lưu.');}});
    if($('#account-email'))$('#account-email').textContent=state.profile.email;
    renderAll();renderSkills();const appId=new URLSearchParams(location.search).get('application');if(appId)renderApplicationDetail(appId);
})();
