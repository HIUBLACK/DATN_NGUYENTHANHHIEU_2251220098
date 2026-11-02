/* Pure presentation rules. Server-side authorization and validation are still required. */
(function (root) {
    'use strict';
    const statuses = {open:'Đang tuyển',closed:'Đã đóng',expired:'Hết hạn',hidden:'Không khả dụng',pending:'Chờ phân tích',processing:'Đang phân tích',needs_review:'Cần xác nhận',completed:'Đã xác nhận',failed:'Phân tích thất bại',applied:'Đã ứng tuyển',reviewing:'Đang xem xét',interview:'Mời phỏng vấn',hired:'Trúng tuyển',rejected:'Từ chối',withdrawn:'Đã rút'};
    function applicationEligibility(job, applications) {
        if (!job || job.status !== 'open') return {allowed:false, reason:'Công việc đã đóng, hết hạn hoặc không khả dụng.'};
        const attempts = applications.filter(a => a.jobId === job.id);
        if (attempts.length >= 2) return {allowed:false, reason:'Bạn đã đạt giới hạn hai lượt ứng tuyển cho công việc này.'};
        if (attempts.length && attempts[attempts.length-1].status !== 'withdrawn') return {allowed:false, reason:'Bạn đã có hồ sơ ứng tuyển cho công việc này.'};
        return {allowed:true, attempt:attempts.length+1};
    }
    function canWithdraw(status) { return ['applied','reviewing','interview'].includes(status); }
    function pdfError(file, count, replacing=false) {
        if (!replacing && count >= 10) return 'Bạn chỉ có thể lưu tối đa 10 CV. Hãy xóa một CV trước khi tải thêm.';
        if (!file || !/\.pdf$/i.test(file.name) || (file.type && file.type !== 'application/pdf')) return 'Chỉ chấp nhận tệp PDF.';
        if (!file.size || file.size > 5*1024*1024) return 'PDF phải có dung lượng lớn hơn 0 và không quá 5 MB.';
        return '';
    }
    function normalizeSkills(skills) {
        const known = {'php':'PHP','laravel':'Laravel','mysql':'MySQL','rest api':'REST API','react':'React','docker':'Docker','redis':'Redis','python':'Python','javascript':'JavaScript','typescript':'TypeScript','sql':'SQL','figma':'Figma'};
        const map = new Map();
        skills.map(s=>String(s).trim().replace(/\s+/g,' ')).filter(Boolean).forEach(s=>map.set(s.toLowerCase(),known[s.toLowerCase()]||s));
        return [...map.values()];
    }
    root.ViejobRules = {statuses, applicationEligibility, canWithdraw, pdfError, normalizeSkills};
})(typeof window !== 'undefined' ? window : globalThis);
