# Giai đoạn 2 — Authentication & Authorization

Ngày hoàn thành: 16/11/2026. Laravel 12, PHP 8.2, MariaDB 11.5.2.

## 1. Phạm vi và kết quả khảo sát

Trước triển khai, các trang tài khoản dùng phiên demo; Recruiter và Admin dùng dữ liệu JavaScript/sessionStorage. Các form và layout hiện có được tái sử dụng. Xác thực hiện dùng Laravel Session Authentication và bảng users thật. Nội dung nghiệp vụ vẫn là demo, không tự chuyển dữ liệu minh họa vào database.

Phần Auth email/mật khẩu không thêm package. Google OAuth được triển khai sau phê duyệt ở mục 9: thêm Socialite, một migration thêm google_subject và ẩn trường này trong User. Giữ 24 bảng nghiệp vụ và 8 bảng hạ tầng; không tạo Seeder/Admin mặc định, không sửa mysql.proc. Kiểm thử ghi dữ liệu chỉ dùng MariaDB riêng; migration Google đã được áp dụng vào db_job sau khi tests đạt.

## 2. File tạo và sửa

### Tạo mới

- `app/Http/Controllers/AuthController.php`.
- `app/Console/Commands/CreateAdmin.php`: lệnh tạo Admin thủ công, không có tài khoản mặc định.
- `app/Services/AuthService.php`.
- `app/Http/Requests/Auth/RegisterRequest.php`, `LoginRequest.php`, `ChangePasswordRequest.php`.
- `app/Http/Middleware/RequireRole.php`, `EnsureActiveAccount.php`.
- `app/Policies/CompanyPolicy.php`, `JobPolicy.php`, `ResumePolicy.php`, `ApplicationPolicy.php`.
- `public/js/auth-session.js`.
- `resources/views/auth/password-errors.blade.php`.
- `tests/AuthenticatedDemoTestCase.php`.
- `tests/Auth/AuthenticationTest.php`, `PoliciesTest.php`, `phpunit.xml`.
- `docs/backend-auth-implementation.md`.

### Sửa

- `bootstrap/app.php`, `routes/web.php`, `app/Providers/AppServiceProvider.php`: middleware, route và rate limiter.
- `app/Http/Controllers/CandidateDemoController.php`: bỏ endpoint xác thực giả.
- `app/Http/Controllers/CandidatePageController.php`: trạng thái đăng nhập lấy từ Auth.
- `app/Services/CompanyProfiles.php`: kiểm tra chủ sở hữu bản demo bằng người dùng đã xác thực và token phiên.
- Candidate views: `auth-form.blade.php`, `password.blade.php`, `account-panel.blade.php`, `header.blade.php`, `master.blade.php`, `ca_pages/login.blade.php`, `ca_pages/register.blade.php`, `ca_pages/settings.blade.php`.
- Recruiter views trong `resources/views/employer`: `account.blade.php`, `component/header.blade.php`, `component/sidebar.blade.php`, `layout.blade.php`.
- Admin views trong `resources/views/Admin`: `ad_pages/account.blade.php`, `component/header.blade.php`, `component/sidebar.blade.php`, `layout/admin.blade.php`.
- `public/recruiter/js/recruiter.js`, `public/admin-assets/js/admin.js`: tách khóa lưu demo theo tài khoản; bỏ đăng xuất giả.
- `tests/Database/models.php`: thêm lựa chọn chạy bộ Auth trên database tách biệt.
- Feature tests: `AdminPageTest.php`, `CandidatePreviewTest.php`, `CompanyProfileTest.php`, `JobDiscoveryTest.php`, `RecruiterPageTest.php`: dùng tài khoản xác thực đúng vai trò trên database thử nghiệm.

## 3. Route và middleware

| Phương thức | Đường dẫn | Named route | Bảo vệ |
| --- | --- | --- | --- |
| GET | /register | register | guest |
| POST | /register | auth.register | guest, throttle:register, CSRF |
| GET | /login | login | guest |
| POST | /login | auth.login | guest, throttle:login, CSRF |
| POST | /dang-xuat | logout | auth, CSRF |
| POST | /tai-khoan/doi-mat-khau | password.change | auth, auth.session, throttle:5,1, CSRF |

Quên/đặt lại mật khẩu đã được bổ sung backend tại mục 10; gửi email chỉ bật khi SMTP được cấu hình. Không có GET đăng xuất. Endpoint `/demo/tai-khoan/{mode}` đã bỏ.

Theo quyết định chính thức, `/login` là trang đăng nhập chung cho Candidate/Recruiter/Admin; không tạo `/admin/login`. `/register` dùng Blade riêng, không kế thừa trang login và không dùng modal/tab. Hai trang chỉ liên kết qua thẻ a; tái sử dụng partial trường nhập/CSS hiện có. Trang login không có lựa chọn vai trò; trang register chỉ có Candidate/Recruiter. Liên kết quên mật khẩu được ẩn vì chức năng email chưa triển khai. GET `/dang-nhap` và `/dang-ky` chuyển hướng tới URL mới để giữ liên kết cũ; các form POST dùng URL mới.

`RequireRole` đăng ký alias `role` trong `bootstrap/app.php`, đọc UserRole enum từ người dùng xác thực. Nhóm Candidate, Recruiter và Admin áp dụng lần lượt `auth`, `auth.session`, `role:candidate/recruiter/admin`; bao gồm các POST demo, tải PDF/ảnh riêng và đường dẫn `/nha-tuyen-dung`. Guest chuyển tới login; sai vai trò trả 403, kể cả Admin truy cập khu vực Candidate/Recruiter.

`EnsureActiveAccount` nối vào web middleware. Mỗi request có người dùng đều đọc lại users, kiểm tra trạng thái và cập nhật danh tính trong guard. Tài khoản bị khóa hoặc không còn tồn tại sẽ bị xóa các phiên database, đăng xuất và từ chối request (web chuyển login, JSON trả 403). Phiên từ remember cookie cũ bị từ chối; MVP không chấp nhận Remember-me. Phản hồi xác thực có `Cache-Control: private, no-store`.

Trang chủ, tìm việc, chi tiết việc, doanh nghiệp công khai và giới thiệu vẫn truy cập công khai với nội dung minh họa hiện có.

## 4. Luồng xác thực

### Đăng ký

Form Request chuẩn hóa email, kiểm tra tên, email duy nhất, mật khẩu xác nhận tối thiểu 8 ký tự có chữ và số. Chỉ chấp nhận candidate/recruiter từ allowlist. Service kiểm tra lại vai trò, gán status active, hash mật khẩu và lưu trong transaction. Candidate được tạo CandidateProfile; Recruiter chưa tạo Company. Các trường id/status hoặc quyền gửi thêm từ client không được dùng. Vi phạm unique khi đăng ký đồng thời được chuyển thành lỗi validation.

Sau thành công, hủy phiên cũ, đăng nhập không Remember-me và regenerate session. Điều hướng Candidate tới `candidate.profile`, Recruiter tới `recruiter.dashboard`, Admin đăng nhập tới `admin.dashboard`.

Candidate hiện chưa có dashboard riêng; giữ khu vực cá nhân `candidate.profile` làm trang đích theo cấu trúc hiện có. Vai trò điều hướng lấy từ User trong database, không dùng role do client gửi.

### Tạo Admin thủ công

Chạy trong thư mục dự án bằng terminal tương tác:

```shell
php artisan admin:create
```

Nhập họ tên, email, mật khẩu và xác nhận mật khẩu. Mật khẩu được nhập ẩn, không có option truyền mật khẩu trên dòng lệnh và không được in ra. Nếu terminal không hỗ trợ nhập ẩn, lệnh không chuyển sang hiển thị mật khẩu; hãy chạy lại bằng terminal tương tác hỗ trợ. Chế độ `--no-interaction` bị từ chối.

Lệnh chuẩn hóa email, kiểm tra unique (kể cả xử lý lỗi tranh chấp unique lúc lưu), độ dài tên/email và độ mạnh/xác nhận mật khẩu. Email đã tồn tại bị từ chối, không nâng quyền hoặc đổi mật khẩu tài khoản đó. Tài khoản mới có role Admin, status Active, mật khẩu hash; không tạo CandidateProfile/Company, không tự đăng nhập, không tạo Admin mặc định. Laravel tự phát hiện command trong `app/Console/Commands`.

Sau khi tạo thành công, vào `/login` với email/mật khẩu vừa nhập. Chỉ người có quyền vận hành máy chủ được chạy lệnh. Trong lần triển khai này, lệnh chỉ được kiểm thử trên database riêng; chưa tạo Admin trong db_job.

### Đăng nhập và đăng xuất

Laravel Auth kiểm tra email/mật khẩu/status active. Lỗi tài khoản thiếu, sai mật khẩu hoặc locked dùng cùng thông báo. Service khóa bản ghi để kiểm tra lại trạng thái và cập nhật last_login_at. Rate limiter: 5 lần/phút theo email chuẩn hóa + IP, đồng thời 30 lần/phút/IP; đăng ký 5 lần/phút/IP.

Đăng nhập thành công đổi session ID và xóa dữ liệu phiên demo trước đó. Đăng xuất dùng Auth::logout, invalidate session, regenerate CSRF token; remember token cũ được xóa nếu có. Không lưu mật khẩu/token xác thực vào sessionStorage/localStorage.

### Đổi mật khẩu

Form Request kiểm tra mật khẩu hiện tại, mật khẩu mới khác mật khẩu cũ, đủ độ mạnh và khớp xác nhận. Service kiểm tra lại hash/trạng thái dưới row lock, hash mật khẩu mới, xóa remember token và tất cả phiên trong bảng sessions. Người dùng phải đăng nhập lại. `auth.session` kiểm tra hash phiên trên các route bảo vệ, hỗ trợ vô hiệu hóa phiên cũ nếu có request đồng thời. Cơ chế thu hồi dùng session driver database của dự án; cần rà soát lại nếu thay driver.

Không đưa mật khẩu vào log hoặc old input của validation.

## 5. Policy nền tảng

Laravel tự phát hiện các Policy theo convention App/Models và App/Policies; đã kiểm thử qua Gate. Không có quyền Admin bỏ qua toàn bộ Policy.

| Policy | Quyền chính |
| --- | --- |
| CompanyPolicy | Recruiter quản lý công ty của mình đang hoạt động; Admin có quyền moderate riêng; Candidate không sửa; không xóa cứng. |
| JobPolicy | Recruiter tạo/sửa tin của công ty mình đang hoạt động, không sửa tin đóng/khóa; Admin moderate; không xóa cứng. |
| ResumePolicy | Candidate chỉ quản lý CV riêng còn hoạt động; người khác không truy cập thư viện CV. |
| ApplicationPolicy | Candidate xem đơn riêng; Recruiter xem đơn thuộc công ty mình; Admin xem thông tin giám sát nhưng không đổi trạng thái tuyển dụng. |

`JobPolicy::viewPublic` kiểm tra tin Open, published version đúng tin/đúng con trỏ và Approved, hạn ứng tuyển theo ngày Việt Nam, expires_at còn hiệu lực, công ty Active và chủ sở hữu là Recruiter Active. `view` còn cho phép chủ sở hữu/Admin xem để quản lý, vì vậy endpoint công khai thật sau này phải gọi `viewPublic` và lọc danh sách theo cùng điều kiện.

Application tách quyền `viewSnapshot` khỏi `view`: snapshot chỉ dành cho Candidate sở hữu và Recruiter sở hữu tin. Ghi chú nội bộ chỉ Recruiter sở hữu; không mở mặc định cho Candidate/Admin. Rút đơn hoặc đổi trạng thái chỉ được xét khi Applied/Reviewing/Interview và đúng vai trò. Service giai đoạn sau phải kiểm tra từng chuyển trạng thái hợp lệ trong transaction.

Quyền xem CV/đơn không sở hữu trả denyAsNotFound (404) để hạn chế lộ tài nguyên. Các thao tác hoặc khu vực sai quyền trả 403. Các Policy là nền tảng cho CRUD thật; chưa có endpoint CRUD thật để gắn authorize theo bản ghi. Không tuyên bố các thao tác demo đã thành CRUD database.

## 6. Giao diện và ranh giới demo

Form đăng ký, đăng nhập, đổi mật khẩu, đăng xuất và tên/email trên header/sidebar/menu tài khoản đã nối Auth thật. Giữ template, Bootstrap, bố cục và CSS hiện tại. Mật khẩu có nút hiện/ẩn; toàn bộ thao tác xác thực dùng POST + CSRF.

CV, ứng tuyển, việc đã lưu, thông báo, hồ sơ nội dung Candidate, doanh nghiệp, tin tuyển dụng, đánh giá AI và quản trị danh mục vẫn là demo. Các thông báo trên giao diện phân biệt đăng nhập thật với nghiệp vụ minh họa. Dữ liệu mẫu không được import vào bảng nghiệp vụ.

Phiên Candidate/company được xóa khi thay tài khoản. Quyền sửa company demo kiểm tra cả Auth ID và token phiên; file CV demo chỉ lấy từ phiên hiện tại. sessionStorage Recruiter/Admin dùng khóa theo Auth ID và được dọn khi đăng xuất. JavaScript không phải nguồn quyết định quyền. PDF mẫu tĩnh Recruiter vẫn là tài liệu minh họa công khai, không phải CV thật của ứng viên.

## 7. Kiểm thử

Lệnh tích hợp:

```shell
php tests/Database/models.php --auth
```

Runner tạo database MariaDB tên ngẫu nhiên `viejob_models_test_<suffix>`, chạy migration hiện có tại đó, truyền cấu hình riêng cho subprocess PHPUnit và xóa đúng database thử nghiệm khi kết thúc. Kiểm tra guard tên database trước test ghi dữ liệu; không dùng RefreshDatabase/migrate:fresh/db:wipe trên db_job. Đối chiếu schema 32 bảng và lịch sử migration của database chính trước/sau; không thay đổi.

Kết quả cập nhật: **55 tests, 1017 assertions — đạt**, gồm Auth, Policies, tests Models giai đoạn 1 và Feature/Unit. Kiểm tra đăng ký hai vai trò, cấm Admin công khai, duplicate email/hash, login sai/locked, khóa giữa phiên, rate limit, đổi session, logout/cookie cũ, mật khẩu và thu hồi phiên, ma trận vai trò, CSRF thật, IDOR, mass assignment, quyền snapshot/ghi chú, rollback đăng ký khi tạo profile lỗi, và các trang demo sau bảo vệ route.

Bổ sung kiểm tra hai Blade/form login/register riêng biệt, route chuẩn và redirect URL cũ, không có `/admin/login`, điều hướng cả ba vai trò qua POST `/login` bất chấp role giả từ client; tạo Admin bằng command rồi đăng nhập, từ chối email trùng mà không nâng quyền tài khoản cũ, từ chối mật khẩu yếu/xác nhận sai và chế độ không tương tác.

### Sửa lỗi console Windows trong đường dẫn có dấu

Đã tái hiện lỗi `Failed to open stream` ngay tại câu hỏi Họ tên khi chạy command thật từ PowerShell. Symfony QuestionHelper tạm chuyển PHP sang OEM code page rồi mới autoload TerminalInputHelper, khiến đường dẫn UTF-8 của dự án bị đọc sai. Lỗi tiếp tục lan tới autoload Collision/ExceptionHandler; không phải thiếu file vendor hay lỗi database.

Command nạp trước TerminalInputHelper và MissingInputException trên Windows trước khi hỏi, đồng thời dùng finally khôi phục code page khi kết thúc đầu vào/exception. Không sửa vendor, không đổi tên thư mục và không thay cấu hình Windows toàn hệ thống. Bổ sung regression test chạy subprocess `php artisan admin:create` thật (không mock câu hỏi), kiểm tra nhập tên/email không hợp lệ và EOF đều không gây lỗi autoload. Các test tạo tài khoản/kiểm tra mật khẩu vẫn chạy trên MariaDB riêng; không tạo Admin thử trong db_job. Chưa kiểm tra nhập mật khẩu ẩn trực tiếp bằng tay trong terminal của người dùng.

Test đặc biệt dùng database session driver kiểm tra cookie cũ sau logout; test đổi tài khoản không đọc PDF hoặc sửa doanh nghiệp của tài khoản trước. Credential không bị flash vào session khi validation thất bại. Bảy tests Models giai đoạn 1 vẫn nằm trong bộ tích hợp.

```shell
node --test tests/recruiter-domain.test.cjs tests/admin-domain.test.cjs tests/admin-categories.test.cjs
```

Kết quả: **13 tests JavaScript — đạt**. Kiểm tra cú pháp các JavaScript thay đổi bằng node --check; kiểm tra PHP style bằng Laravel Pint. Feature tests kiểm tra render và hành vi HTTP; chưa chạy kiểm thử giao diện trình duyệt thủ công/E2E có tài khoản thật.

## 8. Giới hạn và bước tiếp theo

- Backend quên mật khẩu đã triển khai ở mục 10; Gmail SMTP đã xác thực kết nối thành công và chức năng gửi đã bật. Chưa gửi email kiểm thử thật.
- Không tạo Admin mặc định. Người vận hành dùng `php artisan admin:create` để cấp Admin thủ công trước khi sử dụng khu vực Admin.
- Các script trình duyệt trước đây truy cập trực tiếp trang demo bảo vệ cần được cấp phiên/tài khoản thử nghiệm trước khi dùng lại.
- Tiếp theo: CRUD doanh nghiệp thật với authorize; quản lý phiên bản tin và kiểm duyệt; CV/revision/snapshot và ứng tuyển với Service, transaction và Policy tương ứng. Chưa tích hợp FastAPI/AI trong giai đoạn này.
- Trước vận hành: xác nhận HTTPS/cookie production, mail, cấp quyền quản trị và chính sách lưu trữ/xóa dữ liệu cá nhân đã nêu trong thiết kế database. Không thay đổi cấu hình vận hành hoặc schema trong giai đoạn này.

## 9. Google OAuth — đã triển khai sau phê duyệt

Người dùng đã phê duyệt thêm users.google_subject và triển khai luồng phụ thuộc. Phần khảo sát chờ phê duyệt trước đây được thay thế bằng kết quả dưới đây.

### 9.1. Schema và triển khai

Migration `2026_11_16_210000_add_google_subject_to_users_table.php` thêm `google_subject VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NULL` cùng UNIQUE `users_google_subject_unique`. Không thêm bảng: **24 nghiệp vụ + 8 hạ tầng = 32 bảng**. Nhiều NULL được phép; subject phân biệt hoa/thường, không chuyển thành số. Không backfill theo email. PK/FK, email unique và password NOT NULL giữ nguyên.

Migration đã chạy thành công trên db_job sau khi kiểm thử MariaDB riêng. Đã so sánh các trường User cũ trước/sau, không thay đổi dữ liệu. Không tạo tài khoản Google thử trên database chính. down() từ chối xóa cột khi có identity đã liên kết để tránh người dùng mất phương thức đăng nhập. Không dùng rollback để gỡ tính năng đang có người dùng; cần phương án phục hồi/chuyển phương thức xác thực riêng.

User chỉ thêm google_subject vào hidden, không thêm fillable; Service gán explicit. Database-design.md và ERD tổng thể được cập nhật nhất quán.

### 9.2. File và thành phần

- `composer.json`, `composer.lock`: Socialite v5.32.0, constraint ~5.0; thêm 6 package, không nâng package cũ.
- `config/services.php`, `.env.example`: Google client_id/client_secret/redirect lấy từ env. `.env` local chứa cấu hình thật, đã kiểm tra Git ignore; không có credential thật trong source/report.
- `app/Http/Controllers/GoogleAuthController.php`: redirect, callback, trang chọn vai trò, hoàn tất đăng ký và liên kết.
- `app/Services/GoogleAuthService.php`: kiểm tra identity/state context, TTL, transaction tạo/liên kết tài khoản và đăng nhập session.
- `app/Http/Requests/Auth/CompleteGoogleRegistrationRequest.php`, `LinkGoogleRequest.php`: allowlist vai trò và xác nhận mật khẩu liên kết.
- `app/Services/AuthService.php`: xóa Google flow/pending/state khi đăng nhập email để không mang luồng cũ qua tài khoản khác.
- `routes/web.php`: 5 route Google, middleware và session blocking.
- `resources/views/Candidate/google-button.blade.php`, `ca_pages/google-register.blade.php`: nút hai ngữ cảnh và màn hình chọn vai trò; login/register vẫn hai trang riêng.
- `resources/views/auth/google-link.blade.php`: dùng lại trong Candidate settings, Recruiter account và Admin account.
- `app/Models/User.php`, migration ở mục 9.1 và tài liệu thiết kế/ERD.
- `tests/Auth/GooglePreparationTest.php`, `GoogleAuthenticationTest.php`, `tests/Database/models.php`: kiểm thử provider, nghiệp vụ và diễn tập migration trước khi áp dụng.

### 9.3. Routes và phân quyền

| Route | Named route | Bảo vệ |
| --- | --- | --- |
| GET /auth/google/redirect?context=login hoặc register | google.redirect | guest, throttle 10/phút, session lock |
| GET /auth/google/callback | google.callback | web session, auth.session, throttle 20/phút, session lock |
| GET /register/google | google.register | guest, pending hợp lệ trong 10 phút |
| POST /register/google | google.complete | guest, CSRF, throttle đăng ký, session lock |
| POST /account/google/link | google.link | auth, auth.session, active middleware, CSRF, current_password, throttle 5/phút, session lock |

Callback dùng chung, không có guest middleware vì phải phục vụ cả luồng link đã đăng nhập. Context, Auth ID và thời hạn được lưu phía server; callback không nhận role/user_id làm nguồn quyền. Admin vẫn tạo thủ công bằng admin:create; chỉ Admin đã tồn tại và chủ động liên kết mới đăng nhập Google được.

### 9.4. Luồng và bảo mật

Socialite Google driver sử dụng OAuth 2.0/OpenID Connect với openid/profile/email. Không stateless, không userFromToken và không tự xây protocol. Socialite kiểm tra/tiêu thụ state trước khi trao đổi code; callback lấy sub ổn định và yêu cầu email_verified đúng true. Email chỉ dùng kiểm tra xung đột, không làm định danh liên kết. Không tự decode JWT chưa xác minh.

- Subject đã liên kết: lấy User theo subject, khóa và đọc lại trạng thái Active, cập nhật last_login_at rồi Laravel Auth login(false), invalidate/regenerate session và điều hướng theo role database. Không ghi đè email tài khoản chỉ vì Google đổi email.
- Subject mới nhưng email đã tồn tại: từ chối tự đăng nhập/tạo/liên kết; hướng dẫn đăng nhập phương thức hiện có rồi chủ động liên kết từ trang tài khoản.
- Subject và email mới: lưu pending tối thiểu đã mã hóa (subject/email/name/expiry) trong server session tối đa 10 phút; không tạo User ngay. Người dùng chọn Candidate hoặc Recruiter, POST có CSRF mới tạo trong transaction. Candidate tạo thêm CandidateProfile; Recruiter không tự tạo Company. Form và Service đều cấm Admin.
- Tạo tài khoản: kiểm tra lại email/subject trong transaction và bắt UNIQUE violation nếu request đồng thời; mật khẩu là hash của 32 byte random_bytes mã hóa hex, không công bố hoặc gửi plaintext. Không có mật khẩu mặc định có thể đoán. Google-only có thể thiết lập mật khẩu sau Google re-auth theo mục 11 hoặc dùng reset email; tài khoản đã có mật khẩu vẫn yêu cầu mật khẩu hiện tại khi đổi.
- Liên kết: nhập mật khẩu hiện tại, lưu Auth ID và dấu vân tay hash mật khẩu vào context 10 phút; callback kiểm tra vẫn đúng user/active/mật khẩu chưa đổi. Transaction khóa User và từ chối subject thuộc người khác hoặc ghi đè identity khác. Không đổi email, password hoặc role. Chưa có unlink để tránh mất phương thức đăng nhập duy nhất.
- State sai/mất, callback lặp, access_denied, flow hết hạn, provider lỗi hoặc email chưa xác minh: thông báo an toàn, không login; xóa flow/pending/state. Session lock cùng unique index chống callback/form lặp và request đồng thời. Mỗi callback tiêu thụ context trước khi xử lý.
- Email login, logout, OAuth login xóa trạng thái Google cũ. Callback response có no-store/private và Referrer-Policy no-referrer. Không lưu access/refresh/ID token/code vào database, pending hoặc log; exception provider bị xử lý bằng thông báo chung.

### 9.5. Cấu hình Google Cloud và cách dùng

Đã cấu hình ba biến trong `.env` local từ thông tin người dùng cung cấp; không in giá trị secret vào báo cáo hoặc commit. Client Secret đã được chia sẻ trong hội thoại cần được thay mới trong Google Cloud, sau đó thay trực tiếp trong `.env`.

```dotenv
GOOGLE_CLIENT_ID=<client-id>
GOOGLE_CLIENT_SECRET=<secret>
GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback
```

OAuth client phải là Web application. Google Cloud consent screen phải khai báo ứng dụng và test users nếu ở chế độ Testing. Authorized redirect URI phải khớp chính xác `http://localhost:8000/auth/google/callback`, không có backslash trước dấu hai chấm. Nếu khai báo JavaScript origin, dùng `http://localhost:8000`; không cần Google JS SDK cho Socialite server redirect.

Mở **http://localhost:8000/login** hoặc **http://localhost:8000/register**. Không trộn localhost và 127.0.0.1 giữa các bước vì cookie/state khác host. Nút Google tự có hiệu lực khi đủ ba biến cấu hình; khi thiếu cấu hình sẽ disabled và báo rõ. Google mới sẽ tới trang chọn vai trò; Google đã liên kết sẽ tới khu vực tương ứng.

Liên kết tài khoản cũ: đăng nhập email/mật khẩu, mở Cài đặt tài khoản Candidate hoặc Tài khoản Recruiter/Admin, nhập mật khẩu ở mục Liên kết Google, chọn tài khoản Google và hoàn tất callback.

Khi đổi env trên máy có config cache, chạy `php artisan config:clear` hoặc dựng lại config cache theo quy trình triển khai. Production cần HTTPS, redirect URI riêng, debug=false và cấu hình Apache access log không lưu query string code/state trên callback. Ứng dụng không tự thay cấu hình Apache/Google Cloud ngoài workspace.

Tham khảo [Laravel Socialite](https://laravel.com/framework/docs/socialite) và mã package v5.32.0; [Google OpenID Connect](https://developers.google.com/identity/openid-connect/openid-connect) về sub và email_verified.

### 9.6. Kiểm thử và kết quả

Trước triển khai: `php tests/Database/models.php --auth --pending-google` dựng MariaDB riêng, áp dụng migration mới, rollback trên test để so sánh baseline cũ, migrate lại rồi chạy tests. Database chính không bị ghi dữ liệu thử. Sau triển khai dùng lại `php tests/Database/models.php --auth`.

**73 tests / 1177 assertions đạt** cả trước và sau khi áp dụng migration. Lần chạy sau triển khai xác nhận schema db_job khớp migration đầy đủ và không bị thay đổi trong quá trình test; Pint và git diff --check đạt. Bao gồm:

- Google login theo subject cho Candidate/Recruiter/Admin; email Google thay đổi không đổi User; role callback giả bị bỏ qua; session đổi và last_login_at cập nhật.
- First login chưa ghi User; xác nhận Candidate/Recruiter mới tạo; cấm Admin và field giả từ client; transaction rollback khi tạo profile lỗi.
- Email trùng không tự liên kết; kiểm tra lại email ở bước cuối; subject UNIQUE và không mass assign; giữ 32 bảng và ASCII binary.
- Locked account và khóa trong lúc link bị từ chối; liên kết cần mật khẩu, đúng chủ thể và không chiếm subject của người khác.
- State sai, callback lặp, provider lỗi, hủy quyền, email chưa verified, hết hạn flow/pending, CSRF link/register.
- down() từ chối xóa identity đang dùng; OAuth provider response/token/code không xuất hiện trong thông báo lỗi.
- Các tests email/mật khẩu, phân quyền, Models giai đoạn 1 và các trang demo tiếp tục đạt.

HTTP tới Google dùng Guzzle MockHandler với Socialite provider thật trong test; không gọi Google thật. Kiểm thử đồng thời được bảo vệ bằng unique constraint và session lock, chưa chạy stress test nhiều worker. Chưa xác minh một phiên đăng nhập Google thật bằng trình duyệt vì cần người dùng tương tác/Google Cloud cho phép client và test user; không tuyên bố consent screen đã được kiểm tra.

### 9.7. Giới hạn còn lại

- Google-only password setup đã bổ sung tại mục 11; chưa có unlink, nhiều Google identity/User hoặc provider khác.
- Không thay nghiệp vụ demo bằng CRUD thật; không thêm AI hoặc bảng OAuth riêng.
- Composer audit đã báo 8 advisory trên 5 package có sẵn: laravel/framework, symfony/http-foundation, symfony/http-kernel, symfony/routing, symfony/yaml; không có advisory được báo cho 6 package mới. Không tự nâng framework ngoài phạm vi; cần đánh giá cập nhật trước vận hành.
- Schema đã được phê duyệt và áp dụng; không còn chờ phê duyệt phần google_subject. Việc thay Client Secret và xác nhận Google Cloud/đăng nhập thật là thao tác cấu hình phía chủ tài khoản.

## 10. Giai đoạn 2.2 — Password Recovery và đề xuất Google-only Password Setup

Trạng thái cập nhật sau phê duyệt: **khôi phục email và Google-only password setup đã triển khai**. Gmail SMTP đã xác thực kết nối; chưa kiểm chứng email tới hộp thư và Google re-auth thật trên trình duyệt. Kết quả cuối tại mục 11.

### 10.1. Khảo sát và phạm vi đã thực hiện

Password Broker users hiện dùng password_reset_tokens(email PK, token, created_at), expire=60 phút và throttle=60 giây. User kế thừa CanResetPassword của Laravel. Bảng sessions dùng database driver và Auth hiện tại đã có cơ chế thu hồi khi đổi mật khẩu. Không thêm hệ thống token, bảng hoặc package mới.

Chỉ đọc số lượng bản ghi database chính: 4 User, trong đó 2 có Google subject tại thời điểm khảo sát. Mật khẩu thật và mật khẩu ngẫu nhiên đều là hash, không thể phân biệt đáng tin cậy. Không đọc/in mật khẩu/token/SMTP secret vào báo cáo. Không có migration, backfill hoặc dữ liệu thử trên db_job trong giai đoạn này.

### 10.2. File tạo/sửa

Tạo mới:

- app/Services/PasswordRecoveryService.php.
- app/Http/Controllers/PasswordRecoveryController.php.
- app/Http/Requests/Auth/ForgotPasswordRequest.php và ResetPasswordRequest.php.
- app/Notifications/PasswordRecoveryNotification.php (kế thừa notification reset chính thức).
- tests/Auth/PasswordRecoveryTest.php.

Chỉnh sửa:

- routes/web.php: bốn route chuẩn, guest, CSRF/throttle và redirect URL cũ.
- config/auth.php, .env.example: PASSWORD_RECOVERY_ENABLED mặc định false.
- config/mail.php: timeout SMTP 10 giây; không đưa credential vào source.
- app/Providers/AppServiceProvider.php: limiter theo IP và email chuẩn hóa/hash.
- bootstrap/app.php: không flash token/password/current_password/password_confirmation.
- app/Services/AuthService.php: đổi mật khẩu thông thường cũng hủy reset token còn tồn tại.
- Candidate views ca_pages/forgot.blade.php, reset.blade.php, login.blade.php: form phục hồi thật và liên kết chỉ hiển thị khi đủ cấu hình.
- tests/Auth/AuthenticationTest.php, GoogleAuthenticationTest.php: cập nhật redirect cũ và hồi quy Google sau reset.
- docs/backend-auth-implementation.md: báo cáo phần độc lập và phương án phân loại; thiết kế database/ERD đã cập nhật sau phê duyệt ở mục 11.

### 10.3. Routes và quy trình

| Method | Route | Named route |
| --- | --- | --- |
| GET | /forgot-password | password.request |
| POST | /forgot-password | password.email |
| GET | /reset-password/{token} | password.reset |
| POST | /reset-password | password.update |

Tất cả dành cho guest. POST có CSRF; gửi mail tối đa 10 lần/phút/IP và 5 lần/giờ/email (kể cả email không tồn tại); broker giới hạn gửi lại token 60 giây. Reset POST tối đa 10 lần/phút/IP. URL tiếng Việt cũ chuyển hướng trang quên mật khẩu mới, không còn form demo reset.

Form Request chuẩn hóa email, kiểm tra mật khẩu tối thiểu 8 ký tự có chữ/số, max 255 và xác nhận khớp. Không chấp nhận redirect URL hoặc Referer làm đích redirect validation; chỉ về named routes nội bộ.

Yêu cầu email gọi Laravel Password Broker sendResetLink với status active, Notification tiếng Việt dùng mailer SMTP trực tiếp. Thông báo cho email tồn tại/không tồn tại/locked/throttled giống nhau: “Nếu email đã được đăng ký, chúng tôi sẽ gửi hướng dẫn đặt lại mật khẩu.” Không xác nhận đã gửi thành công cho một địa chỉ cụ thể. SMTP lỗi giữ thông báo trung tính, rollback token mới; chỉ ghi một warning cố định không kèm exception, credential, địa chỉ email hoặc token. Gửi đồng bộ, không dùng queue/worker và không thay queue_jobs/jobs. Đây là đồng nhất thông báo, không phải cam kết timing của SMTP hoàn toàn bằng nhau.

Token do broker tạo và hash trong password_reset_tokens, thời hạn 60 phút, tiêu thụ sau một lần dùng. Email chứa nút đặt lại, thời hạn và hướng dẫn bỏ qua khi không yêu cầu; không chứa mật khẩu. URL được tạo từ APP_URL cấu hình tin cậy cùng named route, không lấy host/redirect người dùng gửi.

Reset giữ row lock User trong transaction trước khi broker kiểm tra token và đổi mật khẩu; yêu cầu User active. Save hash mới, null remember_token, xóa tất cả database sessions của User; broker xóa token trong cùng transaction. Gửi lại email cũng khóa cùng User để không đua với reset. Không thay email/role/status/google_subject. Không tự đăng nhập; hủy phiên hiện tại/OAuth context, yêu cầu đăng nhập lại. Mở GET liên kết không đổi mật khẩu hoặc đăng nhập. Google-only có thể đặt mật khẩu qua email mà không biết mật khẩu ngẫu nhiên; Google Login sau đó vẫn giữ mật khẩu mới. Reset thành công đồng thời gán has_local_password=true trong transaction theo mục 11; không có setup bypass dựa vào trạng thái suy đoán.

### 10.4. Cấu hình SMTP

Ban đầu môi trường dùng MAIL_MAILER=log và recovery_enabled=false. Sau khi người dùng cung cấp SMTP, đã cấu hình smtp.gmail.com:587 với MAIL_SCHEME=smtp, tên gửi VIEJOB AI, APP_URL=http://localhost:8000 và bật PASSWORD_RECOVERY_ENABLED=true. Kết nối STARTTLS/xác thực SMTP thành công qua transport start/stop; không gửi email thật. Credentials chỉ lưu trong .env được Git ignore, không ghi vào báo cáo. Backend từ chối tạo/gửi token với log, array, failover hoặc roundrobin để tránh đưa email chứa token vào log hoặc báo thành công giả.

Nhập cấu hình của nhà cung cấp vào .env local, không gửi mật khẩu qua chat:

```dotenv
APP_URL=http://localhost:8000
PASSWORD_RECOVERY_ENABLED=true
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=<smtp-host>
MAIL_PORT=587
MAIL_USERNAME=<smtp-username>
MAIL_PASSWORD=<smtp-password-or-app-password>
MAIL_FROM_ADDRESS=<verified-sender>
MAIL_FROM_NAME="VIEJOB AI"
```

Laravel 12/Symfony Mailer dùng MAIL_SCHEME: smtp trên port 587 tự thương lượng STARTTLS khi máy chủ hỗ trợ; smtps cho TLS ngay từ kết nối, thường port 465. Dùng thông số đúng của nhà cung cấp, không tắt xác minh TLS để khắc phục lỗi. MAIL_ENCRYPTION không được config hiện tại sử dụng. Có thể dùng SMTP local sandbox để kiểm tra, không mặc định log mailer cho reset.

Sau cập nhật env chạy php artisan config:clear hoặc dựng lại cache theo quy trình deploy. Chỉ bật PASSWORD_RECOVERY_ENABLED khi đã cấu hình SMTP, APP_URL và địa chỉ gửi; kiểm tra này xác nhận cấu hình có mặt, không tự chứng minh SMTP kết nối/gửi thành công. Người dùng đã cung cấp cấu hình Gmail; kiểm tra runtime xác nhận recovery available. Cần đổi App Password đã chia sẻ trong hội thoại và cập nhật trực tiếp .env.

Reset page có Cache-Control no-store/private và Referrer-Policy no-referrer. Trước vận hành cần cấu hình Apache/proxy/APM không ghi đầy đủ URL reset-password/{token} và query callback Google; bộ lọc log Laravel không kiểm soát access log của web server. Không tự thay cấu hình Apache ngoài workspace. Không ghi token/password vào application log hoặc sessionStorage/localStorage; token cũng bị loại khỏi flash old input.

### 10.5. Phương án schema đã được phê duyệt (kế hoạch ban đầu)

Đề xuất **users.has_local_password TINYINT(1) NULL DEFAULT NULL**, CHECK (has_local_password IS NULL OR has_local_password IN (0,1)); không thêm bảng/index/FK, vẫn 32 bảng. Trường được cast boolean nhưng giữ NULL, không fillable, chỉ Service/Command cập nhật.

| Giá trị | Ý nghĩa và cách dùng |
| --- | --- |
| 1 / true | Đã biết có mật khẩu người dùng thiết lập; chỉ cho đổi mật khẩu với mật khẩu hiện tại hoặc reset email |
| 0 / false | Được xác nhận Google-only từ luồng tạo tài khoản Google sau migration; được đề nghị setup bằng Google re-auth |
| NULL | Nguồn gốc mật khẩu của tài khoản cũ chưa đủ bằng chứng; không được dùng setup để bỏ qua mật khẩu cũ |

Không dùng google_subject khác NULL hoặc cấu trúc hash để gán false. Không dùng timestamp tự dựng hay activity_logs làm nguồn phân loại giả.

Kế hoạch backfill sau duyệt:

1. Thêm nullable không thay password/role/status/subject.
2. Tài khoản cũ không có Google identity, theo các luồng tạo tài khoản hiện có đều dùng mật khẩu nhập qua form/command: gán true (2 tài khoản ở thời điểm khảo sát; kiểm tra lại trước chạy).
3. Hai tài khoản đang có Google identity giữ NULL vì có thể là đăng ký Google hoặc liên kết sau đăng ký email. Không tự gán false, không đoán theo role/ngày tạo.
4. Sau migration: register email/Admin command gán true; register Google gán false; link/login Google không tự thay flag hoặc password.
5. Email login đúng mật khẩu có thể xác nhận NULL thành true. Đổi mật khẩu, reset email và Google setup thành công phải gán true trong cùng transaction thay hash. Cờ false không được dùng để khóa luồng email reset.
6. User cũ NULL có thể dùng email reset để chứng minh quyền sở hữu và chuyển thành true. Chỉ chuyển NULL thành false nếu có nguồn gốc đăng ký Google đáng tin cậy đã được xác minh; không dựa trên lời khai/email/subject riêng lẻ.

Thiết lập Google-only sau phê duyệt: nút riêng tại trang tài khoản ba vai trò chỉ khi flag=false và có subject. OAuth re-auth mới dùng context riêng cho password_setup, ràng buộc Auth ID, session, subject và dấu vân tay password; không tái sử dụng context login/link/register. Callback kiểm tra subject đúng tài khoản, active, flag vẫn false và thời hạn. Cấp grant dùng một lần 10 phút; form chỉ mật khẩu mới/xác nhận. POST kiểm tra lại dưới row lock, lưu hash+flag=true, xóa reset token/grant/phiên và bắt đăng nhập lại. Callback replay, form replay, subject khác, status locked hoặc flag true/NULL đều bị từ chối. Google Login về sau giữ hash/flag. Kế hoạch này đã được triển khai sau phê duyệt, xem kết quả mục 11.

### 10.6. Kiểm thử

Bộ MariaDB riêng chạy php tests/Database/models.php --auth, không ghi tài khoản/token thử vào db_job. Kết quả cuối: **85 tests / 1332 assertions đạt**, bao gồm redirect validation không dùng Referer bên ngoài. Pint và git diff --check đạt. Bổ sung kiểm tra trang, URL email, hash token, email trung tính, SMTP error rollback, rate limit, token sai/hết hạn/replay, hash mật khẩu, login cũ/mới, thu hồi session, giữ Google subject/role/status/email, locked, CSRF và không flash/log credential. Có test Admin/Recruiter reset, Google-only reset email và Google login giữ hash mới. Mail dùng Notification::fake; Google HTTP dùng mock. Không gửi SMTP/Google thật.

JavaScript regression: **13 tests đạt** (recruiter-domain, admin-domain, admin-categories). Tests Models và các luồng Auth/Policy/Google cũ vẫn nằm trong bộ tích hợp. Chưa kiểm thử stress nhiều worker; tính một lần được bảo vệ bằng transaction, row lock và broker delete token. Các ca Google-only setup được bổ sung và kiểm thử riêng sau phê duyệt schema ở mục 11; không dùng tests reset email thay thế.

### 10.7. Kiểm thử thủ công và phần còn lại

1. Cấu hình SMTP và sender hợp lệ, APP_URL đúng host, bật recovery; mở /login xác nhận liên kết Quên mật khẩu xuất hiện.
2. Yêu cầu reset cho tài khoản thử do người vận hành lựa chọn; kiểm tra email tiếng Việt, URL đúng host và thời hạn. Email không tồn tại/locked phải nhận cùng thông báo web.
3. Mở liên kết không tự đăng nhập; nhập mật khẩu mới; xác nhận đăng nhập mật khẩu cũ thất bại, mật khẩu mới thành công và các phiên cũ bị thu hồi. Thử lại liên kết đã dùng phải thất bại.
4. Với tài khoản có Google, xác nhận Google Login vẫn hoạt động sau reset và không đổi mật khẩu mới.
5. Kiểm tra setup độc lập theo mục 11: chỉ Google-only xác định (false) có nút re-auth/setup, true và NULL không được dùng luồng này.

Quyết định tiếp theo đã được người dùng phê duyệt: triển khai has_local_password nullable, giữ unknown cho tài khoản Google cũ; kết quả thực hiện tại mục 11. SMTP đã cấu hình/xác thực nhưng chưa kiểm chứng email tới hộp thư.

## 11. Hoàn tất Google-only Password Setup sau phê duyệt

### 11.1. Schema đã triển khai

Migration `2026_11_17_000000_add_has_local_password_to_users_table.php` đã được diễn tập up/down/up trên MariaDB riêng rồi áp dụng thành công vào db_job. Thêm has_local_password TINYINT(1) NULL DEFAULT NULL và CHECK users_has_local_password_check giới hạn NULL/0/1. Không thêm bảng/index/FK; vẫn **32 bảng**.

Backfill thực tế: **2 tài khoản có mật khẩu (true), 0 Google-only xác định (false), 2 chưa xác định (NULL)**. Chỉ tài khoản cũ không Google được gán true. Hai tài khoản Google cũ giữ NULL vì không đủ bằng chứng phân biệt đăng ký Google với liên kết sau đăng ký email. Tất cả trường User cũ (bao gồm hash mật khẩu, role/status/email/subject) được đối chiếu trước/sau và giữ nguyên. down() từ chối khi tồn tại User để không xóa mất phân loại mật khẩu.

Đăng ký Google mới gán false; đăng ký email và admin:create gán true. Đăng nhập email kiểm tra lại mật khẩu dưới row lock rồi gán true; đổi mật khẩu, reset email và setup thành công cũng gán true. Đăng nhập/liên kết Google không tự đổi mật khẩu hoặc phân loại. User cast boolean giữ NULL và không thêm trường vào fillable. Không suy đoán từ hash hay tạo dữ liệu demo làm nguồn xác thực.

### 11.2. Luồng thiết lập mật khẩu

Cả ba trang tài khoản sử dụng partial auth/account-password:

- false + Google subject + active: hiển thị Thiết lập mật khẩu và nút Xác thực lại Google; không yêu cầu mật khẩu cũ.
- true: giữ form Đổi mật khẩu yêu cầu mật khẩu hiện tại.
- NULL: giữ form đổi mật khẩu an toàn, kèm hướng dẫn đăng xuất rồi dùng Quên mật khẩu nếu không biết/chưa có mật khẩu. Không cấp setup qua Google cho trạng thái chưa xác định.

POST bắt đầu setup tạo context server-side **google_setup_flow riêng biệt** và xóa context login/register/link/grant cũ. Flow ràng buộc Auth ID, Google subject, session ID, SHA-256 của hash mật khẩu và thời hạn 10 phút. Socialite tạo state mới; yêu cầu Google hiển thị lựa chọn tài khoản qua prompt=select_account. Đây là OAuth challenge mới; website không kiểm soát việc Google yêu cầu nhập lại mật khẩu Google hay sử dụng phiên Google sẵn có.

Callback chung phân nhánh setup chỉ khi có google_setup_flow trong session. Flow bị tiêu thụ ngay; kiểm tra session, tài khoản, active/false và hạn trước khi gọi provider. Socialite xác minh state. Chỉ khi sub khớp chính xác subject hiện tại và email_verified=true, sau đọc lại User, mới cấp google_setup_grant dùng một lần 10 phút. Không nhận user_id/subject từ client làm nguồn quyền; không tái dùng kết quả login Google.

Form mới yêu cầu mật khẩu ≥8 ký tự, chữ và số, xác nhận khớp. Validation không làm mất grant nếu người dùng nhập chưa đạt. POST hoàn tất kéo grant khỏi session, lock User và kiểm tra lại mọi ràng buộc; lưu hash mới + true + remember_token=NULL trong transaction, xóa password reset token và các database sessions của user. Sau commit logout/invalidate/CSRF regenerate, yêu cầu đăng nhập lại. Mật khẩu mới dùng được qua email; Google login vẫn hoạt động và không ghi đè mật khẩu mới.

State/callback sai, Google bị từ chối, subject khác, context/phiên/tài khoản đổi, flag true/NULL, tài khoản locked hoặc quyền hết hạn đều bị từ chối. Callback replay xóa grant còn lại, submit replay không đổi mật khẩu lần nữa. Không thêm unlink hoặc thay role/Policy.

### 11.3. Routes và file

| Route | Named route | Bảo vệ |
| --- | --- | --- |
| POST /account/password/setup/google | password.setup.google | auth, auth.session, active, CSRF, limiter 5/phút/user, session lock |
| GET /auth/google/callback | google.callback | callback hiện có; dispatch setup riêng theo server session, state Socialite |
| POST /account/password/setup | password.setup.complete | auth, auth.session, active, CSRF, limiter 5/phút/user riêng, session lock, Form Request, grant |

Hai limiter setup dùng key riêng để không cộng nhầm bộ đếm bắt đầu flow và lưu mật khẩu. Không cần khai báo callback URL mới trong Google Cloud.

Tạo mới:

- Migration nêu tại mục 11.1.
- app/Services/GooglePasswordSetupService.php.
- app/Http/Controllers/GooglePasswordSetupController.php.
- app/Http/Requests/Auth/GooglePasswordSetupRequest.php.
- resources/views/auth/account-password.blade.php.
- tests/Auth/GooglePasswordSetupTest.php.

Chỉnh sửa: User casts; CreateAdmin; AuthService/GoogleAuthService/PasswordRecoveryService để gán cờ đúng thời điểm và hủy quyền chờ; GoogleAuthController dispatch callback; routes/web.php và AppServiceProvider; Candidate settings, Recruiter/Admin account dùng partial mới; tests Auth/Google/Recovery; tests/Database/models.php và tests/Auth/phpunit.xml; database-design.md, database-erd.mmd, database-implementation.md và báo cáo này.

### 11.4. Kiểm thử và cách chạy

Trước triển khai dùng `php tests/Database/models.php --auth --pending-password-setup`; runner diễn tập rollback cột trên database test rỗng để so sánh baseline, sau đó migrate lại và chạy tests. Sau triển khai dùng `php tests/Database/models.php --auth`. Tuyệt đối không chạy migrate:fresh/db:wipe trên db_job.

Kết quả trước và sau triển khai migration: **97 tests / 1459 assertions đạt**; **13 tests JavaScript đạt**; Pint và git diff --check đạt. Lần chạy sau xác nhận schema db_job khớp migration đầy đủ và database chính không bị thay đổi trong quá trình tests. Tests mới bao phủ:

- Setup trọn luồng không biết mật khẩu ngẫu nhiên; đổi hash + flag, giữ email/role/status/subject, thu hồi grant/reset token/phiên cũ, đăng xuất, email login và Google login sau setup.
- Từ chối true/NULL, subject sai/state sai, callback replay, grant replay, flow/grant hết hạn, session rotate/account switch, khóa tài khoản và đổi password/flag sau re-auth.
- CSRF, mật khẩu yếu, giao diện ba vai trò và password confirmation.
- Backfill chỉ tài khoản không Google; NULL giữ nguyên cho Google cũ; CHECK từ chối 2; không mass assign; rollback chặn khi có User và vẫn 32 bảng.
- Các luồng email register/Admin create/email login/change/reset đều đánh dấu true, Google register đánh dấu false.

Socialite provider dùng HTTP mock. Tests mail dùng Notification::fake; runner ép MAIL_MAILER=array và SMTP host giả, xóa credential SMTP khỏi môi trường subprocess để không kế thừa Gmail thật. PHPunit cũng force recovery off mặc định; test recovery bật config riêng cùng fake notification. Bộ kiểm thử không gửi SMTP hoặc gọi Google thật. Gmail connection/auth đã được kiểm tra ở bước trước, không gửi email.

Chưa stress test nhiều worker; quyền một lần được kiểm tra lại dưới row lock, flag và session lock. Chưa kiểm chứng Google re-auth thật trên trình duyệt hoặc email tới hộp thư.

### 11.5. Hướng dẫn sử dụng và điểm cần lưu ý

1. Google-only mới: đăng ký Google, chọn Candidate/Recruiter, mở Cài đặt/Tài khoản → Thiết lập mật khẩu → Xác thực lại Google. Chọn đúng tài khoản, tạo và xác nhận mật khẩu trong 10 phút, sau đó đăng nhập lại.
2. Tài khoản đã có mật khẩu: dùng Đổi mật khẩu với mật khẩu hiện tại; nếu quên, đăng xuất và mở /forgot-password.
3. **Hai tài khoản Google cũ đang NULL không hiện nút setup Google-only.** Hãy dùng reset email để chứng minh quyền truy cập và gán true, hoặc đăng nhập email với mật khẩu đã biết. Không sửa cờ false thủ công chỉ vì tài khoản có Google subject.
4. Sau reset/setup, kiểm tra cả email login và Google login; thử lại callback/grant cũ phải bị từ chối. Cần kiểm tra thêm thông qua Google Cloud/test users trên trình duyệt của người vận hành.
5. SMTP và OAuth secret chỉ có trong .env; thay các secret đã chia sẻ trong hội thoại. Web server/proxy/APM phải không ghi token trong URL reset hoặc code/query OAuth như mục 10.4.

Không còn phần schema/setup chờ phê duyệt. Các giới hạn còn lại là kiểm thử tích hợp với dịch vụ thật và chính sách vận hành đã nêu; không triển khai CRUD/AI ngoài phạm vi.
