# Giai đoạn 3.3A — Quản lý danh mục CNTT Admin

Cập nhật 18/11/2026. Triển khai bằng code trên schema hiện có; không tạo/chạy migration, không seed và không nhập dữ liệu mẫu vào db_job.

## 1. Phạm vi

Quản lý thật năm danh mục: lĩnh vực, nhóm nghề CNTT, chuyên môn CNTT, cấp bậc, kỹ năng. Có danh sách, tìm tên, lọc ẩn/hiện, sắp xếp, phân trang 15 dòng, thêm/sửa, xác nhận ẩn/hiện và đếm tham chiếu. Không có route DELETE; không mở rộng Admin moderation, Applications, CV hoặc AI.

## 2. Khảo sát database

Đã đối chiếu tài liệu thiết kế/ERD, báo cáo Models/Auth/Company/Jobs, Models, routes, JobInput và màn hình/JavaScript demo. Đọc SHOW CREATE TABLE trực tiếp từ MariaDB 11.5.2 cho cả năm bảng, kiểm tra index, FK, CHECK và số dòng.

Tại khảo sát: db_job vẫn 32 bảng; cả năm bảng danh mục có 0 dòng, companies có 1 dòng, job_versions có 1 dòng, job_version_skills/resume_skills có 0 dòng. Bản tin hiện có được giữ nguyên, không dùng làm fixture. Kiểm tra lại cuối triển khai: cả năm bảng vẫn trống; số FK industry_id/job_specialization_id/job_level_id khác NULL ở job_versions và industry_id ở companies đều bằng 0.

Các bảng có BIGINT UNSIGNED id; code VARCHAR(20) UNIQUE; name/normalized_name VARCHAR(120), normalized_name dùng utf8mb4_bin; description TEXT nullable, CHECK ≤1000; is_active CHECK 0/1; sort_order SMALLINT UNSIGNED; created_at/updated_at DATETIME(6). Bốn bảng trừ skills có slug VARCHAR(160) UNIQUE. Không có cột status dạng enum ở các bảng này.

## 3. Cấu trúc danh mục và quan hệ

JobSpecialization thuộc một JobCategory qua job_category_id. UNIQUE tên chuẩn hóa của chuyên môn theo nhóm; bốn danh mục khác UNIQUE normalized_name toàn bảng. Industry độc lập với JobCategory, không tạo quan hệ giả giữa hai bảng.

Tham chiếu hiển thị: Industry → Company/JobVersion; JobCategory → số chuyên môn và liên kết danh sách con; JobSpecialization/JobLevel → JobVersion; Skill → JobVersionSkill/ResumeSkill. Dùng withCount, không tải nội dung hồ sơ/CV vào trang danh mục.

## 4. File tạo/sửa

Tạo AdminCategoryController, CategoryManagementService, StoreCategoryRequest, UpdateCategoryRequest, CategoryStatusRequest, AdminCategory; hai Blade `Admin/ad_pages/categories/index` và `form`, partial `category-feedback`, JavaScript `category-management.js`, Feature test `AdminCategoryTest` và báo cáo này.

Sửa routes/admin.php, AppServiceProvider (Gate), Admin/layout/admin.blade.php và footer (tách trang thật khỏi demo), AdminPageTest và JobDiscoveryTest (các trang danh mục được kiểm thử theo giao diện thật). Không sửa Model/migration hoặc tạo sidebar mới. Blade danh mục demo cũ còn trên đĩa nhưng không còn route quản lý danh mục trỏ tới.

## 5. Controllers, Services, Requests

Controller điều phối HTTP, tìm/lọc/phân trang và trả view. Form Request giới hạn đầu vào. Service kiểm tra nghiệp vụ, gán từng trường được phép, transaction và xử lý trùng tên. AdminCategory chứa allowlist năm loại và chuẩn hóa Unicode; không nhận tên bảng/class tùy ý từ request.

Gate `manage-categories` chỉ cho User role Admin và status active. Service khóa User và kiểm tra lại Gate; chuyên môn khóa các nhóm cha theo id tăng dần trước khi khóa bản ghi. Thứ tự phù hợp với luồng Jobs khóa danh mục. Phát hiện parent đổi hoặc updated_at cũ rồi từ chối ghi đè. Transaction retry deadlock tối đa 3 lần.

## 6. Routes

Giữ các tên index và đường dẫn sidebar. Với mỗi tiền tố `/admin/danh-muc/{linh-vuc|nhom-nghe|chuyen-mon|cap-bac|ky-nang}`:

| Method | Hậu tố | Chức năng |
| --- | --- | --- |
| GET | / | Danh sách |
| GET | /tao | Form tạo |
| POST | / | Tạo |
| GET | /{item}/chinh-sua | Form sửa |
| PUT | /{item} | Cập nhật |
| PATCH | /{item}/trang-thai | Ẩn/hiện |

30 routes dùng auth, auth.session, role:admin và middleware kiểm tra tài khoản active hiện có. Route ghi giới hạn 30/phút và CSRF web. Item chỉ nhận số, không tồn tại trả 404. Candidate/Recruiter bị từ chối ngay cả khi biết ID hợp lệ; Admin có quyền toàn bộ danh mục, không có khái niệm owner riêng.

## 7. Validation và chuẩn hóa

Tên bắt buộc, tối đa 120 ký tự sau NFKC, trim và gộp whitespace Unicode. Tên chỉ có khoảng trắng/ký tự ẩn bị từ chối. normalized_name là lowercase của tên đã chuẩn hóa, giữ dấu tiếng Việt và dấu +/#; C, C++, C# không bị gộp. Không dùng slug/transliteration để kiểm tra trùng tên. Tên trùng vẫn bị chặn khi bản ghi cũ đang ẩn. Chuyên môn được trùng tên ở nhóm khác.

Mô tả tối đa 1000, thứ tự 0..65535, is_active chỉ boolean. Nhóm nghề bắt buộc cho chuyên môn và bị cấm gửi cho loại khác. Code do server sinh (19 ký tự gồm tiền tố và random hex); slug sinh từ tên có giới hạn chiều dài cùng suffix để tránh va chạm khi bỏ dấu/ký hiệu, không có slug trên skills. Code/slug giữ nguyên khi sửa tên. UNIQUE DB là lớp bảo vệ cuối; lỗi 1062 trả thông báo validation, không lộ SQL. ID, code, slug, normalized_name và timestamp không được mass assign từ client.

## 8. Quản lý ẩn/hiện

Dùng is_active hiện có với badge “Đang hiện/Đang ẩn”. Danh sách có modal Bootstrap xác nhận và cảnh báo tác động. Form thêm/sửa có lựa chọn trạng thái, áp dụng cùng quy tắc Service. Không ẩn nhóm còn chuyên môn active; không tự cascade. Không tạo/chuyển chuyên môn sang nhóm ẩn hoặc bật chuyên môn khi cha ẩn. Cho sửa nội dung chuyên môn đang ẩn ở chính nhóm ẩn cũ để bảo trì lịch sử.

## 9. Bảo vệ dữ liệu đang tham chiếu

Không có xóa cứng. Chuyên môn được bất kỳ JobVersion tham chiếu không thể đổi nhóm, kể cả bản nháp. Đổi trạng thái chỉ ghi bản ghi danh mục, không sửa version/pivot, không xóa tên và không đổi published pointer. Lịch sử vẫn liên kết tới danh mục; tên danh mục khi đọc quan hệ là tên hiện tại, schema không có snapshot riêng cho mọi lần đổi tên.

Khóa nhóm cha phối hợp tạo/chuyển chuyên môn và ẩn nhóm. Khóa bản ghi danh mục phối hợp với kiểm tra active khi lưu/gửi tin hoặc chọn lĩnh vực doanh nghiệp. Kiểm tra tham chiếu/nhóm con dùng locking read để thấy dữ liệu vừa commit thay vì snapshot cũ của REPEATABLE READ. UNIQUE bảo vệ race khi hai Admin tạo tên trùng; deadlock được retry hữu hạn. Không ghi activity_logs giả vì schema hiện giới hạn subject_type user/company/job, chưa có audit danh mục chuyên biệt.

## 10. Giao diện Admin

Tái sử dụng layout/header/sidebar/Bootstrap/assets hiện có, bảng có STT/mã, mô tả, số tham chiếu, ngày tạo, icon thao tác, lọc và phân trang. Form tạo/sửa dùng cùng Blade; lỗi/thành công hiển thị từ Laravel session. Trang thật không nạp các script CRUD demo hoặc thông báo “chưa lưu database”. category-management.js chỉ điền modal bằng dataset và textContent, không lưu sessionStorage, không quyết định quyền.

## 11. Tích hợp Recruiter/Candidate

Jobs 3.2 đã đọc đúng các bảng này nên danh mục Admin tạo dùng được ngay khi hiện. Form Recruiter chỉ chọn mới active, kiểm tra parent và skills ở backend; bản cũ còn hiển thị inactive đã lưu. Submit bị chặn nếu có danh mục ẩn. Company cũng tiếp tục đọc industries thật.

Candidate menu/bộ lọc đọc danh mục active thật, không tạo kết quả mẫu khi trống. Public visibility và cơ chế JobVersion giữ nguyên; ẩn danh mục không tự đóng hoặc xóa tin đã duyệt, các tên liên quan lịch sử còn đọc được.

## 12. Kiểm thử

`php tests/Database/models.php --auth` đạt **130 tests / 1984 assertions**, gồm 8 tests danh mục mới và toàn bộ hồi quy Jobs/Company/Auth/Google/Recovery/Models. Fixtures chỉ nằm trong database ngẫu nhiên có prefix viejob_models_test_, guard từ chối chạy trên db_job. Runner xác nhận schema và lịch sử migration nguồn không đổi. Suite mới bao phủ năm danh mục, quyền, Unicode, code/slug, duplicate, parent, stale form, mass assignment, không DELETE, tham chiếu, dữ liệu lịch sử và tích hợp Jobs/Candidate.

JavaScript regression hiện có đạt **13/13**, cú pháp JavaScript mới đạt. PHP lint, Blade compile, Laravel Pint và git diff --check đạt (Git có cảnh báo chuyển CRLF/LF). Route list xác nhận 30 routes danh mục. Chưa kiểm thử trực quan trình duyệt hoặc chạy stress test đa tiến trình; tests HTTP không thay thế nghiệm thu hình ảnh/khả năng thao tác modal trên thiết bị thật.

## 13. Dữ liệu demo còn lại

Dashboard, người dùng, moderation và các module Admin khác vẫn demo theo phạm vi trước. Mọi link sidebar danh mục trỏ tới backend thật. Code/fixture demo cũ được giữ cho các màn hình mô phỏng và regression tests, không import vào database hoặc sử dụng làm nguồn CRUD danh mục thật.

## 14. Giới hạn

Không triển khai audit danh mục chuyên biệt, nhập hàng loạt, alias kỹ năng, Admin moderation hoặc AI. Không có migration hay thay đổi 32 bảng cần phê duyệt. Không tự sinh bộ danh mục trên database chính. PHP có Normalizer (intl/polyfill hiện có), không cài package mới. Khi triển khai môi trường khác cần bảo đảm Unicode normalizer có sẵn.

## 15. Kiểm thử thủ công

1. Đăng nhập Admin active, mở Quản lý danh mục; thử tạo Lĩnh vực, Nhóm nghề, Cấp bậc, Kỹ năng và Chuyên môn thuộc nhóm.
2. Tìm tên có dấu, lọc ẩn/hiện, thay sắp xếp và chuyển trang; nhấn số chuyên môn để mở đúng nhóm.
3. Thử tên trùng và C/C++/C#, khoảng trắng Unicode; kiểm tra thông báo tiếng Việt.
4. Mở cùng bản ghi hai tab, lưu một tab, thử lưu tab cũ để kiểm tra chặn ghi đè.
5. Thử ẩn nhóm có chuyên môn đang hiện, sau đó ẩn con rồi ẩn cha; thử hiện lại con khi cha ẩn.
6. Trên database test riêng, dùng fixture JobVersion tham chiếu chuyên môn; thử chuyển nhóm và xác nhận bị chặn, tên/pivot lịch sử còn nguyên.
7. Đăng nhập Recruiter kiểm tra danh mục mới trong form và gửi duyệt; thử ẩn danh mục rồi gửi lại. Candidate kiểm tra menu/bộ lọc và kết quả thực.
8. Kiểm tra responsive, icon và modal xác nhận trên trình duyệt. Không dùng db_job để nhập fixture hoặc kiểm thử phá hủy; dữ liệu thật chỉ do Admin nhập chủ động.

## 16. Đề xuất dữ liệu ban đầu — chưa nhập, chờ duyệt riêng

| Danh mục | Ví dụ đề xuất |
| --- | --- |
| Lĩnh vực | Phần mềm và dịch vụ CNTT; Tài chính – ngân hàng; Thương mại điện tử |
| Nhóm nghề | Phát triển phần mềm; Kiểm thử phần mềm; Dữ liệu và AI; An toàn thông tin; Hạ tầng và mạng |
| Chuyên môn | Backend Developer/Frontend Developer thuộc Phát triển phần mềm; Manual Tester/Automation Tester thuộc Kiểm thử phần mềm; Data Analyst thuộc Dữ liệu và AI |
| Cấp bậc | Thực tập sinh; Nhân viên; Trưởng nhóm; Quản lý |
| Kỹ năng | PHP; Laravel; JavaScript; TypeScript; React; SQL; MySQL; Docker; Git; Python; C++; C# |

Đây chỉ là đề xuất để người dùng xem xét, không phải Seeder hoặc dữ liệu đã được phê duyệt nhập.

## Cập nhật danh mục vị trí ngày 19/11/2026

Đã triển khai danh mục Vị trí công việc, một lĩnh vực, 34 tỉnh/thành phố; bỏ ô kinh nghiệm và chọn nhóm nghề trong form Recruiter. Database hiện có 33 bảng. Chi tiết schema, nghiệm thu và backup: [phương án đã chốt](job-catalog-update-proposal.md) và mục 8 của báo cáo **các thay đổi của tôi.md**. Kiểm thử MariaDB riêng: 138 tests, 2.144 assertions đạt. Các mô tả 32 bảng và form cũ phía trên là lịch sử trước cập nhật này.
