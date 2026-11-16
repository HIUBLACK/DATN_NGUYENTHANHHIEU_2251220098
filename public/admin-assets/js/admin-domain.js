(function (root) {
    const labels = {
        active: 'Hoạt động', inactive: 'Vô hiệu hóa', blocked: 'Bị khóa',
        candidate: 'Ứng viên', recruiter: 'Nhà tuyển dụng', admin: 'Quản trị viên',
        draft: 'Bản nháp', open: 'Đang mở', paused: 'Tạm dừng', closed: 'Đã đóng',
        pending: 'Chờ duyệt', approved: 'Đã duyệt', rejected: 'Bị từ chối',
        applied: 'Mới ứng tuyển', reviewing: 'Đang xem xét', interview: 'Phỏng vấn',
        hired: 'Trúng tuyển', withdrawn: 'Đã rút'
    };

    function normalize(value) {
        return String(value || '').normalize('NFKC').trim().replace(/\s+/g, ' ').toLocaleLowerCase('vi');
    }

    function today() {
        const parts = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Ho_Chi_Minh', year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(new Date());
        const values = Object.fromEntries(parts.map(part => [part.type, part.value]));
        return values.year + '-' + values.month + '-' + values.day;
    }

    function record(state, item, action, reason, result) {
        const actor = state.users.find(user => user.id === state.currentAdminId);
        item.history.push({
            action, reason, result,
            version: item.version || '',
            actor: actor.name,
            time: new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short', timeZone: 'Asia/Ho_Chi_Minh' }).format(new Date())
        });
    }

    function requireReason(reason) {
        if (!reason.trim()) {
            throw new Error('Vui lòng nhập lý do, không chỉ nhập khoảng trắng.');
        }
    }

    function applyAction(state, collection, id, action, reason = '') {
        if (!['users', 'companies', 'jobs'].includes(collection)) {
            throw new Error('Thao tác không được hỗ trợ.');
        }
        const item = state[collection].find(entry => entry.id === id);
        if (!item) {
            throw new Error('Không tìm thấy dữ liệu trong phiên demo.');
        }
        if (collection === 'users' && (item.role === 'admin' || item.id === state.currentAdminId)) {
            throw new Error('Không được khóa hoặc mở khóa tài khoản Admin tại đây.');
        }
        if (['lock', 'reject'].includes(action)) {
            requireReason(reason);
        }
        if (collection === 'jobs' && ['approve', 'reject'].includes(action)) {
            if (item.moderation !== 'pending') {
                throw new Error('Chỉ xử lý phiên bản đang chờ duyệt.');
            }
            if (action === 'approve') {
                item.published = structuredClone(item.content);
                item.publishedVersion = item.version;
                item.moderation = 'approved';
                const company = state.companies.find(company => company.id === item.companyId);
                if (item.activity === 'draft') {
                    item.activity = company.status === 'active' && item.content.deadline >= today() ? 'open' : 'paused';
                }
                item.reason = '';
            } else {
                item.moderation = 'rejected';
                item.reason = reason.trim();
            }
            record(state, item, action === 'approve' ? 'Phê duyệt phiên bản' : 'Từ chối phiên bản', reason.trim(), item.moderation);
            return;
        }
        const field = collection === 'jobs' ? 'activity' : 'status';
        if (action === 'lock' && item[field] !== 'blocked') {
            item.previousActivity = item[field];
            item[field] = 'blocked';
        } else if (action === 'unlock' && item[field] === 'blocked') {
            let restored = item.previousActivity || (collection === 'jobs' ? 'paused' : 'active');
            if (collection === 'jobs' && restored === 'open') {
                const company = state.companies.find(company => company.id === item.companyId);
                if (!item.published) {
                    restored = 'draft';
                } else if (item.published.deadline < today() || company.status === 'blocked') {
                    restored = 'paused';
                }
            }
            item[field] = restored;
        } else {
            throw new Error('Trạng thái đã thay đổi hoặc thao tác không hợp lệ.');
        }
        record(state, item, action === 'lock' ? 'Khóa' : 'Mở khóa', reason.trim(), item[field]);
    }

    root.AdminDomain = { labels, normalize, today, applyAction };
})(typeof window === 'undefined' ? globalThis : window);
