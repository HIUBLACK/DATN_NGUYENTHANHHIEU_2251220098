# Giao diện Admin VIEJOB

Triển khai theo `design admin.docx` và `AGENTS.md`. Phạm vi chỉ giao diện và mô phỏng trình duyệt, không có migration, Model nghiệp vụ, API hay thay đổi xác thực.

## Phân tích và template

Dự án dùng Laravel 12; template gốc tại `template admin`, Spark Admin, Bootstrap 5.3.8, Bootstrap Icons, Plus Jakarta Sans, ApexCharts có sẵn. Layout Candidate và Recruiter được giữ nguyên. Trước triển khai, thư mục Admin chỉ có khung thư mục chưa có giao diện hoạt động.

Tái sử dụng bố cục từ `page-blank.html`, thẻ thống kê và biểu đồ từ `index.html`, bảng từ `tables-basic.html`, form và nút từ `ui-forms.html`/`ui-buttons.html`. Giữ màu xanh rừng #051C12/#072F1F, điểm nhấn #B4F105, nền #F4F6F5, sidebar 280px, navbar, typography và spacing của template. Giữ nguyên tài nguyên vendor; CSS bổ sung trong file riêng. Font Google của template cần mạng, có sans-serif dự phòng.

Tài nguyên Admin nằm ở `public/admin-assets`, tránh trùng đường dẫn `/admin` của Laravel. Không chỉnh sửa tài nguyên Recruiter hoặc template gốc.

## Các trang và 26 chức năng

Tất cả đường dẫn bên dưới bắt đầu bằng `/admin`. Tham số `?id=1` chọn bản ghi mẫu. Tên route dùng tiền tố `admin.`.

| Nhóm | Trang | Đáp ứng |
| --- | --- | --- |
| ADM-01.01–04 | `/` | Thống kê người dùng, doanh nghiệp/tin, hồ sơ; biểu đồ tin mới và lượt ứng tuyển theo ngày |
| ADM-02.01–05 | `/nguoi-dung`, `/nguoi-dung/chi-tiet?id=3`, `/nguoi-dung/lich-su?id=10` | Tìm tên/email, lọc kết hợp vai trò/trạng thái, phân trang, chi tiết, khóa/mở khóa có lý do và lịch sử |
| ADM-03.01–04 | `/doanh-nghiep`, `/doanh-nghiep/chi-tiet?id=1` | Logo minh họa, quản lý, liên hệ, số tin; tìm/lọc; chi tiết, khóa/mở khóa và lịch sử |
| ADM-04.01–07 | `/tin-tuyen-dung`, `/kiem-duyet`, `/tin-tuyen-dung/chi-tiet?id=1`, `/tin-tuyen-dung/lich-su?id=1` | Lọc doanh nghiệp/hoạt động/kiểm duyệt; xem/so sánh phiên bản; duyệt/từ chối tin mới và bản sửa; khóa/mở khóa; lịch sử |
| ADM-05.01–03 | `/ung-tuyen`, `/ung-tuyen/chi-tiet?id=1` | Tìm ứng viên, lọc doanh nghiệp/tin/trạng thái, chi tiết và lịch sử xử lý chỉ đọc |
| ADM-06.01–04 | `/danh-muc/nhom-nghe`, `/danh-muc/chuyen-mon` | Tìm/lọc/phân trang; thêm/sửa nhóm nghề, chuyên môn; kích hoạt/vô hiệu hóa có xác nhận và điều kiện |
| Tài khoản | `/tai-khoan` | Thông tin mẫu, liên kết đăng nhập hiện có, thông báo giới hạn đăng xuất và đặt lại phiên Admin |

15 trang/route GET. Nhóm quản lý kỹ năng CNTT cũ đã được loại bỏ. ADM-06 hiện quản lý nhóm nghề và chuyên môn; phạm vi là 27 chức năng thuộc 6 nhóm. Kỹ năng trong nội dung tin tuyển dụng vẫn hiển thị để phục vụ kiểm duyệt.

## Quy tắc mô phỏng

- Nguồn mẫu tập trung tại `AdminCatalog`; chỉ dùng tên giả định và email example.com. Admin có sessionStorage riêng `viejob-admin-preview-v1`, không tác động phiên Candidate/Recruiter.
- Chỉ báo đã lưu mô phỏng sau khi ghi sessionStorage thành công. Nếu bộ nhớ bị chặn, không áp dụng thay đổi vào trạng thái đang xem.
- Không khóa bản thân hay Admin khác; không đổi vai trò và không xóa tài khoản.
- Khóa/từ chối bắt buộc lý do không rỗng. Mọi thay đổi có xác nhận, người thực hiện, thời gian và kết quả.
- Chỉ phê duyệt/từ chối phiên bản pending. Bản sửa bị từ chối giữ bản công khai cũ. Phê duyệt sao chép nội dung mới thành bản đã duyệt; không tự mở tin đóng/bị khóa.
- Khóa doanh nghiệp làm tin không đủ điều kiện hiển thị, không ghi đè trạng thái từng tin. Mở khóa doanh nghiệp không duyệt các bản chờ duyệt hoặc mở lại tin đóng.
- Mở khóa tin khôi phục trạng thái trước khóa; tin đóng vẫn đóng. Tin trước đó mở nhưng hết hạn hoặc doanh nghiệp bị khóa được đưa về tạm dừng. Tin chưa có bản đã duyệt không được mở.
- Admin giám sát ứng tuyển chỉ đọc, không tải/xem CV, chuyển trạng thái hoặc sửa ghi chú Recruiter.
- Thẻ thống kê dùng toàn bộ dữ liệu hiện tại. Biểu đồ lấy ngày tạo tin/ngày nộp hồ sơ trong khoảng được chọn, có tổng theo khoảng và bảng số liệu thay thế. Khoảng tối đa 366 ngày; có trạng thái không dữ liệu. Số tin mở trên thẻ là trạng thái hoạt động, điều kiện công khai được giải thích riêng ở chi tiết.

## File tạo/sửa

- `app/Http/Controllers/AdminPageController.php`: điều hướng và trả seed.
- `app/Support/AdminCatalog.php`: dữ liệu mẫu và quan hệ nhất quán.
- `routes/admin.php`: 13 route; `routes/web.php` chỉ thêm require file này.
- `resources/views/Admin/layout/admin.blade.php`: layout riêng.
- `resources/views/Admin/component`: header, sidebar, footer, bảng/lọc dùng lại, modal xác nhận và modal kỹ năng.
- `resources/views/Admin/ad_pages`: dashboard, account và các thư mục users, companies, jobs, applications.
- `public/admin-assets`: bản sao tài nguyên gốc; `css/admin.css` và `js/admin-*.js` là mã bổ sung.
- JavaScript tách theo trách nhiệm: domain (quy tắc demo), ui (thành phần chung), lists, details, dashboard và admin (khởi tạo/lưu phiên/xác nhận).
- `tests/Feature/AdminPageTest.php`, `tests/admin-domain.test.cjs`, `tests/admin-browser.cjs`.

## Chạy và kiểm tra

```powershell
$env:SESSION_DRIVER = 'file'
$env:CACHE_STORE = 'file'
php artisan serve --host=127.0.0.1 --port=8012
```

Mở `http://127.0.0.1:8012/admin`. Không sửa `.env` hoặc yêu cầu database để thử UI.

```powershell
php artisan test --compact
node --test tests/admin-domain.test.cjs
node tests/admin-browser.cjs
```

Script trình duyệt sử dụng Edge và Playwright tại `%TEMP%/viejob-browser-tools` đã có trong môi trường, không thêm package vào dự án. Ảnh kiểm tra nằm trong `storage/app/private/admin-checks`. Kiểm tra 15 trang ở 1440/768/390px, tài nguyên/JS, modal và lý do bắt buộc, bảo vệ tài khoản Admin, tìm/lọc, so sánh phiên bản, giữ bản cũ khi từ chối, khóa doanh nghiệp, mở khóa tin đóng, giám sát chỉ đọc, tổng biểu đồ và khoảng ngày rỗng. Kiểm tra không có request ghi dữ liệu. Laravel suite kiểm tra cả trang Candidate/Recruiter hiện có.

## Cần tích hợp backend sau

Route hiện là UI demo công khai, chưa phải trang quản trị có quyền thật. Khi nối backend cần middleware xác thực/role và Policy, validation server, lưu dữ liệu và audit log, giao dịch duyệt phiên bản, điều kiện công khai nhất quán, khóa có phạm vi, thông báo và phân trang server. Dashboard cần truy vấn thống kê có thời gian rõ ràng. Đăng nhập/đăng xuất thật sử dụng hệ thống auth của dự án khi được triển khai; không có cơ chế auth mới trong nhiệm vụ này.

## Kết quả kiểm tra cuối

- Laravel: 12 tests đạt, 279 assertions.
- Quy tắc Admin: 5 tests đạt.
- Cập nhật 10/11/2026: chưa chạy lại kiểm tra trình duyệt vì Playwright tại thư mục tạm không còn. Script đã bỏ các trang và thao tác thuộc nhóm kỹ năng. Kết quả ảnh và kiểm tra trình duyệt trước đó thuộc phiên bản trước khi bỏ nhóm này.
- Đã xem ảnh dashboard desktop và so sánh phiên bản mobile; bảng cuộn trong khung riêng.
- Bản sao tài nguyên gốc khớp SHA-256 với template; Candidate/Recruiter không có chỉnh sửa mã nguồn trong nhiệm vụ này.

## Bổ sung danh mục nghề — 10/11/2026

Hai trang Nhóm nghề CNTT và Chuyên môn CNTT nằm trong menu Quản lý danh mục của sidebar hiện tại. Form thêm/sửa dùng chung Bootstrap Modal, có tên bắt buộc, mô tả và trạng thái; chuyên môn có đúng một nhóm trực thuộc. Bảng nhóm hiển thị mã, mô tả, số chuyên môn, trạng thái, ngày tạo và liên kết xem chuyên môn đã lọc.

- Kiểm tra tên trùng không phân biệt hoa/thường, chuẩn hóa Unicode và khoảng trắng; tên chuyên môn duy nhất trong một nhóm, kể cả danh mục vô hiệu hóa.
- Không xóa cứng; danh mục vô hiệu hóa vẫn có thể tìm/lọc/xem trong Admin.
- Chặn vô hiệu hóa nhóm có chuyên môn đang hoạt động, áp dụng cả nút thao tác và form sửa.
- Không tạo hoặc chuyển chuyên môn vào nhóm vô hiệu hóa; không kích hoạt chuyên môn khi nhóm chưa hoạt động. Cho phép sửa thông tin chuyên môn cũ đang vô hiệu hóa mà giữ nhóm cũ.
- Thay đổi trạng thái từ form hoặc nút thao tác đều có xác nhận. Hủy xác nhận từ form quay lại giữ nội dung đang nhập.
- Bổ sung dữ liệu danh mục cho phiên demo cũ mà giữ các thay đổi quản trị khác. Không thêm database hoặc sửa Candidate/Recruiter.

File chính: `AdminCatalog.php`, `routes/admin.php`, sidebar/layout Admin, `ad_pages/occupations/index.blade.php`, `ad_pages/specialties/index.blade.php`, `component/category-modal.blade.php`, `admin-categories-domain.js`, `admin-categories.js`; tái sử dụng `admin-lists.js` và cơ chế lưu phiên `admin.js`.

Kiểm tra: `node --test tests/admin-categories.test.cjs tests/admin-domain.test.cjs` và `node tests/admin-categories-browser.cjs`. Khi tích hợp backend cần validation, ràng buộc duy nhất, khóa giao dịch để kiểm tra trạng thái nhóm/chuyên môn và lọc cả hai trạng thái tại mọi form chọn danh mục cho dữ liệu mới.

Kết quả bổ sung: 12 kiểm thử Laravel (279 assertions) và 9 kiểm thử JavaScript đạt. Hai trang danh mục đã kiểm tra trên 1440/768/390px; thêm chuyên môn, trùng tên, phân trang, bộ lọc, phiên demo cũ và hủy/xác nhận đổi trạng thái đều đạt.
