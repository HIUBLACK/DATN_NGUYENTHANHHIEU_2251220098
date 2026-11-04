(function (root) {
    const activityLabels = { draft: 'Bản nháp', open: 'Đang mở', paused: 'Tạm dừng', closed: 'Đã đóng', blocked: 'Bị khóa' };
    const moderationLabels = { draft: 'Chưa gửi', pending: 'Chờ duyệt', approved: 'Đã duyệt', rejected: 'Bị từ chối' };
    const applicationLabels = { applied: 'Mới ứng tuyển', reviewing: 'Đang xem xét', interview: 'Phỏng vấn', hired: 'Trúng tuyển', rejected: 'Từ chối', withdrawn: 'Đã rút' };
    const transitions = {
        applied: ['reviewing', 'rejected'],
        reviewing: ['interview', 'rejected'],
        interview: ['hired', 'rejected'],
        hired: [],
        rejected: [],
        withdrawn: []
    };

    function localDate() {
        const parts = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Ho_Chi_Minh', year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(new Date());
        const value = {};
        parts.forEach(function (part) {
            value[part.type] = part.value;
        });
        return value.year + '-' + value.month + '-' + value.day;
    }

    function isExpired(job, today = localDate()) {
        const content = job.published || job.content;
        return Boolean(content.deadline && content.deadline < today);
    }

    function allowedJobActions(job, today = localDate()) {
        if (['closed', 'blocked'].includes(job.activity)) {
            return [];
        }
        if (job.activity === 'open') {
            return isExpired(job, today) ? ['close'] : ['pause', 'close'];
        }
        if (job.activity === 'paused') {
            const actions = ['close'];
            if (job.published && !isExpired(job, today)) {
                actions.unshift('reopen');
            }
            return actions;
        }
        return [];
    }

    function transitionApplication(application, nextStatus, timestamp) {
        if (!(transitions[application.status] || []).includes(nextStatus)) {
            throw new Error('Không thể chuyển hồ sơ theo luồng này.');
        }
        application.status = nextStatus;
        application.history.push({ status: nextStatus, date: timestamp, actor: 'Nhà tuyển dụng demo' });
    }

    function transitionJob(job, action, today = localDate()) {
        if (!allowedJobActions(job, today).includes(action)) {
            throw new Error('Thao tác không hợp lệ hoặc tin đã hết hạn.');
        }
        job.activity = { pause: 'paused', reopen: 'open', close: 'closed' }[action];
    }

    const domain = { activityLabels, moderationLabels, applicationLabels, transitions, localDate, isExpired, allowedJobActions, transitionApplication, transitionJob };
    root.RecruiterDomain = domain;
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = domain;
    }
})(typeof window !== 'undefined' ? window : globalThis);
