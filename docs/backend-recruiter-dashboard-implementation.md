# Dashboard và thông báo Nhà tuyển dụng — Giai đoạn 3.5C

Cập nhật ngày 21/11/2026.

## Chức năng hoàn thành

- Dashboard lấy dữ liệu MariaDB của doanh nghiệp đang hoạt động thuộc Recruiter đăng nhập; không có doanh nghiệp hợp lệ thì hiển thị 0.
- Giữ layout, cards, biểu đồ và CSS hiện tại. Thêm danh sách 5 lượt ứng tuyển gần đây bằng thành phần giao diện sẵn có.
- Trang thông báo dùng dữ liệu thật, lọc tất cả/chưa đọc/nộp đơn/rút đơn, phân trang 15 mục, đánh dấu một hoặc tất cả đã đọc.
- Chuông header giữ dấu báo hiện tại; trạng thái dấu báo lấy từ số thông báo chưa đọc, số lượng có trong nhãn trợ năng và trang thông báo.
- Không thay đổi schema, SMTP, quy tắc ứng tuyển hoặc kiểm duyệt; không seed dữ liệu.

## Cách tính

- Tổng tin đếm bản ghi `jobs`, không đếm `job_versions`.
- Các nhóm hoạt động lấy từ trạng thái Job. Tin `open` hết hạn được tách thành nhóm hết hạn và không cộng vào thẻ đang mở. Tin thiếu `expires_at` cũng không tính là đang mở còn hạn.
- Chờ duyệt đếm tin có phiên bản `pending`; từ chối đếm tin có phiên bản mới nhất `rejected`. Đây là chiều kiểm duyệt riêng, không cộng vào tổng nhóm hoạt động.
- Tổng hồ sơ đếm mỗi bản ghi `applications`, gồm đơn đã rút; ứng tuyển lại là một lượt riêng. Cards và biểu đồ trạng thái lấy trạng thái hiện tại của từng lượt, không phải số ứng viên duy nhất hoặc tỷ lệ chuyển đổi.
- Truy vấn tổng hợp ở database; danh sách gần đây eager-load Candidate và phiên bản đã ứng tuyển.

## Thông báo và phân quyền

`CandidateApplicationService` tạo `RecruiterApplicationNotice` qua kênh database trong transaction nộp/rút đơn hiện có. Recipient là Recruiter sở hữu doanh nghiệp của tin. Cơ chế khóa, kiểm tra lượt và trạng thái hiện có chặn thao tác lặp; rollback nghiệp vụ cũng rollback thông báo.

Thông báo lưu UUID hồ sơ, tên công việc, tên ứng viên, số lượt và sự kiện. Không chứa CV, đường dẫn file, ghi chú nội bộ hoặc email. Liên kết tới trang hồ sơ có kiểm tra quyền hiện hành, kể cả hồ sơ đã rút. Không thêm email Recruiter và không đổi gửi email Candidate trực tiếp.

Mọi truy vấn đọc/đánh dấu giới hạn qua quan hệ notifications của người đăng nhập và loại thông báo này. Routes dùng middleware Recruiter active hiện có, POST có CSRF. Không tạo lại thông báo cho các đơn lịch sử trước khi triển khai.

## File và routes

- Mới: `app/Services/RecruiterDashboardService.php`, `app/Notifications/RecruiterApplicationNotice.php`, `resources/views/employer/component/stat-cards.blade.php`.
- Backend: `RecruiterPageController`, `CandidateApplicationService`, `AppServiceProvider` (số chưa đọc header), `routes/recruiter.php`.
- Blade: `employer/dashboard`, `employer/notifications`, `employer/layout`, `employer/component/header`.
- JavaScript: `public/recruiter/js/recruiter.js`, `recruiter-charts.js`; ngừng khởi tạo dữ liệu demo trên Dashboard/Thông báo, giữ cách vẽ biểu đồ cũ.
- Giữ routes GET Dashboard và Thông báo. Thêm POST `/nha-tuyen-dung/quan-ly/thong-bao/{id}/da-doc`, tên `recruiter.notification-read`; `{id}` là UUID thông báo hoặc `all`.
- Test: `tests/Feature/RecruiterDashboardTest.php`, bộ chạy `tests/Database/models.php`.

## Kiểm thử

Lệnh: `php tests/Database/models.php --auth --recruiter-dashboard`.

**50 tests đạt, 0 thất bại, 517 assertions**, gồm 5 nhóm test mới và regression Auth/Policies/Recruiter pages. Kiểm tra số liệu, phạm vi sở hữu, nộp/rút không trùng thông báo, rollback, đọc/đánh dấu/IDOR, dữ liệu trống và chặn ứng tuyển tin tạm dừng. Dùng MariaDB test riêng; bộ chạy xác nhận schema và lịch sử migration database chính không thay đổi.

Kiểm tra thêm cú pháp JavaScript, biên dịch Blade và whitespace diff. Không gửi email thật khi kiểm thử.

## Kiểm tra thủ công và giới hạn

1. Dùng Candidate nộp đơn hợp lệ; mở Dashboard của Recruiter sở hữu tin, kiểm tra tổng hồ sơ, hồ sơ mới và chuông thông báo.
2. Mở thông báo, truy cập đúng hồ sơ; thử lọc chưa đọc, đánh dấu một và tất cả.
3. Rút đơn rồi ứng tuyển lại theo quy tắc hiện có; kiểm tra từng lượt riêng và thông báo tương ứng.
4. Đăng nhập Recruiter khác để kiểm tra không thấy các số liệu/thông báo trên.

Chưa kiểm tra trực quan trên trình duyệt ở nhiều kích thước màn hình; đã giữ cấu trúc/classes và kiểu biểu đồ hiện có. Thông báo lịch sử không được backfill. Không có thay đổi schema cần phê duyệt.
