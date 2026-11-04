# Giao diện nhà tuyển dụng

Triển khai theo `design nha tuyen dung.docx` và `AGENTS.md`. Phạm vi là giao diện Recruiter với dữ liệu minh họa; không thêm API, migration, cơ chế đăng nhập hoặc xử lý AI.

## Template và cấu trúc

Sử dụng Spark Admin trong `template admin`: Bootstrap 5, Bootstrap Icons, Plus Jakarta Sans, thanh bên xanh đậm #051C12/#072F1F, điểm nhấn #B4F105, nền #F4F6F5. Giữ CSS/JavaScript và tài nguyên gốc tại `public/recruiter`, không sửa thư viện. Font Google của template cần kết nối mạng.

Tái sử dụng sidebar, navbar, card thống kê, bảng, badge trạng thái, form, nút và điều hướng responsive của template. Không đưa các biểu đồ doanh thu hoặc chức năng ngoài phạm vi vào giao diện. CSS bổ sung giới hạn cho Recruiter; không nạp CSS ứng viên vào layout này.

- `app/Http/Controllers/RecruiterPageController.php`: trả trang và dữ liệu mẫu.
- `app/Support/RecruiterCatalog.php`: nguồn dữ liệu mẫu tập trung.
- `routes/recruiter.php`: các route GET giao diện.
- `resources/views/employer`: layout và trang; `component` chứa header, sidebar, footer, modal xác nhận.
- `public/recruiter/css/recruiter.css`: CSS bổ sung.
- `public/recruiter/js/recruiter-domain.js`: quy tắc chuyển trạng thái.
- `public/recruiter/js/recruiter-views.js`: hiển thị dữ liệu.
- `public/recruiter/js/recruiter.js`: form và tương tác.
- `public/recruiter/demo/cv-demo-01.pdf`: PDF tổng hợp riêng cho ứng viên giả định số 1; các hồ sơ khác không gắn CV.

## Đường dẫn

Mở `/nha-tuyen-dung` để vào tổng quan. Các đường dẫn sau nằm dưới `/nha-tuyen-dung/quan-ly`:

| Đường dẫn | Chức năng |
| --- | --- |
| `/` | Tổng quan tin và hồ sơ theo trạng thái |
| `/doanh-nghiep` | Thông tin doanh nghiệp và tin đang mở |
| `/doanh-nghiep/tao` | Tạo hồ sơ doanh nghiệp demo |
| `/doanh-nghiep/chinh-sua` | Sửa thông tin và xem trước logo |
| `/tin-tuyen-dung` | Tìm kiếm, lọc, phân trang và thao tác tin |
| `/tin-tuyen-dung/tao` | Tạo tin, lưu nháp, xem trước, gửi duyệt mô phỏng |
| `/tin-tuyen-dung/chinh-sua?id=1` | Sửa tin, giữ riêng bản công khai |
| `/tin-tuyen-dung/chi-tiet?id=1` | Nội dung, trạng thái, kết quả duyệt và phiên bản |
| `/ho-so` | Tìm tên, lọc trạng thái hoặc tin tuyển dụng |
| `/ho-so/chi-tiet?id=1` | CV, chuyển trạng thái, ghi chú và lịch sử |
| `/thong-bao` | Hồ sơ mới, kết quả duyệt, đánh dấu đã đọc |
| `/tai-khoan` | Giới hạn demo, liên kết đăng nhập có sẵn và đặt lại dữ liệu |

## Hành vi đã triển khai

Xem trước dùng trực tiếp dữ liệu đang nhập, không lưu và không gửi request; quay lại giữ form. Lưu nháp chỉ cần tiêu đề; gửi duyệt kiểm tra các trường, khoảng lương và hạn ứng tuyển. Bản sửa chờ duyệt không ghi đè bản công khai. Tin đóng, bị khóa hoặc quá hạn không được mở lại; hạn của bản công khai được dùng khi bản sửa đang chờ duyệt.

Hồ sơ đi theo applied → reviewing → interview → hired; có thể từ chối từ ba trạng thái đầu. Hired, rejected và withdrawn không chuyển tiếp; nhà tuyển dụng không được tự chuyển sang withdrawn. Ghi chú nội bộ tách khỏi lịch sử trạng thái, có thời gian và người thao tác.

Các thao tác chỉ lưu `sessionStorage` trong tab, có thông báo rõ là mô phỏng. Đặt lại demo tại trang Tài khoản. Không có nút giả thông báo đã gửi email, đã đăng tin thật hay đã đăng xuất thành công.

## Kiểm tra

- `php artisan test --compact`: 10 tests, 147 assertions; bao gồm kiểm tra giao diện ứng viên không bị trộn tài nguyên Recruiter.
- `node --test tests/recruiter-domain.test.cjs`: 3 tests, bao phủ chuyển trạng thái được phép và bị cấm.
- `node tests/recruiter-browser.cjs`: Playwright với Edge; cần Playwright có tại `%TEMP%/viejob-browser-tools` và server tại cổng 8012.
- Kiểm tra 12 trang ở 1440, 768 và 390 px: không lỗi JavaScript/HTTP, không tràn ngang trang; bảng cuộn trong vùng riêng.
- Kiểm tra logo, lưu doanh nghiệp, nháp tối thiểu, validation lương, xem trước, bản công khai, tin hết hạn/đóng/khóa, lọc hồ sơ, tải PDF, trạng thái cuối, ghi chú và thông báo. Không phát sinh request ghi dữ liệu.
- Ảnh kiểm tra nằm tại `storage/app/private/recruiter-checks` (không công khai).

Chạy thử không phụ thuộc database bằng PowerShell:

```powershell
$env:SESSION_DRIVER = 'file'
$env:CACHE_STORE = 'file'
php artisan serve --host=127.0.0.1 --port=8012
```

## Phần cần nối backend sau

Dữ liệu mẫu không phải dữ liệu tuyển dụng thật. Các route demo hiện chưa có phân quyền Recruiter. Khi tích hợp cần xác thực/Policy kiểm tra quyền sở hữu doanh nghiệp, tin và hồ sơ; validation server; lưu bản công khai và bản chờ duyệt riêng; duyệt bởi Admin; giao dịch cập nhật trạng thái/lịch sử; ghi chú nội bộ; thông báo thật; upload logo và CV qua storage có kiểm tra; tải CV qua endpoint có quyền truy cập. Không dùng URL PDF công khai như mẫu cho CV thật.

Tài khoản và đăng xuất thật, gửi email, lưu database và công khai tin chưa được triển khai trong nhiệm vụ giao diện này. Giao diện ứng viên hiện có được giữ nguyên; điểm vào nhà tuyển dụng trên header dẫn đến tổng quan mới.
