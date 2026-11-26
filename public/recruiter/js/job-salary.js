document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-salary-control]').forEach(function (control) {
        const input = control.querySelector('[data-salary-input]');
        const value = control.querySelector('[data-salary-value]');
        if (!input || !value) {
            return;
        }
        const limit = Math.floor(Number(input.dataset.limit));

        function update(raw, caretDigits) {
            const valid = /^\d*$/.test(raw);
            const normalized = valid ? raw.replace(/^0+(?=\d)/, '') : raw;
            const error = !valid
                ? 'Vui lòng nhập số nguyên VNĐ không âm.'
                : (Number(normalized) > limit ? 'Mức lương vượt quá giới hạn cho phép.' : '');
            value.value = normalized;
            input.dataset.salaryError = error;
            input.setCustomValidity(error);
            input.value = valid ? normalized.replace(/\B(?=(\d{3})+(?!\d))/g, '.') : normalized;

            // Đếm chữ số trước con trỏ để việc chèn dấu chấm không đẩy con trỏ về cuối ô.
            if (caretDigits !== undefined && valid) {
                let position = 0;
                let digits = 0;
                while (position < input.value.length && digits < caretDigits) {
                    if (/\d/.test(input.value[position])) digits++;
                    position++;
                }
                input.setSelectionRange(position, position);
            }
        }

        // DECIMAL từ database thường có đuôi .00; chỉ bỏ phần lẻ bằng 0, không làm tròn dữ liệu.
        update(value.value.replace(/\.0+$/, ''));
        input.addEventListener('input', function () {
            const digits = input.value.slice(0, input.selectionStart).replace(/\D/g, '').length;
            update(input.value.replace(/\./g, ''), digits);
        });
        input.addEventListener('beforeinput', function (event) {
            if (event.data && /[-+eE]/.test(event.data)) {
                event.preventDefault();
            }
        });
        control.querySelectorAll('[data-salary-step]').forEach(function (button) {
            button.addEventListener('click', function () {
                if (input.matches(':disabled') || input.readOnly || input.dataset.salaryError) return;
                const next = Number(value.value || 0) + Number(button.dataset.salaryStep) * 100000;
                update(String(Math.min(limit, Math.max(0, next))));
                input.dispatchEvent(new Event('input', { bubbles: true }));
            });
        });
    });
});
