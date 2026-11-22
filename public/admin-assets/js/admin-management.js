document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('admin-status-modal');
    const form = document.getElementById('admin-status-form');
    if (modal && form) {
        modal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            if (!button) {
                return;
            }
            const locked = button.dataset.currentStatus === 'locked';
            form.action = button.dataset.statusUrl;
            form.elements.status.value = locked ? 'active' : 'locked';
            form.elements.expected_status.value = button.dataset.currentStatus;
            form.elements.expected_log_id.value = button.dataset.logId;
            form.elements.updated_at.value = button.dataset.updatedAt;
            document.getElementById('admin-status-message').textContent = (locked ? 'Mở khóa ' : 'Khóa ') + button.dataset.recordName + '?';
            form.querySelector('[type="submit"]').disabled = false;
        });
        form.addEventListener('submit', function (event) {
            if (!event.defaultPrevented && form.checkValidity()) {
                form.querySelector('[type="submit"]').disabled = true;
            }
        });
    }
    const data = document.getElementById('admin-real-trend');
    if (!data || typeof ApexCharts === 'undefined') {
        return;
    }
    const trend = JSON.parse(data.textContent);
    const chart = new ApexCharts(document.getElementById('admin-trend-chart'), {
        chart: { type: 'bar', height: 330, fontFamily: 'Plus Jakarta Sans, sans-serif', toolbar: { show: false }, animations: { enabled: false } },
        series: [
            { name: 'Tin mới', data: trend.map(day => day.jobs) },
            { name: 'Lượt ứng tuyển', data: trend.map(day => day.applications) }
        ],
        colors: ['#072F1F', '#B4F105'],
        plotOptions: { bar: { borderRadius: 3, columnWidth: '60%' } },
        dataLabels: { enabled: false },
        xaxis: { categories: trend.map(day => day.date), tickAmount: Math.min(6, trend.length), labels: { hideOverlappingLabels: true, rotate: -35 } },
        yaxis: { min: 0, forceNiceScale: true, labels: { formatter: value => Number.isInteger(value) ? value.toString() : '' } },
        legend: { position: 'top', horizontalAlign: 'left' },
        grid: { borderColor: '#E9EFEF' },
        tooltip: { y: { formatter: value => value + ' lượt' } }
    });
    chart.render();
});
