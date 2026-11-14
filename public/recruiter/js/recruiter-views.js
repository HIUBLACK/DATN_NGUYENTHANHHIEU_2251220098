window.createRecruiterViews = function (context) {
    const { domain, routes, selectedId, escapeHtml, empty, badge, url, jobById, logoMarkup, applicationById } = context;

    function companyMarkup() {
        const company = context.state.company;
        const website = /^https?:\/\//i.test(company.website || '')
            ? '<a href="' + escapeHtml(company.website) + '" target="_blank" rel="noopener">' + escapeHtml(company.website) + '</a>'
            : 'Chưa có website';
        return `
            <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
                ${logoMarkup(company)}
                <h2 class="h4 mb-0">${escapeHtml(company.name || 'Chưa đặt tên doanh nghiệp')}</h2>
            </div>
            <p class="recruiter-content-text">${escapeHtml(company.description)}</p>
            <dl class="row mb-0">
                <dt class="col-sm-3"><i class="bi bi-globe2 me-2" aria-hidden="true"></i>Website</dt>
                <dd class="col-sm-9">${website}</dd>
                <dt class="col-sm-3"><i class="bi bi-geo-alt me-2" aria-hidden="true"></i>Địa chỉ</dt>
                <dd class="col-sm-9">${escapeHtml(company.address)}</dd>
                <dt class="col-sm-3"><i class="bi bi-envelope me-2" aria-hidden="true"></i>Liên hệ</dt>
                <dd class="col-sm-9">${escapeHtml(company.email)} · ${escapeHtml(company.phone)}</dd>
            </dl>`;
    }

    function previewMarkup(content) {
        const skills = String(content.skills || '').split(',').map(function (skill) {
            return '<span class="badge text-bg-light border">' + escapeHtml(skill.trim()) + '</span>';
        }).join(' ');
        const sections = [['description', 'Mô tả công việc'], ['requirements', 'Yêu cầu ứng viên'], ['benefits', 'Quyền lợi']].map(function ([field, label]) {
            return `
                <section class="mb-4">
                    <h3 class="recruiter-section-title">${label}</h3>
                    <p class="recruiter-content-text">${escapeHtml(content[field] || 'Chưa nhập nội dung')}</p>
                </section>`;
        }).join('');
        return `
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card p-4 mb-4">
                        <h2 class="recruiter-preview-title">${escapeHtml(content.title || 'Tiêu đề tuyển dụng')}</h2>
                        <p class="recruiter-salary">${escapeHtml(content.salary_min || '0')}–${escapeHtml(content.salary_max || '0')} triệu/tháng</p>
                        <p><i class="bi bi-geo-alt me-2" aria-hidden="true"></i>${escapeHtml(content.location)} · ${escapeHtml(content.mode)} · ${escapeHtml(content.type)}</p>
                        <p class="mb-0"><i class="bi bi-calendar-event me-2" aria-hidden="true"></i>Hạn ứng tuyển: ${escapeHtml(content.deadline || 'Chưa chọn')}</p>
                    </div>
                    <div class="card p-4">
                        ${sections}
                        <h3 class="recruiter-section-title">Kỹ năng CNTT</h3>
                        <div class="d-flex flex-wrap gap-2">${skills}</div>
                    </div>
                </div>
                <aside class="col-lg-4">
                    <div class="card p-4 mb-4">${companyMarkup()}</div>
                    <div class="card p-4">
                        <h3 class="h5">Thông tin chung</h3>
                        <p>Nhóm nghề: ${escapeHtml(context.state.occupations.find(group => group.id === content.occupationId)?.name || content.occupationId || 'Chưa chọn')}</p>
                        <p>Chuyên môn: ${escapeHtml(context.state.specialties.find(specialty => specialty.id === content.specialtyId)?.name || content.specialtyId || 'Chưa chọn')}</p>
                        <p>Vị trí: ${escapeHtml(content.position)}</p>
                        <p>Kinh nghiệm: ${escapeHtml(content.experience)}</p>
                        <p class="mb-0">Số lượng: ${escapeHtml(content.quantity)}</p>
                    </div>
                </aside>
            </div>`;
    }

    function renderDashboard() {
        function cards(target, metrics) {
            document.getElementById(target).innerHTML = metrics.map(function ([label, count, icon]) {
                return `
                    <div class="col-sm-6 col-xl-3">
                        <div class="card card-stat p-4 h-100">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="stat-label">${label}</span>
                                <i class="bi bi-${icon} recruiter-stat-icon" aria-hidden="true"></i>
                            </div>
                            <div class="stat-value">${count}</div>
                        </div>
                    </div>`;
            }).join('');
        }
        cards('job-stat-cards', [
            ['Tổng số tin', context.state.jobs.length, 'briefcase'],
            ['Tin đang mở', context.state.jobs.filter(job => job.activity === 'open' && !domain.isExpired(job)).length, 'broadcast'],
            ['Tin tạm dừng', context.state.jobs.filter(job => job.activity === 'paused').length, 'pause-circle'],
            ['Tin đã đóng', context.state.jobs.filter(job => job.activity === 'closed').length, 'archive']
        ]);
        const metrics = [['Tổng hồ sơ', context.state.applications.length, 'people']];
        ['applied', 'reviewing', 'interview', 'hired', 'rejected'].forEach(function (status) {
            metrics.push([domain.applicationLabels[status], context.state.applications.filter(application => application.status === status).length, 'person-check']);
        });
        cards('application-stat-cards', metrics);
        window.renderRecruiterCharts(context.state, domain, routes);
    }

    function renderCompany() {
        const details = document.getElementById('company-details');
        if (details) {
            details.innerHTML = companyMarkup();
        }
        const openJobs = context.state.jobs.filter(job => context.state.company.status !== 'blocked' && job.activity === 'open' && job.published && !domain.isExpired(job));
        document.getElementById('company-open-jobs').innerHTML = openJobs.map(function (job) {
            return `
                <div class="col-md-6">
                    <div class="card p-4 h-100">
                        <h3 class="h5">${escapeHtml(job.published.title)}</h3>
                        <p>${escapeHtml(job.published.location)}</p>
                        <a href="${url('jobDetail', job.id)}" class="btn btn-outline-dark align-self-start">
                            <i class="bi bi-eye" aria-hidden="true"></i>
                            Xem tin tuyển dụng
                        </a>
                    </div>
                </div>`;
        }).join('') || empty('Chưa có tin đang mở.');
    }

    function jobActions(job) {
        const links = ['<a class="btn btn-light btn-sm" href="' + url('jobDetail', job.id) + '"><i class="bi bi-eye" aria-hidden="true"></i> Xem</a>'];
        if (!['blocked', 'closed'].includes(job.activity)) {
            links.push('<a class="btn btn-light btn-sm" href="' + url('jobEdit', job.id) + '"><i class="bi bi-pencil-square" aria-hidden="true"></i> Sửa</a>');
        }
        domain.allowedJobActions(job).forEach(function (action) {
            const label = { pause: 'Tạm dừng', reopen: 'Mở lại', close: 'Đóng tin' }[action];
            const icon = { pause: 'pause-circle', reopen: 'play-circle', close: 'archive' }[action];
            links.push('<button class="btn btn-outline-dark btn-sm" type="button" data-job-action="' + action + '" data-job-id="' + escapeHtml(job.id) + '">' + '<i class="bi bi-' + icon + '" aria-hidden="true"></i> ' + label + '</button>');
        });
        return '<div class="recruiter-actions">' + links.join('') + '</div>';
    }

    function renderJobs() {
        const filters = Object.fromEntries(new FormData(document.getElementById('job-filters')));
        const jobs = context.state.jobs.filter(function (job) {
            return job.content.title.toLocaleLowerCase('vi').includes(filters.q.trim().toLocaleLowerCase('vi'))
                && (!filters.activity || job.activity === filters.activity)
                && (!filters.moderation || job.moderation === filters.moderation);
        });
        const totalPages = Math.max(1, Math.ceil(jobs.length / 5));
        context.jobPage = Math.min(context.jobPage, totalPages);
        document.getElementById('job-count').textContent = jobs.length + ' tin tuyển dụng demo';
        document.getElementById('job-table-body').innerHTML = jobs.slice((context.jobPage - 1) * 5, context.jobPage * 5).map(function (job, index) {
            const count = context.state.applications.filter(application => application.jobId === job.id).length;
            return `
                <tr>
                    <td class="recruiter-order">${(context.jobPage - 1) * 5 + index + 1}</td>
                    <td>
                        <a href="${url('jobDetail', job.id)}">${escapeHtml(job.content.title)}</a>
                        <div class="small text-muted-green">${escapeHtml(job.created)}</div>
                    </td>
                    <td>${escapeHtml(job.content.deadline)}${domain.isExpired(job) ? '<div class="text-danger small">Hết hạn</div>' : ''}</td>
                    <td>${badge(job.activity, domain.activityLabels)}</td>
                    <td>${badge(job.moderation, domain.moderationLabels)}</td>
                    <td>
                        <a href="${routes.applications}?jobId=${encodeURIComponent(job.id)}">${count} hồ sơ</a>
                    </td>
                    <td>${jobActions(job)}</td>
                </tr>`;
        }).join('') || '<tr><td colspan="7">' + empty('Không có tin theo bộ lọc.') + '</td></tr>';
        document.getElementById('job-pagination').innerHTML = Array.from({ length: totalPages }, function (_, index) {
            return '<button class="btn btn-light recruiter-page-number" data-job-page="' + (index + 1) + '" aria-label="Trang ' + (index + 1) + '"' + (index + 1 === context.jobPage ? ' aria-current="page"' : '') + '>' + (index + 1) + '</button>';
        }).join('');
    }

    function renderJobDetail() {
        const job = jobById(selectedId);
        const container = document.getElementById('job-detail-content');
        if (!job) {
            container.innerHTML = empty('Không tìm thấy tin trong phiên demo.');
            return;
        }
        const hasSeparateVersion = job.published && JSON.stringify(job.content) !== JSON.stringify(job.published);
        container.innerHTML = `
            <div class="card p-4 mb-4">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    ${badge(job.activity, domain.activityLabels)}
                    ${badge(job.moderation, domain.moderationLabels)}
                    ${domain.isExpired(job) ? '<span class="badge text-bg-danger">Hết hạn</span>' : ''}
                </div>
                <p>Thời điểm gửi duyệt: ${escapeHtml(job.submitted || 'Chưa gửi')}</p>
                <p>Kết quả duyệt: ${escapeHtml(domain.moderationLabels[job.moderation])}</p>
                ${job.reason ? '<div class="alert alert-danger">Lý do từ chối: ' + escapeHtml(job.reason) + '</div>' : ''}
                ${job.published ? '<p>Bản công khai đã duyệt được lưu riêng. Việc sửa và gửi lại không thay thế bản này ngay.</p>' : '<p>Tin chưa có phiên bản được duyệt để công khai.</p>'}
                <div class="recruiter-actions">
                    ${jobActions(job)}
                    <a href="${routes.applications}?jobId=${encodeURIComponent(job.id)}" class="btn-dark-custom"><i class="bi bi-people" aria-hidden="true"></i> Xem hồ sơ ứng tuyển</a>
                </div>
            </div>
            <h2 class="h4 mb-3">${hasSeparateVersion ? 'Bản chỉnh sửa — ' + escapeHtml(domain.moderationLabels[job.moderation]) : 'Nội dung tin minh họa'}</h2>
            ${previewMarkup(job.content)}
            ${hasSeparateVersion ? '<details class="card p-4 mt-4"><summary>Bản công khai đã duyệt — chưa bị thay thế</summary><div class="mt-4">' + previewMarkup(job.published) + '</div></details>' : ''}`;
    }

    function cvActions(application) {
        if (!application.cv) {
            return '<span class="small text-muted-green">Chưa có tệp CV</span><button class="btn btn-light btn-sm" disabled><i class="bi bi-download me-1" aria-hidden="true"></i>Tải CV</button>';
        }
        return '<a class="btn btn-light btn-sm" href="' + escapeHtml(application.cv) + '" target="_blank" rel="noopener"><i class="bi bi-file-earmark-person" aria-hidden="true"></i> Xem CV</a><a class="btn btn-light btn-sm" href="' + escapeHtml(application.cv) + '" download="cv-demo-01.pdf"><i class="bi bi-download me-1" aria-hidden="true"></i>Tải CV demo</a>';
    }

    function renderApplications() {
        const filters = Object.fromEntries(new FormData(document.getElementById('application-filters')));
        const applications = context.state.applications.filter(function (application) {
            return application.name.toLocaleLowerCase('vi').includes(filters.q.trim().toLocaleLowerCase('vi'))
                && (!filters.status || application.status === filters.status)
                && (!filters.jobId || application.jobId === filters.jobId)
                && RecruiterAI.matches(application, filters.aiStatus);
        });
        RecruiterAI.sort(applications, filters.sort);
        document.getElementById('application-count').textContent = applications.length + ' hồ sơ minh họa';
        document.getElementById('application-table-body').innerHTML = applications.map(function (application, index) {
            const job = jobById(application.jobId);
            return `
                <tr>
                    <td class="recruiter-order">${index + 1}</td>
                    <td>
                        <a href="${url('applicationDetail', application.id)}">${escapeHtml(application.name)}</a>
                        <div class="small text-muted-green">${escapeHtml(job?.content.title || 'Tin không còn trong phiên demo')}</div>
                    </td>
                    <td>${escapeHtml(application.date)}</td>
                    <td>${badge(application.status, domain.applicationLabels)}</td>
                    <td>${RecruiterAI.summary(application, url('applicationDetail', application.id))}</td>
                    <td>
                        <div class="recruiter-actions">
                            <a class="btn btn-light btn-sm" href="${url('applicationDetail', application.id)}"><i class="bi bi-person-lines-fill" aria-hidden="true"></i> Chi tiết</a>
                            ${cvActions(application)}
                        </div>
                    </td>
                </tr>`;
        }).join('') || '<tr><td colspan="6">' + empty('Không có ứng viên phù hợp. Hãy xóa từ khóa hoặc mở rộng bộ lọc.') + '</td></tr>';
    }

    function renderApplicationDetail() {
        const application = applicationById(selectedId);
        const container = document.getElementById('application-detail-content');
        if (!application) {
            container.innerHTML = empty('Không tìm thấy hồ sơ trong dữ liệu demo.');
            return;
        }
        const job = jobById(application.jobId);
        const buttons = domain.transitions[application.status].map(function (status) {
            return '<button type="button" class="btn btn-outline-dark" data-application-status="' + status + '">' + domain.applicationLabels[status] + '</button>';
        }).join('');
        const timeline = application.history.map(function (event) {
            return '<li><strong>' + escapeHtml(domain.applicationLabels[event.status]) + '</strong><p class="small mb-0">' + escapeHtml(event.date) + ' · ' + escapeHtml(event.actor) + '</p></li>';
        }).join('');
        container.innerHTML = `
            ${RecruiterAI.detail(application, job)}
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card p-4 mb-4">
                        <h2 class="h4">${escapeHtml(application.name)}</h2>
                        <p>${escapeHtml(application.email)} · ${escapeHtml(application.phone)}</p>
                        <p>Ứng tuyển: ${escapeHtml(job?.content.title)} · ${escapeHtml(application.date)}</p>
                        <div class="mb-3">${badge(application.status, domain.applicationLabels)}</div>
                        <div class="recruiter-actions">${buttons || '<span class="text-muted-green">Trạng thái kết thúc; không thể chuyển tiếp.</span>'}</div>
                    </div>
                    <div class="card p-4">
                        <h2 class="h5">CV đã nộp</h2>
                        <div class="recruiter-actions mb-3">${cvActions(application)}</div>
                        ${application.cv ? '<p class="small">PDF tổng hợp riêng cho hồ sơ demo này, không phải CV thật.</p><iframe title="CV PDF minh họa của Nguyễn Minh An" class="recruiter-pdf" src="' + escapeHtml(application.cv) + '"></iframe>' : empty('Hồ sơ demo này chưa có tệp PDF. Không sử dụng CV của ứng viên khác.')}
                    </div>
                </div>
                <aside class="col-lg-4">
                    <div class="card p-4">
                        <h2 class="h5 mb-4">Lịch sử xử lý</h2>
                        <ol class="recruiter-timeline">${timeline}</ol>
                    </div>
                </aside>
            </div>`;
        document.getElementById('application-note-card').hidden = false;
        document.getElementById('application-notes').innerHTML = application.notes.map(function (note) {
            return '<article class="recruiter-note"><p class="recruiter-content-text">' + escapeHtml(note.text) + '</p><p class="small mb-0">' + escapeHtml(note.date) + ' · ' + escapeHtml(note.actor) + '</p></article>';
        }).join('') || '<p class="text-muted-green">Chưa có ghi chú nội bộ.</p>';
    }

    function renderNotifications() {
        const filter = document.getElementById('notification-filter').value;
        const notifications = context.state.notifications.filter(function (notification) {
            return filter === 'all' || (filter === 'unread' && !notification.read) || notification.type === filter;
        });
        document.getElementById('recruiter-notifications').innerHTML = notifications.map(function (notification) {
            const destination = notification.type === 'job' ? 'jobDetail' : 'applicationDetail';
            return `
                <article class="recruiter-notification ${notification.read ? '' : 'is-unread'}">
                    <div>
                        <a href="${url(destination, notification.target)}">${escapeHtml(notification.title)}</a>
                        <p class="small mb-0">${escapeHtml(notification.date)} · ${notification.read ? 'Đã đọc' : 'Chưa đọc'}</p>
                    </div>
                    ${notification.read ? '' : '<button type="button" class="btn btn-light btn-sm" data-read-notification="' + escapeHtml(notification.id) + '">Đánh dấu đã đọc</button>'}
                </article>`;
        }).join('') || empty('Không có thông báo trong mục này.');
    }

    return { companyMarkup, previewMarkup, renderDashboard, renderCompany, jobActions, renderJobs, renderJobDetail, cvActions, renderApplications, renderApplicationDetail, renderNotifications };
};
