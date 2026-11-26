document.addEventListener('DOMContentLoaded', function () {
    const seedElement = document.getElementById('admin-seed');
    const routeElement = document.getElementById('admin-routes');
    if (!seedElement || !routeElement) {
        return;
    }
    const seed = JSON.parse(seedElement.textContent);
    const storageKey = 'viejob-admin-preview-v1-user-' + document.body.dataset.previewUser;
    const page = document.body.dataset.adminPage;
    const routes = routeElement.dataset;
    const modal = new bootstrap.Modal(document.getElementById('admin-confirm'));
    const reason = document.getElementById('admin-reason');
    let state = structuredClone(seed);
    let confirmCallback = null;
    let list = null;

    function showError(message) {
        window.ViejobToast.notify(message, 'error');
    }

    try {
        const stored = sessionStorage.getItem(storageKey);
        if (stored) {
            const parsed = JSON.parse(stored);
            if (!['users', 'companies', 'jobs', 'applications'].every(key => Array.isArray(parsed[key]))) {
                throw new Error('Dữ liệu phiên không hợp lệ.');
            }
            state = parsed;
            // Bổ sung trường hồ sơ mới, giữ nguyên trạng thái và lịch sử của phiên cũ.
            state.companies = state.companies.map(function (company) {
                const defaults = seed.companies.find(entry => entry.id === company.id);
                return { ...defaults, ...company };
            });

        }
    } catch (failure) {
        showError('Không đọc được phiên demo. Đang dùng dữ liệu mẫu ban đầu.');
    }



    function commit(change) {
        const next = structuredClone(state);
        change(next);
        try {
            sessionStorage.setItem(storageKey, JSON.stringify(next));
            state = next;
            window.ViejobToast.notify('Đã lưu thay đổi mô phỏng trong tab này. Chưa cập nhật database hoặc hệ thống thật.', 'success');
            return true;
        } catch (failure) {
            showError('Không thể lưu phiên demo. Thay đổi chưa được áp dụng; hãy cho phép bộ nhớ phiên của trình duyệt.');
            return false;
        }
    }

    function confirm(message, needsReason, callback) {
        document.getElementById('admin-confirm-message').textContent = message;
        document.getElementById('admin-reason-group').hidden = !needsReason;
        document.getElementById('admin-confirm-error').hidden = true;
        reason.value = '';
        reason.required = needsReason;
        confirmCallback = callback;
        modal.show();
    }

    document.getElementById('admin-confirm-form').addEventListener('submit', function (event) {
        event.preventDefault();
        const modalError = document.getElementById('admin-confirm-error');
        if (reason.required && !reason.value.trim()) {
            modalError.textContent = 'Lý do không được để trống hoặc chỉ gồm khoảng trắng.';
            modalError.hidden = false;
            return;
        }
        try {
            if (confirmCallback) {
                confirmCallback(reason.value.trim());
            }
            modal.hide();
            confirmCallback = null;
        } catch (failure) {
            modalError.textContent = failure.message;
            modalError.hidden = false;
        }
    });

    const context = { page, routes, commit, confirm, get state() { return state; } };
    function render() {
        if (list) {
            list.render();
        } else if (document.getElementById('admin-detail')) {
            window.renderAdminDetail(context);
        }
    }

    if (document.getElementById('admin-filters')) {
        list = window.createAdminList(context);
    }
    if (page === 'dashboard') {
        window.setupAdminDashboard(context);
    }
    render();

    document.addEventListener('click', function (event) {
        const button = event.target.closest('[data-action]');
        if (button) {
            const { collection, id, action } = button.dataset;
            const item = state[collection].find(entry => entry.id === id);
            let message = button.textContent.trim() + ': ' + (item.name || item.content.title) + '?';
            if (collection === 'jobs') {
                message += ' Phiên bản ' + item.version + '. Mở khóa không tự phê duyệt hoặc mở lại tin đã đóng.';
            }
            if (collection === 'companies') {
                message += ' Khi doanh nghiệp bị khóa, các tin không đủ điều kiện hiển thị. Giữ nguyên trạng thái tin; mở khóa chỉ khôi phục điều kiện hiển thị của tin hợp lệ.';
            }
            confirm(message, ['lock', 'reject'].includes(action), function (text) {
                commit(next => AdminDomain.applyAction(next, collection, id, action, text));
                render();
            });
        }
    });
    const reset = document.getElementById('admin-reset');
    if (reset) {
        reset.addEventListener('click', function () {
            confirm('Đặt lại dữ liệu Admin trong tab? Không tác động dữ liệu Candidate hoặc Recruiter.', false, function () {
                commit(next => Object.assign(next, structuredClone(seed)));
            });
        });
    }
});
