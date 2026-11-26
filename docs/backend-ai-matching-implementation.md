# Giai đoạn 3.6C — Matching CV–Job và Semantic Matching

Cập nhật 26/11/2026. Đã tích hợp embedding thật vào Laravel, giữ nguyên 27 bảng, UI và Qwen3 1.7B phân tích CV/Job. Chưa triển khai danh sách Recommendation hoặc ghi đánh giá ứng tuyển.

## Cách hoạt động

- `AIMatchingService::forCandidate(User $actor, int $jobId)` lấy CV chính/revision hiện hành của Candidate active; tin lấy qua PublicJobService, dùng published version đã duyệt, còn hạn và công khai hợp lệ.
- Chỉ dùng JSON phân tích completed, có cấu trúc và bằng chứng. Skill matching lấy mã pivot đúng revision, kỹ năng bắt buộc do Recruiter chọn; loại trùng, không biến kỹ năng ưu tiên thành bắt buộc. Kỹ năng chưa tìm thấy nghĩa là chưa có bằng chứng, không khẳng định ứng viên không có.
- `AISemanticSimilarityService` gọi Ollama `/api/embed`. Hai đầu vào dùng cùng model và quy tắc xử lý. Kiểm tra vector là danh sách số hữu hạn, cùng kích thước, khác vector 0; chuẩn hóa L2, cosine là tích vô hướng. Kiểm tra digest trước/sau lần tính để tránh thay model giữa chừng.
- `semantic_score = 100 × clamp(cosine, 0, 1)`; `skill_score = 100 × matched_required / total_required`; không có required skill thì bằng 0 và giải thích. `score = round(0.6 × semantic_score + 0.4 × skill_score, 2)`, formula_version=v1. Không làm tròn điểm thành phần trước tổng.
- Chỉ trả completed khi đủ hai điểm. Thiếu phân tích/dữ liệu nghề nghiệp: insufficient_data. Model thiếu, HTTP lỗi, vector sai hoặc cache đang bận: unavailable, điểm tổng NULL và mã lỗi an toàn. Không log raw CV/request/response.

## Văn bản, dữ liệu cá nhân và giới hạn

CV: kỹ năng, vai trò, kinh nghiệm làm việc và dự án trong kết quả đã kiểm chứng. Job: kỹ năng, vai trò và trách nhiệm. Mã kỹ năng cố định được chuyển thành tên; không sử dụng hồ sơ liên hệ, địa chỉ, lương, tuổi, giới tính, học vấn hoặc ảnh để tính điểm. Tên/email Candidate và tên doanh nghiệp bị loại khỏi nội dung; claim chứa dấu hiệu liên hệ hoặc đặc điểm nhạy cảm bị bỏ.

Preprocessing `career-1`: bỏ HTML, chuẩn hóa khoảng trắng, loại trùng; tối đa 6.000 ký tự mỗi phía, chia đoạn tối đa 1.000 ký tự. Các vector đoạn chuẩn hóa được lấy trung bình có trọng số theo số ký tự và chuẩn hóa lại. `truncate=false`: vượt context sẽ báo lỗi, không âm thầm cắt từ Ollama. Cùng cách xử lý cho cả hai phía; phần vượt 6.000 ký tự không tham gia điểm. Không gửi lại PDF/raw extraction và không gọi Qwen để phân tích lại.

Bộ lọc dữ liệu cá nhân dựa trên trường cho phép, tên đã biết và mẫu nhận diện; chưa phải hệ thống ẩn danh tuyệt đối. Claim nghề nghiệp có thể chứa tên bên thứ ba hoặc thông tin nhạy cảm diễn đạt khác. Cần rà soát tập dữ liệu tiếng Việt trước sử dụng rộng rãi; không dùng điểm để tự tuyển/từ chối.

## Cache và kiểm tra stale

File cache nội bộ mặc định, TTL 1 giờ, chỉ lưu điểm/metadata, không lưu văn bản. Khóa gồm Candidate, ResumeRevision, JobVersion, fingerprint đầu vào, digest model, preprocessing/config và nội dung đã chuẩn bị. Khóa đồng thời 120 giây; HTTP tối đa 90 giây, chờ khóa 2 giây rồi trả unavailable nếu bận. Chưa thêm Queue: gọi trực tiếp lần đầu, các lần sau dùng cache; chưa có UI tự chạy khi tải trang.

Đọc `/api/tags` mỗi lần để cache không dùng lại digest cũ. HTTP chỉ đến loopback 127.0.0.1/[::1], không redirect/proxy; tái sử dụng kiểm tra endpoint của AIOllamaService.

Đọc dữ liệu trong transaction ngắn, đóng trước HTTP; đọc lại sau embedding để phát hiện thay CV, published version hoặc quyền. `isStale()` so fingerprint, model_version và trạng thái. V2 pending/rejected không thay V1. Khi nối vào workflow mới, gọi service ngoài transaction dài và kiểm tra lại trước khi dùng kết quả; không coi kết quả đọc là khóa dữ liệu vĩnh viễn.

Schema recommendation_items yêu cầu ba điểm NOT NULL; application_ai_evaluations yêu cầu đủ điểm khi completed. Bước này không ghi vào hai bảng đó. Recruiter sau này phải dùng Application snapshot/JobVersion đã nộp, không dùng CV hiện tại ghi đè lịch sử.

## File thay đổi

- `app/Services/AISemanticSimilarityService.php`: chuẩn bị văn bản, HTTP, cache, vector và cosine.
- `app/Services/AIMatchingService.php`: điểm tổng thật, trạng thái lỗi và kiểm tra sau HTTP.
- `app/Services/AIOllamaService.php`: tách hàm endpoint để dùng chung, giữ nguyên luồng Qwen.
- `config/ai.php`, `.env.example`: cấu hình embedding riêng.
- `tests/Feature/AIMatchingTest.php`: 5 nhóm bổ sung (vector, tổng/cache, lỗi/thiếu dữ liệu, stale/quyền, xử lý văn bản).
- `tests/semantic-matching-probe.php`: thử cặp tiếng Việt giả lập bằng model thật, không tạo dữ liệu nghiệp vụ.

## Vận hành và thử lại

Ollama đã khảo sát: **0.40.17**. Đã tải `embeddinggemma:300m`; Qwen3 vẫn giữ nguyên. Digest embedding: `85462619ee721b466c5927d109d4cb765861907d5417b9109caebc4e614679f1`.

```dotenv
OLLAMA_BASE_URL=http://127.0.0.1:11434
OLLAMA_MODEL=qwen3:1.7b
AI_EMBEDDING_ENABLED=true
AI_EMBEDDING_CACHE_STORE=file
```

Máy triển khai khác: `ollama pull embeddinggemma:300m`, bảo đảm Ollama chạy; nếu Laravel đang cache cấu hình, chạy `php artisan config:clear`. Không cần chạy worker cho Matching hiện tại. Tắt `AI_EMBEDDING_ENABLED=false` thì chỉ còn điểm kỹ năng, tổng NULL. Không đổi trọng số v1 khi chưa được phê duyệt.

Trong `php artisan tinker`, gán ID thật thuộc Candidate và jobs.id:

```php
$candidate = App\Models\User::findOrFail($candidateId);
$service = app(App\Services\AIMatchingService::class);
$result = $service->forCandidate($candidate, $jobId);
collect($result)->only(['status', 'skill_score', 'semantic_score', 'score', 'missing_data', 'model_version'])->all();
$service->isStale($candidate, $result);
```

Controller tương lai phải truyền user đã xác thực, không nhận candidate_id tùy ý từ client.

## Kết quả kiểm thử

`php tests/Database/models.php --auth --matching`: **28 đạt, 0 thất bại, 1 bỏ qua; 231 assertions**. Test Ollama Job live có sẵn được bỏ qua có chủ đích; regression Qwen CV/Job dùng mock. MariaDB test riêng, runner xác nhận schema/lịch sử migration gốc không đổi; không reset/seed db_job.

Thử thật riêng bằng `php tests/semantic-matching-probe.php`, cặp nội dung nghề nghiệp tiếng Việt giả lập: CV Backend PHP/Laravel/MySQL, Job yêu cầu thêm Docker. Không dùng CV của người thật, không sửa tin/đơn thật.

| Chỉ số đo | Kết quả |
| --- | --- |
| Vector | 768 chiều; 2 đoạn |
| Cosine thật | 0,9018392449 |
| Semantic / Skill / Tổng | 90,18392449 / 75 / **84,11** |
| Lần đầu (gồm HTTP/model load) | 4,781 giây |
| Lần dùng cache | 0,019 giây |
| Model resident / VRAM do api/ps báo | 618.617.896 byte, khoảng 590 MiB |
| PHP peak allocation | 24 MiB |
| GPU khảo sát | RTX 3050 Laptop GPU, 4.096 MiB |

Đây là một lần đo, không phải benchmark hay số đo đỉnh VRAM/RAM toàn hệ thống. Chưa đo tranh chấp GPU khi Qwen đang chạy cùng lúc. Cặp giả lập xác nhận embedding thật và phép tính, chưa chứng minh chất lượng xếp hạng cho mọi CV/JD tiếng Việt. Bước tiếp theo cần tập kiểm định phù hợp, quy tắc văn bản dài và Queue khi triển khai hàng loạt; chưa thêm bảng hoặc backend Python.

Tham khảo chính thức: [Ollama Embed API](https://docs.ollama.com/api/embed), [EmbeddingGemma 300M](https://ollama.com/library/embeddinggemma:300m).
