/* The original smooth-scroll handler also captures normal mobile page links. */
if (window.jQuery && window.jQuery.fn.selectpicker) {
    const selectDefaults = window.jQuery.fn.selectpicker.Constructor.DEFAULTS;
    selectDefaults.noneSelectedText = 'Chưa chọn';
    selectDefaults.noneResultsText = 'Không có kết quả cho {0}';
    selectDefaults.selectAllText = 'Chọn tất cả';
    selectDefaults.deselectAllText = 'Bỏ chọn tất cả';
}

document.addEventListener('click', function (event) {
    const link = event.target.closest('.site-mobile-menu a');

    if (!link || !link.href || link.getAttribute('href').startsWith('#')) {
        return;
    }

    event.stopPropagation();
}, true);
