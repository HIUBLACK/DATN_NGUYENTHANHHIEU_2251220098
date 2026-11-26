# Giai đoạn 3.5A — Candidate lưu việc làm yêu thích

Ngày cập nhật: 21/11/2026. Giữ schema 33 bảng, không seed/reset database chính.

## Chức năng hoàn thành

- Candidate active lưu/bỏ lưu tin thật vào `saved_jobs`. Trạng thái trái tim trên danh sách, chi tiết và trang đã lưu lấy từ database của tài khoản đang đăng nhập.
- Giữ layout, card, icon, font, màu và Bootstrap hiện có. Guest có liên kết đăng nhập; Recruiter/Admin không có quyền lưu. Tin demo không thể dùng nút lưu để ghi vào database thật.
- Trang đã lưu phân trang 10 tin, hiển thị công việc, doanh nghiệp, vị trí, tỉnh/thành, địa điểm chi tiết, lương, ngày lưu, trạng thái và thao tác phù hợp. Giao diện cũ không có bộ tìm kiếm/lọc nên không bổ sung ngoài phạm vi.

## File và routes

Thêm `SavedJobController`, `SavedJobRequest`, `SavedJobService`, `SavedJobPolicy` và Blade dùng chung `Candidate/save-job-button.blade.php`.

Cập nhật `CandidatePageController`, `routes/web.php`, các Blade Candidate `search-job-card`, `job-row`, `master`, `ca_pages/job`, `ca_pages/job-demo`, `ca_pages/saved`. `public/jobboard/js/candidate.js` tải lại trạng thái khi trình duyệt khôi phục trang từ back/forward cache. Không dùng localStorage/sessionStorage làm nguồn lưu việc làm.

| Route | Chức năng |
| --- | --- |
| `GET /ung-vien/da-luu` | Trang đã lưu hiện có, dữ liệu MariaDB thật |
| `POST /ung-vien/luu/{id}` — `candidate.save` | `saved=1` lưu; `saved=0` bỏ lưu |
| `DELETE /ung-vien/luu/{id}` — `candidate.unsave` | Bỏ lưu, kể cả tin đã bị ẩn |

Các route ghi dùng middleware auth/session/role Candidate, CSRF và session blocking hiện có. Backend chỉ dùng Candidate từ tài khoản đăng nhập, không nhận `candidate_id` từ client.

## Quy tắc lưu và hiển thị

- Thao tác biểu thị trạng thái mong muốn, không toggle mù: gửi lại request lưu không sinh bản ghi trùng, gửi lại bỏ lưu không tạo lại bản ghi. Dùng transaction, khóa hàng User/Company/Job theo thứ tự tương thích các luồng hiện có; UNIQUE(candidate_id,job_id) là bảo vệ bổ sung.
- Lưu mới yêu cầu đúng Policy công khai: tin mở, phiên bản đã duyệt, còn hạn, doanh nghiệp và Recruiter hợp lệ. Bỏ lưu chỉ xóa bản ghi thuộc Candidate hiện tại, không yêu cầu tin còn công khai.
- Tin đóng/tạm dừng/hết hạn vẫn giữ bookmark và metadata cơ bản của phiên bản published đã approved. Không truyền mô tả/yêu cầu/quyền lợi vào card đã lưu; không có liên kết chi tiết/ứng tuyển khi tin không còn công khai.
- Tin blocked/draft, mất phiên bản approved hoặc doanh nghiệp/Recruiter không hợp lệ: chỉ hiện thông báo không khả dụng, ngày lưu và nút bỏ lưu; không hiện tiêu đề hay nội dung tin.
- V2 pending không thay V1 đang published; khi published pointer chuyển sang V2 approved, trang đã lưu hiển thị V2. Không thay đổi JobVersion, trạng thái tin, PublicJobService hay nghiệp vụ ứng tuyển/kiểm duyệt/email.

## Kiểm thử

Lệnh: `php tests/Database/models.php --auth --saved-jobs`.

`CandidateSavedJobsTest` có đúng 5 nhóm: lưu/bỏ lưu idempotent và nút đồng bộ; quyền/Guest/IDOR; danh sách riêng từng Candidate; tin đóng/hết hạn/blocked; published V1/V2. Tái sử dụng JobFixtures, chạy cùng regression Candidate pages, Jobs, Admin Moderation, Policies và Authentication trên MariaDB test riêng.

Kết quả: **72 test đạt, 822 assertions, 0 thất bại**. Runner xác nhận schema và lịch sử migration nguồn không đổi. PHP lint, Pint, biên dịch Blade, cú pháp JavaScript và `git diff --check` đạt (Git chỉ cảnh báo CRLF/LF).

## Vấn đề còn lại

- Chưa kiểm tra trực quan bằng trình duyệt thật; không thay CSS global hoặc template Recruiter/Admin.
- Các tab đã mở không nhận cập nhật realtime; tải lại hoặc điều hướng để đọc trạng thái mới. Khi quay lại trang từ back/forward cache, trang tự tải lại nếu có nút lưu.
- Không thêm AI recommendation hoặc dữ liệu demo. Các module demo khác giữ nguyên phạm vi.
