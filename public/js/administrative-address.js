document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const source = document.getElementById('administrative-address-data');
    if (!source) {
        return;
    }
    const wards = JSON.parse(source.textContent);

    document.querySelectorAll('[data-administrative-address]').forEach(function (container) {
        if (container.dataset.initialized) {
            return;
        }
        container.dataset.initialized = 'true';
        const province = container.querySelector('[data-province-select]');
        const ward = container.querySelector('[data-ward-select]');
        const country = document.getElementById(container.dataset.country);
        if (!province || !ward) {
            return;
        }

        function updateWards() {
            const selected = ward.value;
            const items = wards.filter(item => item.province_code === province.value);
            ward.replaceChildren(new Option('Chọn xã/phường/đặc khu', ''));
            items.forEach(function (item) {
                ward.add(new Option(item.name, item.code, false, item.code === selected));
            });
            ward.disabled = !province.value || province.disabled;
        }

        function updateCountry() {
            const vietnam = !country || country.value === 'Việt Nam';
            container.querySelector('[data-vietnam-address]').hidden = !vietnam;
            province.disabled = !vietnam;
            province.required = vietnam;
            ward.required = vietnam;
            updateWards();
        }

        province.addEventListener('change', function () {
            ward.value = '';
            updateWards();
            ward.dispatchEvent(new Event('change', { bubbles: true }));
        });
        if (country) {
            country.addEventListener('change', updateCountry);
        }
        updateCountry();
    });
});
