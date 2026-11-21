# Email thông báo ứng tuyển

Cập nhật 21/11/2026 theo phạm vi mới: chỉ hoàn thiện code email thông báo, người dùng tự kiểm thử. Giữ nguyên `.env`, cấu hình SMTP và chức năng quên mật khẩu đang hoạt động.

## Đã cập nhật

- Theo yêu cầu mới, gửi trực tiếp bằng Notification sau commit cho nộp hồ sơ, rút đơn, mời phỏng vấn, trúng tuyển và từ chối. Không cần queue worker cho các email mới. `reviewing` chỉ thông báo trong ứng dụng.
- Email tiếng Việt có tên người nhận, công việc, doanh nghiệp, trạng thái và liên kết hồ sơ cần đăng nhập. Template HTML dạng bảng, nội dung động được Blade escape; không đính kèm CV hoặc ghi chú nội bộ.
- Giữ kiểm tra request lặp hiện có. Lỗi SMTP được bắt sau commit, không hoàn tác hồ sơ/trạng thái; log chỉ ghi mã hồ sơ, không ghi nguyên phản hồi SMTP. Gửi trực tiếp không có retry tự động; request sẽ chờ SMTP hoàn tất hoặc báo lỗi.
- Giữ class `SendApplicationReceipt` để tương thích các tác vụ cũ; luồng mới không enqueue job này. Không tự xử lý hoặc xóa email cũ trong queue, không đổi `QUEUE_CONNECTION` cho các chức năng khác.

File: `ApplicationReceipt`, `SendApplicationReceipt`, `CandidateApplicationService`, `RecruiterApplicationService`, `resources/views/emails/application-receipt.blade.php`. Không thay database hay giao diện website.

## Người dùng tự kiểm tra

1. Không cần chạy worker để gửi email thông báo mới. Nếu worker cũ đang mở và không muốn gửi email tồn đọng, dừng cửa sổ đó bằng Ctrl+C.
2. Thực hiện các thao tác ứng tuyển/rút đơn/chuyển trạng thái, kiểm tra Inbox và Spam của Candidate. `reviewing` không có email.
3. Đảm bảo `APP_URL` là địa chỉ website người nhận truy cập được; localhost chỉ dùng thử trên cùng máy.
4. Nếu email mới không gửi được, kiểm tra log `Application email delivery failed` hoặc `Application status email delivery failed` trong `storage/logs/laravel.log`. Email gửi trực tiếp không xuất hiện trong `failed_jobs`.

## Trạng thái bàn giao

- Code email thông báo: đã cập nhật; chỉ kiểm tra cú pháp theo quy định dự án.
- Kiểm thử tự động và gửi email thử: không chạy theo yêu cầu người dùng.
- SMTP chấp nhận email thông báo mới: chưa xác minh.
- Inbox/Spam email thông báo mới: chờ người dùng xác nhận. Việc quên mật khẩu gửi được là thông tin người dùng đã xác nhận, không thay thế kiểm chứng các email thông báo.
- Không chạy worker, không gửi lại các tác vụ cũ. Các kiểm thử cũ có assertion enqueue/retry phản ánh cơ chế queue trước thay đổi này; chưa cập nhật/chạy kiểm thử theo phạm vi người dùng yêu cầu tự kiểm tra.
