# Báo cáo cập nhật gửi email thông báo trực tiếp

**Dự án:** VIEJOB AI  
**Ngày cập nhật:** 21/11/2026

## 1. Mục đích thay đổi

Chuyển email thông báo ứng tuyển từ cơ chế chờ queue sang gửi trực tiếp qua SMTP sau khi dữ liệu được lưu thành công. Người dùng không cần chạy `php artisan queue:work` cho các email thông báo mới.

Giữ nguyên cấu hình SMTP trong `.env`, chức năng quên mật khẩu và các quy tắc ứng tuyển hiện có. Không thay đổi schema 33 bảng hoặc giao diện website.

## 2. Các sự kiện gửi thông báo

| Sự kiện | Thông báo trong ứng dụng | Email gửi trực tiếp |
| --- | --- | --- |
| Candidate nộp hồ sơ | Có | Có |
| Candidate rút đơn | Có | Có |
| Recruiter chuyển sang đang xem xét | Có | Không |
| Recruiter mời phỏng vấn | Có | Có |
| Recruiter đánh dấu trúng tuyển | Có | Có |
| Recruiter từ chối hồ sơ | Có | Có |

Email gửi đến Candidate tương ứng, có tiêu đề tiếng Việt, tên người nhận, tên công việc, doanh nghiệp, trạng thái và liên kết xem hồ sơ cần đăng nhập. Template HTML sử dụng bố cục bảng và escape nội dung động; không đính kèm CV, ghi chú nội bộ hoặc thông tin đăng nhập SMTP.

## 3. Cơ chế xử lý

1. Backend kiểm tra quyền, dữ liệu đầu vào và điều kiện nghiệp vụ hiện có.
2. Lưu hồ sơ hoặc trạng thái cùng lịch sử trong transaction.
3. Sau khi transaction commit thành công, gửi email ngay bằng `notifyNow(..., ['mail'])`.
4. Nếu SMTP gặp lỗi, giữ nguyên dữ liệu đã lưu và ghi log chẩn đoán không chứa nguyên phản hồi SMTP có thể mang thông tin bí mật.

Các kiểm tra chống request lặp và chuyển trạng thái không hợp lệ được giữ nguyên. Luồng mới không tạo job email trong queue. Do gửi trực tiếp, trình duyệt phải chờ SMTP xử lý; email thất bại không được tự động thử lại bằng cơ chế queue.

## 4. File liên quan

| File | Nội dung cập nhật |
| --- | --- |
| `app/Services/CandidateApplicationService.php` | Gửi email nộp/rút đơn trực tiếp sau commit; bắt lỗi SMTP và ghi log an toàn. |
| `app/Services/RecruiterApplicationService.php` | Gửi trực tiếp email phỏng vấn/trúng tuyển/từ chối; đang xem xét chỉ thông báo trong ứng dụng. |
| `app/Notifications/ApplicationReceipt.php` | Chuẩn bị tiêu đề, tên công việc, doanh nghiệp và dữ liệu cho template email. |
| `resources/views/emails/application-receipt.blade.php` | Giao diện email tiếng Việt và liên kết xem hồ sơ. |
| `app/Jobs/SendApplicationReceipt.php` | Giữ tương thích với các tác vụ email cũ; luồng thông báo mới không gọi job này. |

Không đổi `QUEUE_CONNECTION` toàn hệ thống. Các email cũ đang chờ vẫn nằm trong queue; chưa tự gửi lại hoặc xóa. Nếu worker cũ vẫn chạy, nó vẫn có thể xử lý những email này.

## 5. Hướng dẫn người dùng kiểm tra

1. Thực hiện thao tác mới: nộp đơn, rút đơn hoặc chuyển trạng thái sang phỏng vấn, trúng tuyển, từ chối.
2. Kiểm tra Inbox và Spam của Candidate tương ứng; đối chiếu tên công việc, doanh nghiệp và trạng thái.
3. Chuyển sang đang xem xét để xác nhận chỉ có thông báo trong ứng dụng.
4. Kiểm tra liên kết trong email. `APP_URL` phải là địa chỉ website người nhận truy cập được; địa chỉ localhost chỉ phù hợp khi thử trên cùng máy.
5. Nếu không có email, kiểm tra `storage/logs/laravel.log` với thông điệp `Application email delivery failed` hoặc `Application status email delivery failed`. Email gửi trực tiếp thất bại không được ghi vào `failed_jobs`.

Không cần khởi động worker cho email mới. Nếu không muốn worker đang chạy xử lý email tồn đọng, dừng cửa sổ worker bằng Ctrl+C.

## 6. Kết quả và giới hạn xác minh

| Hạng mục | Trạng thái |
| --- | --- |
| Code gửi email trực tiếp | Đã cập nhật |
| Kiểm tra cú pháp PHP của các Service thay đổi | Đạt |
| Kiểm thử tự động | Không chạy theo yêu cầu người dùng |
| Gửi thử email thông báo thực tế | Chưa thực hiện |
| SMTP chấp nhận email thông báo sau thay đổi | Chưa xác minh |
| Candidate nhận email trong Inbox/Spam | Chờ người dùng xác nhận |
| Email quên mật khẩu | Người dùng đã xác nhận gửi được; chức năng giữ nguyên |

Các kiểm thử cũ còn kỳ vọng enqueue/retry thuộc cơ chế trước đây, chưa cập nhật hoặc chạy lại. Việc lưu trạng thái thành công không chứng minh email đã được SMTP chấp nhận hoặc đã đến hộp thư; cần xác nhận riêng khi người dùng tự kiểm tra.
