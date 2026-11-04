window.renderRecruiterCharts = function (state, domain, routes) {
    const jobChart = document.getElementById('recruiter-job-chart');
    const applicationChart = document.getElementById('recruiter-application-chart');
    const attention = document.getElementById('recruiter-attention');
    if (!jobChart || !applicationChart || !attention) {
        return;
    }

    const jobGroups = [
        ['open', 'Đang mở', '#072f1f'],
        ['paused', 'Tạm dừng', '#accc58'],
        ['draft', 'Bản nháp', '#94aaa1'],
        ['closed', 'Đã đóng', '#596b63'],
        ['blocked', 'Bị khóa', '#b25949'],
        ['expired', 'Hết hạn đang mở', '#d69b39']
    ];
    let angle = 0;
    const segments = [];
    const legend = document.createElement('ul');
    legend.className = 'recruiter-chart-legend';
    jobGroups.forEach(function ([status, label, color]) {
        const count = state.jobs.filter(function (job) {
            // Tách tin đang mở nhưng hết hạn để khớp thẻ "Tin đang mở".
            const category = job.activity === 'open' && domain.isExpired(job) ? 'expired' : job.activity;
            return category === status;
        }).length;
        const nextAngle = angle + (state.jobs.length ? count / state.jobs.length * 360 : 0);
        segments.push(color + ' ' + angle + 'deg ' + nextAngle + 'deg');
        angle = nextAngle;
        const item = document.createElement('li');
        const marker = document.createElement('span');
        marker.className = 'recruiter-chart-marker';
        marker.style.backgroundColor = color;
        marker.setAttribute('aria-hidden', 'true');
        const text = document.createElement('span');
        text.textContent = label;
        const value = document.createElement('strong');
        value.textContent = count + ' tin';
        item.append(marker, text, value);
        legend.append(item);
    });
    const ring = document.createElement('div');
    ring.className = 'recruiter-chart-ring';
    ring.setAttribute('aria-hidden', 'true');
    ring.style.background = state.jobs.length ? 'conic-gradient(' + segments.join(',') + ')' : '#e5ebe7';
    const center = document.createElement('div');
    const total = document.createElement('strong');
    total.textContent = state.jobs.length;
    const caption = document.createElement('span');
    caption.textContent = 'Tổng số tin';
    center.append(total, caption);
    ring.append(center);
    jobChart.replaceChildren(ring, legend);

    const counts = Object.entries(domain.applicationLabels).map(function ([status, label]) {
        return { status, label, count: state.applications.filter(application => application.status === status).length };
    });
    const maximum = Math.max(1, ...counts.map(group => group.count));
    const bars = document.createElement('ul');
    bars.className = 'recruiter-chart-bars';
    counts.forEach(function (group) {
        const row = document.createElement('li');
        const label = document.createElement('span');
        label.textContent = group.label;
        const track = document.createElement('span');
        track.className = 'recruiter-chart-track';
        track.setAttribute('aria-hidden', 'true');
        const bar = document.createElement('span');
        bar.className = 'recruiter-chart-bar recruiter-chart-bar-' + group.status;
        bar.style.width = group.count / maximum * 100 + '%';
        track.append(bar);
        const count = document.createElement('strong');
        count.textContent = group.count;
        row.append(label, track, count);
        bars.append(row);
    });
    applicationChart.replaceChildren(bars);
    if (!state.applications.length) {
        const empty = document.createElement('p');
        empty.className = 'text-muted-green small';
        empty.textContent = 'Chưa có hồ sơ ứng tuyển để thống kê.';
        applicationChart.append(empty);
    }

    const tasks = [
        ['Hồ sơ mới cần xem', state.applications.filter(application => application.status === 'applied').length, routes.applications, 'person-plus'],
        ['Tin đang chờ duyệt', state.jobs.filter(job => job.moderation === 'pending').length, routes.jobs, 'hourglass-split'],
        ['Tin bị từ chối', state.jobs.filter(job => job.moderation === 'rejected').length, routes.jobs, 'exclamation-circle']
    ];
    attention.replaceChildren();
    tasks.forEach(function ([label, count, destination, icon]) {
        const column = document.createElement('div');
        column.className = 'col-md-4';
        const link = document.createElement('a');
        link.className = 'recruiter-attention-link';
        link.href = destination;
        const symbol = document.createElement('i');
        symbol.className = 'bi bi-' + icon;
        symbol.setAttribute('aria-hidden', 'true');
        const text = document.createElement('span');
        text.textContent = label;
        const value = document.createElement('strong');
        value.textContent = count;
        link.append(symbol, text, value);
        column.append(link);
        attention.append(column);
    });
};
