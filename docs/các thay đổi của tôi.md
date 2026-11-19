# các thay đổi của tôi

Ngày cập nhật: **18/11/2026**  
Dự án: **VIEJOB AI**  
Phạm vi: Form tạo/chỉnh sửa doanh nghiệp của Nhà tuyển dụng và các trang hiển thị thông tin doanh nghiệp liên quan.

## 1. Thay đổi giao diện

| Khu vực | Nội dung cập nhật |
| --- | --- |
| Thông tin cơ bản | Bỏ Tên viết tắt, Mô tả ngắn và Năm thành lập. Đổi nhãn “Giới thiệu chi tiết” thành “Giới thiệu doanh nghiệp”. |
| Mô hình công ty | Chuyển từ nhập tự do sang danh sách 17 lựa chọn, gồm Product, Outsourcing, dịch vụ CNTT, Startup, In-house, R&D và Mô hình khác. |
| Quốc gia | Chuyển sang danh sách 249 quốc gia/vùng lãnh thổ với tên tiếng Việt. |
| Website | Thêm ô chọn “Không có website”; khi chọn, không yêu cầu nhập URL và lưu website là NULL. |
| Địa chỉ | Giữ nhập địa chỉ thủ công. Không tích hợp Google Maps theo quyết định cuối cùng. |
| Thời gian làm việc | Thêm hai lựa chọn từ thứ nào đến thứ nào, từ Thứ Hai đến Chủ Nhật. |
| Chính sách OT | Hai lựa chọn: “Có làm thêm giờ” hoặc “Không làm thêm giờ”. |
| Môi trường làm việc | Bỏ Phúc lợi nổi bật và Công nghệ sử dụng; thêm “Giới thiệu môi trường làm việc”, tối đa 3.000 ký tự. |
| Hình ảnh | Bỏ ảnh bìa; giữ logo và ảnh văn phòng, hiển thị trong khung cố định. |

Thông tin đã được cập nhật đồng bộ trên form Nhà tuyển dụng, trang hồ sơ doanh nghiệp dùng chung cho Recruiter/Candidate và phần hiển thị doanh nghiệp Admin. Module doanh nghiệp Admin hiện có vẫn giữ nguồn dữ liệu demo; đợt cập nhật này không triển khai backend quản lý doanh nghiệp Admin mới.

## 2. Quy tắc nhập liệu và hiển thị

- Mô hình công ty và quốc gia được kiểm tra bằng danh sách cho phép ở backend. Giá trị cũ ngoài danh sách vẫn có thể được giữ trên chính hồ sơ đã lưu.
- Hai đầu của khoảng ngày làm việc phải được chọn đầy đủ. Nếu để trống cả hai khi sửa, lịch cũ được giữ nguyên; khoảng ngày qua cuối tuần được phép.
- Chính sách OT cũ không được tự suy diễn thành Có/Không. Người dùng chọn một trong hai lựa chọn để cập nhật; để trống khi sửa sẽ giữ nội dung cũ.
- Giới thiệu môi trường làm việc được lưu thật, kiểm tra giới hạn 3.000 ký tự ở backend và database.
- Khung logo hồ sơ: **100 × 100 px**; ảnh xem trước khi tải lên: **180 × 120 px**; ảnh văn phòng trên hồ sơ: chiều cao **200 px**, chiều rộng theo bố cục. Ảnh được co/cắt theo khung bằng CSS, không làm giãn bố cục.
- Giữ kiểm tra upload JPG/PNG/WebP, dung lượng, kích thước và xử lý ảnh an toàn. Giữ phân quyền chủ doanh nghiệp và cơ chế phát hiện form cũ.

## 3. Thay đổi database đã được phê duyệt

Thực hiện trên bảng `companies`:

| Thao tác | Cột | Ý nghĩa |
| --- | --- | --- |
| Thêm | `work_environment` | TEXT, cho phép NULL, CHECK tối đa 3.000 ký tự. |
| Xóa | `short_name` | Tên viết tắt. |
| Xóa | `short_description` | Mô tả ngắn. |
| Xóa | `founded_year` | Năm thành lập. |
| Xóa | `benefits` | Phúc lợi doanh nghiệp. |
| Xóa | `technologies` | Công nghệ doanh nghiệp sử dụng. |
| Xóa | `cover_image_path` | Đường dẫn ảnh bìa. |

Migration: `database/migrations/2026_11_18_120000_simplify_company_profile.php`.

Đã xóa các CHECK liên quan đến ba trường cũ và thêm CHECK cho `work_environment`. Database vẫn có **32 bảng**; giữ nguyên Primary Key, Foreign Key và index. Không thay đổi trường `benefits` của **tin tuyển dụng**. Không tự chuyển dữ liệu phúc lợi/công nghệ cũ sang nội dung môi trường làm việc, không seed dữ liệu mẫu.

## 4. Sao lưu và triển khai

Đã triển khai bằng `php database/deploy-company-profile.php --apply` theo trình tự:

1. Đưa ứng dụng vào maintenance và sao lưu đầy đủ database.
2. Khôi phục bản sao vào database tạm, đối chiếu schema và dữ liệu.
3. Diễn tập migration up/down/up trên bản khôi phục.
4. Áp dụng migration lên `db_job`.
5. Đối chiếu dữ liệu các cột được giữ lại, kiểm tra 32 bảng, dọn database tạm và thoát maintenance.

Bản sao lưu riêng tư:

`storage/app/private/database-backups/20261118_145159_company_profile/before-migration.sql`

File `manifest.json` cùng thư mục lưu hash bản sao và kết quả đối chiếu. Dữ liệu sáu cột đã xóa còn trong backup, không còn ở bảng đang hoạt động. Các tệp ảnh bìa cũ không bị xóa tự động khỏi storage, nhưng không còn hiển thị hoặc được phục vụ qua route ảnh bìa.

**Lưu ý rollback:** `down()` chỉ dựng lại cấu trúc cột cũ, không khôi phục nội dung đã xóa; đồng thời từ chối chạy khi `work_environment` có dữ liệu. Muốn khôi phục nội dung cũ cần sử dụng backup theo quy trình riêng, tránh ghi đè dữ liệu mới.

## 5. Các thành phần đã cập nhật

- **Backend:** Company Model, CompanyService, CompanyProfileController, SaveCompanyRequest và CompanyOptions.
- **Giao diện:** Form doanh nghiệp Recruiter, Blade hồ sơ dùng chung, phần hiển thị doanh nghiệp Admin, JavaScript xử lý website và CSS khung ảnh.
- **Dữ liệu lựa chọn:** `resources/data/countries-vi.json`.
- **Database:** Migration mới và công cụ triển khai có backup/restore.
- **Kiểm thử:** CompanyProfileTest và runner MariaDB riêng.
- **Tài liệu:** Thiết kế database, ERD, báo cáo triển khai database và báo cáo backend doanh nghiệp.

## 6. Kết quả kiểm thử

| Thời điểm | Lệnh | Kết quả |
| --- | --- | --- |
| Trước triển khai | `php tests/Database/models.php --auth --pending-company-profile` | 137 tests đạt, 2.090 assertions. |
| Sau triển khai | `php tests/Database/models.php --auth` | 137 tests đạt, 2.090 assertions. |

Kiểm thử sử dụng **MariaDB riêng**, không tạo fixture hoặc reset `db_job`. Runner xác nhận schema và lịch sử migration nguồn không đổi trong quá trình chạy test.

Đã kiểm tra lưu/hiển thị môi trường làm việc, giới hạn độ dài, sáu cột đã bỏ, lựa chọn ngày/mô hình/quốc gia/OT, website NULL và hồi quy phân quyền/upload. Laravel Pint, Blade compilation, cú pháp JavaScript và `git diff --check` đạt.

**Chưa thực hiện:** kiểm tra trực quan và thao tác trên trình duyệt thực tế. Khi nghiệm thu giao diện cần kiểm tra thêm form trên desktop/mobile, lựa chọn ngày làm việc, checkbox website và khung ảnh với ảnh có nhiều tỷ lệ khác nhau.

## 7. Cập nhật giao diện ngày 19/11/2026

- Thay checkbox xóa logo/ảnh văn phòng bằng nút **×** trên từng ảnh. Với ảnh đã lưu, nhấn × chỉ đánh dấu và ẩn ảnh khỏi form; ảnh chỉ bị xóa khi **Lưu hồ sơ** thành công. Không gọi API xóa ngay. Với ảnh mới chọn, × loại ảnh khỏi danh sách tải lên.
- Bỏ nút **Xem trang doanh nghiệp phía Candidate** trên hồ sơ Nhà tuyển dụng, **Xem trang công khai** trên form doanh nghiệp và **Xem bản công khai** trên chi tiết tin tuyển dụng. Xóa các khối Blade và điều kiện hiển thị riêng của những nút này.
- Giữ trang doanh nghiệp/tin tuyển dụng công khai dành cho ứng viên, các route và kiểm tra quyền phục vụ những trang đó. Giữ trang quản lý bản nháp và gửi duyệt của Nhà tuyển dụng.
- Đổi dấu **\*** thành màu đỏ tại bốn trường bắt buộc trong form tạo/sửa doanh nghiệp: **Tên doanh nghiệp, Email, Số điện thoại, Địa chỉ**, cùng dấu sao trong phần hướng dẫn. Tái sử dụng class Bootstrap `text-danger`; giữ nguyên thuộc tính `required` và validation hiện có.
- Các file giao diện liên quan: `resources/views/employer/company.blade.php`, `company-form.blade.php`, `job-detail.blade.php`, `public/recruiter/js/company-profile-form.js` và `public/css/company-profile.css`.

Kiểm tra đợt cập nhật giao diện: biên dịch Blade thành công; không còn liên kết `companies.show` hoặc `jobs.show` trong giao diện Nhà tuyển dụng. Không thay đổi database trong đợt cập nhật này. Kết quả 137 tests ở mục 6 thuộc đợt triển khai database trước đó; chưa chạy lại toàn bộ bộ kiểm thử cho thay đổi giao diện này.
