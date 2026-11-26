# Các thay đổi: bỏ quản lý danh mục, dùng lựa chọn cố định

Ngày thực hiện: 25/11/2026.

## Cập nhật mới nhất: làm sạch dữ liệu để kiểm thử AI

Theo yêu cầu tiếp theo của người dùng, đã xóa dữ liệu trong 25 bảng, chỉ giữ nguyên toàn bộ **8 tài khoản trong `users`** và **37 dòng lịch sử `migrations`**. Đã so sánh checksum tài khoản trước/sau trong transaction: không đổi tên, email, mật khẩu, Google identity, vai trò hoặc trạng thái.

Đã xóa hồ sơ Candidate, doanh nghiệp, tin/phiên bản, CV/revision, snapshot, đơn ứng tuyển/lịch sử/ghi chú, kết quả AI, việc làm đã lưu, thông báo, hàng đợi, failed jobs, phiên và token reset cũ. Cache cũ được dọn; hệ thống có thể tạo lại cache kỹ thuật (bao gồm tín hiệu dừng worker). Không đặt lại bộ đếm ID và không thay đổi cấu trúc 27 bảng.

Đã xóa 69 file sao lưu thuộc `catalog-backups`, `database-backups`, `rollback-before-candidate-design-20261103-003643`, cùng 47 file upload/snapshot/phân tích/ảnh và dữ liệu preview cũ. Không tạo bản sao lưu mới trong lần làm sạch này. Các đường dẫn backup ghi ở phần lịch sử bên dưới **không còn tồn tại**.

`config/job-catalog.php` không còn chú thích hoặc yêu cầu giữ ánh xạ database cũ. Chuẩn hóa các tên thành “Gia công phần mềm”, “Quản lý CNTT”, “Giám đốc công nghệ”, “Lập trình viên Java”, “HTML”. Giữ mã số cố định đang dùng trong code để không thay đổi test/logic không cần thiết; các mã này không còn phụ thuộc dữ liệu đã xóa.

Website đã mở lại. Cần đăng nhập lại, tạo hồ sơ doanh nghiệp/tin tuyển dụng và tải CV mới để thử AI. Không có tác vụ queue đang xử lý lúc xóa. Worker được gửi tín hiệu dừng; khởi động worker khi cần phân tích dữ liệu mới.

Kiểm tra riêng lần này: checksum 8 tài khoản và lịch sử migration khớp; 25 bảng được xác nhận rỗng ngay sau transaction; PHP config hợp lệ. Không gửi mail hoặc chạy AI thật. Kết quả test ở phần dưới thuộc lần chuyển danh mục trước đó.

## Kết quả

- Bỏ toàn bộ chức năng quản lý danh mục Admin: menu, routes, Controller, Service, Form Requests, 6 Model danh mục, Blade, modal, JavaScript CRUD và các test CRUD cũ.
- Danh sách lĩnh vực, vị trí, nhóm nghề, chuyên môn, cấp bậc và kỹ năng nằm trong `config/job-catalog.php`. `app/Support/JobCatalog.php` cung cấp tra cứu dùng chung.
- Giữ nguyên ID và tên của các mục cũ, bổ sung lựa chọn CNTT. Không nhập danh mục mẫu vào database.
- Form doanh nghiệp/tin tuyển dụng, bộ lọc Candidate, trang kiểm duyệt, chi tiết và chuẩn hóa kỹ năng AI dùng danh sách trong code. Backend từ chối mã không tồn tại; chuyên môn vẫn xác định nhóm nghề.
- Giữ layout và các trường lựa chọn hiện có; chỉ bỏ khu vực quản lý danh mục Admin.

## Database đã cập nhật

Đã chạy riêng migration `2026_11_25_160000_replace_managed_catalogs_with_static_options` trên `db_job`.

- Xóa `industries`, `job_categories`, `job_specializations`, `skills`, `job_levels`, `job_positions` cùng 8 FK tham chiếu danh mục.
- Hiện còn **27 bảng: 19 nghiệp vụ + 8 hạ tầng**.
- Giữ các cột mã lựa chọn và index hiện có. Các mã được kiểm tra bằng code, không còn FK tới danh mục.
- Giữ `job_version_skills`, `resume_skills`: đây là dữ liệu kỹ năng đã chọn/đã phân tích, không phải bảng quản lý danh mục.
- Đối chiếu SHA-256 toàn bộ dòng của 14 bảng liên quan trước/sau: doanh nghiệp, tin và phiên bản, kỹ năng tin, CV và revision, kỹ năng CV, ứng tuyển/lịch sử/ghi chú, tin đã lưu, recommendation và đánh giá AI — **không thay đổi**.
- Không đổi published pointer, CV snapshot, nội dung JobVersion, kết quả AI hoặc trạng thái ứng tuyển. Không gửi mail hay chạy lại AI.

Migration kiểm tra ánh xạ trước DDL và sao lưu 6 bảng cùng schema/FK liên quan vào private storage:

`storage/app/private/catalog-backups/db_job-20261125-091910-578113.json`

Ứng dụng đã mở lại sau chuyển đổi. Đã gửi `queue:restart` để worker dùng mã cũ dừng an toàn; nếu đang dùng terminal chạy worker, khởi động lại khi cần xử lý AI:

```powershell
php artisan queue:work ai --queue=ai --tries=3 --timeout=240 --sleep=2
```

Các migration lịch sử được giữ để cài mới và kiểm tra lịch sử; migration cuối loại bỏ các bảng cũ. Không xóa lịch sử migration đã chạy. Chuyển đổi không tự rollback: phục hồi cần bản sao lưu và đối chiếu các mã mới đã sử dụng, không chỉ tạo lại bảng trống.

## Các file chính

- `config/job-catalog.php`, `app/Support/JobCatalog.php` và migration chuyển đổi.
- `RecruiterJobService`, `PublicJobService`, `CompanyService`, `AIJobAnalysisService`, `AIResumeAnalysisService`, `AdminJobService`, `SavedJobService`.
- Models `Company`, `JobVersion`, `JobVersionSkill`, `ResumeSkill`: accessor danh mục chỉ đọc thay quan hệ tới bảng đã bỏ; quan hệ nghiệp vụ vẫn giữ.
- Controllers và Requests doanh nghiệp, kiểm duyệt, quản trị; đổi tên Gate dùng chung thành `manage-system`.
- `routes/admin.php`, sidebar/layout/footer Admin, JavaScript danh sách Admin, form tin tuyển dụng và thông báo dùng chung.
- Fixtures và test liên quan; `docs/database-design.md`, `docs/database-erd.mmd`.

## Kiểm tra

- MariaDB test riêng: **77 test chức năng đạt, 998 assertions; 1 test Ollama thật bỏ qua**. Bao gồm tạo/sửa/gửi duyệt, V1/V2, doanh nghiệp, tin đã lưu, Admin, bộ lọc kết hợp, mã không hợp lệ, bỏ routes CRUD và AI CV/Job với mock.
- Model: **7 test đạt, 330 assertions**, quan hệ CV/revision/pivot và các bảng còn lại.
- Schema MariaDB: **33 kiểm tra đạt**, bao gồm cài mới, giữ queue và ràng buộc nghiệp vụ; JavaScript Admin: **5 test đạt**.
- PHP syntax, JavaScript syntax và biên dịch Blade đạt.
- Migration thật hoàn tất; 27 bảng và checksum dữ liệu nghiệp vụ trước/sau khớp. Test không reset hoặc seed `db_job`.

## Lưu ý bảo trì

- Thêm lựa chọn bằng mã mới trong config; **không đổi/tái sử dụng mã hoặc chuyển nhóm của chuyên môn đã lưu**. Tên vị trí cũ có lỗi chính tả được giữ nguyên để không tự sửa lịch sử tin.
- Không còn chức năng ẩn/hiện danh mục. Dữ liệu SQL nhập thủ công phải tuân thủ danh sách trong code.
- Các tài liệu CRUD danh mục trước ngày này chỉ còn giá trị lịch sử; báo cáo này và phần cập nhật đầu tài liệu database là quyết định hiện hành.
- Không kiểm thử gọi Ollama thật hoặc gửi SMTP thật trong thay đổi này.
