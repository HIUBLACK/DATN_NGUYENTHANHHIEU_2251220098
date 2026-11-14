document.addEventListener('DOMContentLoaded', function () {
    const source = document.getElementById('job-menu-data');
    if (!source) {
        return;
    }
    const data = JSON.parse(source.textContent);
    let categories = { ...data.categories, occupations: data.occupations, specialties: data.specialties };
    try {
        const stored = JSON.parse(sessionStorage.getItem('viejob-admin-preview-v1') || 'null');
        Object.keys(categories).forEach(function (key) {
            if (Array.isArray(stored?.[key])) {
                categories[key] = stored[key].map(function (item) {
                    const original = categories[key].find(entry => entry.id === item.id);
                    return { ...original, ...item };
                });
            }
        });
    } catch (error) {
        // Nếu phiên demo không đọc được, dùng danh mục mẫu từ server.
    }
    const collections = { industry: 'industries', skill: 'skills', level: 'levels', specialtyId: 'specialties' };

    function items(field) {
        if (!collections[field]) {
            return data.filters[field][1].map(name => ({ name }));
        }
        return categories[collections[field]].filter(function (item) {
            return item.status === 'active' && (field !== 'specialtyId'
                || categories.occupations.some(group => group.id === item.occupationId && group.status === 'active'));
        });
    }

    function showCategory(menu, field) {
        const row = menu.querySelector('[data-job-category="' + field + '"]');
        menu.querySelector('.job-mega-title').textContent = row.querySelector('a').textContent;
        menu.querySelectorAll('[data-job-category]').forEach(function (category) {
            const active = category === row;
            category.classList.toggle('is-active', active);
            category.querySelector('button').setAttribute('aria-expanded', String(active));
        });
        const links = items(field).map(function (item) {
            const url = new URL(data.url);
            url.searchParams.set(field, field === 'specialtyId' ? item.id : (item.filterValue || item.name));
            if (field === 'specialtyId') {
                url.searchParams.set('occupationId', item.occupationId);
            }
            const link = document.createElement('a');
            link.href = url.href;
            link.textContent = item.name;
            return link;
        });
        const options = menu.querySelector('.job-mega-options');
        options.replaceChildren(...links);
        options.scrollTop = 0;
        if (!links.length) {
            options.textContent = 'Chưa có danh mục đang hiện.';
        }
    }

    function setOpen(menu, open) {
        menu.querySelector('.job-mega-panel').hidden = !open;
        menu.querySelector('.job-mega-toggle').setAttribute('aria-expanded', String(open));
        if (open && !menu.querySelector('.is-active')) {
            showCategory(menu, 'industry');
        }
    }

    // Template sao chép menu sang thanh điều hướng di động sau khi DOM sẵn sàng.
    document.addEventListener('pointerover', function (event) {
        const menu = event.target.closest('.job-mega');
        if (!menu || event.pointerType === 'touch' || menu.closest('.site-mobile-menu')) {
            return;
        }
        setOpen(menu, true);
        const category = event.target.closest('[data-job-category]');
        if (category && !category.classList.contains('is-active')) {
            showCategory(menu, category.dataset.jobCategory);
        }
    });
    document.addEventListener('pointerout', function (event) {
        const menu = event.target.closest('.job-mega');
        if (menu && !menu.closest('.site-mobile-menu') && !menu.contains(event.relatedTarget) && event.pointerType !== 'touch') {
            setOpen(menu, false);
        }
    });
    document.addEventListener('click', function (event) {
        const toggle = event.target.closest('.job-mega-toggle');
        const expand = event.target.closest('[data-job-expand]');
        if (toggle) {
            const menu = toggle.closest('.job-mega');
            setOpen(menu, menu.querySelector('.job-mega-panel').hidden);
        } else if (expand) {
            showCategory(expand.closest('.job-mega'), expand.dataset.jobExpand);
        } else if (!event.target.closest('.job-mega')) {
            document.querySelectorAll('.job-mega').forEach(menu => setOpen(menu, false));
        }
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            const menu = event.target.closest('.job-mega');
            if (menu) {
                setOpen(menu, false);
                menu.querySelector('.job-mega-toggle').focus();
            }
        }
    });
    document.addEventListener('focusout', function (event) {
        const menu = event.target.closest('.job-mega');
        if (menu && !menu.contains(event.relatedTarget)) {
            setOpen(menu, false);
        }
    });

    ['industry', 'skill', 'level'].forEach(function (field) {
        const select = document.getElementById('filter-' + field);
        if (!select) {
            return;
        }
        const selected = new URLSearchParams(location.search).get(field) || '';
        select.replaceChildren(new Option('Tất cả', ''));
        items(field).forEach(item => select.add(new Option(item.name, item.filterValue || item.name)));
        if (selected && !Array.from(select.options).some(option => option.value === selected)) {
            select.add(new Option(selected + ' (đã chọn)', selected));
        }
        select.value = selected;
    });
    const browse = new URLSearchParams(location.search).get('browse');
    if (browse && ['industry', 'skill', 'specialtyId', 'level', 'company', 'location'].includes(browse)) {
        const select = document.getElementById('filter-' + browse);
        if (select) {
            select.classList.add('job-filter-highlight');
            select.focus({ preventScroll: true });
        }
    }
});
