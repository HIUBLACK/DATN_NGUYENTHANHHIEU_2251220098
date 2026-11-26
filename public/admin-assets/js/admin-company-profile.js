window.renderAdminCompanyProfile = function (company) {
    const { escape, badge } = AdminUI;

    function safeUrl(value) {
        try {
            const url = new URL(value || '', location.origin);
            return value && ['http:', 'https:'].includes(url.protocol) ? escape(url.href) : '';
        } catch (error) {
            return '';
        }
    }

    function fact(label, value) {
        return `
            <div>
                <dt>${label}</dt>
                <dd>${escape(value || 'Chưa cập nhật')}</dd>
            </div>`;
    }

    function environment(label, value) {
        return `
            <div class="mb-3">
                <h4>${label}</h4>
                <p class="company-profile-text">${escape(value || 'Chưa cập nhật')}</p>
            </div>`;
    }

    const logo = safeUrl(company.logo_url);
    const website = safeUrl(company.website);
    const gallery = (company.gallery_urls || []).map(safeUrl).filter(Boolean);

    return `
        ${company.status === 'blocked' ? `
            <div class="alert alert-warning" role="status">
                <i class="bi bi-lock me-2" aria-hidden="true"></i>
                Doanh nghiệp đang bị khóa. Hồ sơ vẫn hiển thị để Admin kiểm tra; tin tuyển dụng không đủ điều kiện công khai.
            </div>` : ''}
        <article class="company-profile">
            <header class="company-profile-heading">
                ${logo ? `
                    <img class="company-profile-logo" src="${logo}" alt="Logo ${escape(company.name)}">
                ` : '<div class="company-profile-logo company-logo-placeholder" aria-hidden="true">DN</div>'}
                <div>
                    <div class="mb-2">${badge(company.status)}</div>
                    <h2>${escape(company.name)}</h2>
                </div>
            </header>
            <div class="company-profile-body">
                <section class="mb-4">
                    <h3>
                        <i class="bi bi-building me-2" aria-hidden="true"></i>
                        Giới thiệu doanh nghiệp
                    </h3>
                    <p class="company-profile-text">${escape(company.description || 'Chưa cập nhật')}</p>
                </section>
                <section class="mb-4">
                    <h3>
                        <i class="bi bi-info-circle me-2" aria-hidden="true"></i>
                        Thông tin doanh nghiệp
                    </h3>
                    <dl class="company-facts">
                        ${fact('Lĩnh vực', company.industry)}
                        ${fact('Mô hình công ty', company.company_type)}
                        ${fact('Quy mô nhân sự', company.size)}
                        ${fact('Quốc gia', company.country)}
                        ${fact('Địa chỉ', company.address)}
                        <div>
                            <dt>Website</dt>
                            <dd>
                                ${website ? `<a href="${website}" target="_blank" rel="noopener noreferrer">${escape(company.website)}</a>` : 'Chưa cập nhật'}
                            </dd>
                        </div>
                    </dl>
                </section>
                <section class="mb-4">
                    <h3>
                        <i class="bi bi-people me-2" aria-hidden="true"></i>
                        Môi trường làm việc
                    </h3>
                    ${environment('Thời gian làm việc', company.working_hours)}
                    ${environment('Giới thiệu môi trường làm việc', company.work_environment)}
                    ${environment('Chính sách làm thêm giờ (OT)', company.overtime_policy)}
                </section>
                <section>
                    <h3>
                        <i class="bi bi-images me-2" aria-hidden="true"></i>
                        Không gian văn phòng
                    </h3>
                    <div class="company-office-gallery">
                        ${gallery.map((url, index) => `
                            <a href="${url}" target="_blank" rel="noopener noreferrer">
                                <img src="${url}" alt="Không gian làm việc minh họa ${index + 1}" loading="lazy">
                            </a>`).join('')}
                    </div>
                    ${gallery.length ? '<p class="small text-muted mt-2">Hình ảnh minh họa cho dữ liệu demo.</p>' : '<p class="text-muted">Doanh nghiệp chưa bổ sung hình ảnh văn phòng.</p>'}
                </section>
            </div>
        </article>`;
};
