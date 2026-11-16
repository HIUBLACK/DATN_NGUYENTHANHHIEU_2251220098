# Giai đoạn 1 — Eloquent Models & Relationships

## Kết quả và phạm vi

Đã cập nhật `User`, tạo 22 Model ứng dụng còn lại và tái sử dụng `Illuminate\Notifications\DatabaseNotification`: tổng cộng **24 Model ánh xạ 24 bảng nghiệp vụ**. Không tạo Model cho queue/cache/session, không thêm package hoặc schema mới.

Đã đọc thiết kế, ERD, báo cáo migration, migration hiện có và metadata schema thực tế bằng SHOW FULL COLUMNS/SHOW CREATE TABLE. Database `db_job` có 32 bảng, 29 migration đã ghi nhận. Bản dựng từ toàn bộ migration trong database thử riêng được so sánh SHOW CREATE TABLE với `db_job`, bỏ qua bộ đếm AUTO_INCREMENT: **không phát hiện khác biệt schema với migration**. Tên bảng/cột, PK/FK, kiểu dữ liệu và các giá trị trạng thái sử dụng đúng schema thực tế; không cần chỉnh sửa schema để khớp tài liệu.

Thay đổi ngoài thư mục Model/Enum chỉ gồm đăng ký morph map trong `AppServiceProvider`, bộ kiểm thử và báo cáo này. Morph map là cấu hình quan hệ Model, không phải Service nghiệp vụ. Không đổi giao diện, route, Controller, Authentication, Middleware, Policy, Service, cấu hình database, migration, Seeder hoặc API AI. Không xử lý `mysql.proc`, không chuyển dữ liệu demo.

## Model và quan hệ

Các phương thức trong bảng dưới là Eloquent relationships, có thể lazy/eager load, trừ query composite được liệt kê riêng ở phần sau.

| Model | Bảng | Quan hệ |
| --- | --- | --- |
| User — cập nhật | users | candidateProfile, company, resumes, applications, savedJobs, recommendationRuns, actedActivityLogs, activityLogs; notifications/readNotifications/unreadNotifications kế thừa Notifiable |
| CandidateProfile | candidate_profiles | user |
| Company | companies | recruiter, industry, jobs, activityLogs |
| Industry | industries | companies, jobVersions |
| JobCategory | job_categories | specializations |
| JobSpecialization | job_specializations | category, jobVersions |
| Skill | skills | resumeSkills, jobVersionSkills, jobVersions |
| JobLevel | job_levels | jobVersions |
| Job | jobs | company, versions, publishedVersion, applications, moderationLogs, activityLogs |
| JobVersion | job_versions | job, industry, specialization, level, skills, applications, skillRequirements, moderationLog |
| JobVersionSkill | job_version_skills | jobVersion, skill; custom Pivot, BIGINT id tăng tự động |
| JobModerationLog | job_moderation_logs | job, jobVersion, admin |
| Resume | resumes | candidate, revisions, skills, applications |
| ResumeRevision | resume_revisions | resume; skills() là query đủ hai khóa, không phải relationship eager-loadable |
| ResumeSkill | resume_skills | resume, skill |
| Application | applications | candidate, job, jobVersion, resume, statusHistories, notes, aiEvaluations |
| ApplicationStatusHistory | application_status_histories | application, actor |
| ApplicationNote | application_notes | application, recruiter |
| SavedJob | saved_jobs | candidate, job |
| RecommendationRun | recommendation_runs | candidate, resume, items |
| RecommendationItem | recommendation_items | run, job, jobVersion |
| ApplicationAiEvaluation | application_ai_evaluations | application, job, jobVersion |
| ActivityLog | activity_logs | actor, subject (morphTo) |
| DatabaseNotification — framework | notifications | notifiable (morphTo); giữ nguyên collection, read/unread scopes và markAsRead/markAsUnread của Laravel |

`JobVersion::skills()` và `Skill::jobVersions()` dùng `job_version_skills`, custom Pivot `JobVersionSkill`, pivot id/is_required và timestamps. `Resume::skills()` trả mọi revision của CV; dùng currentSkillsQuery() nếu chỉ cần revision hiện tại.

Morph map cố định: `user → User`, `company → Company`, `job → Job`. Dùng `Relation::enforceMorphMap()` để khi ghi quan hệ không lưu tên PHP class vào database. `ActivityLog.subject_type` còn được CHECK bảo vệ. Notifications không có CHECK/FK recipient; backend sau này phải giới hạn recipient User và kiểm tra quyền khi truy cập notification. Morph map không thay Policy hoặc validation dữ liệu đa hình.

## Khóa, mass assignment và casts

- 23 Model ứng dụng dùng BIGINT `id` làm PK. Company/Resume/Application dùng HasUuids với `uniqueIds() = ['public_id']`, tự cấp UUID cho public_id khi tạo; không đổi PK hoặc route key thành public_id. Không sửa route binding hiện có.
- Notification giữ PK UUID string, không tăng tự động, theo Model framework. ID do notification channel hoặc mã nội bộ đáng tin cậy cấp.
- User chỉ fillable name/email/password; giữ Hash cast và ẩn password/remember_token. Role/status/last_login_at không nhận mass assignment.
- Company/CandidateProfile, danh mục, JobVersion, Resume chỉ cho fill các trường nội dung được liệt kê. FK, owner, public_id, trạng thái, mã/slug/normalized_name, generated slots, is_primary/current_revision, attempt_no, metadata kiểm duyệt/xử lý và điểm AI đều không nằm trong fillable.
- Job, revision/file metadata, pivot, application, lịch sử/ghi chú, saved, AI và activity log dùng `guarded = ['*']`. Giai đoạn Service sẽ gán tường minh sau kiểm tra quyền và quy tắc; không truyền `request()->all()` hoặc dùng `forceFill()` với input chưa kiểm tra. Test dùng forceFill chỉ để dựng fixture tin cậy.
- **Ngoại lệ framework:** DatabaseNotification chuẩn Laravel có guarded rỗng để nhận payload từ notification channel. Không tạo wrapper chỉ để nhân bản framework. Chỉ sử dụng qua `User::notifications()`/Notification chuẩn với payload do server dựng; không expose endpoint mass-assign notification trực tiếp từ request. Quyền recipient phải xử lý ở giai đoạn backend.
- JSON office_images, matched_skills, missing_skills, old_values/new_values cast array. Điểm AI và salary decimal:2, experience decimal:1, confidence_score decimal:4; decimal trả chuỗi để giữ độ chính xác. Nullable giữ NULL, không đổi thành 0 hoặc mảng rỗng giả.
- Các FK/count/revision/generation cast integer; cờ is_active/is_primary/is_required/salary_negotiable cast boolean; ngày hạn tuyển cast date, thời điểm cast datetime. Model mới dùng định dạng DATETIME(6), User và Notification giữ timestamp framework theo schema cũ.
- JobModerationLog, ApplicationStatusHistory, SavedJob, ActivityLog có `UPDATED_AT = null`, vẫn tự ghi created_at. Chỉ Resume có SoftDeletes. Những quan hệ ngược về Resume dùng withTrashed để đọc nguồn lịch sử; Application không bị mất liên kết khi CV soft delete.
- File path, processing_token và error_message được ẩn khỏi serialization ở các Model có cột tương ứng. Đây không phải bộ lọc thông tin công khai hoàn chỉnh: office_images còn chứa path và nội dung lịch sử/notes cần Resource/Policy ở giai đoạn sau. Không serialize toàn bộ Model ra trang công khai.

## Enums

Tạo 9 string-backed enums tại `app/Enums`; các giá trị đã được đối chiếu tự động với CHECK trong schema MariaDB:

| Enum | Giá trị |
| --- | --- |
| UserRole | candidate, recruiter, admin |
| UserStatus | active, locked |
| CompanyStatus | active, locked |
| JobStatus | draft, open, paused, closed, blocked |
| JobReviewStatus | draft, pending, approved, rejected |
| ApplicationStatus | applied, reviewing, interview, hired, rejected, withdrawn |
| ResumeParsingStatus | pending, processing, completed, failed |
| AiEvaluationStatus | pending, processing, completed, failed |
| RecommendationStatus | pending, processing, completed, failed |

Job.status_before_block dùng JobStatus nhưng CHECK vẫn cấm blocked cho giá trị trạng thái trước khóa. ApplicationStatusHistory old/new dùng ApplicationStatus; old có thể NULL lúc tạo. Không đổi giá trị database theo nhãn tiếng Việt trên UI.

## Query composite: cách dùng và giới hạn

Eloquent dùng PK đơn. Các belongsTo theo id duy nhất như jobVersion/application được giữ để hỗ trợ eager loading; composite FK của database bảo vệ dữ liệu đã lưu. Để truy vấn đủ cặp khóa, đặc biệt trên object chưa lưu hoặc được gán thuộc tính mới, bổ sung:

| Model | Scope/query | Điều kiện |
| --- | --- | --- |
| ResumeRevision | scopeForRevision(resumeId, revision) | resume_id + revision |
| ResumeSkill | scopeForRevision(resumeId, revision), revisionQuery() | resume_id + resume_revision, tra đúng nguồn revision |
| ResumeRevision | skills() | resume_skills.resume_id + resume_revision |
| Resume | currentRevision(), currentSkillsQuery(), scopeForCandidate(candidateId) | CV + revision hiện tại; ownership scope |
| Application | resumeRevisionQuery(), resumeForCandidateQuery(), jobVersionQuery(), scopeForJob(jobId) | CV + revision; CV + Candidate; version + job; đơn + job |
| RecommendationRun | resumeRevisionQuery(), resumeForCandidateQuery() | CV + revision và CV + Candidate |
| JobVersion | scopeForJob(jobId) | version thuộc job |
| Job | publishedVersionQuery() | published_version_id + job.id |
| JobModerationLog, RecommendationItem | jobVersionQuery() | job_version_id + job_id |
| ApplicationAiEvaluation | applicationQuery(), jobVersionQuery() | application_id + job_id; job_version_id + job_id |

Ví dụ:

```php
$revision = $resume->currentRevision()->first();
$skills = $revision?->skills()->get();
$snapshotSource = $application->resumeRevisionQuery()->first();
$version = JobVersion::forJob($job->id)->find($versionId);
$sourceSkills = ResumeSkill::forRevision($resume->id, 2)->with('skill')->get();
```

**Không dùng** `with('currentRevision')`, `with('revisions.skills')` hoặc truy cập `$revision->skills` như relationship. Đây là Builder có điều kiện khóa tường minh, không phải custom composite relationship. Có thể eager load `Resume::with('revisions', 'skills')` rồi nhóm theo cả resume_id/revision trong tầng đọc sau này, hoặc query phạm vi cụ thể như ví dụ. Không thêm package/custom relation phức tạp, không dùng `where('revision', $this->revision)` trong HasMany eager loading khiến các parent bị trộn dữ liệu.

Các query này kiểm tra tính nhất quán liên kết, **không** kiểm tra quyền người dùng hoặc điều kiện công khai. Không diễn giải publishedVersionQuery() là tin đã được duyệt/còn hạn.

## Kiểm thử đã thực hiện

```powershell
php tests/Database/models.php
php artisan test --compact
php vendor/bin/pint --test app/Models app/Enums app/Providers/AppServiceProvider.php tests/Models tests/Database/models.php
```

- Model integration: **7 tests, 371 assertions**, PHP 8.2.0, MariaDB 11.5.2. Kiểm tra mapping đủ 24 Model, mass assignment, quan hệ thuận/ngược và eager loading, custom pivot, UUID không đổi PK, timestamps không updated_at, JSON/decimal/date casts, soft delete/restore và nguồn CV đã xóa, notifications chuẩn, morph map và 9 enum khớp CHECK.
- Kiểm thử nhiều CV, nhiều revision và hai job; các query composite từ object có khóa không khớp không trả nhầm dữ liệu; object mới thiếu khóa không trả dữ liệu toàn bảng.
- Hồi quy: **21 tests, 393 assertions**, không đổi giao diện demo.
- Pint kiểm tra style các file mới/sửa.

Runner tạo database `viejob_models_test_<random>` riêng, chạy migration có sẵn trên đó và khởi chạy PHPUnit bằng tiến trình riêng với biến môi trường trỏ đúng database test. TestCase từ chối ghi nếu tên database không đúng prefix; không dùng RefreshDatabase, migrate:fresh hoặc db:wipe. Runner so sánh schema và migration history của nguồn trước/sau, rồi dọn đúng database tạm vừa tạo. Nếu config đang cache, runner dừng để tránh sử dụng nhầm kết nối. `db_job` chỉ được đọc metadata và migration history trong giai đoạn này; không chạy deploy script hoặc suite deployment có maintenance.

## Trước giai đoạn Authentication

1. Xây luồng xác thực thật và phân quyền role/status bằng Service/Policy/Middleware ở giai đoạn sau. UserFactory hiện tại chưa cấp role bắt buộc; không chạy Seeder/UserFactory hiện có vào database thật. Bổ sung state/factory phù hợp khi triển khai Authentication, không đoán vai trò tài khoản cũ.
2. Tạm tắt Remember-me trong luồng Authentication mới. Cột/trait framework vẫn được giữ để tương thích; giai đoạn Model chưa có luồng đăng nhập thật và không thay Authentication theo yêu cầu.
3. Service cần bảo đảm luôn có CV chính khi còn CV hoạt động, xử lý thay/xóa CV chính đúng transaction, duyệt tin/published pointer, chặn chuyển nhóm chuyên môn đã được tham chiếu và giới hạn revision bất biến. Model mass assignment không thay các quy tắc này; không tự động phát AI/notification khi save Model.
4. Append-only history, snapshot, quyền đọc CV/notes/activity logs và public projection phải được áp dụng ở tầng nghiệp vụ. Không coi withTrashed hoặc UUID là quyền truy cập.
5. Hoàn thiện retention/xóa/ẩn danh và xác định FastAPI trước vận hành thật. Lỗi hệ thống mysql.proc không thuộc phạm vi này và chưa được xử lý.

Không phát hiện lệch schema cần migration mới. Các giới hạn composite/notification nêu trên là cách dùng Eloquent được lựa chọn, không phải thay đổi kiến trúc database.
