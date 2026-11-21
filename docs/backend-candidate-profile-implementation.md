# Giai đoạn 3.5B — Hồ sơ cá nhân Candidate

Cập nhật: 21/11/2026. Giữ schema 33 bảng và giao diện Candidate hiện có.

## Chức năng đã làm

- Trang hồ sơ và avatar đọc dữ liệu thật của tài khoản đăng nhập; cập nhật vào `users`/`candidate_profiles`. Không lấy dữ liệu demo hoặc localStorage/sessionStorage làm nguồn chính.
- Giữ form hai cột, Bootstrap, màu/font, header và khung avatar 80 × 80 hiện có. Email chỉ đọc; hiển thị phương thức đăng nhập dựa trên liên kết Google và `has_local_password`.
- Backend chỉ cho Candidate active cập nhật hồ sơ của mình. Không nhận ID chủ sở hữu từ client; từ chối các trường role, trạng thái, email, mật khẩu, Google identity và đường dẫn avatar. Dữ liệu nhập hợp lệ được giữ bằng `old()` khi validation thất bại; tệp cần chọn lại theo cơ chế trình duyệt.
- Avatar JPG/PNG/WebP tối đa 2 MB, 5000 × 5000 pixel. Kiểm tra MIME/đuôi/kích thước, giải mã và mã hóa lại bằng GD để bỏ metadata/dữ liệu nối thêm; tên tệp UUID, lưu private storage.
- Cho thay/xóa ảnh khi lưu form. Transaction khóa User và Profile; lỗi lưu database dọn ảnh mới, giữ ảnh cũ. Chỉ xóa ảnh cũ sau commit nếu đúng thư mục của Candidate và không còn profile tham chiếu. Lỗi dọn file được ghi log, không làm thất bại hồ sơ đã lưu.

## Trường dữ liệu hỗ trợ

| Trường giao diện | Cột database | Quy tắc |
| --- | --- | --- |
| Họ tên | `users.name` | Bắt buộc, tối đa 255 ký tự |
| Email | `users.email` | Chỉ đọc |
| Điện thoại | `candidate_profiles.phone` | Tối đa 20 ký tự, 8–15 chữ số và dấu phân cách hợp lệ |
| Địa chỉ / Nơi ở | `candidate_profiles.location` | Tối đa 100 ký tự |
| Vị trí chuyên môn | `candidate_profiles.professional_title` | Tối đa 100 ký tự |
| Kinh nghiệm | `candidate_profiles.experience_summary` | Tối đa 100 ký tự |
| Giới thiệu bản thân | `candidate_profiles.summary` | Tối đa 1500 ký tự |
| Ảnh đại diện | `candidate_profiles.avatar_path` | Đường dẫn do server tạo, không nhận chuỗi đường dẫn từ form |

Schema hỗ trợ toàn bộ trường hiện có. Không bổ sung trường ngoài form hoặc thay đổi OAuth, quên mật khẩu, thiết lập mật khẩu Google, CV, Applications hay SMTP. GET không tự tạo profile còn thiếu; lần lưu hợp lệ đầu tiên tạo profile thuộc tài khoản hiện tại.

## File và routes

- Thêm `CandidateProfileController`, `CandidateProfileService`, `CandidateProfilePolicy`.
- Tái sử dụng/cập nhật `CandidateProfileRequest`, `CandidatePageController`, `routes/web.php`, Blade `Candidate/ca_pages/profile`, `Candidate/input`, `Candidate/master`.
- Tái sử dụng bộ kiểm tra/mã hóa ảnh `CompanyImageStorage`: thêm tham số thư mục tùy chọn, mặc định `companies` giữ nguyên. Avatar dùng `candidate-avatars/{user_id}/avatar/{UUID}.{ext}`; không đổi hành vi upload doanh nghiệp hoặc UI Recruiter/Admin.
- Giữ các route: `GET /ung-vien/ho-so`, `POST /ung-vien/ho-so`, `GET /ung-vien/anh-dai-dien`. POST có CSRF, giới hạn 20 lần/phút và session blocking; route không nhận ID Candidate. Avatar chỉ trả ảnh của chủ phiên đăng nhập với cache private/no-store và nosniff; dùng ảnh mặc định khi chưa có ảnh hợp lệ.

## Kiểm thử

Lệnh: `php tests/Database/models.php --auth --candidate-profile`.

Năm nhóm mới trong `CandidateProfileTest`: đọc đúng hồ sơ; lưu và tải lại; validation/mass assignment; phân quyền/IDOR; avatar hợp lệ/giả mạo/giới hạn dung lượng-kích thước/quyền đọc/thay-xóa/rollback. Tái sử dụng test helpers và chạy cùng regression Auth, Google, khôi phục mật khẩu, trang Candidate và việc làm đã lưu. Mail/Google trong regression được giả lập, không gọi dịch vụ thật.

Kết quả: **75 test đạt, 814 assertions, 0 thất bại**. PHP lint, Pint, biên dịch Blade và `git diff --check` đạt (Git chỉ cảnh báo CRLF/LF). Mọi fixture nằm trong MariaDB test riêng; runner xác nhận schema và lịch sử migration nguồn không đổi, không reset/seed `db_job`.

## Giới hạn còn lại

- Chưa kiểm tra trực quan bằng trình duyệt thật. Không thay CSS global hoặc thêm thư viện frontend.
- Máy chủ PHP phải bật GD để xử lý ảnh; nếu thiếu GD, upload trả lỗi tiếng Việt, vẫn có thể lưu hồ sơ không kèm ảnh.
- Nếu storage lỗi khi dọn ảnh sau commit, ảnh cũ có thể còn lại và cần xử lý vận hành theo log. Không xóa tệp ngoài namespace avatar hoặc tệp còn được tham chiếu.
- Chưa có quy trình đổi email trong trang hồ sơ; không tự bổ sung xác minh email hoặc đổi mật khẩu ở đây.
