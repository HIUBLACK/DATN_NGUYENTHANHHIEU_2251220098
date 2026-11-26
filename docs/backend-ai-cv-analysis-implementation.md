# Phân tích CV bằng Ollama — Giai đoạn 3.6A

Cập nhật 24/11/2026.

## Chức năng đã triển khai

- Upload/thay PDF dispatch sau commit, vào queue AI riêng. AI lỗi không làm mất PDF và không cản ứng tuyển.
- Đọc PDF private bằng smalot/pdfparser, gọi Ollama cục bộ, validate JSON trước khi lưu. Không OCR.
- Lưu theo revision; kiểm tra token, current revision, soft delete và row lock trước khi ghi. Không sửa snapshot hay hồ sơ Candidate.
- Đối chiếu kỹ năng với danh mục đang hoạt động, lưu resume_skills đúng revision, không thêm kỹ năng hoặc tự gán confidence score.
- Giữ trang CV hiện tại; bổ sung trạng thái, phần mở rộng kết quả và nút Phân tích lại khi thất bại. Chỉ chủ CV được xem/thao tác.
- Migration được duyệt đã chạy: `2026_11_24_000001_add_analysis_result_to_resume_revisions`, thêm JSON nullable `analysis_result`. Vẫn 33 bảng; không backfill/seed. Rollback từ chối xóa cột đã có kết quả.

## File và route

Tổ chức lại theo yêu cầu: `AIResumeController` chỉ nhận yêu cầu thử lại và trả response; `AIResumeAnalysisService` quản lý vòng đời phân tích và đọc PDF; `AIOllamaService` gọi model, kiểm tra JSON; `AIAnalyzeResumeJob` xử lý queue; `config/ai.php` giữ cấu hình. Các biến môi trường OLLAMA_* không đổi. Controller CV cũ không còn phương thức AI; Service CV chỉ gọi enqueue sau khi lưu revision. PHPDoc và chú thích nghiệp vụ dùng tiếng Việt, không thêm Repository/DTO/interface.

Sau khi đổi tên Job, dừng và mở lại worker AI để nạp class mới. Nếu còn job mang tên `AnalyzeResume` từ bản code cũ, cần xử lý chuyển tiếp riêng trước khi chạy chúng; không retry hàng loạt job cũ. Lần kiểm thử này không xử lý queue thật.

- Mới: `app/Services/AIOllamaService.php`, `AIResumeAnalysisService.php`, `app/Jobs/AIAnalyzeResumeJob.php`, `config/ai.php`, migration nêu trên, `resources/views/Candidate/resume-analysis.blade.php`.
- Tích hợp: CandidateResumeService, CandidateDocumentController, CandidatePageController, ResumeRevision, trang resumes.blade.php, config/queue.php, .env.example, routes/web.php.
- Test: `tests/Feature/ResumeAnalysisTest.php`, tùy chọn `--cv-analysis` trong tests/Database/models.php.
- Route mới: POST `/ung-vien/cv/{id}/phan-tich-lai`, tên `candidate.analysis-retry`; UUID, CSRF, middleware Candidate active, throttle 5/phút, session lock và kiểm tra quyền trong Service.

## Cấu hình và dữ liệu

Máy đã có model `qwen3:1.7b`. Mặc định trong config, có thể ghi đè bằng .env:

```dotenv
OLLAMA_BASE_URL=http://127.0.0.1:11434
OLLAMA_MODEL=qwen3:1.7b
OLLAMA_TIMEOUT=120
OLLAMA_MAX_CHARACTERS=10000
OLLAMA_CONTEXT=8192
OLLAMA_OUTPUT_TOKENS=2500
```

Chỉ cho phép HTTP loopback literal 127.0.0.1 hoặc [::1], không proxy/redirect hoặc URL do client cung cấp. Giới hạn cứng 16.000 ký tự đầu vào, 150 giây HTTP, 8.192 context, 3.000 token đầu ra. Tắt thinking/streaming; keep_alive=0 giải phóng model sau xử lý. Tham chiếu [Ollama Chat API](https://docs.ollama.com/api/chat).

JSON có schema_version=1 và bảy mảng: technical_skills, professional_roles, work_experience, education, certifications, projects, professional_summary. Mỗi mảng tối đa 12 phần tử {text, evidence}; text tối đa 300, evidence 600 ký tự. Evidence phải có trong văn bản CV đã đọc; text phải có trong evidence. Không chấp nhận key thừa; thiếu thông tin trả mảng rỗng.

Prompt coi CV là dữ liệu không tin cậy, bỏ qua chỉ dẫn bên trong và không suy diễn thuộc tính nhạy cảm, bằng cấp hoặc số năm. AI không có tools/quyền truy cập CV người khác. Kiểm tra bằng chứng giảm thông tin bịa, không bảo đảm phân loại ngữ nghĩa hoàn toàn chính xác. Tóm tắt hiện là trích đoạn chuyên môn, không phải văn bản AI tự sáng tác. Blade escape kết quả.

## Chạy AI Queue

Mở Ollama và chạy **một worker** tại thư mục dự án:

```powershell
php artisan queue:work ai --queue=ai --tries=3 --timeout=240 --sleep=2
```

Connection ai dùng queue_jobs hiện có, không phụ thuộc default queue sync. SMTP gửi trực tiếp giữ nguyên. Khóa database cache 300 giây, retry_after=360, worker timeout=240, HTTP có timeout riêng. Tối đa ba lần chạy, backoff 30/90 giây; PDF không có văn bản thất bại ngay. Thử lại từ giao diện tạo token mới; job/token cũ không ghi đè.

Windows không có PCNTL nên timeout worker không phải cơ chế cưỡng chế dừng đáng tin cậy; cần giữ một worker và theo dõi PDF bất thường. Có thể đặt OLLAMA_NUM_PARALLEL=1 và OLLAMA_MAX_LOADED_MODELS=1 cho **tiến trình Ollama**, rồi khởi động lại Ollama; đây không phải biến Laravel. Không chạy nhiều model trên GPU 4 GB.

Không log nội dung CV/response AI; trạng thái lỗi dùng thông báo chung. Kiểm tra bằng `php artisan queue:failed`; ưu tiên nút thử lại theo CV, không retry hàng loạt job cũ. Chưa tự chạy worker nền lâu dài.

## Kiểm thử và giới hạn

Lệnh: `php tests/Database/models.php --auth --cv-analysis`.

Năm nhóm mock Ollama: upload/thay revision và after-commit; lưu JSON/kỹ năng, không xử lý lặp; bỏ qua revision cũ/soft delete; IDOR, schema/bằng chứng không hợp lệ và chặn endpoint ngoài máy; lỗi Ollama, ba lần thử và token mới. Chạy regression Auth/Policies trên MariaDB riêng; runner đối chiếu schema/history chính, không reset/seed db_job.

Kết quả: **48/48 test đạt, 453 assertions, 0 thất bại** (gồm năm test nhóm AI và regression Auth/Policies). Runner xác nhận schema và lịch sử migration chính không thay đổi trong lúc kiểm thử. Pint và biên dịch Blade đạt. Từ điển dữ liệu/ERD trong database-design.md đã bổ sung cột được duyệt.

Ollama thật với văn bản CV giả lập: JSON qua validator thành công trong khoảng **9,2 giây**, có kỹ năng, vị trí, kinh nghiệm, học vấn, dự án và trích đoạn chuyên môn. Không dùng CV người thật. Đây là smoke test model/kết nối, chưa phải E2E trình duyệt + worker + PDF thật.

Kiểm tra thủ công: bật Ollama/worker, upload PDF có văn bản chọn được, tải lại trang CV và mở kết quả. Thử thay PDF khi đang xử lý; thử tắt Ollama rồi bật lại và Phân tích lại sau khi thất bại.

CV có sẵn không tự động backfill; thay PDF sẽ tạo revision mới và kích hoạt AI. Chưa có polling tự động; CV dài bị cắt theo giới hạn, PDF scan không OCR. Chưa triển khai matching/recommendation/chấm điểm hoặc khôi phục xác nhận kỹ năng CV chính.
