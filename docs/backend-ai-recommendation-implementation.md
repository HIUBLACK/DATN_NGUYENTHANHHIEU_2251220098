# Giai đoạn 3.6D — Đề xuất việc làm AI

## Sửa lỗi Recommendation FAIL — 26/11/2026

Kiểm thử sau sửa: **16 test đạt, 0 thất bại, 189 assertions** (`--auth --recommendation`) trên MariaDB riêng; gồm test tái hiện batch/413 và regression Recommendation/Matching. Runner xác nhận schema và lịch sử migration database gốc không đổi.

**Nguyên nhân đã xác minh:** `num_batch=128` của request EmbeddingGemma quá nhỏ cho đoạn đầu vào. Cùng cặp CV revision ID **15** (resume_id=15, revision=1) và JobVersion **27** (jobs.id=18), hai đoạn dài 750/270 ký tự: cấu hình 128 trả **HTTP 413**, `the input length exceeds the context length`; đổi riêng num_batch thành **2048** trả HTTP 200 và hai vector. Giữ num_ctx=2048, truncate=false, model/digest và công thức v1.

Run **2** bị lỗi ở bước đầu, attempt_count=3; failed_jobs **9, 10** chỉ ghi `Recommendation temporarily unavailable.`. Matching giữ mã `embedding_request_failed`, nhưng Recommendation bắt exception chung và callback failed ghi đè thành queue_failed, làm mất dấu nguyên nhân. Không có bằng chứng lỗi schema, JSON hoặc token là nguyên nhân của lần FAIL này.

File sửa: `AISemanticSimilarityService` dùng batch 2048 và nhận diện HTTP 413; `AIMatchingService` chuyển tiếp mã `embedding_input_too_long`; `AIRecommendationService` phân biệt lỗi dữ liệu vĩnh viễn với lỗi tạm thời, giữ mã lỗi sau failed callback và log metadata an toàn (run/version/class/code, không raw CV/response). Test bổ sung trong `AIRecommendationTest` tái hiện đầu vào dài và HTTP 413; không tăng số retry. Tin chưa phân tích vẫn được bỏ qua theo quy tắc cũ; 413 thật sau sửa không được giả thành điểm 0 hoặc completed, vì chưa thể khẳng định lỗi thuộc riêng phía Job thay vì CV.

Kiểm tra thật sau sửa: Matching cặp nêu trên trả **completed**, semantic_score=83,9353717165; skill_score=0; score=**50,36**. Đây là phép kiểm tra dữ liệu đã phân tích qua Ollama thật, chỉ đọc dữ liệu nghiệp vụ và ghi cache nội bộ. Không ghi recommendation_items hoặc reset/seed database chính. Run 2 vẫn **failed / queue_failed**, lease=NULL, attempt_count=3; không tự sửa trạng thái lịch sử hoặc retry queue thật.

**Cách kiểm tra lại website:** dừng worker cũ bằng Ctrl+C, chạy lại lệnh worker dưới đây để nạp code mới. Mở trang gợi ý, bấm **Kiểm tra lại đề xuất** sau cooldown 60 giây; nếu run cùng dữ liệu thì hệ thống tạo token mới và reset lượt thử theo cơ chế sẵn có. Chờ xử lý rồi tải lại trang. Không dùng `queue:retry all`. Run phải completed và có item điểm thật thì mới coi luồng Recommendation thật đã hoàn tất; phép thử Matching đơn lẻ chưa thay thế bước này.

Cập nhật 26/11/2026. Giữ 27 bảng, layout Candidate và SMTP. Không tạo migration hoặc seed/reset db_job.

## Luồng hoạt động

Candidate mở `/ung-vien/goi-y-ai` → kiểm tra CV chính/revision đã phân tích → xác định pool tối đa 20 tin công khai mới nhất → tạo/tái sử dụng Recommendation Run → Queue `ai` → Matching 3.6C → lưu item → hoàn tất run → tải lại trang để xem.

HTTP chỉ đọc metadata model qua `/api/tags` (timeout 5 giây), không tính embedding. Run gắn Candidate, resume/revision, recommendation_generation, model digest/preprocessing, formula v1 và request_key. Khóa request còn bao gồm nội dung phân tích, kỹ năng, danh mục và pool tin. Tải lại/bấm nhiều lần với cùng dữ liệu dùng lại run. Tin mới lọt vào pool, đổi published version, đổi CV hoặc danh mục tạo khóa mới; run cũ được đánh dấu stale.

Pool sắp published_at giảm dần rồi id giảm dần; không phải quét toàn bộ thị trường. Giới hạn cấu hình `ai.recommendation.max_jobs` mặc định 20 và chặn tối đa 20. Danh sách kết quả phân trang 10 tin, sắp score giảm dần rồi job_id tăng dần để ổn định khi đồng điểm. Không đặt ngưỡng điểm tùy ý hoặc đổi công thức v1.

## Queue, retry và lưu dữ liệu

- Mỗi tác vụ xử lý một cặp CV–Job, dùng khóa GPU chung `ollama-cv-single-worker`. Giữa các tin nối tiếp bằng một tác vụ mới, không giữ GPU cho cả 20 tin trong một job.
- Job dispatch sau commit, connection/queue `ai`, timeout 240 giây; retry tối đa 3 lần, backoff 30/90 giây. Lease run 260 giây; retry_after connection giữ 360 giây.
- Token đổi sau mỗi bước; job/token cũ không được ghi lại. Row lock của run và UNIQUE(run_id,job_id) bảo vệ chống trùng. attempt_count đếm lượt thử của bước hiện tại, reset khi chuyển bước.
- Chỉ lưu Matching completed có đủ ba điểm hữu hạn trong 0–100 và đúng revision/JobVersion/model. Không gọi lại Qwen để phân tích.
- Item được lưu theo từng bước nhưng hoàn toàn ẩn khi pending/processing/failed. Bước cuối lưu item và chuyển completed trong cùng transaction. Lỗi giữa chừng không công bố danh sách một phần.
- Tin chưa phân tích/không đủ văn bản nghề nghiệp được bỏ qua, không tạo item điểm 0. CV thiếu bằng chứng hoặc mã kỹ năng sai làm run failed. Lỗi kết nối/embedding tạm thời được retry; vector sai hoặc endpoint không hợp lệ thất bại rõ ràng. Pool/CV thay đổi làm run stale, không xuất kết quả cũ.
- Nút **Kiểm tra lại đề xuất** tạo run cho dữ liệu mới; với failed/tác vụ bị gián đoạn có thể chạy lại sau 60 giây và lease đã hết. Khi thử lại cùng run, xóa item tạm, tạo token mới. Run completed còn hợp lệ được tái sử dụng, không tạo lượt tốn tài nguyên chỉ vì bấm nút.
- Cache embedding của 3.6C giúp retry cùng đầu vào không tính lại các cặp đã thành công. Không cache trạng thái quyền công khai.

## Giao diện và quyền

Giữ bố cục card, thanh điểm, bộ lọc địa điểm/hình thức; thay dữ liệu demo bằng kết quả thật. Hiển thị điểm /100, Semantic/Skill, kỹ năng khớp và kỹ năng chưa tìm thấy bằng chứng; link tới `jobs.show`.

Có trạng thái chưa có CV, CV chờ/lỗi phân tích, pending/processing, failed/unavailable/stale và completed rỗng. Chỉ hiển thị điểm khi run hợp lệ. Truy vấn lại PublicJobService và published pointer khi hiển thị, nên tin đóng/tạm dừng/blocked/hết hạn hoặc Company/Recruiter không active không hiện.

Candidate không truyền user_id/run_id từ trình duyệt; Service dùng tài khoản đã xác thực và kiểm tra lại role/status. POST làm mới có CSRF, role middleware và throttle 5/phút. Không trả CV, JSON phân tích riêng tư hoặc nội dung exception ra UI. Xóa thuật toán điểm giả trong CandidateCatalog; trang này không còn cảnh báo demo hoặc link jobs.demo.

## File/routes thay đổi

- Thêm `app/Services/AIRecommendationService.php`, `app/Jobs/AIRecommendJobsJob.php`.
- `CandidatePageController`, `routes/web.php`: nối GET hiện tại và POST `/ung-vien/goi-y-ai/lam-moi` (`candidate.recommendations.refresh`).
- `resources/views/Candidate/ca_pages/recommendations.blade.php`, `Candidate/master.blade.php`: dữ liệu thật và trạng thái.
- `CandidateCatalog`: bỏ phương thức tạo điểm demo không còn dùng.
- `config/ai.php`: giới hạn/cooldown/phân trang; `AISemanticSimilarityService`: timeout đọc tags 5 giây.
- `tests/Feature/AIRecommendationTest.php`, runner `tests/Database/models.php --recommendation`.

## Cách vận hành

1. Ollama có `qwen3:1.7b` và `embeddinggemma:300m`, embedding bật như Giai đoạn 3.6C.
2. Candidate có CV chính đã phân tích; tin đã duyệt/công khai và đã phân tích AI.
3. Chạy **một worker** dùng chung cho các tác vụ AI:

```powershell
php artisan queue:work ai --queue=ai --tries=3 --timeout=240 --sleep=2
```

4. Mở trang gợi ý, chờ worker xử lý và tải lại trang. Không có polling/embedding trong request tải trang.
5. Lỗi: kiểm tra Ollama/worker, rồi bấm Kiểm tra lại đề xuất sau cooldown. Không cần retry hàng loạt failed_jobs. Sau cập nhật code, khởi động lại worker đang chạy để nạp code mới.

## Kiểm thử và giới hạn

Chạy `php tests/Database/models.php --auth --recommendation` trên MariaDB ngẫu nhiên riêng; mock HTTP Ollama, dùng Matching/Service/DB thật trong test. **15 test đạt, 0 thất bại, 181 assertions**. Gồm 5 nhóm Recommendation và regression Matching: xếp hạng/render Blade; chống trùng/retry/cache; stale CV/published/catalog/tin mới; public visibility/quyền; missing/pending/failed/empty/completed. Runner xác nhận schema và lịch sử migration database gốc không đổi.

Đã compile Blade và kiểm tra routes; HTTP feature test render trang thật với dữ liệu test. Chưa kiểm tra ảnh chụp trình duyệt/mobile và chưa chạy worker Recommendation trên queue db_job. Không tuyên bố E2E Ollama thật của 3.6D; phép thử embedding thật trước đó thuộc 3.6C.

Giới hạn trước 3.6E: chỉ xét pool 20 tin mới nhất, có thể bỏ qua tin cũ phù hợp hơn; chưa có thuật toán chọn pool đa dạng. Nếu dữ liệu đổi liên tục, run có thể bị stale và cần chạy lại. Metadata model phải truy cập được để hiển thị kết quả cache. Item thất bại được giữ nội bộ cho tới lần thử lại; cần chính sách dọn run cũ trước vận hành dài hạn. Chưa có scheduler tự phục hồi chuỗi queue bị gián đoạn; nút thử lại khôi phục khi lease/cooldown đã hết. 3.6E phải dùng đúng application snapshot/JobVersion lúc nộp, không dùng CV hiện hành thay lịch sử.
