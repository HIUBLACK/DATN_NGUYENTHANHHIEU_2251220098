# Giao diện Public và Candidate — JobBoard

Triển khai theo `design ứng viên.docx` và quy định `AGENTS.md`. Phạm vi là giao diện và các thao tác minh họa; chưa triển khai hệ thống tuyển dụng thật.

## Giai đoạn 1 — Phân tích

- Template `jobboard-master` có 18 trang: index, about, job-listings, job-single, login, post-job, contact, blog, blog-single, services, service-single, portfolio, portfolio-single, testimonials, faq, gallery, main.
- Bootstrap 4.3.1, jQuery 3.3.1, Bootstrap Select, Owl Carousel, Fancybox, Waypoints, AnimateNumber, Stickyfill và Isotope. Giữ phiên bản hiện có; không nâng cấp Bootstrap 5.
- CSS chính: `custom-bs.css`, `style.css`; hành vi gốc: `custom.js`. Icon dùng Icomoon và Line Icons.
- Font Nunito 200/300/400/700; màu chính `#89ba16`; nền trắng, footer `#555`; khối nội dung 7rem trên desktop, 3rem trên mobile; danh sách việc viền `#eee`, bo góc 7px. Giữ grid Bootstrap, nút, form, hiệu ứng hover, ảnh nền và carousel.
- Header phủ lên hero, logo bên trái, menu giữa, tài khoản bên phải. Template không có dashboard/sidebar ứng viên riêng nên trang mới dùng container/grid và form của template.
- Dự án ban đầu dùng trang `welcome`, chưa có backend CV/ứng tuyển/AI. Vite được cấu hình nhưng các trang mới dùng asset tĩnh để giữ nguyên template, không thêm framework hay UI kit.

## Giai đoạn 2 — Blade và assets

- Layout: `resources/views/Candidate/master.blade.php`.
- Thành phần chung cùng cấp `ca_pages`: header, footer, account-links, page-hero, job-row, input, password, auth-form, empty-state, demo-notice, apply-modal, confirm-modal, resume-card.
- Trang chủ và giới thiệu kế thừa cấu trúc section, ảnh và hiệu ứng gốc; danh sách/chi tiết việc và xác thực kế thừa trang mẫu tương ứng.
- Assets tại `public/jobboard/{css,js,fonts,images}`. Giữ nguyên thư viện bên thứ ba; CSS/JS bổ sung ở `candidate.css`, `candidate.js`, `navigation-bridge.js`.
- Nunito được lưu cục bộ cùng giấy phép SIL OFL; không phụ thuộc yêu cầu Google Fonts lúc mở trang. Vẫn cùng font, không thay thiết kế.
- `navigation-bridge.js` sửa xung đột smooth-scroll của template với liên kết trang trong menu di động, đồng thời Việt hóa Bootstrap Select.
- Không sửa `.env`, migrations hoặc dữ liệu database. Các thay đổi/xóa từ lần hoàn tác giao diện trước không thuộc phạm vi triển khai này.

## Giai đoạn 3 — Trang và chức năng

Các trang nằm trong `resources/views/Candidate/ca_pages`.

| URL | Trang |
| --- | --- |
| `/` | Trang chủ |
| `/viec-lam`, `/viec-lam/{id}` | Tìm kiếm, lọc, phân trang và chi tiết việc |
| `/cong-ty`, `/gioi-thieu` | Công ty minh họa, giới thiệu |
| `/dang-nhap`, `/dang-ky` | Biểu mẫu tài khoản ứng viên, nút mở phiên xem thử |
| `/quen-mat-khau`, `/dat-lai-mat-khau` | Biểu mẫu khôi phục mật khẩu |
| `/nha-tuyen-dung` | Trang thông báo phạm vi, chưa triển khai Recruiter |
| `/ung-vien/cv` | Tải, xem, tải xuống, đổi tên, thay PDF, chọn CV chính, xóa |
| `/ung-vien/ky-nang?cv={id}` | Nạp mẫu, thêm/sửa/xóa và xác nhận kỹ năng |
| `/ung-vien/goi-y-ai` | Gợi ý và điểm minh họa, bộ lọc, kỹ năng đáp ứng/còn thiếu |
| `/ung-vien/da-luu` | Lưu/bỏ lưu, trạng thái tin ẩn |
| `/ung-vien/ung-tuyen` | Lượt ứng tuyển, timeline, rút và ứng tuyển lại |
| `/ung-vien/thong-bao` | Đã đọc/chưa đọc, liên kết hồ sơ |
| `/ung-vien/ho-so`, `/ung-vien/cai-dat` | Thông tin cá nhân, ảnh đại diện, biểu mẫu mật khẩu, thoát/xóa demo |
| `/ung-vien/tin-nhan` | Trạng thái chưa kết nối nhắn tin |

Routes POST và route tải tệp được khai báo trong `routes/web.php`; xem đầy đủ bằng `php artisan route:list`.

PHP mới: `CandidatePageController`, `CandidateDemoController`, `CandidateProfileRequest`, `ResumeUploadRequest`, `CandidateDemo` và `CandidateCatalog`. Controller điều hướng/validation; service quản lý trạng thái phiên; catalog cung cấp dữ liệu mẫu.

### Phần có xử lý phía máy chủ

- Validation biểu mẫu; lọc và phân trang dữ liệu mẫu.
- PDF tối đa 5 MB, tối đa 10 CV; kiểm tra loại tệp và dấu đầu/cuối PDF cơ bản. Không phải bộ kiểm định PDF đầy đủ hoặc công cụ quét mã độc.
- Tệp lưu trên disk private, truy cập qua ID thuộc phiên; không dùng đường dẫn public. Chỉ một CV chính; xóa CV chính có CV khác phải chọn thay thế.
- Thay PDF đưa trạng thái về chờ phân tích; xóa CV cuối làm gợi ý tạm dừng.
- Phiên demo lưu kỹ năng, việc đã lưu, hồ sơ, thông báo, thông tin cá nhân. Không dùng tài khoản/database thật.
- Ứng tuyển chấp nhận PDF chưa phân tích; lần hai chỉ sau lượt đầu đã rút; không có lần ba. Chỉ rút ở applied/reviewing/interview. Tin đóng/hết hạn không nhận ứng tuyển.
- Nút xóa dữ liệu demo xóa PDF, ảnh và dữ liệu của phiên hiện tại.

### Phần chưa kết nối

- Đăng nhập/đăng ký/đổi và khôi phục mật khẩu chỉ kiểm tra biểu mẫu, không tạo tài khoản hay thay mật khẩu thật.
- Phân tích CV, kỹ năng nhận diện và điểm phù hợp là minh họa. Chưa gọi FastAPI/Sentence Transformers.
- Hồ sơ ứng tuyển không gửi đến nhà tuyển dụng; thông báo không phát email. Đang xem xét được ghi rõ chỉ thông báo nội bộ.
- Công ty, công việc và số liệu là mẫu; logo/ảnh thương hiệu lấy từ template, không chứng minh quan hệ với các công ty mẫu.
- Recruiter/Admin và nhắn tin chưa thuộc phần triển khai. Video/ảnh minh họa gốc vẫn thuộc template; chữ nằm trong ảnh gốc không được biên tập lại.
- Dữ liệu phiên có thể mất khi hết phiên; tệp còn lại sau phiên hết hạn cần cơ chế dọn định kỳ khi tích hợp backend thật. Không dùng bản demo để vận hành tuyển dụng thực tế.

## Chạy và kiểm tra

Trong PowerShell tại thư mục dự án:

```powershell
$env:SESSION_DRIVER = 'file'
$env:CACHE_STORE = 'file'
php artisan serve --host=127.0.0.1 --port=8012 --no-reload
```

Hai biến trên chỉ áp dụng cho tiến trình hiện tại, không ghi `.env`; giúp xem giao diện độc lập với cấu hình MySQL hiện tại. Mở `http://127.0.0.1:8012`, vào Đăng nhập → Xem thử giao diện đã đăng nhập.

Thử CV bằng PDF mẫu: tải lên → xác nhận kỹ năng → nạp mẫu/chỉnh kỹ năng → xác nhận → xem gợi ý. Từ chi tiết việc chọn ứng tuyển → xem hồ sơ → rút → ứng tuyển lại. Các khu vực mở rộng trên trang cho phép xem trạng thái phân tích, tin ẩn và sáu trạng thái ứng tuyển. Xóa dữ liệu demo sau khi thử.

```powershell
php artisan test
php artisan view:cache
node --check public/jobboard/js/candidate.js
node --check public/jobboard/js/navigation-bridge.js
```

Kiểm tra trình duyệt: `tests/jobboard-browser.cjs` và `tests/jobboard-interactions.cjs`, cần Playwright tại `%TEMP%/viejob-browser-tools/node_modules/playwright`, Edge và server cổng 8012. Ảnh kiểm tra lưu ở `storage/app/private/jobboard-checks`.

## Giai đoạn 4 — Kết quả kiểm tra

- PHPUnit: 7 tests, 78 assertions đạt; gồm 19 trang, CV private, giới hạn số lượng/kích thước, chuẩn hóa kỹ năng, thay CV chính, giới hạn ứng tuyển, rút hồ sơ, thông báo và validation.
- Blade compile và cú pháp JavaScript đạt.
- 19 trang × 3 độ rộng 1440/768/390 px: không có page error, HTTP asset lỗi hoặc tràn ngang trong lần kiểm tra.
- Kiểm tra tương tác đạt: menu di động chuyển trang, avatar hiện menu khi hover, tải PDF, thêm kỹ năng, hiển thị gợi ý, ứng tuyển, modal xác nhận rút, liên kết ứng tuyển lại và xóa dữ liệu phiên. Không có page error trong luồng này.
- Không kiểm chứng tích hợp database, AI, email hoặc xác thực thật vì các phần này chưa được kết nối trong phạm vi giao diện.
