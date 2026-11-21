# Quản lý CV và ứng tuyển — Giai đoạn 3.4A

Cập nhật: 21/11/2026.

## Chức năng đã triển khai

- Candidate tải lên tối đa 10 CV đang hoạt động, chỉ PDF hợp lệ tối đa 5 MB. Kiểm tra MIME, phần mở rộng, cấu trúc PDF và trang; không phân tích AI/OCR. Bổ sung `smalot/pdfparser` để kiểm tra cấu trúc, có giới hạn giải nén và độ sâu lồng nhau.
- Xem/tải CV qua controller kiểm tra chủ sở hữu; tệp nằm trong `storage/app/private`, không trả URL public. Đổi tên, thay PDF, chọn CV chính và xóa mềm CV. Thay PDF tạo revision mới; giữ revision cũ.
- CV đầu tiên tự làm CV chính. Nếu xóa CV chính khi còn CV khác, phải chỉ định CV thay thế thuộc cùng Candidate. Khóa hàng User trong transaction để tuần tự hóa thao tác và giữ giới hạn 10 CV/cơ chế CV chính.
- Trang tin thật có modal chọn CV và xác nhận nộp. Gửi kèm phiên bản tin và số lượt; backend kiểm tra lại, không tin dữ liệu từ trình duyệt. Trang tin demo không cho nộp hồ sơ thật.
- Danh sách và chi tiết việc làm đã ứng tuyển, lịch sử trạng thái, xem/tải đúng PDF đã nộp; xác nhận trước khi rút đơn.
- Thông báo trong ứng dụng được lưu cùng transaction. Email được đưa vào queue sau khi lưu thành công, thử tối đa 3 lần. Lỗi đưa email vào queue không hoàn tác đơn; có log cảnh báo không chứa CV/token.

## Quy tắc và dữ liệu

- Chỉ Candidate active và tin đủ điều kiện công khai theo JobPolicy hiện có: phiên bản đúng tin đã duyệt, đang mở, còn hạn, doanh nghiệp và Recruiter hợp lệ. Vị trí công việc, tỉnh/thành và chuyên môn tiếp tục lấy từ phiên bản tin thật hiện có.
- Lượt đầu là 1. Chỉ được nộp lượt 2 nếu lượt 1 đã rút; không có lượt 3. Request cũ mang lượt/phiên bản khác hiện tại bị từ chối. Transaction khóa User theo ID, Company, Job, phiên bản và CV; dùng các constraint hiện có, không thêm schema.
- Mỗi application giữ JobVersion, resume revision và một bản PDF snapshot riêng kèm SHA-256/kích thước/tên tệp. Thay hoặc xóa mềm CV không thay snapshot hay lịch sử ứng tuyển.
- Rút đơn chỉ khi `applied`, `reviewing`, `interview`. `hired`, `rejected`, `withdrawn` không rút tiếp. Mọi lần nộp/rút tạo `application_status_histories`.
- Thay CV/chọn CV chính/xóa CV tăng generation để kết quả recommendation cũ có thể bị nhận diện là lỗi thời; không chạy AI.

## File và routes

- Nghiệp vụ: `app/Services/CandidateResumeService.php`, `CandidateApplicationService.php`.
- Điều phối/validation: `CandidateDocumentController`, cập nhật `CandidatePageController`; `CandidateApplyRequest`, `CandidateResumeActionRequest`, tái sử dụng `ResumeUploadRequest` và các Policy hiện có.
- Email/thông báo: `app/Jobs/SendApplicationReceipt.php`, `app/Notifications/ApplicationReceipt.php`.
- `routes/web.php`: nối các routes CV upload/replace/action/pdf, apply/withdraw/read sang backend thật; thêm `candidate.application-detail`, `candidate.snapshot`. Các routes nằm trong nhóm Candidate đã xác thực; tệp/đơn dùng UUID và kiểm tra chủ sở hữu.
- Blade Candidate: `ca_pages/resumes`, `applications`, `application-detail`, `job`, `job-demo`, `notifications`; `real-apply-modal`, `notification-panel`, `master`. JavaScript khóa gửi lặp: `public/jobboard/js/application-submit.js`.
- Dependency: `composer.json`, `composer.lock`.
- Kiểm thử: `CandidateApplicationsTest`, `Support/PdfFixtures`, cập nhật `AuthenticationTest`, `CandidatePreviewTest` (bỏ các assertion demo đã được thay bằng kiểm thử dữ liệu thật), bộ chạy `tests/Database/models.php`.

## Kiểm thử

Lệnh: `php tests/Database/models.php --auth --candidate-applications`.

Sáu nhóm kiểm thử CV/ứng tuyển bao gồm giới hạn PDF/CV chính, quyền sở hữu, snapshot/JobVersion, chặn tin không hợp lệ và gửi lặp, rút đơn/giới hạn ứng tuyển lại, bảo toàn CV đã nộp. Có kiểm tra Recruiter bị khóa, đọc thông báo và lỗi đưa email vào queue. Chạy cùng regression Jobs, Admin Moderation, Policies, Authentication và trang Candidate.

Kết quả cuối cùng: **73 test đạt, 854 assertions, 0 thất bại** (gồm 6 test nghiệp vụ mới và regression liên quan). Kiểm tra cú pháp PHP, JavaScript, biên dịch Blade, Laravel Pint cho các file mới và `composer validate` đều đạt.

Bộ chạy tạo MariaDB test riêng có tên ngẫu nhiên, kiểm tra schema tương thích với database chính, chỉ tạo fixture trong database test và xóa database test khi kết thúc. Không reset, seed hay thay schema `db_job`.

## Kiểm tra thủ công và vận hành

1. Đăng nhập Candidate, vào CV: tải PDF, đổi tên, chọn CV chính, thay PDF; thử tệp sai và xóa CV chính chưa chọn thay thế.
2. Mở tin thật đang nhận hồ sơ, chọn CV trong modal và xác nhận. Kiểm tra danh sách, thông báo và PDF đã nộp.
3. Thay/xóa CV gốc, kiểm tra PDF trong hồ sơ cũ còn nguyên. Rút lượt đầu, nộp lại; sau rút lượt hai không nộp được nữa.
4. Đăng nhập Candidate khác, thử URL CV/snapshot cũ: bị từ chối. Kiểm tra tin paused/closed/blocked không nhận đơn.
5. Để xử lý email với cấu hình database queue hiện tại, chạy `php artisan queue:work --tries=3`. Chỉ chạy worker khi sẵn sàng xử lý hàng đợi thật. Chưa gửi email SMTP thật trong kiểm thử; dùng queue giả/mailer kiểm thử.

Giới hạn: chưa kiểm tra trực quan bằng trình duyệt thật; PDF được kiểm tra cấu trúc, không có quét antivirus. Revision và snapshot được giữ lại, chưa triển khai chính sách dọn/xóa dữ liệu cá nhân. Nếu hạ tầng queue lỗi ngay lúc enqueue, hồ sơ vẫn thành công nhưng email cần xử lý vận hành; retry tự động áp dụng cho tác vụ đã vào queue. Các module hồ sơ cá nhân, AI, việc làm đã lưu còn demo được giữ nguyên phạm vi. Không triển khai Recruiter xử lý hồ sơ, AI hoặc thêm bảng/migration trong giai đoạn này.
