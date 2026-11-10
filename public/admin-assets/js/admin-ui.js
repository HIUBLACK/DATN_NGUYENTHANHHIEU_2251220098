window.AdminUI = (function () {
    function escape(value) {
        return String(value ?? '').replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]);
    }

    function badge(status, label = null) {
        let tone = 'pending';
        if (['active', 'open', 'approved', 'hired'].includes(status)) {
            tone = 'success';
        } else if (['blocked', 'closed', 'rejected', 'inactive'].includes(status)) {
            tone = 'failed';
        }
        return `<span class="badge-table ${tone}">${escape(label || AdminDomain.labels[status] || status)}</span>`;
    }

    function link(url, label, icon = 'eye') {
        return `
            <a href="${escape(url)}" class="btn btn-outline-dark btn-sm">
                <i class="bi bi-${icon}" aria-hidden="true"></i>
                ${escape(label)}
            </a>`;
    }

    function action(collection, item, operation, label, icon) {
        return `
            <button type="button" class="btn btn-outline-dark btn-sm"
                data-action="${operation}" data-collection="${collection}" data-id="${escape(item.id)}">
                <i class="bi bi-${icon}" aria-hidden="true"></i>
                ${escape(label)}
            </button>`;
    }

    function lockButton(collection, item) {
        if (collection === 'users' && item.role === 'admin') {
            return '';
        }
        const locked = (item.activity || item.status) === 'blocked';
        return action(collection, item, locked ? 'unlock' : 'lock', locked ? 'Mở khóa' : 'Khóa', locked ? 'unlock' : 'lock');
    }

    function history(items) {
        if (!items.length) {
            return '<p class="admin-empty">Chưa có lịch sử thao tác.</p>';
        }
        return `
            <div class="table-responsive">
                <table class="table-custom admin-table">
                    <thead>
                        <tr>
                            <th>Phiên bản</th>
                            <th>Hành động / Kết quả</th>
                            <th>Người thực hiện</th>
                            <th>Thời gian</th>
                            <th>Lý do</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${[...items].reverse().map(event => `
                            <tr>
                                <td>${escape(event.version || '—')}</td>
                                <td>
                                    <div class="mb-2">${escape(event.action)}</div>
                                    ${badge(event.result)}
                                </td>
                                <td>${escape(event.actor)}</td>
                                <td>${escape(event.time)}</td>
                                <td class="admin-text">${escape(event.reason || '—')}</td>
                            </tr>`).join('')}
                    </tbody>
                </table>
            </div>`;
    }

    function details(fields) {
        return `<dl class="admin-description">${fields.map(([label, value]) => `
            <div>
                <dt>${escape(label)}</dt>
                <dd class="admin-text">${escape(value || 'Chưa cung cấp')}</dd>
            </div>`).join('')}</dl>`;
    }

    return { escape, badge, link, action, lockButton, history, details };
})();
