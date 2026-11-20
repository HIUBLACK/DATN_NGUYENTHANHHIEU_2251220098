document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('real-job-form');
    const modalElement = document.getElementById('job-review-modal');
    if (!form || !modalElement || !window.bootstrap) {
        return;
    }
    const baseline = JSON.parse(document.getElementById('job-review-baseline').textContent);
    const openButton = document.getElementById('job-review-open');
    const confirmButton = document.getElementById('job-review-confirm');
    const intent = document.getElementById('job-intent');
    const content = document.getElementById('job-review-content');
    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const fields = [
        ['title', 'Tiêu đề'], ['job_position_id', 'Vị trí công việc'],
        ['industry_id', 'Lĩnh vực'], ['job_specialization_id', 'Chuyên môn'],
        ['job_level_id', 'Cấp bậc'], ['skills', 'Kỹ năng yêu cầu'],
        ['salary_min', 'Lương tối thiểu'], ['salary_max', 'Lương tối đa'],
        ['province_code', 'Tỉnh/thành phố'], ['location', 'Địa điểm chi tiết'],
        ['work_mode', 'Hình thức làm việc'], ['employment_type', 'Loại công việc'],
        ['quantity', 'Số lượng tuyển'], ['application_deadline', 'Hạn ứng tuyển'],
        ['description', 'Mô tả công việc'], ['requirements', 'Yêu cầu ứng viên'], ['benefits', 'Quyền lợi'],
    ];
    let busy = false;
    let confirmed = false;
    let reviewed = '';

    function values() {
        const result = {};
        fields.forEach(function ([key]) {
            const input = document.getElementById(key);
            result[key] = input.multiple
                ? Array.from(input.selectedOptions, option => option.value).sort()
                : input.value;
        });
        return result;
    }

    function normalize(key, value) {
        if (Array.isArray(value)) {
            return value.map(String).sort().join('|');
        }
        if (value === null || value === undefined || value === '') {
            return '';
        }
        if (['salary_min', 'salary_max', 'quantity'].includes(key)) {
            return String(Number(value));
        }
        return String(value).replace(/\r\n/g, '\n').trim();
    }

    function display(key, value, old) {
        if (old && Object.hasOwn(baseline.labels, key)) {
            const label = baseline.labels[key];
            return (Array.isArray(label) ? label.join(', ') : label) || 'Chưa nhập';
        }
        if (normalize(key, value) === '') {
            return 'Chưa nhập';
        }
        if (key.startsWith('salary_')) {
            return new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 2 }).format(value) + ' VND/tháng';
        }
        if (key === 'application_deadline') {
            return String(value).split('-').reverse().join('/');
        }
        const input = document.getElementById(key);
        if (input.tagName === 'SELECT') {
            const selected = Array.isArray(value) ? value.map(String) : [String(value)];
            return Array.from(input.options).filter(option => selected.includes(option.value))
                .map(option => option.textContent.trim()).join(', ') || String(value);
        }
        return String(value);
    }

    function textElement(tag, text, className) {
        const element = document.createElement(tag);
        element.className = className;
        element.textContent = text;
        return element;
    }

    function render() {
        const current = values();
        reviewed = JSON.stringify(current);
        content.replaceChildren();
        document.getElementById('job-review-source').textContent = baseline
            ? 'So sánh với ' + baseline.label + '. Các mục màu xanh có thay đổi.'
            : 'Tin mới — chưa có dữ liệu cũ. Nội dung dưới đây lấy từ form đang nhập.';
        fields.forEach(function ([key, label]) {
            const changed = baseline && normalize(key, baseline.values[key]) !== normalize(key, current[key]);
            const section = textElement('section', '', 'job-review-field' + (changed ? ' is-changed' : ''));
            section.append(textElement('h3', label + (changed ? ' — Đã thay đổi' : ''), 'h6'));
            const row = textElement('div', '', 'row g-3');
            if (baseline) {
                const oldColumn = textElement('div', '', 'col-12 col-md-6');
                oldColumn.append(textElement('div', 'Thông tin cũ', 'small text-muted mb-1'));
                oldColumn.append(textElement('div', display(key, baseline.values[key], true), 'job-review-value'));
                row.append(oldColumn);
            }
            const column = textElement('div', '', baseline ? 'col-12 col-md-6' : 'col-12');
            column.append(textElement('div', 'Thông tin mới', 'small text-muted mb-1'));
            column.append(textElement('div', display(key, current[key], false), 'job-review-value'));
            row.append(column);
            section.append(row);
            content.append(section);
        });
    }

    const minimum = document.getElementById('salary_min');
    const maximum = document.getElementById('salary_max');
    function checkSalary() {
        const reversed = minimum.value !== '' && maximum.value !== '' && Number(maximum.value) < Number(minimum.value);
        const message = reversed ? 'Lương tối đa phải lớn hơn hoặc bằng lương tối thiểu.' : '';
        maximum.setCustomValidity(message);
        return message;
    }
    minimum.addEventListener('input', checkSalary);
    maximum.addEventListener('input', checkSalary);

    const draftButton = document.getElementById('job-save-draft');
    if (draftButton) {
        draftButton.addEventListener('click', function () {
            if (busy) return;
            // Create or edit a draft; keep validation for supplied values.
            const requiredInputs = Array.from(form.querySelectorAll('[required]'));
            requiredInputs.forEach(function (input) {
                input.required = input.id === 'title';
            });
            try {
                const salaryError = checkSalary();
                const valid = window.ViejobValidation ? window.ViejobValidation.validate(form) : form.checkValidity();
                if (!valid || salaryError) {
                    if (salaryError) window.ViejobValidation?.notify(salaryError);
                    return;
                }
                intent.value = 'draft';
                confirmed = true;
                form.requestSubmit();
            } finally {
                requiredInputs.forEach(function (input) { input.required = true; });
                if (!busy) {
                    intent.value = 'submit';
                    confirmed = false;
                }
            }
        });
    }

    function canReview() {
        const salaryError = checkSalary();
        const valid = window.ViejobValidation ? window.ViejobValidation.validate(form) : form.checkValidity();
        if (!valid || salaryError) {
            if (salaryError && window.ViejobValidation) window.ViejobValidation.notify(salaryError);
            return false;
        }
        const current = values();
        if (baseline?.savedValues && fields.every(([key]) => normalize(key, current[key]) === normalize(key, baseline.savedValues[key]))) {
            window.ViejobValidation?.notify('Bạn chưa thay đổi nội dung tin tuyển dụng.');
            return false;
        }
        return true;
    }

    openButton.addEventListener('click', function () {
        if (!canReview()) return;
        render();
        modal.show();
    });
    confirmButton.addEventListener('click', function () {
        if (busy) {
            return;
        }
        if (reviewed !== JSON.stringify(values())) {
            render();
            document.getElementById('job-review-source').textContent += ' Form vừa thay đổi. Hãy kiểm tra và xác nhận lại.';
            return;
        }
        if (!canReview()) {
            modal.hide();

            return;
        }
        intent.value = 'submit';
        confirmed = true;
        form.requestSubmit();
    });
    form.addEventListener('submit', function (event) {
        if (!confirmed) {
            event.preventDefault();
            if (!canReview()) return;
            render();
            modal.show();
            return;
        }
        if (busy) {
            event.preventDefault();
            return;
        }
        busy = true;
        if (draftButton) draftButton.disabled = true;
        confirmButton.disabled = true;
        openButton.disabled = true;
        form.querySelectorAll('button[type="submit"]').forEach(button => { button.disabled = true; });
    });
    window.addEventListener('pageshow', function () {
        busy = false;
        if (draftButton) draftButton.disabled = false;
        confirmed = false;
        intent.value = 'submit';
        confirmButton.disabled = false;
        openButton.disabled = false;
        form.querySelectorAll('button[type="submit"]').forEach(button => { button.disabled = false; });
    });
});
