# Giai đoạn 3.2 — Quản lý tin tuyển dụng CNTT thật

Cập nhật 18/11/2026. Đã triển khai chức năng và migration theo phương án A được phê duyệt. Môi trường MariaDB 11.5.2, PHP 8.2.0, Laravel 12, Windows/XAMPP. HTTP được kiểm thử tự động trên database riêng; chưa kiểm thử trực quan bằng trình duyệt.

## 1. Phạm vi triển khai

Danh sách, tìm kiếm, lọc, phân trang; tạo/sửa nháp; xem trước riêng; gửi duyệt; tạm dừng/mở lại/đóng tin; bảo toàn phiên bản công khai. Candidate tìm kiếm và xem tin đủ điều kiện từ MariaDB. Không triển khai Admin moderation, Applications, CV hoặc AI trong giai đoạn này.

## 2. Quyết định schema được phê duyệt

Giữ 32 bảng. Bản nháp chỉ bắt buộc title, không dùng giá trị giả. Cho phép NULL đúng 13 cột job_versions: position, description, requirements, benefits, salary_min, salary_max, location, work_mode, employment_type, quantity, experience_summary, job_specialization_id, application_deadline. industry_id/job_level_id đã nullable trước đó. Giữ nguyên kiểu cột, FK/PK/UNIQUE, pending_slot, tiền tệ VND và salary_negotiable mặc định false. Không thêm UUID, pending pointer hoặc bảng mới.

## 3. Migration đã tạo

`database/migrations/2026_11_17_100000_allow_incomplete_job_drafts.php` kiểm tra dữ liệu trước DDL. CHECK title có nội dung; khi không còn draft, cả 13 cột và industry_id/job_level_id/submitted_at phải NOT NULL, văn bản bắt buộc không rỗng sau TRIM. Các CHECK lương, số lượng, loại/hình thức làm việc và độ dài TEXT xử lý NULL tường minh. Không sửa baseline.

Down từ chối nếu còn NULL trong 13 cột; không xóa hoặc tự điền dữ liệu. MariaDB yêu cầu tháo rồi dựng lại nguyên FK chuyên môn khi đổi về NOT NULL, vì vậy rollback chỉ thực hiện khi ngừng ghi. Up giữ FK hiện hữu.

## 4. Backup và kiểm tra restore

Đã chạy `php database/deploy-job-drafts.php --apply`. Backup riêng tư: `storage/app/private/database-backups/20261118_043458_job_drafts/before-migration.sql`. `manifest.json` cùng thư mục lưu SHA-256 backup, count/hash từng bảng trước/sau, kết quả restore và diễn tập. Không đưa nội dung backup hoặc credentials lên Git.

Restore sang database ngẫu nhiên có prefix `viejob_drafts_restore_`; đối chiếu toàn bộ schema và dữ liệu với nguồn trước khi triển khai. Backup gồm bảng và triggers, không gồm stored routines như quy trình baseline. Database tạm đã được dọn sau kiểm chứng.

## 5. Diễn tập up/down/up

Đã đạt trên database restore trước khi tác động nguồn. Runner `tests/Database/models.php --auth --pending-job-drafts` cũng kiểm tra migration/rollback/reapply trên database test riêng. So sánh schema chuẩn hóa thứ tự CHECK/FK do ALTER TABLE thay đổi thứ tự khai báo; không bỏ qua nội dung ràng buộc hoặc kiểu cột.

## 6. Kết quả áp dụng migration

PASS trên db_job: vẫn 32 bảng, dữ liệu từng bảng giữ nguyên; migrations thêm đúng một bản ghi mới. Schema sau triển khai khớp bản diễn tập. Ứng dụng đã thoát maintenance. Không tạo User/Company/Job thử, không seed/reset database thật.

## 7. Controller, Service, Request và Policy

- RecruiterJobController: nhận request, truy vấn danh sách theo chủ sở hữu, điều hướng và trả view.
- RecruiterJobService: transaction, khóa User → Company → Job → Version, quản lý nội dung/kỹ năng, gửi duyệt và chuyển trạng thái.
- StoreJobRequest, UpdateJobRequest, SubmitJobRequest: kiểm tra quyền và đầu vào.
- JobInput: trường được phép, quy tắc draft/submit, nhãn giao diện.
- JobPolicy: chủ sở hữu, role, trạng thái User/Company/Job và quyền công khai.
- PublicJobService: truy vấn công khai dùng chung cho danh sách, chi tiết, trang chủ và trang doanh nghiệp.

Job thêm quan hệ latestVersion. Không mở mass assignment trường hệ thống; chỉ gán nội dung được phép. Pivot kỹ năng cập nhật tường minh theo version.

## 8. Routes

Tiền tố `/nha-tuyen-dung/quan-ly/tin-tuyen-dung`:

| Method | Đường dẫn tiếp theo | Chức năng |
| --- | --- | --- |
| GET | / | Danh sách |
| GET | /tao | Form tạo |
| POST | / | Lưu nháp mới |
| GET | /{job} | Chi tiết quản lý |
| GET | /{job}/chinh-sua | Form sửa |
| PUT | /{job} | Lưu thay đổi |
| GET | /{job}/xem-truoc | Xem riêng bản đã lưu |
| POST | /{job}/gui-duyet | Gửi bản đã lưu |
| POST | /{job}/{action} | pause/reopen/close |

Có auth/session/role, CSRF và giới hạn tần suất ghi. ID job là số theo schema. Candidate dùng `/viec-lam`, `/viec-lam/{id}`; demo tách `/viec-lam/mau/{id}`.

## 9. Tạo và chỉnh sửa draft

Recruiter cần doanh nghiệp thuộc sở hữu và active. Tạo Job draft cùng Version 1 draft trong một transaction. Chỉ nhập title vẫn lưu được; trường thiếu giữ NULL. Form nhập VND/tháng, không tự biến dữ liệu rỗng thành 0. GET form/xem trước không tạo phiên bản.

Creation key trong session kết hợp session blocking chống gửi lặp thông thường; token đã dùng trả về Job cũ. Sửa dùng version_id và updated_at để phát hiện form cũ. Không cho tự gán company_id, review_status, published pointer hoặc trường hệ thống.

## 10. Quản lý JobVersion

Draft sửa tại chỗ. Approved/rejected giữ bất biến; thao tác lưu tạo draft tiếp theo cùng kỹ năng tương ứng. Khi có pending, không cho sửa/gửi thêm. Khóa Job cha tuần tự hóa thay đổi; UNIQUE(job_id,version_number) và UNIQUE(job_id,pending_slot) bổ sung bảo vệ database. Recruiter lưu/gửi không đổi published_version_id.

## 11. Submit kiểm duyệt

Chỉ gửi bản đã lưu, token hiện tại, review_status=draft. Kiểm tra đủ nội dung, lương, số lượng, danh mục active, chuyên môn đúng nhóm, ít nhất một kỹ năng, deadline không trước ngày hiện tại theo Asia/Ho_Chi_Minh. Thành công chuyển pending và ghi submitted_at; lỗi giữ nguyên dữ liệu. Không giả lập quyết định Admin hoặc tạo job_moderation_logs khi submit.

## 12. Trạng thái Job

Open → paused/closed; paused → open/closed. Mở lại cần bản công khai đã duyệt, còn hạn và User/Company active. Closed/blocked không được Recruiter mở lại. Lặp cùng trạng thái không tạo log thừa. activity_logs ghi create/open/pause/close, actor, subject Job và snapshot trước/sau. Không xóa cứng tin.

## 13. Danh mục và kỹ năng

Form lấy industries/job_categories/job_specializations/job_levels/skills từ MariaDB. Nhóm nghề lọc chuyên môn ở JavaScript và backend. Tạo mới chỉ chọn active; bản cũ vẫn hiển thị inactive đã lưu, nhưng gửi duyệt yêu cầu active. Không nhận skill tự do hoặc tự tạo danh mục từ dữ liệu demo.

Khi khảo sát, cả 5 bảng danh mục trống. Có thể lưu nháp; cần danh mục thật qua giai đoạn quản trị phù hợp trước khi gửi duyệt. Không tự seed dữ liệu mẫu lên db_job.

## 14. Tích hợp giao diện

Giữ template, sidebar/navbar và màu hiện tại. Danh sách có STT, trạng thái, hành động kèm icon, tìm kiếm và phân trang. Form hiện cảnh báo danh mục thiếu, phiên bản, trạng thái và lý do từ chối. Trang tin thật không chạy Recruiter demo JavaScript hoặc thông báo lưu giả.

Candidate lấy menu, bộ lọc, tin và doanh nghiệp đang tuyển từ backend. Không dùng sessionStorage danh mục demo ghi đè dữ liệu thật. Gợi ý AI thật hiển thị chưa triển khai; liên kết trải nghiệm demo được ghi rõ.

## 15. Điều kiện công khai

Chỉ đọc publishedVersion thuộc đúng Job và approved; Job open, expires_at trong tương lai, deadline chưa qua; Company/Recruiter active và chủ tài khoản có role Recruiter. Không dùng latestVersion cho nội dung công khai. Không đủ điều kiện trả 404 ở chi tiết hoặc bị loại khỏi danh sách/trang chủ/tin doanh nghiệp. Bộ lọc kết hợp dùng phiên bản công khai, không tìm trúng nội dung nháp.

## 16. Kết quả kiểm thử

Trước migration: `php tests/Database/models.php --auth --pending-job-drafts` đạt **122 tests / 1732 assertions** trên MariaDB riêng. Sau triển khai: `php tests/Database/models.php --auth` tiếp tục đạt **122 tests / 1732 assertions**, xác nhận schema và lịch sử migration nguồn không đổi trong lúc test. Gồm Models/Auth/Google/Recovery/Company hồi quy và Job draft/schema/permissions/version/submit/public filters. Google/mail được mock hoặc cấu hình test; không gọi dịch vụ thật.

JavaScript regression **13/13** tests. Laravel Pint đạt; Blade cache/clear đạt; `git diff --check` đạt (chỉ cảnh báo CRLF/LF của Git); kiểm tra cú pháp JavaScript và 9 routes Recruiter đạt. Chưa kiểm thử trực quan bằng trình duyệt hoặc đo tải đồng thời nhiều tiến trình. Khóa, stale tokens, UNIQUE pending và lặp request được kiểm tra bằng tests, không coi đó là benchmark concurrency.

## 17. Dữ liệu demo còn lại

Admin moderation, ứng tuyển, lưu việc, CV, AI và một số dashboard vẫn demo. Tin thật không có nút gửi vào luồng ứng tuyển/lưu việc demo. Link demo chuyển tới chi tiết demo riêng để tránh trùng ID tin thật. Không biến Admin demo thành thao tác ghi database trong giai đoạn này.

## 18. Giới hạn và phần chưa triển khai

Admin duyệt/từ chối thật là giai đoạn sau; Recruiter gửi pending thành công nhưng Admin demo không công khai tin thật. Fixtures approved/rejected chỉ ở database test. Không tự thêm danh mục thật.

Creation key chống gửi lại trong session, chưa là idempotency ledger bền vững qua sự cố giữa DB commit và ghi session. Không thêm bảng/cột ngoài phê duyệt. Không mở rộng Applications/CV/AI hoặc khôi phục xác nhận kỹ năng. Cần kiểm tra trực quan trước nghiệm thu giao diện. Không có thay đổi schema ngoài phương án A cần xin duyệt trong phạm vi này.

## 19. Hướng dẫn kiểm thử thủ công

Thực hiện trên database test riêng, không tạo fixture trên db_job:

1. Đăng nhập Recruiter có Company active, tạo nháp chỉ có title; tải lại xác nhận lưu thành công.
2. Mở hai tab: lưu tab thứ nhất, xác nhận tab thứ hai bị báo dữ liệu cũ. Dùng Recruiter khác để kiểm tra từ chối quyền qua URL.
3. Tạo danh mục fixture ở DB test; điền đầy đủ form, lưu rồi gửi duyệt. Xác nhận pending không sửa được và chưa công khai.
4. Dùng fixture approved của tests, không thao tác Admin demo; sửa bản đã duyệt, xác nhận Candidate giữ nội dung cũ lúc draft/pending.
5. Thử pause/reopen/close, expired, locked; tin không đủ điều kiện phải biến mất và chi tiết trả 404.
6. Kết hợp bộ lọc nhóm nghề/chuyên môn/địa điểm/lương/kinh nghiệm/hình thức/kỹ năng; kiểm tra phân trang, menu desktop/mobile và dấu tiếng Việt.
7. Kiểm tra Auth/Google/Recovery/Company theo các báo cáo trước; không gửi email hoặc OAuth thật khi chạy suite tự động.

## Cập nhật danh mục vị trí ngày 19/11/2026

Đã triển khai danh mục Vị trí công việc, một lĩnh vực, 34 tỉnh/thành phố; bỏ ô kinh nghiệm và chọn nhóm nghề trong form Recruiter. Database hiện có 33 bảng. Chi tiết schema, nghiệm thu và backup: [phương án đã chốt](job-catalog-update-proposal.md) và mục 8 của báo cáo **các thay đổi của tôi.md**. Kiểm thử MariaDB riêng: 138 tests, 2.144 assertions đạt. Các mô tả 32 bảng và form cũ phía trên là lịch sử trước cập nhật này.
