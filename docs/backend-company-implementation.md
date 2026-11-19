# Giai đoạn 3.1 — Hồ sơ doanh nghiệp thật

Cập nhật: 17/11/2026. **Đã triển khai và kiểm thử backend: 109 tests / 1.562 assertions đạt; 13 tests JavaScript đạt.** Không tạo migration, sửa schema, import demo hoặc ghi dữ liệu nghiệp vụ vào `db_job` trong giai đoạn này.

## 1. Mục tiêu và phạm vi

Chuyển tạo/xem/sửa hồ sơ doanh nghiệp Recruiter sang Eloquent và bảng `companies`. Giữ layout, header, sidebar, Bootstrap và các trường mở rộng hiện có. Trang danh sách/chi tiết doanh nghiệp công khai đọc database. Không triển khai Jobs, Applications, CV hoặc AI.

## 2. Khảo sát ban đầu

Đã đối chiếu `database-design.md`, `database-erd.mmd`, `database-implementation.md`, `backend-models-implementation.md`, `backend-auth-implementation.md`, migrations và code thực tế.

- Migration `2026_11_15_000009_create_companies_table.php` có đủ trường: `public_id`, `recruiter_id`, `slug`, thông tin cơ bản/liên hệ/môi trường, `industry_id`, logo/cover riêng và JSON `office_images`.
- UNIQUE `recruiter_id` bảo vệ tối đa một doanh nghiệp mỗi Recruiter; `public_id` và `slug` cũng UNIQUE. FK đến users/industries, CHECK trạng thái và JSON tối đa sáu ảnh đã tồn tại.
- Company Model có relationships recruiter, industry, jobs, activityLogs; `CompanyStatus` gồm active/locked; timestamp có microsecond.
- Trước thay đổi: `CompanyProfiles` lưu session, tự khởi tạo doanh nghiệp demo; JS đọc sessionStorage. Đã gỡ service session này khỏi ứng dụng.
- Không cần migration/Model mới. Lỗi kết nối MariaDB 1045 ở lần trước đã được giải quyết. Test runner đã đối chiếu schema hiện tại với migrations, chạy trên database MariaDB riêng rồi xác nhận schema và lịch sử migration nguồn không thay đổi.

## 3. File tạo/sửa

Tạo:

- `app/Services/CompanyService.php`, `CompanyImageStorage.php`.
- `app/Http/Requests/StoreCompanyRequest.php`, `UpdateCompanyRequest.php`.
- `tests/Unit/CompanyImageStorageTest.php` và báo cáo này.

Sửa:

- Controllers: `CompanyProfileController`, `RecruiterPageController`, `CandidatePageController`.
- `SaveCompanyRequest`, `CompanyPolicy`, `routes/recruiter.php`.
- Blade employer: company, company-form, layout; Candidate: companies, company.
- `public/recruiter/js/recruiter.js`.
- Tests: `CompanyProfileTest`, `RecruiterPageTest`, `AuthenticationTest`, runner `tests/Database/models.php`.

Xóa service cũ `app/Services/CompanyProfiles.php`. Không sửa Model, migration, authentication service hoặc credential.

## 4. Routes

| Method / URL | Route name | Chức năng |
| --- | --- | --- |
| GET `/nha-tuyen-dung/quan-ly/doanh-nghiep` | recruiter.company | Hồ sơ của người đang đăng nhập hoặc trạng thái chưa có hồ sơ |
| GET `.../doanh-nghiep/tao` | recruiter.company-create | Form tạo; đã có hồ sơ thì chuyển về trang quản lý |
| GET `.../doanh-nghiep/chinh-sua` | recruiter.company-edit | Form sửa; chưa có thì chuyển sang tạo |
| POST `/nha-tuyen-dung/ho-so-doanh-nghiep` | recruiter.company-store | Tạo thật |
| PUT `/nha-tuyen-dung/ho-so-doanh-nghiep/{company}` | recruiter.company-update | Sửa bằng public UUID và Policy |
| GET `/nha-tuyen-dung/ho-so-doanh-nghiep/{company}/anh/{slot}` | recruiter.company-media | Ảnh quản trị của đúng chủ hồ sơ |
| GET `/cong-ty` | companies | Danh sách thật, 12 hồ sơ/trang |
| GET `/cong-ty/{company}` | companies.show | Chi tiết thật, đủ điều kiện công khai |
| GET `/cong-ty/{company}/anh/{slot}` | companies.media | Ảnh công khai, kiểm tra trạng thái lại mỗi request |

`{company}` là UUID; slot chỉ logo/cover/office-0…5. Routes Recruiter nằm trong auth/auth.session/role:recruiter, với middleware kiểm tra active chung. POST/PUT giữ CSRF, throttle 20/phút và session blocking. Không có route xóa doanh nghiệp.

## 5. Trách nhiệm các lớp

- Controller điều phối HTTP, truy vấn hồ sơ theo Auth, render Blade và trả ảnh.
- Form Requests kiểm tra quyền và validation. Input UI `summary`, `size`, `ot_policy` được Service ánh xạ sang `short_description`, `company_size`, `overtime_policy`.
- CompanyService giữ whitelist scalar, khóa bản ghi, transaction, chống cập nhật từ form cũ, kiểm tra lĩnh vực và quản lý ảnh sau commit.
- CompanyImageStorage xác minh MIME/giải mã ảnh, sinh đường dẫn ngẫu nhiên và giới hạn dọn file trong namespace sở hữu.
- CompanyPolicy giữ quyền create/update hiện có; bổ sung viewManagement chỉ chủ Recruiter active và viewPublic kiểm tra company/recruiter active. Admin không được sửa qua route Recruiter. Chủ hồ sơ vẫn xem hồ sơ locked và ảnh riêng, không được sửa hoặc xem qua URL công khai.

## 6. Tạo doanh nghiệp

Form mới không tự sinh dữ liệu mẫu. Chỉ nhận dữ liệu validated; owner lấy từ Auth, status active do server đặt, public UUID/slug do server sinh. Không nhận id, owner/recruiter/status hoặc đường dẫn ảnh từ client.

Trong transaction, khóa User, kiểm tra lại role/status và doanh nghiệp hiện có, sau đó INSERT. User row lock tuần tự hóa hai request cùng owner; UNIQUE recruiter_id là lớp bảo vệ DB cuối cùng. Gửi lại POST báo lỗi và không tạo bản ghi thứ hai. Đăng ký Recruiter không bị thay đổi và không tự tạo Company.

## 7. Cập nhật doanh nghiệp

Form Request kiểm tra Policy theo bản ghi UUID trước validation. Service khóa User rồi Company, kiểm tra quyền lại và so sánh `updated_at` của form với dữ liệu hiện tại. Form cũ bị từ chối, người dùng tải lại để tránh ghi đè thay đổi mới.

Lĩnh vực lấy từ `industries` thật: được chọn giá trị active hoặc giữ chính giá trị inactive đã lưu; không được chọn một lĩnh vực inactive mới. Trường này nullable nên vẫn tạo được hồ sơ khi chưa có danh mục thật. Không suy diễn tên lĩnh vực demo thành FK.

Lỗi validation trả về form và dữ liệu cũ; lỗi ghi DB được chuyển thành thông báo không lưu được hồ sơ. Thành công chuyển về trang quản lý với thông báo. Ảnh thay thế chỉ dọn sau commit, sau kiểm tra không còn được tham chiếu; lỗi dọn file được ghi cảnh báo, không báo sai rằng hồ sơ lưu thất bại.

## 8. Upload và Storage

- JPG/JPEG, PNG, WebP; kiểm tra extension, MIME thực, image, kích thước tối đa 5000×5000 và giải mã bằng GD ở server. Mã hóa lại để bỏ metadata/nội dung nối thêm; từ chối nội dung PHP nhận diện được.
- Logo tối đa 2 MiB; cover 5 MiB; mỗi ảnh văn phòng 3 MiB. Tổng gallery sau giữ/xóa/thêm tối đa sáu ảnh. Thứ tự giữ nguyên rồi nối ảnh mới; JSON chỉ chứa path do server sinh và caption null.
- Private disk `local`: `storage/app/private/companies/<public_id>/<logo|cover|office>/<uuid>.<ext>`. Không dùng tên file client hoặc public symlink.
- Ảnh được phục vụ từ path đang được Company tham chiếu, đúng namespace. Response có MIME ảnh, `nosniff`, cache private/no-store. Không xuất đường dẫn disk cho Candidate.
- File mới được staging trước transaction, cố gắng dọn nếu lỗi. File cũ không xóa trước commit. Nếu process bị ngắt hoặc DB/storage không truy cập được khi dọn, có thể còn file mồ côi; cần đối soát có kiểm soát trước vận hành, chưa có job tự động xóa.
- **Đã bật `extension=gd` trong `C:\xampp\php\php.ini`**, sao lưu cấu hình trước khi sửa vào thư mục TEMP với tiền tố `viejob-php-before-gd-`. PHP CLI xác nhận GD enabled; bộ tests chạy thành công không cần `--with-gd`. `upload_max_filesize` và `post_max_size` hiện đều 40M, đủ tổng ảnh tối đa 25 MiB. Apache kiểm tra cấu hình trả Syntax OK. Lệnh nạp lại bằng `httpd -k restart` không thực hiện được vì XAMPP không đăng ký service Apache2.4; cần Stop/Start Apache trong XAMPP Control Panel để tiến trình web hiện có nạp GD (hoặc khởi động lại `php artisan serve` nếu dùng server đó). Chưa xác nhận GD trong tiến trình web cũ.

## 9. Trang công khai

Danh sách và chi tiết thật chỉ lấy Company active có Recruiter active đúng role. Chỉ đưa projection các trường công khai vào Blade; không đưa email/phone quản lý, owner, status nội bộ, đường dẫn Storage hoặc logs. Website chỉ http(s); Blade escape nội dung.

Không ghép tin demo vào doanh nghiệp thật bằng tên. Khu vực tin tuyển dụng thật hiện trống vì module Jobs chưa triển khai. Route `/cong-ty/mau/{id}` được giữ cho luồng demo cũ, có nhãn minh họa; không trộn vào danh sách doanh nghiệp thật.

## 10. Kiểm thử

Đã chạy:

- `php tests/Database/models.php --auth`: **109 tests / 1.562 assertions đạt**, bao gồm Authentication, Google Login, Password Recovery, Google-only Password Setup, Models, Feature và Unit.
- Trong bộ trên, `CompanyImageStorageTest` có **3 tests / 11 assertions đạt**, gồm cách ly namespace, chống traversal/dọn file công ty khác, chặn PHP nối ảnh và tên ngẫu nhiên.
- `node --test tests/recruiter-domain.test.cjs tests/admin-domain.test.cjs tests/admin-categories.test.cjs`: **13 tests đạt**.
- `node --check public/recruiter/js/recruiter.js`, biên dịch Blade, Pint và kiểm tra whitespace đã chạy. Blade cache đã được dọn sau kiểm tra.

Lần chạy đầu sau khi DB hoạt động phát hiện GD chưa bật và hai fixture kiểm thử chuyển tài khoản nhưng giữ hash phiên đăng nhập cũ. Đã bật GD và sửa helper đăng nhập của `CompanyProfileTest` để tạo phiên sạch khi đổi user, phù hợp middleware `auth.session`; không nới lỏng kiểm tra bảo mật ứng dụng. Sau sửa đã chạy lại toàn bộ suite và đạt kết quả trên.

Đã chạy đạt 14 Feature tests hồ sơ: tạo thật, roles/guest, tạo trùng, whitelist/mass assignment, cập nhật, IDOR, locked, public projection, upload không hợp lệ, rollback/gallery overflow, thay ảnh sau commit, stale update, lỗi CHECK tại DB, lĩnh vực inactive và tách demo. Các test mới dùng DB thật riêng, commit thật để kiểm tra dọn ảnh; teardown chỉ xóa fixture trên database có prefix được kiểm tra.

Lệnh kiểm thử hồi quy:

```powershell
# Nếu GD đã bật trong php.ini:
php tests/Database/models.php --auth
# Nếu chỉ bật GD cho tiến trình PHPUnit kiểm thử:
php tests/Database/models.php --auth --with-gd
```

Runner chỉ đọc schema/history nguồn, tạo MariaDB ngẫu nhiên `viejob_models_test_<12hex>`, migrate trên DB riêng, chạy cả Auth/Google/Password Recovery/Models/Feature/Unit và dọn chính DB test. `--with-gd` chỉ bật extension trong subprocess test, không sửa PHP web. Mail/Google không gọi thật trong tests. Chưa stress test request đồng thời nhiều worker hoặc kiểm thử trình duyệt có lưu thật; script browser demo cũ không phải bằng chứng nghiệm thu module mới.

## 11. Dữ liệu demo còn lại

Dashboard Recruiter, Jobs, Applications, AI, Admin và các module Candidate chưa nối backend vẫn giữ dữ liệu demo riêng cùng thông báo hiện có. Company page/form thoát khỏi JS demo trước khi đọc sessionStorage; form ảnh chỉ preview/validate, submit HTTP Laravel. Session doanh nghiệp cũ không được import. Auth vẫn có cleanup tên session cũ để tương thích phiên trước.

## 12. Giới hạn và bước vận hành còn lại

1. Nạp lại PHP web qua XAMPP/Artisan như mục 8 để áp dụng GD cho tiến trình đang chạy, rồi xác minh upload trên trình duyệt.
2. Chưa chạy screenshot desktop/mobile hoặc stress test nhiều worker. Đã kiểm tra render Blade và nghiệp vụ qua Feature tests; không coi đó là kiểm thử trực quan trên trình duyệt.
3. File mồ côi do process bị ngắt cần đối soát theo mục 8; chưa có tác vụ tự dọn tự động.

Không cần phê duyệt schema mới. Phần backend Giai đoạn 3.1 và kiểm thử MariaDB đã hoàn tất; các giới hạn tích hợp/vận hành ở trên được giữ rõ để kiểm tra trước sử dụng thực tế.

## 13. Hướng dẫn kiểm thử thủ công

1. Dùng môi trường kiểm thử riêng, đăng nhập Recruiter chưa có doanh nghiệp. Mở Doanh nghiệp: phải thấy trạng thái chưa có hồ sơ, không có hồ sơ demo tự tạo.
2. Tạo hồ sơ đầy đủ, đính kèm ảnh hợp lệ. Tải lại/đăng xuất rồi đăng nhập: dữ liệu vẫn tồn tại. Mở cửa sổ ẩn danh kiểm tra trang công khai, không có email/phone quản lý.
3. Mở lại URL tạo: chuyển về hồ sơ hiện có. Gửi lặp POST không tạo thêm doanh nghiệp.
4. Chỉnh sửa; thử form cũ ở tab thứ hai. Tab cũ phải báo cần tải lại. Thử ảnh PHP/SVG, quá dung lượng và gallery hơn sáu ảnh; ảnh đã lưu không bị mất khi lỗi.
5. Recruiter B không sửa được UUID của A hoặc mở ảnh quản trị của A. Candidate/Admin không dùng được routes quản lý Recruiter.
6. Với fixture Company locked trên DB test, chủ vẫn xem được trang quản lý nhưng không sửa; URL công khai/ảnh công khai trả 404. Khóa Recruiter cũng loại doanh nghiệp khỏi trang công khai.
7. Kiểm tra các module demo khác, đăng nhập email/Google và recovery bằng bộ mock; không chạy script demo có ghi dữ liệu vào `db_job`.

## 14. Cập nhật hồ sơ và schema theo phê duyệt ngày 18/11/2026

Đã bỏ tên viết tắt, mô tả ngắn, năm thành lập, phúc lợi, công nghệ và ảnh bìa khỏi form và các trang hồ sơ Recruiter/Candidate/Admin. Không thay trường quyền lợi của tin tuyển dụng. Giới thiệu chi tiết đổi thành Giới thiệu doanh nghiệp; thêm Giới thiệu môi trường làm việc (tối đa 3000 ký tự), có validation backend và lưu database thật.

CompanyOptions cung cấp 17 lựa chọn mô hình gồm Mô hình khác; không coi đây là danh mục loại hình pháp lý. Danh sách quốc gia/vùng lãnh thổ gồm 249 mã ISO alpha-2 với tên tiếng Việt được dựng bằng Intl.DisplayNames của Node/ICU, lưu tĩnh ở resources/data/countries-vi.json. Giá trị mô hình/quốc gia cũ ngoài danh sách vẫn được giữ và cho chọn chính giá trị đã lưu. Frontend select và backend allowlist thống nhất.

Checkbox Không có website lưu website NULL. Thứ bắt đầu/kết thúc chỉ nhận 1..7; lưu working_hours dưới dạng “Thứ Hai – Thứ Sáu”; khoảng qua cuối tuần được phép. Để trống cả hai khi sửa giữ lịch cũ, thiếu một đầu bị chặn. OT có hai lựa chọn Có làm thêm giờ/Không làm thêm giờ; chính sách cũ không bị tự suy diễn, để trống khi sửa giữ nguyên. Preview 180×120, logo hồ sơ 100×100, gallery cao 200px với object-fit/overflow giữ ảnh trong khung.

**Địa chỉ chỉ nhập thủ công theo quyết định mới nhất. Không tích hợp Google Maps, không cần API key hoặc billing.**

### Migration và dữ liệu

Đã áp dụng `2026_11_18_120000_simplify_company_profile` lên db_job: bỏ short_name, short_description, founded_year, benefits, technologies, cover_image_path và ba CHECK liên quan; thêm work_environment TEXT NULL với CHECK tối đa 3000. Giữ 32 bảng, PK/FK/index và mọi dữ liệu ở các cột còn lại. Không backfill nội dung phúc lợi sang môi trường làm việc, không seed.

Chạy `php database/deploy-company-profile.php --apply`: maintenance, backup đầy đủ, restore kiểm chứng toàn bộ dữ liệu/schema, diễn tập up/down/up rồi mới áp dụng nguồn. Backup riêng tư: `storage/app/private/database-backups/20261118_145159_company_profile/before-migration.sql`; manifest cùng thư mục lưu hash backup và đối chiếu dữ liệu. Dữ liệu sáu cột đã bỏ còn trong bản backup, không còn ở bảng đang hoạt động. Ảnh bìa cũ trên storage không bị xóa tệp tự động, không còn hiển thị/được route cover phục vụ.

Down chỉ dựng lại cấu trúc các cột cũ, không khôi phục nội dung; từ chối nếu work_environment đã có dữ liệu. Khi cần lấy lại nội dung cũ phải dùng backup đã kiểm chứng trong quy trình riêng, không rollback mù hoặc ghi đè dữ liệu mới.

### Kiểm thử và giới hạn

Trước áp dụng: `php tests/Database/models.php --auth --pending-company-profile` đạt **137 tests / 2090 assertions**. Sau áp dụng: `php tests/Database/models.php --auth` tiếp tục đạt **137 tests / 2090 assertions**, chỉ dùng database test riêng, schema/lịch sử migration nguồn giữ nguyên trong lúc test. Có kiểm tra nội dung môi trường làm việc lưu/hiển thị, giới hạn 3000, sáu cột không tồn tại, lựa chọn ngày/mô hình/quốc gia/OT, website NULL và hồi quy phân quyền/upload. Pint, Blade compile, cú pháp JavaScript đạt. Chưa kiểm thử trực quan trình duyệt.
