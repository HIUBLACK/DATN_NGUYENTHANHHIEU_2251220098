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

GET quên/đặt lại mật khẩu vẫn tồn tại nhưng thông báo rõ chưa hỗ trợ gửi email; không giả lập thành công. Không có GET đăng xuất. Endpoint `/demo/tai-khoan/{mode}` đã bỏ.

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

- Quên mật khẩu email chưa triển khai vì chưa xác nhận hạ tầng gửi mail vận hành; giao diện không báo gửi thành công giả.
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
- Tạo tài khoản: kiểm tra lại email/subject trong transaction và bắt UNIQUE violation nếu request đồng thời; mật khẩu là hash của 32 byte random_bytes mã hóa hex, không công bố hoặc gửi plaintext. Không có mật khẩu mặc định có thể đoán. Tài khoản Google-only tiếp tục dùng Google; luồng tự đặt mật khẩu mới chưa thuộc phạm vi, form đổi mật khẩu hiện tại vẫn yêu cầu mật khẩu cũ.
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

- Chưa triển khai đặt mật khẩu lần đầu cho Google-only, unlink, nhiều Google identity/User hoặc provider khác.
- Không thay nghiệp vụ demo bằng CRUD thật; không thêm AI hoặc bảng OAuth riêng.
- Composer audit đã báo 8 advisory trên 5 package có sẵn: laravel/framework, symfony/http-foundation, symfony/http-kernel, symfony/routing, symfony/yaml; không có advisory được báo cho 6 package mới. Không tự nâng framework ngoài phạm vi; cần đánh giá cập nhật trước vận hành.
- Schema đã được phê duyệt và áp dụng; không còn chờ phê duyệt phần google_subject. Việc thay Client Secret và xác nhận Google Cloud/đăng nhập thật là thao tác cấu hình phía chủ tài khoản.
