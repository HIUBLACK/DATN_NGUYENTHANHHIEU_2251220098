window.createAdminList = function (context) {
    const { escape, badge, link, lockButton } = AdminUI;
    const { page, routes } = context;
    const kind = page.split('.')[0];
    const collection = page === 'skills.aliases' ? 'aliases' : kind;
    const form = document.getElementById('admin-filters');
    let currentPage = 1;
    const pageSize = 6;

    function related(group, id) {
        return context.state[group].find(item => item.id === id);
    }

    function detailLink(route, id, label = 'Xem') {
        return link(routes[route] + '?id=' + encodeURIComponent(id), label);
    }

    function skillEdit(item) {
        return `
            <button type="button" class="btn btn-outline-dark btn-sm" data-skill-edit="${escape(item.id)}">
                <i class="bi bi-pencil-square" aria-hidden="true"></i>
                Chỉnh sửa
            </button>`;
    }

    function columns() {
        if (collection === 'users') {
            return [
                ['ID', item => escape(item.id)],
                ['Họ tên / Email', item => `<strong>${escape(item.name)}</strong><div>${escape(item.email)}</div>`],
                ['Vai trò', item => escape(AdminDomain.labels[item.role])],
                ['Trạng thái', item => badge(item.status)],
                ['Ngày đăng ký', item => escape(item.created)],
                ['Thao tác', item => detailLink('userDetail', item.id) + lockButton('users', item)]
            ];
        }
        if (collection === 'companies') {
            return [
                ['ID', item => escape(item.id)],
                ['Doanh nghiệp', item => `<i class="bi bi-building admin-company-logo" aria-label="Logo minh họa"></i><strong>${escape(item.name)}</strong>`],
                ['Recruiter / Email', item => `${escape(related('users', item.managerId)?.name)}<div>${escape(item.email)}</div>`],
                ['Số tin', item => context.state.jobs.filter(job => job.companyId === item.id).length],
                ['Trạng thái', item => badge(item.status)],
                ['Thao tác', item => detailLink('companyDetail', item.id) + lockButton('companies', item)]
            ];
        }
        if (collection === 'jobs') {
            return [
                ['ID / Tiêu đề', item => `<small>TIN-${escape(item.id)}</small><div><strong>${escape(item.content.title)}</strong></div>`],
                ['Doanh nghiệp', item => escape(related('companies', item.companyId)?.name)],
                ['Ngày tạo / Hạn ứng tuyển', item => `${escape(item.created)}<div>Hạn: ${escape(item.content.deadline)}</div>`],
                ['Hoạt động', item => badge(item.activity)],
                ['Kiểm duyệt', item => badge(item.moderation)],
                ['Thao tác', item => detailLink('jobDetail', item.id, item.moderation === 'pending' ? 'Xem & duyệt' : 'Chi tiết') + lockButton('jobs', item)]
            ];
        }
        if (collection === 'applications') {
            return [
                ['ID', item => escape(item.id)],
                ['Ứng viên', item => escape(related('users', item.userId)?.name)],
                ['Doanh nghiệp', item => escape(related('companies', related('jobs', item.jobId)?.companyId)?.name)],
                ['Tin tuyển dụng', item => escape(related('jobs', item.jobId)?.content.title)],
                ['Ngày ứng tuyển', item => escape(item.created)],
                ['Trạng thái', item => badge(item.status)],
                ['Thao tác', item => detailLink('applicationDetail', item.id, 'Xem lịch sử')]
            ];
        }
        if (collection === 'aliases') {
            return [
                ['ID', item => escape(item.id)],
                ['Tên đồng nghĩa', item => escape(item.name)],
                ['Kỹ năng chuẩn', item => escape(related('skills', item.skillId)?.name)],
                ['Trạng thái', item => badge(item.status)],
                ['Thao tác', item => skillEdit(item) + `
                    <button type="button" class="btn btn-outline-dark btn-sm" data-alias-toggle="${escape(item.id)}">
                        <i class="bi bi-toggle-on" aria-hidden="true"></i>
                        ${item.status === 'active' ? 'Vô hiệu hóa' : 'Kích hoạt'}
                    </button>`]
            ];
        }
        return [
            ['ID', item => escape(item.id)],
            ['Kỹ năng / Tên chuẩn hóa', item => `<strong>${escape(item.name)}</strong><div>${escape(item.normalized)}</div>`],
            ['Tên đồng nghĩa', item => context.state.aliases.filter(alias => alias.skillId === item.id).length],
            ['Trạng thái', item => badge(item.status)],
            ['Ngày tạo', item => escape(item.created)],
            ['Thao tác', item => skillEdit(item) + link(routes.aliases + '?skillId=' + encodeURIComponent(item.id), 'Ánh xạ', 'diagram-3')]
        ];
    }

    function addFilter(name, label, options) {
        const group = document.createElement('div');
        group.className = 'col-sm-6 col-xl-4';
        const title = document.createElement('label');
        title.className = 'form-label-custom';
        title.htmlFor = 'filter-' + name;
        title.textContent = label;
        const select = document.createElement('select');
        select.className = 'form-select-custom';
        select.name = name;
        select.id = title.htmlFor;
        select.add(new Option('Tất cả', ''));
        options.forEach(([value, text]) => select.add(new Option(text, value)));
        group.append(title, select);
        document.getElementById('admin-extra-filters').append(group);
    }

    function statusOptions(values) {
        return values.map(value => [value, AdminDomain.labels[value]]);
    }

    function setupFilters() {
        if (collection === 'users') {
            addFilter('role', 'Vai trò', statusOptions(['candidate', 'recruiter', 'admin']));
        }
        if (collection === 'jobs' || collection === 'applications') {
            addFilter('companyId', 'Doanh nghiệp', context.state.companies.map(company => [company.id, company.name]));
        }
        if (collection === 'jobs') {
            addFilter('activity', 'Hoạt động', statusOptions(['draft', 'open', 'paused', 'closed', 'blocked']));
            if (page !== 'jobs.queue') {
                addFilter('moderation', 'Kiểm duyệt', statusOptions(['draft', 'pending', 'approved', 'rejected']));
            }
        } else if (collection === 'applications') {
            addFilter('jobId', 'Tin tuyển dụng', context.state.jobs.map(job => [job.id, job.content.title]));
            addFilter('status', 'Trạng thái', statusOptions(['applied', 'reviewing', 'interview', 'hired', 'rejected', 'withdrawn']));
        } else {
            addFilter('status', 'Trạng thái', statusOptions(['users', 'companies'].includes(collection) ? ['active', 'blocked'] : ['active', 'inactive']));
        }
        if (collection === 'aliases') {
            addFilter('skillId', 'Kỹ năng chuẩn', context.state.skills.map(skill => [skill.id, skill.name]));
            form.elements.skillId.value = new URLSearchParams(location.search).get('skillId') || '';
        }
        if (kind === 'skills') {
            document.getElementById('admin-list-actions').innerHTML = `
                <button type="button" class="btn-dark-custom" data-skill-edit="">
                    <i class="bi bi-plus-circle" aria-hidden="true"></i>
                    ${collection === 'aliases' ? 'Thêm tên đồng nghĩa' : 'Thêm kỹ năng'}
                </button>
                ${link(collection === 'aliases' ? routes.skills : routes.aliases, collection === 'aliases' ? 'Danh mục kỹ năng' : 'Tên đồng nghĩa', 'diagram-3')}`;
        }
        form.addEventListener('submit', event => event.preventDefault());
        form.addEventListener('input', function () {
            currentPage = 1;
            render();
        });
        form.addEventListener('reset', function () {
            setTimeout(function () {
                currentPage = 1;
                render();
            }, 0);
        });
        document.getElementById('admin-pagination').addEventListener('click', function (event) {
            const button = event.target.closest('[data-page]');
            if (button) {
                currentPage = Number(button.dataset.page);
                render();
            }
        });
    }

    function matches(item, filters) {
        if (page === 'jobs.queue' && item.moderation !== 'pending') {
            return false;
        }
        let searchText = item.name || item.content?.title || '';
        if (collection === 'users') {
            searchText += ' ' + item.email;
        }
        if (collection === 'applications') {
            searchText = related('users', item.userId)?.name || '';
        }
        if (!AdminDomain.normalize(searchText).includes(AdminDomain.normalize(filters.q))) {
            return false;
        }
        return Object.entries(filters).every(function ([field, value]) {
            if (field === 'q' || !value) {
                return true;
            }
            const actual = field === 'companyId' && collection === 'applications' ? related('jobs', item.jobId)?.companyId : item[field];
            return actual === value;
        });
    }

    function render() {
        const filters = Object.fromEntries(new FormData(form));
        const items = context.state[collection].filter(item => matches(item, filters));
        const headers = columns();
        const totalPages = Math.max(1, Math.ceil(items.length / pageSize));
        currentPage = Math.min(currentPage, totalPages);
        document.getElementById('admin-result-count').textContent = items.length + ' kết quả · Dữ liệu minh họa';
        document.getElementById('admin-table-head').innerHTML = '<tr>' + headers.map(([label]) => `<th scope="col">${escape(label)}</th>`).join('') + '</tr>';
        document.getElementById('admin-table-body').innerHTML = items.slice((currentPage - 1) * pageSize, currentPage * pageSize).map(item => `
            <tr>
                ${headers.map(([label, value], index) => `<td>${index === headers.length - 1 ? '<div class="admin-actions">' + value(item) + '</div>' : value(item)}</td>`).join('')}
            </tr>`).join('') || `<tr><td colspan="${headers.length}" class="admin-empty">Không tìm thấy kết quả. Hãy thay từ khóa hoặc xóa bộ lọc.</td></tr>`;
        document.getElementById('admin-pagination').innerHTML = Array.from({ length: totalPages }, (_, index) => `
            <button type="button" class="btn btn-outline-dark" data-page="${index + 1}" aria-label="Trang ${index + 1}" ${currentPage === index + 1 ? 'aria-current="page"' : ''}>
                ${index + 1}
            </button>`).join('');
    }

    setupFilters();
    return { render, collection };
};
