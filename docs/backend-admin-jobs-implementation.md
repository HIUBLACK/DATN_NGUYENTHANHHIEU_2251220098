# Admin quản lý tin tuyển dụng — Giai đoạn 3.5E

Cập nhật ngày 22/11/2026.

## Chức năng hoàn thành

- Danh sách tin tuyển dụng lấy từ MariaDB thật, phân trang 15 mục.
- Tìm kiếm theo tiêu đề hoặc doanh nghiệp; lọc riêng trạng thái vận hành của Job và trạng thái kiểm duyệt của phiên bản mới nhất.
- Hiển thị doanh nghiệp, Recruiter, ngày tạo/cập nhật, hạn bản công khai, phiên bản mới nhất và `published_version_id` dưới dạng phiên bản công khai.
- Trang chi tiết cho xem nội dung phiên bản được chọn, bản đang công khai, vị trí, chuyên môn, kỹ năng, tỉnh/thành, địa điểm, lương, hình thức, hạn ứng tuyển và các trường nội dung khác.
- Có bảng phiên bản, lịch sử kiểm duyệt và lịch sử vận hành/chặn tin. Không cho Admin sửa nội dung Job hoặc JobVersion.
- Nút Chặn/Mở chặn dùng modal hiện có, bắt buộc lý do và thông báo nổi chung của hệ thống.

Giao diện Admin hiện tại, bảng, badge, modal, sidebar, header, Bootstrap và CSS được giữ nguyên. JavaScript demo không còn được dùng trên các trang Jobs thật.

## Quy tắc Chặn/Mở chặn

- Admin active mới được thao tác; Candidate, Recruiter, Guest và Admin bị khóa đều bị chặn ở middleware/policy.
- Chặn chuyển Job sang `blocked`, lưu trạng thái trước đó vào `jobs.status_before_block`, giữ nguyên JobVersion, published pointer, Application và CV snapshot.
- Audit `activity_logs` ghi `job.block`/`job.unblock`, actor, trạng thái cũ/mới, lý do và thời điểm trong cùng transaction.
- Mở chặn phục hồi trạng thái trước khi chặn. Nếu trạng thái cũ là `open` nhưng tin hết hạn, doanh nghiệp hoặc Recruiter không còn active, hoặc bản công khai không còn đạt PublicJobPolicy thì chuyển về `paused`. Tin `closed` không tự mở lại thành `open`.
- Mở chặn không duyệt phiên bản pending/rejected, không xuất bản phiên bản mới và không thay `published_version_id`.
- Row lock, kiểm tra `updated_at`, trạng thái hiện tại và ID audit cuối cùng ngăn request cũ, gửi lặp hoặc thao tác đồng thời. Lý do trống hoặc chỉ có khoảng trắng bị từ chối.

## Bảo vệ phiên bản và lịch sử

`AdminJobService` luôn lấy các phiên bản theo đúng `job_id`. Bản công khai được tải qua `publishedVersion`; phiên bản đang xem có thể chọn bằng `?version=` và không thể lấy phiên bản của tin khác. Lịch sử kiểm duyệt lấy từ `job_moderation_logs`, còn khóa/mở chặn lấy từ `activity_logs`; hai loại lịch sử không bị gộp.

Candidate tiếp tục chỉ thấy tin qua `PublicJobService`. Khi tin bị chặn, tin không xuất hiện công khai và không thể ứng tuyển mới. Khi mở chặn, điều kiện công khai được kiểm tra lại; dữ liệu cũ và các lượt ứng tuyển đã lưu không bị xóa.

## File và routes

- Backend: `AdminJobController`, `AdminJobService`, `AdminJobStatusRequest`, `routes/admin.php`.
- Blade: `Admin/ad_pages/jobs/index`, `show`, `history`, `actions`, `histories`.
- JavaScript dùng modal: `public/admin-assets/js/admin-management.js`.
- Test: `tests/Feature/AdminJobsTest.php`; bộ chạy thêm cờ `--admin-jobs` trong `tests/Database/models.php`.

Routes GET Jobs cũ được giữ nguyên. Thêm PATCH `admin.jobs.status` tại `/admin/tin-tuyen-dung/{job}/trang-thai`; route yêu cầu CSRF, throttle và middleware Admin active của nhóm route hiện có.

## Kiểm thử

Lệnh:

`php tests/Database/models.php --auth --admin-jobs`

Kết quả: **35 tests đạt, 551 assertions, 0 thất bại** trên MariaDB test riêng. Bộ test kiểm tra danh sách/tìm kiếm/lọc/phân trang; chọn đúng phiên bản và lịch sử; Chặn bắt buộc lý do và ẩn tin khỏi Candidate; Mở chặn an toàn với tin hết hạn/doanh nghiệp hoặc Recruiter bị khóa; không tự duyệt/xuất bản; chống request cũ/lặp; phân quyền và bảo toàn Application/CV snapshot. Schema và lịch sử migration của `db_job` không thay đổi.

Đã kiểm tra Blade cache, JavaScript syntax và `git diff --check`.

## Vấn đề còn lại

Chưa kiểm tra trực quan trên trình duyệt ở nhiều kích thước màn hình. Không thay đổi schema, không seed dữ liệu và không chạy migration. Trang kiểm duyệt Admin 3.3B vẫn là luồng duyệt/từ chối riêng; Giai đoạn 3.5E chỉ liên kết tới luồng đó.
