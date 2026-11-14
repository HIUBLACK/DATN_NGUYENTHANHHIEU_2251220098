window.RecruiterAI = (function () {
    const labels = {
        completed: 'Đã đánh giá',
        pending: 'Chưa đánh giá',
        processing: 'Đang đánh giá',
        failed: 'Đánh giá thất bại'
    };

    function escape(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (character) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character];
        });
    }

    function seed(applications) {
        const samples = [
            { status: 'completed', semantic: 92, matched: ['PHP', 'Laravel', 'MySQL'], missing: ['Docker'] },
            { status: 'completed', semantic: 80, matched: ['React', 'JavaScript'], missing: ['TypeScript', 'CSS'] },
            { status: 'pending' },
            { status: 'processing' },
            { status: 'failed' },
            { status: 'completed', semantic: 60, matched: ['JavaScript'], missing: ['React', 'TypeScript', 'CSS'] }
        ];
        applications.forEach(function (application) {
            if (application.ai) {
                return;
            }
            const sample = samples[Number(application.id) - 1] || { status: 'pending' };
            application.ai = {
                ...structuredClone(sample),
                jdVersion: 1,
                version: 'demo-v1',
                evaluatedAt: application.date
            };
        });
    }

    function score(application) {
        const result = application.ai;
        if (result?.status !== 'completed') {
            return null;
        }
        const total = result.matched.length + result.missing.length;
        const skillScore = total ? result.matched.length / total * 100 : 0;
        return Math.round(result.semantic * 0.6 + skillScore * 0.4);
    }

    function matches(application, status) {
        const current = application.ai?.status || 'pending';
        return !status || (status === 'unrated' ? current !== 'completed' : current === status);
    }

    function sort(applications, order) {
        if (!['ai-desc', 'ai-asc'].includes(order)) {
            return;
        }
        applications.sort(function (first, second) {
            const firstScore = score(first);
            const secondScore = score(second);
            if (firstScore === null) {
                return secondScore === null ? 0 : 1;
            }
            if (secondScore === null) {
                return -1;
            }
            return order === 'ai-desc' ? secondScore - firstScore : firstScore - secondScore;
        });
    }

    function badge(application) {
        const status = application.ai?.status || 'pending';
        let tone = 'pending';
        if (status === 'completed') {
            tone = 'success';
        } else if (status === 'failed') {
            tone = 'failed';
        }
        return `<span class="badge-table ${tone}">${labels[status] || labels.pending}</span>`;
    }

    function summary(application, url) {
        const value = score(application);
        return `
            <div class="fw-bold mb-2">${value === null ? '—' : value + '/100'}</div>
            <div class="mb-2">${badge(application)}</div>
            <a class="btn btn-outline-dark btn-sm" href="${escape(url)}#ai-evaluation">
                <i class="bi bi-stars" aria-hidden="true"></i>
                Xem đánh giá AI
            </a>`;
    }

    function skills(items, icon) {
        return items.map(function (skill) {
            return `
                <span class="badge bg-light text-dark border me-2 mb-2 p-2">
                    <i class="bi bi-${icon} me-1" aria-hidden="true"></i>
                    ${escape(skill)}
                </span>`;
        }).join('') || '<p>Không có trong dữ liệu minh họa.</p>';
    }

    function detail(application, job) {
        const result = application.ai || { status: 'pending' };
        const value = score(application);
        const messages = {
            pending: 'Chưa có kết quả đánh giá. Bạn vẫn có thể xem CV và xử lý hồ sơ bình thường.',
            processing: 'Minh họa tác vụ đang đánh giá. Demo không chạy AI nền; bạn vẫn có thể xử lý hồ sơ.',
            failed: 'Minh họa lỗi kết nối AI Service. Hồ sơ vẫn được giữ nguyên và có thể xử lý bình thường.'
        };
        let content = `
            <p>${messages[result.status] || messages.pending}</p>
            ${result.status === 'failed' ? `
                <button type="button" class="btn btn-outline-dark" data-ai-retry>
                    <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>
                    Thử lại (demo)
                </button>` : ''}`;
        if (value !== null) {
            content = `
                <div class="row g-4">
                    <div class="col-md-4">
                        <p class="mb-1">Điểm phù hợp tổng thể</p>
                        <p class="display-5 fw-bold mb-2">${value}<span class="fs-5">/100</span></p>
                        <p>Tương đồng ngữ nghĩa CV–JD: <strong>${result.semantic}/100</strong></p>
                    </div>
                    <div class="col-md-8">
                        <h3 class="h6">Kỹ năng đáp ứng</h3>
                        <div class="mb-3">${skills(result.matched, 'check-circle')}</div>
                        <h3 class="h6">Kỹ năng chưa thể hiện trong CV</h3>
                        <div>${skills(result.missing, 'dash-circle')}</div>
                    </div>
                </div>
                <h3 class="h6 mt-3">Nhận xét AI minh họa</h3>
                <p>CV thể hiện ${escape(result.matched.join(', '))}. Cần xác minh thêm ${escape(result.missing.join(', '))} qua phỏng vấn; kỹ năng chưa xuất hiện trong CV không đồng nghĩa ứng viên không có kỹ năng đó.</p>
                <details class="border rounded p-3 mb-3">
                    <summary>Cách tính điểm minh họa</summary>
                    <p class="mt-2 mb-0">Điểm = làm tròn (60% × điểm ngữ nghĩa + 40% × điểm kỹ năng). Điểm kỹ năng = số kỹ năng đáp ứng / tổng kỹ năng JD × 100. Các kỹ năng có trọng số bằng nhau. Đây là thang điểm phù hợp, không phải xác suất được tuyển.</p>
                </details>
                <p class="small mb-1">Thời gian đánh giá mẫu: ${escape(result.evaluatedAt)}</p>`;
        }
        return `
            <section id="ai-evaluation" class="card p-4 mb-4" aria-labelledby="ai-evaluation-title">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <h2 id="ai-evaluation-title" class="h4 mb-0">
                        <i class="bi bi-stars me-2" aria-hidden="true"></i>
                        Đánh giá phù hợp bằng AI
                    </h2>
                    ${badge(application)}
                </div>
                <p class="alert alert-light border">Dữ liệu minh họa giao diện, chưa phân tích CV hoặc gọi AI Service. Điểm số chỉ hỗ trợ tham khảo, không tự động quyết định tuyển dụng.</p>
                <p>Đối chiếu tin: <strong>${escape(job?.content.title || 'Chưa có tin')}</strong> · Mã hồ sơ: ${escape(application.id)}</p>
                ${content}
                <p class="small text-muted-green mt-3 mb-0">Phiên bản JD đối chiếu mẫu: ${escape(result.jdVersion || '—')} · Phiên bản đánh giá: ${escape(result.version || '—')}. Kết quả mẫu không tự thay đổi khi chỉnh sửa tin.</p>
            </section>`;
    }

    return { seed, score, matches, sort, summary, detail };
})();
