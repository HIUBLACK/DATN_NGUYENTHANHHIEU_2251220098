# Báo cáo triển khai Public và Candidate — VIEJOB AI

Ngày kiểm tra: 02/11/2026. Phạm vi: giao diện theo tài liệu `prompt ứng viên.pdf` (12 trang). Đây là bản frontend/demo, không phải hệ thống tuyển dụng đã kết nối nghiệp vụ.

## Hiện trạng và nguyên tắc

- Đã đọc routes, controller, User model, ba migration mặc định, Blade, Bootstrap, JavaScript, Vite và mẫu trong `theme sample` trước khi triển khai.
- Tái sử dụng Bootstrap và Font Awesome đã có; tham khảo cấu trúc danh sách/chi tiết việc làm trong mẫu. Giữ credit Colorlib ở footer, không chỉnh sửa thư mục mẫu người dùng.
- Không sửa `.env`, cấu hình MySQL, migrations hoặc model. Không tạo bảng nghiệp vụ.
- Route `/` vẫn là trang chủ, nay dùng controller để cung cấp dữ liệu mẫu tách biệt.
- CSS và JS dùng asset trực tiếp trong `public`, không yêu cầu cài Node/Vite để chạy giao diện. Các cấu hình Vite hiện có giữ nguyên.
- Font Inter lưu cục bộ, ba độ đậm và giấy phép SIL OFL trong `public/assets/fonts`.
- Hai file rỗng cũ `Candidate/ca.layout.blade.php` và `Candidate/ca_pages/ca.home.blade.php` được đổi thành `Candidate/layout.blade.php` và `Candidate/ca_pages/home.blade.php`. Dấu chấm trong tên view Laravel biểu thị thư mục nên tên cũ không phù hợp cho view kế thừa.

## Giai đoạn 1 — Design system và layout

**Tạo:**

- `resources/views/layouts/public.blade.php`.
- `resources/views/partials/header.blade.php`, `footer.blade.php`, `candidate-sidebar.blade.php`.
- Components: `job-card`, `company-card`, `status-badge`, `skill-tag`, `empty-state`, `alert`, `breadcrumb`, `form-input`, `loading-state`, `pagination` trong `resources/views/components`.
- `public/assets/css/platform.css`, `public/assets/js/platform.js`, `platform-domain.js`.
- `public/assets/fonts/inter-regular.ttf`, `inter-semibold.ttf`, `inter-bold.ttf`, `Inter-LICENSE.txt`.

**Sửa/đổi tên:** Candidate layout nêu trên. Thiết lập màu #051c12 / #b4f105, Inter, focus/hover/disabled, layout responsive, dialog xác nhận chung và modal ứng tuyển chung.

**Trang/route:** Layout dùng cho toàn bộ route của giai đoạn sau. Chưa tạo giao diện nghiệp vụ Recruiter/Admin.

**Demo/backend:** Trạng thái “đăng nhập” của bản xem thử chỉ thay đổi navigation trong trình duyệt, không xác thực. Không tạo tài khoản Admin. Header không tuyên bố người dùng đã được xác thực thật.

**Kiểm tra:** Blade biên dịch được; bộ icon tái sử dụng được đối chiếu với CSS có sẵn; ảnh chụp desktop/mobile được xem trực tiếp.

## Giai đoạn 2 — Giao diện công khai

**Tạo:**

- `app/Support/DemoCatalog.php`: dữ liệu công ty, việc làm, danh mục CNTT.
- `app/Http/Controllers/FrontendController.php`: render view, tìm kiếm/lọc/phân trang dữ liệu mẫu, kiểm tra tin ẩn/không tồn tại.
- `resources/views/public/{jobs,job,companies,about,employer,auth}.blade.php`.
- `resources/views/errors/404.blade.php`.

**Sửa:** `resources/views/welcome.blade.php`, `routes/web.php`, CSS/JS dùng chung.

**Trang/route:**

| Route | Trang |
| --- | --- |
| `/` | Trang chủ |
| `/viec-lam` | Tìm kiếm, bộ lọc, phân trang |
| `/viec-lam/{id}` | Chi tiết công việc |
| `/cong-ty` | Công ty |
| `/gioi-thieu` | Giới thiệu |
| `/nha-tuyen-dung` | Thông báo phạm vi giai đoạn sau |
| `/dang-nhap`, `/dang-ky` | Form tài khoản |
| `/quen-mat-khau`, `/dat-lai-mat-khau` | Form khôi phục mật khẩu |

**Demo/backend:** Danh mục là fixture; tìm kiếm/lọc/phân trang thực hiện trong Laravel trên fixture. Form tài khoản có kiểm tra dữ liệu và hiện/ẩn mật khẩu, nhưng không đăng nhập, đăng ký, thay mật khẩu hoặc gửi email thật. Mật khẩu không được lưu vào localStorage/IndexedDB.

**Kiểm tra:** Các route trả HTTP 200; tin 9 đóng, tin 10 hết hạn không có nút ứng tuyển khả dụng; tin 11 ẩn và tin không tồn tại trả 404, không đưa nội dung chi tiết tin ẩn vào JavaScript công khai. Kiểm tra lọc kết hợp, không có kết quả, phân trang.

## Giai đoạn 3 — Dashboard, CV và hồ sơ

**Tạo/hoàn thiện:**

- `resources/views/Candidate/ca_pages/{home,resumes,skills,profile,settings}.blade.php`.
- Các hàm render CV, kỹ năng, hồ sơ trong `platform.js`.

**Route:** `/ung-vien/tong-quan`, `/ung-vien/cv`, `/ung-vien/ky-nang?cv=...`, `/ung-vien/ho-so`, `/ung-vien/cai-dat`.

**Đã có:** Dashboard thống kê, CV chính, hoạt động, thông báo; kéo thả/chọn PDF; xem/tải xuống/đổi tên/thay thế/chọn chính/xóa; tối đa 5 MB, 10 CV; một CV chính; bắt buộc chọn CV thay thế khi xóa CV chính còn CV khác. Không có CV Builder hoặc chỉnh sửa PDF. Kỹ năng có thêm/xóa/chuẩn hóa/xác nhận. Hồ sơ có ảnh đại diện và kiểm tra form. Cài đặt có form mật khẩu, thoát demo và xóa dữ liệu cục bộ.

**Demo/backend:** Metadata ở localStorage; file PDF/ảnh ở IndexedDB của origin hiện tại. Không upload máy chủ. Kiểm tra PDF phía trình duyệt gồm phần mở rộng, MIME, dung lượng, chữ ký `%PDF-` và dấu kết thúc `%%EOF`; đây không thay thế bộ kiểm tra PDF phía server. AI chưa xử lý tài liệu. Người dùng có thể chọn trạng thái demo (chờ/đang phân tích/cần xác nhận/hoàn tất/thất bại); hoàn tất cần xác nhận ít nhất một kỹ năng. Thay PDF đưa CV về chờ phân tích lại và xóa kỹ năng cũ. Không tự trích xuất thông tin thật từ CV.

**Kiểm tra:** PDF mẫu được lưu, đổi tên, tải xuống, thay thế, xác nhận kỹ năng, CV chính thay thế và xóa CV cuối được thử trong Edge. Giới hạn 5 MB/10 CV kiểm tra bằng cả Node và trình duyệt; trạng thái phân tích thất bại cũng được kiểm tra. Thống kê thay đổi theo thao tác demo, không có số liệu “thành công” tự bịa.

## Giai đoạn 4 — Việc đã lưu và AI

**Tạo:** `Candidate/ca_pages/saved.blade.php`, `recommendations.blade.php`; render job/điểm/kỹ năng dùng chung trong `platform.js`.

**Route:** `/ung-vien/da-luu`, `/ung-vien/goi-y-ai`.

**Đã có:** Lưu/bỏ lưu xuyên trang; lọc trạng thái; tin ẩn chỉ có thông báo không khả dụng; tin đóng/hết hạn không ứng tuyển. Gợi ý hiển thị CV chính, bộ lọc địa điểm/hình thức, điểm, thanh tiến trình, kỹ năng đáp ứng/còn thiếu, chi tiết/ứng tuyển/lưu. Không có CV chính đã xác nhận thì tạm dừng gợi ý.

**Demo/backend:** Điểm là số minh họa được ghi rõ, không tính bằng AI và không phải xác suất trúng tuyển. Danh sách kỹ năng đáp ứng được đối chiếu với danh sách người dùng xác nhận trong demo. Không gọi dịch vụ ngoài.

**Kiểm tra:** Lưu tin, thêm trạng thái đóng/hết hạn/ẩn để kiểm tra; xác nhận không có link chi tiết tin ẩn. Trạng thái chưa có CV, CV đã xác nhận và xóa CV cuối đã kiểm tra.

## Giai đoạn 5 — Ứng tuyển và thông báo

**Tạo:** `Candidate/ca_pages/applications.blade.php`, `notifications.blade.php`; modal chung trong layout, quy tắc thuần trong `platform-domain.js`, tương tác trong `platform.js`.

**Route:** `/ung-vien/ung-tuyen`, `/ung-vien/thong-bao`; modal mở từ chi tiết việc làm/gợi ý/đã lưu.

**Đã có:** Chọn PDF (không bắt buộc hoàn tất AI), thư giới thiệu, checkbox xác nhận, thông báo thành công demo. Lưu tên CV tại thời điểm ứng tuyển, lượt, ngày, trạng thái và timeline. Rút chỉ ở applied/reviewing/interview; cần dialog xác nhận. Chỉ cho lượt 2 khi lượt 1 đã rút và tin còn mở; chặn lượt 3. Chống submit lặp trong lúc đọc file. Có sáu trạng thái hồ sơ, thông báo đọc/chưa đọc, đánh dấu đọc và liên kết hồ sơ.

**Demo/backend:** Không gửi hồ sơ/email. Các sự kiện email được gắn nhãn minh họa; reviewing chỉ thông báo nội bộ. Nút “Tạo hồ sơ mẫu” phục vụ kiểm tra tất cả trạng thái, không giả làm nhà tuyển dụng cập nhật hồ sơ thật.

**Kiểm tra:** Trình duyệt chạy chuỗi ứng tuyển → rút → ứng tuyển lại → rút → không còn lượt 3. Bốn thông báo tương ứng được kiểm tra và đánh dấu đã đọc. Node kiểm tra toàn bộ điều kiện rút/ứng tuyển.

## Giai đoạn 6 — Kiểm tra và bàn giao

**Tạo:** `tests/Feature/FrontendTest.php`, `tests/frontend-rules.test.cjs`, `tests/frontend-browser.cjs`, báo cáo này.

**Kết quả đã chạy:**

- Laravel: 5 tests, 81 assertions đạt, gồm test cũ.
- Node: 4 tests quy tắc đạt.
- Edge: 19 trang × 3 kích thước (1440, 768, 390 px), không tràn ngang toàn trang.
- 48 liên kết nội bộ trả thành công.
- Không có lỗi JavaScript console hoặc request tài nguyên thất bại trong lượt kiểm thử.
- Kiểm tra ảnh chụp giao diện, UTF-8 và font tiếng Việt; điều hướng mobile/đóng bằng Escape.
- Ảnh và kết quả kỹ thuật lưu trong `storage/app/private/ui-checks` (không commit).

**Backend thật:** Chỉ Laravel routing/render và tìm/lọc/phân trang fixture. Chưa nối CRUD tuyển dụng, xác thực, lưu trữ CV server, phân tích AI, thông báo hoặc email thật. Không triển khai Admin/Recruiter ngoài trang thông báo phạm vi. Những phần này là tích hợp tiếp theo, không được tuyên bố đã hoàn thành.

## Cách chạy

Khi MySQL và tài khoản kết nối hoạt động:

```powershell
php artisan serve
```

Sau đó vào `http://127.0.0.1:8000`, hoặc `/ung-vien/tong-quan` để xem không gian ứng viên.

**Lỗi môi trường hiện tại:** Kết nối MySQL trong phiên kiểm tra trả `SQLSTATE[HY000] [1045] Access denied for user 'root'@'localhost' (using password: NO)`. Theo yêu cầu PDF, không đổi cấu hình MySQL hay mật khẩu để sửa lỗi này. Đây là lỗi thông tin đăng nhập, khác lỗi thiếu bảng sessions ở phiên trước.

Để xem frontend độc lập, mở một cửa sổ PowerShell mới tại dự án:

```powershell
$env:SESSION_DRIVER='file'
$env:CACHE_STORE='array'
php artisan serve --host=127.0.0.1 --port=8011 --no-reload
```

Vào `http://127.0.0.1:8011`. Hai biến chỉ ảnh hưởng tiến trình trong cửa sổ đó; không sửa `.env` và không chuyển database. Đóng cửa sổ sau khi xem để bỏ override.

Kiểm thử:

```powershell
php artisan test
node --test tests/frontend-rules.test.cjs
```

Kiểm thử trình duyệt cần Playwright cài riêng, không bắt buộc với ứng dụng:

```powershell
$env:PLAYWRIGHT_MODULE='đường-dẫn-tới/node_modules/playwright'
node tests/frontend-browser.cjs
```

Mặc định dùng Edge và URL 8011; có thể đặt `EDGE_PATH` và `FRONTEND_URL`. Kiểm thử dùng browser context tách biệt, PDF tự tạo và không dùng dữ liệu người dùng. Khi trình diễn thủ công, dùng PDF mẫu; mọi dữ liệu demo có thể xóa tại Cài đặt tài khoản.

## Điểm tích hợp tiếp theo

Thay `DemoCatalog` bằng truy vấn nghiệp vụ; thêm xác thực/phân quyền thật; chuyển thao tác cục bộ sang API có validation phía server; lưu file riêng tư; nối pipeline PDF/AI; quản lý trạng thái tuyển dụng, gửi email và kiểm tra tất cả quy tắc tại server. Chỉ triển khai database hoặc thay cấu hình khi được yêu cầu ở giai đoạn backend.
