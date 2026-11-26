# Giai đoạn 3.6E — AI đánh giá hồ sơ ứng tuyển

Cập nhật: 26/11/2026. Giữ nguyên 27 bảng, giao diện Recruiter và công thức Matching v1.

## Chức năng

- Recruiter yêu cầu đánh giá riêng từng hồ sơ tại trang chi tiết, không tự chạy hồ sơ cũ hàng loạt.
- Danh sách hiển thị điểm `/100`, trạng thái; sử dụng bộ lọc và sắp xếp AI có sẵn.
- Chi tiết hiển thị điểm tổng, Semantic, Skill, kỹ năng có bằng chứng, kỹ năng bắt buộc chưa tìm thấy bằng chứng, giải thích và thời điểm đánh giá.
- Điểm là mức độ phù hợp tham khảo, không phải xác suất trúng tuyển. AI không thay đổi trạng thái ứng tuyển.

## Nguồn dữ liệu và bảo mật

- Dùng snapshot private của application: kiểm tra namespace Candidate, kích thước và SHA-256 trước khi đọc PDF.
- Chỉ dùng phân tích của đúng `resume_id + resume_revision` khi hash trùng snapshot và claim được kiểm chứng lại với văn bản snapshot.
- Nếu thiếu phân tích tương ứng, đọc PDF và gọi Qwen qua code hiện có; không sửa snapshot/revision hoặc phân tích lịch sử.
- Dùng đúng `applications.job_version_id`, không lấy CV chính hay phiên bản công khai mới nhất. JobVersion lịch sử thiếu phân tích hợp lệ thì báo lỗi, không thay bằng phiên bản khác.
- Kỹ năng lấy từ claim đã xác minh qua JobCatalog; Skill chỉ xét kỹ năng bắt buộc của tin. Tái sử dụng EmbeddingGemma, cosine và `round(0.6 × semantic + 0.4 × skill, 2)`.
- Policy `viewNotes` và `viewSnapshot` kiểm tra Recruiter active sở hữu doanh nghiệp hợp lệ. Không mở quyền riêng tư cho Admin/Candidate hoặc URL PDF công khai.

## Queue và lưu kết quả

- POST `recruiter.application-evaluate`: `/nha-tuyen-dung/quan-ly/ho-so/{id}/danh-gia-ai`, có middleware Recruiter, CSRF, throttle và session block.
- Khóa application khi tạo yêu cầu; unique constraint và fingerprint ngăn bản ghi trùng. Pending không bị đẩy thêm job khi bấm lại.
- Dispatch sau commit, connection/queue `ai`, khóa GPU chung với các module AI. Timeout 300 giây, tối đa 3 lượt; lỗi dữ liệu/quyền/stale kết thúc ngay, lỗi tạm thời có retry hữu hạn.
- Bảng `application_ai_evaluations` lưu nguồn lịch sử, điểm, matched/missing skills, giải thích, model/formula version, token, attempt và lease. Chỉ completed khi lưu đủ kết quả hợp lệ; cập nhật kiểm tra token và trạng thái.
- `model_version` là `application-v1:<sha256>` của nguồn lịch sử, digest embedding, cấu hình và thuật toán. Tên model embedding thực tế nằm trong giải thích. Kiểm tra stale trước khi ghi và khi hiển thị.
- `unavailable`/`stale` là trạng thái UI; DB vẫn dùng pending/processing/completed/failed. Không gọi Qwen/embedding khi mở trang, chỉ kiểm tra metadata `/api/tags`.
- Lọc/sắp xếp theo lần đánh giá đã lưu gần nhất; kết quả stale được ghi rõ và ẩn điểm. Lỗi Queue sau commit chuyển failed để thử lại, không rollback application. Không log CV/raw response.

## File thay đổi

- Mới: `app/Services/AIApplicationEvaluationService.php`, `app/Jobs/AIEvaluateApplicationJob.php`.
- Tái sử dụng PDF/validation/Matching: `AIResumeAnalysisService.php`, `AIOllamaService.php`, `AIMatchingService.php` trong `app/Services`.
- HTTP/UI: `app/Http/Controllers/RecruiterApplicationController.php`, `routes/recruiter.php`, `resources/views/employer/applications.blade.php`, `resources/views/employer/application-detail.blade.php`.
- Test: `tests/Feature/AIApplicationEvaluationTest.php`, `tests/Database/models.php`, `tests/Feature/RecruiterApplicationsTest.php`. Test email cũ được đổi kỳ vọng sang gửi trực tiếp sau commit; không sửa code SMTP.

## Kiểm thử

Lệnh: `php tests/Database/models.php --auth --evaluation`.

Năm nhóm test mới:

1. Lưu/hiển thị Matching từ vector giả lập: Semantic 80, Skill 100, tổng **88/100**, đúng application/JobVersion.
2. Fallback snapshot khi revision thiếu JSON; thay PDF và published V2 vẫn giữ nguồn lịch sử.
3. Chống bấm trùng, retry embedding thành công, token cũ bị bỏ qua và stale khi cấu hình thay đổi.
4. Recruiter khác/Candidate/Admin bị chặn; khóa Recruiter trước lúc chạy không sinh điểm.
5. Snapshot hỏng hoặc JobVersion thiếu phân tích không tạo điểm giả, không đổi trạng thái ứng tuyển; UI báo lỗi.

Kết quả: **36 test đạt, 382 assertions, 0 thất bại**, gồm 5 test mới và regression Recruiter Applications, Matching, Recommendation, CV Analysis. Runner dùng MariaDB test riêng; không reset/seed `db_job`, không đổi schema/lịch sử migration chính. Blade compile và route mới đã kiểm tra.

Ollama được mock; chưa xác nhận một lượt đánh giá hồ sơ thật qua Ollama trong giai đoạn này.

## Vận hành và giới hạn

1. Bật Ollama với `qwen3:1.7b` và `embeddinggemma:300m` hiện có.
2. Chạy **một worker AI**: `php artisan queue:work ai --queue=ai --tries=3 --timeout=300 --sleep=2`. Dừng/chạy lại worker cũ để nạp code; không retry hàng loạt.
3. Recruiter mở một hồ sơ của mình → **Yêu cầu đánh giá AI** → đợi Queue rồi tải lại trang. Kiểm tra UI và DB: `evaluation_status = completed`, điểm/arrays/giải thích hợp lệ, không chỉ dựa vào Queue DONE.
4. Thử lại riêng hồ sơ failed sau thời gian chống bấm liên tục một phút. Pending cần worker xử lý, không tạo thêm job.

Giới hạn: không OCR PDF ảnh; JobVersion lịch sử cần có phân tích hợp lệ; chưa polling UI. Fallback snapshot chưa cache JSON riêng nên retry sau lỗi embedding có thể gọi lại Qwen. Không thêm schema để giải quyết các giới hạn này.
