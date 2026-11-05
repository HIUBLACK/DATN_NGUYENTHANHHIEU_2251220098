window.renderAdminDetail = function (context) {
    const { escape, badge, link, action, lockButton, history, details } = AdminUI;
    const { state, page, routes } = context;
    const [collection, mode] = page.split('.');
    const id = new URLSearchParams(location.search).get('id');
    const container = document.getElementById('admin-detail');
    const item = state[collection].find(entry => entry.id === id);
    if (!item) {
        container.innerHTML = '<div class="card p-4 admin-empty">Không tìm thấy dữ liệu trong phiên demo.</div>';
        return;
    }
    function related(group, relatedId) {
        return state[group].find(entry => entry.id === relatedId);
    }
    function route(name, target = id) {
        return routes[name] + '?id=' + encodeURIComponent(target);
    }
    function historyCard(title = 'Lịch sử thao tác') {
        return `
            <section class="card p-4 mt-4">
                <h2 class="h5 mb-3">${title}</h2>
                ${history(item.history)}
            </section>`;
    }
    if (mode === 'history') {
        container.innerHTML = link(route(collection === 'jobs' ? 'jobDetail' : 'userDetail'), 'Quay lại chi tiết', 'arrow-left') + historyCard();
        return;
    }
    if (collection === 'users') {
        container.innerHTML = `
            <section class="card p-4">
                <h2 class="h4">${escape(item.name)}</h2>
                <div class="mb-3">${badge(item.status)}</div>
                ${details([['Mã tài khoản', item.id], ['Email', item.email], ['Vai trò', AdminDomain.labels[item.role]], ['Ngày tạo', item.created], ['Hồ sơ cơ bản', item.profile]])}
                <div class="admin-actions">
                    ${lockButton('users', item)}
                    ${link(route('userHistory'), 'Lịch sử khóa/mở khóa', 'clock-history')}
                </div>
                ${item.role === 'admin' ? '<p class="small mt-3 mb-0">Tài khoản Admin được bảo vệ; không có thao tác khóa, đổi vai trò hoặc xóa.</p>' : ''}
            </section>
            ${historyCard()}`;
        return;
    }
    if (collection === 'companies') {
        const jobs = state.jobs.filter(job => job.companyId === id);
        container.innerHTML = `
            <section class="card p-4">
                <h2 class="h4">
                    <i class="bi bi-building me-2" aria-hidden="true"></i>
                    ${escape(item.name)}
                </h2>
                <div class="mb-3">${badge(item.status)}</div>
                ${details([['Recruiter quản lý', related('users', item.managerId)?.name], ['Website', item.website], ['Địa chỉ', item.address], ['Email', item.email], ['Điện thoại', item.phone], ['Giới thiệu', item.description]])}
                <p class="alert alert-light border">Khóa doanh nghiệp làm các tin không đủ điều kiện hiển thị. Giữ nguyên trạng thái và phiên bản từng tin; mở khóa không tự duyệt hoặc mở lại tin đã đóng.</p>
                <div class="admin-actions">${lockButton('companies', item)}</div>
            </section>
            <section class="card p-4 mt-4">
                <h2 class="h5 mb-3">Tin tuyển dụng của doanh nghiệp</h2>
                ${jobs.map(job => `
                    <div class="admin-related-row">
                        <span>${escape(job.content.title)}</span>
                        ${badge(job.activity)}
                        ${link(route('jobDetail', job.id), 'Xem tin')}
                    </div>`).join('') || '<p>Chưa có tin tuyển dụng.</p>'}
            </section>
            ${historyCard()}`;
        return;
    }
    if (collection === 'applications') {
        const candidate = related('users', item.userId);
        const job = related('jobs', item.jobId);
        const company = related('companies', job.companyId);
        container.innerHTML = `
            <section class="card p-4">
                <h2 class="h4">${escape(candidate.name)}</h2>
                <div class="mb-3">${badge(item.status)}</div>
                ${details([['Mã hồ sơ', item.id], ['Email', candidate.email], ['Tin ứng tuyển', job.content.title], ['Doanh nghiệp', company.name], ['Ngày nộp', item.created]])}
                <p class="alert alert-light border mb-0">Chế độ giám sát: không chuyển trạng thái, không sửa ghi chú Recruiter và không truy cập nội dung CV.</p>
            </section>
            ${historyCard('Lịch sử xử lý hồ sơ')}`;
        return;
    }
    if (collection === 'jobs') {
        const company = related('companies', item.companyId);
        const fields = {
            title: 'Tiêu đề', description: 'Mô tả công việc', requirements: 'Yêu cầu', benefits: 'Quyền lợi',
            skills: 'Kỹ năng', salary: 'Lương', location: 'Địa điểm', mode: 'Hình thức làm việc',
            type: 'Loại công việc', quantity: 'Số lượng', experience: 'Kinh nghiệm', deadline: 'Hạn ứng tuyển'
        };
        const visible = item.activity === 'open' && company.status === 'active' && item.published && item.published.deadline >= AdminDomain.today();
        const comparison = Object.entries(fields).map(function ([field, label]) {
            const changed = item.published && item.content[field] !== item.published[field];
            return `
                <tr class="${changed ? 'admin-changed' : ''}">
                    <th scope="row">
                        ${label}
                        ${changed ? '<span class="admin-change-label">Đã thay đổi</span>' : ''}
                    </th>
                    <td class="admin-text">${escape(item.published?.[field] || 'Chưa có bản công khai')}</td>
                    <td class="admin-text">${escape(item.content[field])}</td>
                </tr>`;
        }).join('');
        const moderationButtons = item.moderation === 'pending' ? action('jobs', item, 'approve', 'Phê duyệt phiên bản ' + item.version, 'check2-circle') + action('jobs', item, 'reject', 'Từ chối phiên bản', 'x-circle') : '';
        container.innerHTML = `
            <section class="card p-4 mb-4">
                <h2 class="h4">${escape(item.content.title)}</h2>
                <p>${escape(company.name)} · Tin #${escape(item.id)} · Ngày tạo: ${escape(item.created)}</p>
                <div class="admin-actions mb-3">
                    <span>Hoạt động: ${badge(item.activity)}</span>
                    <span>Phiên bản ${item.version}: ${badge(item.moderation)}</span>
                </div>
                <p>${visible ? 'Bản đã duyệt hiện đủ điều kiện hiển thị trong mô phỏng.' : 'Tin hiện không đủ điều kiện hiển thị công khai trong mô phỏng.'}</p>
                ${item.previousActivity ? '<p>Trạng thái trước khi khóa: ' + escape(AdminDomain.labels[item.previousActivity]) + '</p>' : ''}
                ${item.reason ? '<p class="alert alert-warning admin-text">Lý do từ chối: ' + escape(item.reason) + '</p>' : ''}
                <p class="small text-muted-green">Từ chối bản sửa không thay thế bản đã duyệt. Bản cũ chỉ hiển thị khi tin đang mở, chưa hết hạn và doanh nghiệp hoạt động.</p>
                <div class="admin-actions">
                    ${moderationButtons}
                    ${lockButton('jobs', item)}
                    ${link(route('jobHistory'), 'Lịch sử kiểm duyệt', 'clock-history')}
                    ${link(route('companyDetail', company.id), 'Doanh nghiệp', 'building')}
                </div>
            </section>
            <section class="card p-4">
                <h2 class="h5 mb-3">So sánh phiên bản trước / sau</h2>
                <div class="table-responsive">
                    <table class="table-custom admin-table admin-comparison">
                        <thead>
                            <tr>
                                <th>Nội dung</th>
                                <th>Bản công khai ${escape(item.publishedVersion || '—')}</th>
                                <th>Phiên bản ${item.version} — ${escape(AdminDomain.labels[item.moderation])}</th>
                            </tr>
                        </thead>
                        <tbody>${comparison}</tbody>
                    </table>
                </div>
            </section>
            ${historyCard('Lịch sử kiểm duyệt và khóa tin')}`;
    }
};
