window.setupAdminDashboard = function (context) {
    const { state } = context;
    const { escape } = AdminUI;
    let chart = null;

    function cards(target, metrics) {
        document.getElementById(target).innerHTML = metrics.map(([label, count, icon]) => `
            <div class="col-sm-6 col-xl-3">
                <div class="card card-stat p-4 h-100">
                    <div class="d-flex justify-content-between gap-3">
                        <span class="stat-label">${escape(label)}</span>
                        <i class="bi bi-${icon} fs-5" aria-hidden="true"></i>
                    </div>
                    <strong class="stat-value">${count}</strong>
                </div>
            </div>`).join('');
    }

    cards('admin-user-stats', [
        ['Ứng viên', state.users.filter(user => user.role === 'candidate').length, 'people'],
        ['Nhà tuyển dụng', state.users.filter(user => user.role === 'recruiter').length, 'person-workspace'],
        ['Tài khoản hoạt động', state.users.filter(user => user.status === 'active').length, 'person-check'],
        ['Tài khoản bị khóa', state.users.filter(user => user.status === 'blocked').length, 'person-lock']
    ]);
    const systemMetrics = [
        ['Tổng doanh nghiệp', state.companies.length, 'buildings'],
        ['Doanh nghiệp bị khóa', state.companies.filter(company => company.status === 'blocked').length, 'building-lock'],
        ['Tổng tin tuyển dụng', state.jobs.length, 'briefcase'],
        ['Tin chờ duyệt', state.jobs.filter(job => job.moderation === 'pending').length, 'hourglass-split']
    ];
    ['open', 'paused', 'closed', 'blocked'].forEach(status => {
        systemMetrics.push(['Tin ' + AdminDomain.labels[status].toLocaleLowerCase('vi'), state.jobs.filter(job => job.activity === status).length, 'file-earmark-text']);
    });
    cards('admin-system-stats', systemMetrics);
    const applicationMetrics = [['Tổng hồ sơ', state.applications.length, 'files']];
    ['applied', 'reviewing', 'interview', 'hired', 'rejected', 'withdrawn'].forEach(status => {
        applicationMetrics.push([AdminDomain.labels[status], state.applications.filter(application => application.status === status).length, 'person-lines-fill']);
    });
    cards('admin-application-stats', applicationMetrics);

    const form = document.getElementById('admin-date-filter');
    const start = document.getElementById('admin-date-start');
    const end = document.getElementById('admin-date-end');
    const dates = [...state.jobs, ...state.applications].map(item => item.created).sort();
    start.value = dates[0] || AdminDomain.today();
    end.value = AdminDomain.today();

    function renderTrend() {
        const first = new Date(start.value + 'T00:00:00Z');
        const last = new Date(end.value + 'T00:00:00Z');
        const days = Math.round((last - first) / 86400000) + 1;
        end.setCustomValidity('');
        if (!Number.isFinite(days) || days < 1 || days > 366) {
            end.setCustomValidity('Chọn khoảng ngày hợp lệ, tối đa 366 ngày.');
            end.reportValidity();
            return;
        }
        const categories = [];
        const jobs = [];
        const applications = [];
        for (let offset = 0; offset < days; offset++) {
            const date = new Date(first.getTime() + offset * 86400000).toISOString().slice(0, 10);
            categories.push(date);
            jobs.push(state.jobs.filter(job => job.created === date).length);
            applications.push(state.applications.filter(application => application.created === date).length);
        }
        const jobCount = jobs.reduce((total, value) => total + value, 0);
        const applicationCount = applications.reduce((total, value) => total + value, 0);
        document.getElementById('admin-range-summary').textContent = 'Trong khoảng đã chọn: ' + jobCount + ' tin mới · ' + applicationCount + ' lượt ứng tuyển.' + (jobCount + applicationCount === 0 ? ' Chưa có dữ liệu trong khoảng này.' : '');
        document.getElementById('admin-trend-table').innerHTML = categories.map((date, index) => `
            <tr>
                <td>${escape(date)}</td>
                <td>${jobs[index]}</td>
                <td>${applications[index]}</td>
            </tr>`).join('');
        if (chart) {
            chart.destroy();
        }
        chart = new ApexCharts(document.getElementById('admin-trend-chart'), {
            chart: { type: 'bar', height: 330, fontFamily: 'Plus Jakarta Sans, sans-serif', toolbar: { show: false }, animations: { enabled: false } },
            series: [{ name: 'Tin mới', data: jobs }, { name: 'Lượt ứng tuyển', data: applications }],
            colors: ['#072F1F', '#B4F105'],
            plotOptions: { bar: { borderRadius: 3, columnWidth: '60%' } },
            dataLabels: { enabled: false },
            xaxis: { categories, tickAmount: Math.min(6, days), labels: { hideOverlappingLabels: true, rotate: -35 } },
            yaxis: { min: 0, forceNiceScale: true, labels: { formatter: value => Number.isInteger(value) ? value.toString() : '' } },
            legend: { position: 'top', horizontalAlign: 'left' },
            grid: { borderColor: '#E9EFEF' },
            tooltip: { y: { formatter: value => value + ' lượt' } }
        });
        chart.render();
    }
    form.addEventListener('input', () => end.setCustomValidity(''));
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        renderTrend();
    });
    renderTrend();
};
