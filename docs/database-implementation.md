# Triển khai schema VIEJOB AI — 15/11/2026

## Kết quả

Đã triển khai schema vào `db_job`, MariaDB 11.5.2/InnoDB: **24 bảng nghiệp vụ + 8 bảng hạ tầng**, đúng [thiết kế](database-design.md) và [ERD](database-erd.mmd). Ba migration gốc giữ nguyên; 26 migration mới thuộc batch 2, tổng 29 migration đã ghi nhận.

- Đổi bảng queue `jobs` thành `queue_jobs` trước khi tạo bảng tin tuyển dụng `jobs`.
- Cấu hình queue mặc định dùng `queue_jobs`, dispatch sau commit; `.env.example` có DB_QUEUE_TABLE. Không thay `.env` hiện tại vì không có override bảng queue cũ.
- Thêm PK, FK đơn/phức hợp, UNIQUE, index, CHECK và hai generated columns STORED theo thiết kế.
- Thêm FK published version sau khi đủ hai bảng; FK sessions sau kiểm tra không có user mồ côi.
- Không thêm/bỏ bảng ngoài danh sách đã duyệt. Không import dữ liệu demo, chạy Seeder, sửa Model hay nối giao diện vào database trong đợt schema này.

## Sao lưu và kiểm chứng

Bản sao trước DDL: `storage/app/private/database-backups/20261115_113159/before-migration.sql`. Manifest kiểm chứng nằm cùng thư mục. Đây là thư mục private bị Git bỏ qua; không chia sẻ SQL dump vì có thể chứa session/cache. Không thay file CV/ảnh của dự án.

Đã restore bản sao vào database ngẫu nhiên riêng, đối chiếu số dòng và SHA-256 dữ liệu nền. Đã so sánh SHOW CREATE TABLE của cả 32 bảng với một bản restore được migrate độc lập; các định nghĩa khớp, bỏ qua bộ đếm AUTO_INCREMENT. Dữ liệu nền trước triển khai được giữ nguyên. Database diễn tập đã được dọn; website đã ra khỏi maintenance.

### Sự cố đã xử lý

1. CLI MariaDB trên Windows gặp TLS khi kết nối local: chỉ tắt TLS đối với loopback trong công cụ backup; không áp dụng cho host từ xa.
2. Server báo `Cannot load from mysql.proc` khi dump stored routines. Bản sao bao gồm bảng và triggers, **không bao gồm stored routines**. Không tự sửa system tables MariaDB. Cần kiểm tra việc nâng cấp system tables của server trước vận hành; lỗi này không được coi là đã khắc phục.
3. Kết nối diễn tập từng giữ `name=mysql` từ cấu hình runtime, khiến DDL chạy vào nguồn nhưng migration history ghi ở bản diễn tập. Đã bỏ thuộc tính name khi tạo kết nối riêng, kiểm tra cả tên kết nối và database. Sau đối chiếu đầy đủ 32 schema và dữ liệu nền với bản backup, chỉ đồng bộ 26 dòng migration history còn thiếu vào nguồn, không chạy lại CREATE TABLE hoặc reset database. Đã bổ sung kiểm thử deployment bằng tiến trình riêng, xác nhận database nguồn và migration history không thay đổi khi chạy trên database test.

## Kiểm thử

```powershell
php tests/Database/mariadb-schema.php
php tests/Database/deployment.php
php artisan test --compact
```

- Schema suite: 36 assertions trên MariaDB 11.5.2, fresh install, giữ payload/attempts queue, kiểm tra JSON ảnh, CHECK trạng thái/điểm, FK revision/owner/cùng tin, UNIQUE AI, hai generated slots, tranh chấp CV chính bằng hai kết nối, rollback bảng nghiệp vụ trống và migrate lại.
- Deployment suite: dựng baseline riêng, backup/restore và migrate đủ 32 bảng, giữ queue sentinel, xác nhận không tác động bảng/lịch sử migration của database nguồn.
- Hồi quy ứng dụng: 21 tests, 393 assertions.

Các suite database tạo database tên ngẫu nhiên theo prefix riêng, không dùng migrate:fresh/RefreshDatabase trên `db_job`. Tài khoản chạy test cần quyền tạo/xóa database test. Deployment suite sử dụng maintenance ngắn trong ứng dụng; chạy khi không có worker/producers, không chạy trên hệ thống đang phục vụ người dùng thật.

## Giới hạn của giai đoạn schema và bước tiếp theo

Migration bảo vệ **tối đa một CV chính**, không tự bảo đảm **phải có một CV chính khi còn CV hoạt động**. Composite FK bảo vệ published version thuộc đúng job, không tự kiểm tra version đã được Admin duyệt. Hai quy tắc này, không chuyển nhóm của chuyên môn đã được tham chiếu, quyền owner/role, trạng thái công khai, immutable snapshot và xử lý AI cần Service/Policy/Job trong giai đoạn nối backend; chưa được tuyên bố đã hoạt động chỉ nhờ schema.

Chưa bật Remember-me, xác nhận kỹ năng CV hoặc AI thật. Giao diện vẫn sử dụng dữ liệu demo hiện có. Tiếp theo là Model/relations/casts, Service/Policy và các test nghiệp vụ, sau đó nối từng luồng giao diện. Chính sách retention/xóa/ẩn danh tại mục 13.1 thiết kế phải được hoàn thiện trước dùng dữ liệu cá nhân thật.

## Vận hành migration

`database/deploy-schema.php --apply` dành cho một bản baseline chưa nâng cấp: kiểm tra schema, maintenance, backup, restore kiểm chứng, diễn tập và migrate. Script có đường dẫn CLI XAMPP cố định cho môi trường Windows hiện tại; phải kiểm tra lại khi triển khai máy khác. Script từ chối chạy lại khi đã có queue_jobs. Không dùng script để reset dữ liệu.

`down()` của migration mới từ chối drop bảng có dữ liệu; rollback không phải phương án vận hành sau có dữ liệu thật. Ưu tiên forward migration đã duyệt. Không chạy migrate:fresh, không sửa migration đã triển khai, không rename queue về jobs khi bảng tin còn tồn tại. Lỗi DDL có thể để lại schema từng phần vì implicit commit; giữ maintenance để kiểm tra thay vì retry mù.
