# Giai đoạn 3.3B — Admin kiểm duyệt tin tuyển dụng

Ngày 18/11/2026. Chỉ triển khai kiểm duyệt; không đổi schema, không seed, không tạo fixture trên db_job.

## 1. Chức năng hoàn thành

- Danh sách phiên bản pending thật: tìm theo tên tin/doanh nghiệp, lọc trạng thái vận hành, phân trang 15 dòng.
- Chi tiết nội dung, kỹ năng, chuyên môn, doanh nghiệp; đối chiếu hai cột với bản đã duyệt.
- Duyệt/từ chối bằng Admin active, lưu quyết định và hiển thị người duyệt/thời điểm/lý do. Recruiter đọc lý do qua màn hình hiện có.
- Không có endpoint chỉnh nội dung pending; Candidate tiếp tục dùng PublicJobService hiện tại.

Đã đọc SHOW CREATE TABLE của jobs, job_versions, job_moderation_logs: published pointer có composite FK cùng job; UNIQUE(job_id,pending_slot); UNIQUE log theo job_version_id; approved/rejected phải có reviewed_at; reject phải có reason ≤2000. Schema đáp ứng đủ, vẫn 32 bảng.

## 2. File và routes

Thêm `AdminJobModerationController`, `JobModerationService`, `ModerateJobRequest`, ba Blade trong `Admin/ad_pages/moderation`, `JobModerationTest`. Tái sử dụng JobPolicy::moderate, layout/Bootstrap và fixtures Jobs.

Sửa routes/admin.php, layout/footer Admin để trang kiểm duyệt thật không chạy script demo; AdminPageTest chuyển kiểm thử queue sang suite thật. RecruiterJobService tách kiểm tra đầy đủ nội dung/danh mục thành validateForReview dùng chung submit/approve, giữ quy tắc cũ. Runner database thêm cờ lọc `--moderation` và từ chối suite rỗng.

| Route | Chức năng |
| --- | --- |
| GET /admin/kiem-duyet | Danh sách pending, tên route admin.jobs.queue giữ nguyên |
| GET /admin/kiem-duyet/{version} | Chi tiết và quyết định đã lưu |
| POST /admin/kiem-duyet/{version}/{approve\|reject} | Quyết định kiểm duyệt |

Routes nằm trong auth/auth.session/role:admin, middleware active và CSRF web hiện có; POST giới hạn 30/phút.

## 3. Quy trình và phiên bản

Transaction khóa User Admin/Recruiter theo id tăng dần → Company → Job → Version; kiểm tra lại quyền, quan hệ sở hữu, pending và updated_at của cả Job/Version. Tranh chấp cùng phiên bản được tuần tự hóa; request cũ/lặp bị từ chối. Retry deadlock tối đa 3 lần.

Approve kiểm tra Company/Recruiter active, role Recruiter, Job không blocked, nội dung/danh mục/kỹ năng còn hợp lệ và deadline chưa qua. Ghi approved/reviewed_at, moderation log với admin_id, published_version_id/published_at/expires_at trong cùng transaction. Expires_at là đầu ngày sau hạn tuyển theo Asia/Ho_Chi_Minh chuyển UTC. Job draft chuyển open; open giữ open; paused/closed giữ nguyên. Tin bị khóa hoặc doanh nghiệp/tài khoản bị khóa không được phê duyệt, nhưng Admin vẫn có thể từ chối kèm lý do.

Reject yêu cầu lý do có nội dung, tối đa 2000 ký tự; ghi rejected/reviewed_at/log, không cập nhật hàng Job, pointer, ngày công khai hoặc ngày hết hạn. V1 còn công khai khi V2 pending/rejected; V2 chỉ thay V1 sau approve. Phiên bản bị từ chối không bị xóa; Recruiter tiếp tục tạo V3 qua logic 3.2.

## 4. Kiểm thử

Lệnh: `php tests/Database/models.php --auth --moderation`. Sáu test mới đúng sáu nhóm yêu cầu, tái sử dụng JobFixtures; chạy cùng regression Recruiter Jobs, Auth/Google/Recovery/Policies và AdminPage. Chỉ dùng MariaDB test riêng; runner đối chiếu schema/lịch sử migration nguồn.

Kết quả: **89 tests đạt, 0 thất bại; 1066 assertions**, gồm sáu test kiểm duyệt mới và regression đã chọn. Có kiểm tra rollback khi ghi log lỗi, locked/paused/closed, danh mục ẩn và deadline hết hạn. Runner xác nhận schema/lịch sử migration nguồn không đổi. JavaScript Admin/Recruiter regression đạt **8/8**; PHP lint, Pint, Blade compile, routes và git diff --check đạt (Git chỉ cảnh báo CRLF/LF). Không chạy stress test/benchmark, không thêm test cho Applications/CV/AI.

## 5. Giới hạn còn lại

Chưa kiểm thử trực quan trình duyệt. So sánh nội dung là hai cột, chưa tô khác biệt theo từng từ. Danh sách/lịch sử tin và các module Admin demo khác giữ nguyên phạm vi cũ; lịch sử thật của quyết định được xem tại URL phiên bản kiểm duyệt và phía Recruiter. Không gửi email mới, không thêm dữ liệu danh mục thật, không triển khai khóa/mở khóa Admin thật trong bước này.

## 6. Kiểm thử thủ công

1. Trên database test riêng, Recruiter tạo tin đầy đủ và gửi duyệt; Admin mở Kiểm duyệt tin, lọc và xem nội dung, nhấn Phê duyệt. Candidate phải xem được V1 nếu đủ điều kiện.
2. Recruiter lưu V2 rồi gửi; Candidate vẫn thấy V1. Admin mở chi tiết đối chiếu và duyệt: Candidate chuyển sang V2.
3. Với V2 khác, thử từ chối bỏ trống lý do rồi nhập lý do hợp lệ: V1 giữ nguyên, Recruiter đọc được lý do và tạo bản nháp mới.
4. Mở hai tab kiểm duyệt cùng phiên bản; xử lý một tab rồi gửi tab còn lại, xác nhận bị chặn. Thử tài khoản Candidate/Recruiter hoặc Admin locked.
5. Kiểm tra paused/closed không tự mở; blocked/Company locked/Recruiter locked, danh mục ẩn và deadline hết hạn không được approve. Không nhập fixture hoặc reset db_job để thử các tình huống này.
