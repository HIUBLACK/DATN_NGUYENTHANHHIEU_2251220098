# Giai đoạn 3.4B — Recruiter quản lý hồ sơ ứng tuyển

Cập nhật ngày 21/11/2026. Không thay schema 33 bảng, không seed/reset `db_job`.

## Hoàn thành

- Danh sách hồ sơ thật thuộc doanh nghiệp active của Recruiter: tìm tên Candidate, lọc tin/trạng thái, phân trang 15 dòng, hiển thị ngày nộp và lượt ứng tuyển. Giữ cả đơn đã rút và từng lượt ứng tuyển riêng.
- Chi tiết Candidate, thư giới thiệu, phiên bản tin được ứng tuyển, PDF snapshot, lịch sử xử lý và ghi chú nội bộ. Không dùng CV hiện tại thay cho snapshot.
- Ghi chú tối đa 2.000 ký tự, thêm/sửa qua kiểm tra quyền; sửa có token thời điểm để chặn ghi đè bản cũ. Không đưa ghi chú vào lịch sử/thông báo Candidate. Quyền Admin giám sát giữ theo Policy hiện có, không thêm endpoint Admin.
- Giữ layout, sidebar/header, CSS, khung bảng, card, badge, iframe PDF và modal xác nhận cũ. Thay phần render demo bằng Blade thật. Bộ lọc/cột AI giữ vị trí nhưng vô hiệu hóa và ghi rõ chưa triển khai, không dùng điểm giả.

## File và routes

Thêm `RecruiterApplicationController`, `RecruiterApplicationService`, `RecruiterApplicationRequest`, `public/recruiter/js/application-actions.js`, `tests/Feature/RecruiterApplicationsTest.php`.

Cập nhật `routes/recruiter.php`, `resources/views/employer/{applications,application-detail,layout}.blade.php`, `public/recruiter/js/recruiter.js` (không cho script demo ghi đè hai trang thật), `ApplicationReceipt` (nội dung email theo trạng thái), `RecruiterPageTest` và runner `tests/Database/models.php`.

Tái sử dụng `ApplicationPolicy`, `ApplicationNote`, `ApplicationStatusHistory`, `SendApplicationReceipt`, Notifications/Queue và fixtures PDF/Jobs của 3.4A.

| Method | Route | Chức năng |
| --- | --- | --- |
| GET | `/nha-tuyen-dung/quan-ly/ho-so` | Danh sách; giữ tên `recruiter.applications` |
| GET | `/nha-tuyen-dung/quan-ly/ho-so/chi-tiet?id={UUID}` | Chi tiết; giữ cách điều hướng hiện có |
| GET | `/nha-tuyen-dung/quan-ly/ho-so/{UUID}/pdf` | Xem snapshot; `?download=1` để tải |
| POST | `/nha-tuyen-dung/quan-ly/ho-so/{UUID}/trang-thai` | Chuyển trạng thái |
| POST | `/nha-tuyen-dung/quan-ly/ho-so/{UUID}/ghi-chu` | Thêm/sửa ghi chú |

Các route dùng nhóm auth/session/role Recruiter hiện có; POST có CSRF, throttle và session blocking. Controller truy vấn theo chủ sở hữu, Policy kiểm tra lại quyền.

## Trạng thái, snapshot và thông báo

- Giữ quy tắc đã chốt: `applied → reviewing/rejected`; `reviewing → interview/rejected`; `interview → hired/rejected`. Không chuyển tiếp `withdrawn/hired/rejected`.
- Transaction khóa User theo ID → Company → Job → Application, kiểm tra lại quyền và token `expected_status`/`updated_at`. Request lặp/cũ hoặc bước chuyển sai bị chặn trước khi ghi. Lịch sử lưu trạng thái cũ/mới, Recruiter và thời điểm cùng transaction. Không thay application identity, JobVersion, CV revision hoặc snapshot.
- Mọi lần đọc PDF kiểm tra quyền; chỉ trả đúng đường dẫn snapshot lưu trên application qua private storage, kèm `private, no-store`/`nosniff`/CSP sandbox. Không cấp URL public.
- `reviewing`: thông báo trong ứng dụng. `interview/hired/rejected`: thông báo trong ứng dụng và email qua job queue sẵn có. Đăng ký `afterCommit`, rollback không gửi thông báo. Không đổi luồng Candidate nộp/rút đơn.
- Lỗi gửi thông báo hoặc enqueue email được ghi log, không hoàn tác trạng thái đã commit. Job email giữ retry 3 lần. Chạy `php artisan queue:work --tries=3` để xử lý email thật khi sẵn sàng.

## Kiểm thử

Lệnh: `php tests/Database/models.php --auth --recruiter-applications`.

Sáu test mới: quyền sở hữu/bộ lọc; PDF snapshot riêng tư; chuyển trạng thái và lịch sử; chặn trạng thái sai/lặp/cũ/đã rút/kết thúc; ghi chú riêng tư và XSS; thông báo sau commit/rollback, không trùng và lỗi queue. Chạy cùng regression Candidate Applications, Recruiter Jobs, Policies, Authentication và Recruiter pages. Fixtures chỉ nằm trong MariaDB test riêng, tự dọn sau chạy.

Kết quả: **73 test đạt, 877 assertions, 0 thất bại** (6 test mới và regression liên quan). Runner xác nhận schema/lịch sử migration nguồn không đổi. PHP lint, JavaScript syntax, Pint và biên dịch Blade đạt; `git diff --check` không có lỗi khoảng trắng (chỉ cảnh báo CRLF/LF).

## Vấn đề còn lại và kiểm tra thủ công

- Chưa kiểm tra trực quan bằng trình duyệt thật hoặc gửi SMTP thật. Blade render, cú pháp PHP/JavaScript và nghiệp vụ được kiểm tra tự động; không thay CSS global.
- Nếu lưu thông báo hoặc enqueue thất bại sau commit, cần xử lý vận hành từ log; không bổ sung outbox/bảng mới. Retry email tự động áp dụng khi job đã vào queue.
- Dashboard/thông báo Recruiter và các module demo ngoài phạm vi giữ nguyên; không triển khai AI hay Recruiter chỉnh CV.

Kiểm tra thủ công: đăng nhập Recruiter sở hữu tin, lọc và mở hồ sơ; xem/tải PDF, chuyển lần lượt xem xét → phỏng vấn → trúng tuyển hoặc từ chối, mở tab cũ để xác nhận bị chặn. Thêm/sửa ghi chú rồi đăng nhập Candidate để xác nhận chỉ thấy trạng thái/thông báo, không thấy ghi chú. Với Recruiter khác, URL chi tiết/PDF và POST xử lý phải bị chặn. Thử đơn đã rút vẫn có trong danh sách nhưng không có nút chuyển trạng thái.
