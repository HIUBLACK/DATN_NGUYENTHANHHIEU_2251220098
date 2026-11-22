document.addEventListener('DOMContentLoaded', function () {
    if (document.body.dataset.realOverview === '1') {
        const counts = document.getElementById('recruiter-dashboard-counts');
        if (counts) {
            window.renderRecruiterCharts({ counts: JSON.parse(counts.textContent) }, window.RecruiterDomain, document.getElementById('recruiter-routes').dataset);
        }
        return;
    }
    if (document.body.dataset.realJobs === '1' || document.body.dataset.realApplications === '1') {
        return;
    }
    if (['company', 'company-form'].includes(document.body.dataset.recruiterPage)) {
        window.setupCompanyProfileForm();
        return;
    }
    const seedElement = document.getElementById('recruiter-seed');
    const routeElement = document.getElementById('recruiter-routes');
    if (!seedElement || !routeElement) {
        return;
    }

    const domain = window.RecruiterDomain;
    const routes = routeElement.dataset;
    const seed = JSON.parse(seedElement.textContent);
    const storageKey = 'viejob-recruiter-preview-v1-user-' + document.body.dataset.previewUser;
    const page = document.body.dataset.recruiterPage;
    const parameters = new URLSearchParams(window.location.search);
    const selectedId = parameters.get('id');
    const modal = new bootstrap.Modal(document.getElementById('recruiter-confirm'));
    let confirmationAction = null;
    let jobPage = 1;
    let state = structuredClone(seed);

    try {
        const stored = sessionStorage.getItem(storageKey);
        if (stored) {
            state = JSON.parse(stored);
        }
    } catch (error) {
        showError('Không đọc được phiên demo. Đang hiển thị dữ liệu mẫu ban đầu.');
    }

    RecruiterAI.seed(state.applications);
    state.company = structuredClone(seed.company);
    state.occupations = structuredClone(seed.occupations);
    state.specialties = structuredClone(seed.specialties);
    try {
        const adminState = JSON.parse(sessionStorage.getItem('viejob-admin-preview-v1-user-' + document.body.dataset.previewUser) || 'null');
        if (Array.isArray(adminState?.occupations) && Array.isArray(adminState?.specialties)) {
            state.occupations = adminState.occupations;
            state.specialties = adminState.specialties;
        }
    } catch (error) {
        showError('Không đọc được danh mục Admin demo. Đang dùng danh mục mẫu.');
    }
    // Bổ sung phân loại cho tin mẫu ở phiên cũ; không ghi đè lựa chọn đã lưu.
    state.jobs.forEach(function (job) {
        const original = seed.jobs.find(item => item.id === job.id);
        for (const version of ['content', 'published']) {
            if (job[version] && !('occupationId' in job[version]) && original?.[version]) {
                job[version].occupationId = original[version].occupationId;
                job[version].specialtyId = original[version].specialtyId;
            }
        }
    });
    let categorySelection = null;

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (character) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character];
        });
    }

    function timestamp() {
        return new Intl.DateTimeFormat('vi-VN', { timeZone: 'Asia/Ho_Chi_Minh', dateStyle: 'short', timeStyle: 'short' }).format(new Date());
    }

    function showError(message) {
        window.ViejobToast.notify(message, 'error');
    }

    function persist(message = 'Đã cập nhật mô phỏng trong tab này. Chưa lưu hoặc gửi dữ liệu thật.') {
        try {
            sessionStorage.setItem(storageKey, JSON.stringify(state));
            window.ViejobToast.notify(message, 'success');
            return true;
        } catch (error) {
            showError('Trình duyệt không lưu được phiên demo. Hãy giảm kích thước logo hoặc cho phép bộ nhớ phiên.');
            return false;
        }
    }

    function confirmAction(message, action) {
        document.getElementById('recruiter-confirm-message').textContent = message;
        confirmationAction = action;
        modal.show();
    }

    document.getElementById('recruiter-confirm-submit').addEventListener('click', function () {
        modal.hide();
        if (confirmationAction) {
            try {
                confirmationAction();
            } catch (error) {
                showError(error.message);
            }
            confirmationAction = null;
        }
    });

    function jobById(id) {
        return state.jobs.find(job => job.id === id);
    }

    function applicationById(id) {
        return state.applications.find(application => application.id === id);
    }

    function url(name, id) {
        return routes[name] + (id ? '?id=' + encodeURIComponent(id) : '');
    }

    function badge(status, labels) {
        let tone = 'pending';
        if (['open', 'approved', 'hired'].includes(status)) {
            tone = 'success';
        } else if (['closed', 'blocked', 'rejected', 'withdrawn'].includes(status)) {
            tone = 'failed';
        }
        return '<span class="badge-table ' + tone + '">' + escapeHtml(labels[status] || status) + '</span>';
    }

    function empty(message) {
        return '<div class="recruiter-empty"><i class="bi bi-inbox me-2" aria-hidden="true"></i>' + escapeHtml(message) + '</div>';
    }

    function logoMarkup(company) {
        if (company.logo_url) {
            return '<img class="recruiter-logo-preview" src="' + escapeHtml(company.logo_url) + '" alt="Logo doanh nghiệp">';
        }
        if (company.logo && /^data:image\/(png|jpeg|webp);base64,/.test(company.logo)) {
            return '<img class="recruiter-logo-preview" src="' + escapeHtml(company.logo) + '" alt="Logo doanh nghiệp minh họa">';
        }
        return '<div class="recruiter-company-icon"><i class="bi bi-building" aria-hidden="true"></i></div>';
    }

    function fillOptions() {
        document.querySelectorAll('[data-options]').forEach(function (select) {
            const labels = { activity: domain.activityLabels, moderation: domain.moderationLabels, application: domain.applicationLabels }[select.dataset.options];
            select.innerHTML = '<option value="">Tất cả</option>' + Object.entries(labels).map(function ([value, label]) {
                return '<option value="' + value + '">' + label + '</option>';
            }).join('');
        });
    }

    function validateForm(form, draftOnly = false) {
        form.querySelectorAll('input, textarea, select').forEach(function (input) {
            input.setCustomValidity('');
            if (input.required && !String(input.value).trim()) {
                input.setCustomValidity('Vui lòng điền trường bắt buộc.');
            }
        });
        if (form.id === 'job-form' && categorySelection && !categorySelection.validate(draftOnly)) {
            return false;
        }
        if (draftOnly) {
            const title = form.elements.title;
            title.setCustomValidity(title.value.trim() ? '' : 'Vui lòng nhập tiêu đề bản nháp.');
            return title.reportValidity();
        }
        if (form.id === 'job-form') {
            if (Number(form.elements.salary_max.value) < Number(form.elements.salary_min.value)) {
                form.elements.salary_max.setCustomValidity('Lương tối đa phải lớn hơn hoặc bằng lương tối thiểu.');
            }
            if (form.elements.deadline.value < domain.localDate()) {
                form.elements.deadline.setCustomValidity('Hạn ứng tuyển không được ở quá khứ.');
            }
        }
        form.classList.add('was-validated');
        return form.reportValidity();
    }

    function setupJobForm() {
        const form = document.getElementById('job-form');
        const editor = document.getElementById('job-editor');
        const preview = document.getElementById('job-preview');
        let job = selectedId ? jobById(selectedId) : null;
        const isEditRoute = window.location.pathname.endsWith('/chinh-sua');
        if ((isEditRoute && !job) || (job && ['blocked', 'closed'].includes(job.activity))) {
            editor.innerHTML = empty('Tin không tồn tại hoặc không được chỉnh sửa ở trạng thái hiện tại.');
            return;
        }
        if (job) {
            Object.entries(job.content).forEach(function ([field, value]) {
                if (form.elements[field]) {
                    form.elements[field].value = value;
                }
            });
            document.getElementById('job-version-warning').hidden = !job.published;
        }
        categorySelection = window.setupRecruiterCategories(form, state, job?.content || {});
        document.getElementById('preview-job').addEventListener('click', function () {
            const content = Object.fromEntries(new FormData(form));
            document.getElementById('job-preview-content').innerHTML = previewMarkup(content);
            editor.hidden = true;
            preview.hidden = false;
            preview.scrollIntoView({ block: 'start' });
        });
        document.getElementById('back-to-editor').addEventListener('click', function () {
            preview.hidden = true;
            editor.hidden = false;
            document.getElementById('preview-job').focus();
        });
        form.addEventListener('input', function (event) {
            if (event.target.setCustomValidity) {
                event.target.setCustomValidity('');
            }
        });
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            const intent = event.submitter?.value || 'draft';
            if (!validateForm(form, intent === 'draft')) {
                return;
            }
            const content = Object.fromEntries(new FormData(form));
            function saveVersion() {
                if (!job) {
                    job = { id: crypto.randomUUID(), created: domain.localDate(), activity: 'draft', published: null };
                    state.jobs.unshift(job);
                }
                job.content = content;
                job.moderation = intent === 'submit' ? 'pending' : 'draft';
                job.submitted = intent === 'submit' ? timestamp() : null;
                job.reason = '';
                if (persist(intent === 'submit' ? 'Đã mô phỏng trạng thái chờ duyệt. Chưa gửi Admin; nội dung chưa được công khai.' : 'Đã lưu bản nháp demo trong tab. Chưa lưu lên máy chủ.')) {
                    history.replaceState(null, '', url('jobEdit', job.id));
                }
            }
            if (intent === 'submit') {
                confirmAction('Tin mới hoặc bản chỉnh sửa cần Admin duyệt trước khi công khai. Thao tác này chỉ mô phỏng trạng thái chờ duyệt, không gửi dữ liệu thật.', saveVersion);
            } else {
                saveVersion();
            }
        });
    }

    function updateNotificationIndicator() {
    }

    function connectFilters(formId, render) {
        const form = document.getElementById(formId);
        form.addEventListener('submit', event => event.preventDefault());
        form.addEventListener('input', function () {
            jobPage = 1;
            render();
        });
        form.addEventListener('reset', function () {
            setTimeout(function () {
                jobPage = 1;
                render();
            }, 0);
        });
    }

    document.addEventListener('click', function (event) {
        const jobButton = event.target.closest('[data-job-action]');
        if (jobButton) {
            const job = jobById(jobButton.dataset.jobId);
            confirmAction('Xác nhận ' + jobButton.textContent.toLowerCase() + '? Đây là thay đổi mô phỏng, chưa cập nhật tin thật.', function () {
                domain.transitionJob(job, jobButton.dataset.jobAction);
                persist();
                if (page === 'jobs') {
                    renderJobs();
                } else {
                    renderJobDetail();
                }
            });
        }
        const pageButton = event.target.closest('[data-job-page]');
        if (pageButton) {
            jobPage = Number(pageButton.dataset.jobPage);
            renderJobs();
        }
        const statusButton = event.target.closest('[data-application-status]');
        if (statusButton) {
            confirmAction('Chuyển hồ sơ sang ' + statusButton.textContent + '? Chỉ cập nhật dữ liệu demo, không gửi email.', function () {
                domain.transitionApplication(applicationById(selectedId), statusButton.dataset.applicationStatus, timestamp());
                persist();
                renderApplicationDetail();
            });
        }
        const notificationButton = event.target.closest('[data-read-notification]');
        if (notificationButton) {
            const notification = state.notifications.find(item => item.id === notificationButton.dataset.readNotification);
            notification.read = true;
            persist();
            renderNotifications();
            updateNotificationIndicator();
        }
    });

    const { companyMarkup, previewMarkup, renderDashboard, renderCompany, jobActions, renderJobs, renderJobDetail, cvActions, renderApplications, renderApplicationDetail, renderNotifications } = window.createRecruiterViews({
        domain, routes, selectedId, escapeHtml, empty, badge, url, jobById, logoMarkup, applicationById,
        get state() {
            return state;
        },
        get jobPage() {
            return jobPage;
        },
        set jobPage(value) {
            jobPage = value;
        }
    });

    fillOptions();
    updateNotificationIndicator();
    if (page === 'dashboard') {
        renderDashboard();
    } else if (page === 'jobs') {
        connectFilters('job-filters', renderJobs);
        renderJobs();
    } else if (page === 'job-form') {
        setupJobForm();
    } else if (page === 'job-detail') {
        renderJobDetail();
    } else if (page === 'applications') {
        const select = document.getElementById('application-job');
        select.innerHTML = '<option value="">Tất cả tin</option>' + state.jobs.map(job => '<option value="' + escapeHtml(job.id) + '">' + escapeHtml(job.content.title) + '</option>').join('');
        select.value = parameters.get('jobId') || '';
        connectFilters('application-filters', renderApplications);
        renderApplications();
    } else if (page === 'application-detail') {
        renderApplicationDetail();
        document.addEventListener('click', function (event) {
            if (!event.target.closest('[data-ai-retry]')) {
                return;
            }
            const application = applicationById(selectedId);
            if (application?.ai?.status !== 'failed') {
                return;
            }
            application.ai.status = 'pending';
            persist('Đã mô phỏng yêu cầu thử lại. Chưa gửi tác vụ tới AI Service.');
            renderApplicationDetail();
        });
        document.getElementById('application-note-form').addEventListener('submit', function (event) {
            event.preventDefault();
            const input = document.getElementById('application-note');
            const text = input.value.trim();
            if (!text) {
                showError('Vui lòng nhập nội dung ghi chú.');
                return;
            }
            applicationById(selectedId).notes.unshift({ text, date: timestamp(), actor: 'Nhà tuyển dụng demo' });
            persist();
            input.value = '';
            renderApplicationDetail();
        });
    } else if (page === 'notifications') {
        document.getElementById('notification-filter').addEventListener('change', renderNotifications);
        document.getElementById('read-all-notifications').addEventListener('click', function () {
            state.notifications.forEach(notification => notification.read = true);
            persist();
            renderNotifications();
            updateNotificationIndicator();
        });
        renderNotifications();
    } else if (page === 'account') {
        document.getElementById('reset-recruiter-demo').addEventListener('click', function () {
            confirmAction('Đặt lại tin, hồ sơ ứng tuyển và thông báo demo trong tab? Hồ sơ doanh nghiệp và ảnh đã lưu trong phiên được giữ nguyên.', function () {
                state = structuredClone(seed);
                persist('Đã đặt lại dữ liệu Recruiter demo của tab này.');
                updateNotificationIndicator();
            });
        });
    }
});
