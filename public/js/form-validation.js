(function () {
    let timer;
    const toast = document.getElementById('validation-toast');

    function notify(message) {
        if (!toast) return;
        clearTimeout(timer);
        toast.textContent = message;
        toast.hidden = false;
        timer = setTimeout(function () { toast.hidden = true; }, 5000);
    }

    function mark(input, invalid) {
        input.classList.toggle('field-validation-error', invalid);
        if (invalid) {
            input.setAttribute('aria-invalid', 'true');
        } else {
            input.removeAttribute('aria-invalid');
        }
    }

    function validate(form, focus = true) {
        const invalid = [];
        Array.from(form.elements).forEach(function (input) {
            if (!input.willValidate) return;
            const blank = input.required && typeof input.value === 'string' && !input.value.trim();
            const failed = !input.validity.valid || blank;
            mark(input, failed);
            if (failed) invalid.push(input);
        });
        if (invalid.length) {
            notify('Vui lòng nhập đầy đủ và kiểm tra các trường được đánh dấu đỏ.');
            if (focus) invalid[0].focus();
        }
        return invalid.length === 0;
    }

    window.ViejobValidation = { validate, notify };
    document.addEventListener('invalid', function (event) {
        event.preventDefault();
        if (event.target.form) validate(event.target.form, false);
    }, true);
    document.addEventListener('submit', function (event) {
        if (!validate(event.target)) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }, true);
    function clearCorrected(event) {
        const input = event.target;
        if (!input.classList.contains('field-validation-error')) return;
        if (input.validity.valid && (!input.required || input.value.trim())) mark(input, false);
    }
    document.addEventListener('input', clearCorrected);
    document.addEventListener('change', clearCorrected);
    const data = document.getElementById('validation-server-errors');
    const errors = data ? JSON.parse(data.textContent) : {};
    Object.keys(errors).forEach(function (key) {
        const name = key.replace(/\.([^\.]+)/g, '[$1]');
        document.querySelectorAll('input[name], select[name], textarea[name]').forEach(function (input) {
            if (input.name === name || input.name === name + '[]' || input.name === key.split('.')[0] + '[]') {
                mark(input, true);
            }
        });
    });
    if (Object.keys(errors).length) {
        const messages = Object.values(errors).flat();
        const missingRequired = messages.some(function (message) {
            return /field is required|bắt buộc/i.test(message);
        });
        notify(missingRequired
            ? 'Vui lòng nhập đầy đủ các trường bắt buộc trước khi gửi duyệt hoặc lưu thông tin.'
            : messages[0]);
    }
})();
