# Phân tích tin tuyển dụng bằng AI — Giai đoạn 3.6B

Cập nhật 24/11/2026. Đã triển khai theo phương án metadata JobVersion được người dùng phê duyệt.

## Chức năng và luồng xử lý

Admin approve → lưu review_status và published_version_id trong transaction hiện có → sau commit chuẩn bị metadata, dispatch AIAnalyzeJobJob → queue ai → AIJobAnalysisService → AIOllamaService → validate JSON/bằng chứng → lưu theo version_id và token.

Không gọi AI trong request duyệt. Lỗi enqueue/Ollama không đảo kết quả phê duyệt. Chỉ phiên bản approved được phân tích; pending/rejected không được enqueue. Tác vụ lặp bị chặn bằng row lock, token, trạng thái và lease. Tối đa ba lần thử với backoff 30/90 giây; failed có thể được đưa lại vào queue bằng Service, tạo token mới.

V1/V2 lưu riêng; V1 hoàn tất trễ chỉ cập nhật metadata V1. Dữ liệu hiện hành phải được lấy theo jobs.published_version_id và chỉ sử dụng khi ai_analysis_status=completed. Khi Recruiter tạo V2 bằng replicate, toàn bộ metadata AI V1 bị loại khỏi bản sao. Worker không cập nhật updated_at nghiệp vụ, nội dung đã duyệt, published pointer hoặc trạng thái open/paused/closed/blocked.

Giữ nguyên UI và SMTP trực tiếp; chưa triển khai Matching/Recommendation hoặc HTTP endpoint phân tích lại.

## Schema và file

Migration đã chạy: 2026_11_24_100000_add_ai_analysis_to_job_versions. Vẫn **33 bảng**, không seed/backfill.

Mười cột mới tại job_versions:
- ai_analysis_result JSON nullable.
- ai_analysis_status VARCHAR(20) nullable: NULL/pending/processing/completed/failed.
- ai_processing_token CHAR(36) nullable.
- ai_attempt_count SMALLINT UNSIGNED, mặc định 0.
- ai_started_at, ai_lease_expires_at, ai_next_retry_at, ai_analyzed_at: DATETIME(6) nullable.
- ai_model_version VARCHAR(100) nullable.
- ai_error_code VARCHAR(60) nullable.

CHECK giới hạn trạng thái, số lần thử <=3 và metadata bắt buộc khi completed. Index trạng thái/thời điểm retry/id. Không cần FK/unique mới vì kết quả nằm trên PK của JobVersion. Không thêm trường AI vào fillable. Rollback từ chối xóa cột nếu đã có kết quả.

File mới: app/Services/AIJobAnalysisService.php, app/Jobs/AIAnalyzeJobJob.php, migration và tests/Feature/AIJobAnalysisTest.php.
File cập nhật: AIOllamaService, JobVersion, JobModerationService, RecruiterJobService, tests/Database/models.php, tài liệu database.
Không thêm client AI, Repository, DTO hoặc interface. Các phương thức nghiệp vụ có PHPDoc/chú thích tiếng Việt.

## JSON và nguồn dữ liệu

JSON gồm schema_version=1, structured_requirements và bảy mảng:
required_skills, preferred_skills, professional_roles, experience_requirements, education_certifications, responsibilities, other_requirements.

Mỗi phần tử AI có text/evidence, dùng cùng validator với CV: kiểm tra key, kiểu, số lượng, độ dài; evidence phải nằm trong phần văn bản đã gửi, text phải nằm trong evidence. Không tự tính số năm kinh nghiệm hoặc thêm bằng cấp.

structured_requirements lấy từ JobVersion và danh mục thật: vị trí, chuyên môn, cấp bậc, tỉnh/thành, địa điểm, hình thức làm việc và kỹ năng kèm is_required. Nội dung này đưa lên đầu prompt, sau đó là tiêu đề, yêu cầu, mô tả, kinh nghiệm nếu có và quyền lợi.

Kỹ năng AI đối chiếu tên không phân biệt hoa/thường với skills đang hoạt động; khớp có skill_id, không khớp NULL. Yêu cầu is_required do Recruiter nhập luôn ưu tiên khi AI phân loại khác. Không thêm skill hoặc sửa job_version_skills. Trùng trong mỗi nhóm được gộp.

## Vận hành

Dùng config/ai.php và OLLAMA_* hiện có, model qwen3:1.7b, chỉ endpoint loopback. Cùng giới hạn prompt/context/output/timeout của CV. Cả hai Job dùng chung khóa database cache để tuần tự gọi Ollama.

Khởi động lại worker đang chạy để nạp code mới, chỉ chạy một worker:

```powershell
php artisan queue:work ai --queue=ai --tries=3 --timeout=240 --sleep=2
```

Kiểm tra theo job_versions.id: ai_analysis_status, ai_attempt_count, ai_error_code, ai_analyzed_at. Không xem JSON pending/failed như kết quả hoàn chỉnh.

Thử lại một phiên bản failed đã xác định rõ trong Tinker:

```php
app(\App\Services\AIJobAnalysisService::class)->enqueue($versionId);
```

$versionId phải là ID phiên bản cần xử lý, không phải jobs.id. Không retry hàng loạt queue cũ. Nếu dispatch lỗi, Service cố ghi queue_failed; lỗi database ở bước enqueue được log chỉ với version_id. Sau khi khắc phục có thể enqueue lại phiên bản approved chưa có trạng thái hoặc failed. Chưa có cơ chế outbox tự khôi phục nếu tiến trình dừng ngay sau commit.

## Kiểm thử

Lệnh: php tests/Database/models.php --auth --job-analysis.

Năm nhóm test: enqueue sau commit và không trùng; JSON/kỹ năng và giữ updated_at; V1/V2 độc lập/không sao chép metadata; JSON ngoài schema bị chặn; timeout, ba lần thử và token cũ. Tái sử dụng regression CV, Jobs và Moderation trên MariaDB test riêng. Không gọi model thật hoặc gửi email thật trong tests.

Kết quả: **32/32 test đạt, 368 assertions**, gồm năm test AI Job và regression CV/Jobs/Moderation. Pint đạt; migration metadata AI đã chạy thành công trên MariaDB. Runner xác nhận schema và lịch sử migration database chính không đổi trong lúc chạy test. Chưa chạy phân tích Job bằng Ollama thật hoặc xử lý queue thật trong giai đoạn này.

## Giới hạn

- Không OCR, không gửi dữ liệu ra ngoài; AI không có tools và không được làm theo chỉ dẫn trong tài liệu. Validator ngăn key lạ/khẳng định không có bằng chứng, nhưng không bảo đảm phân loại ngữ nghĩa tuyệt đối.
- Văn bản dài bị giới hạn; kỹ năng có cấu trúc đầy đủ vẫn nằm trong structured_requirements.
- Chưa backfill tin đã duyệt trước khi triển khai; cần chỉ định version bằng Service nếu muốn phân tích các tin đó.
- Trên Windows không PCNTL, timeout HTTP vẫn có hiệu lực nhưng worker timeout không bảo đảm cưỡng chế dừng mọi xử lý. Giữ một worker, không chạy model khác đồng thời.
