# Điều tra lỗi AI phân tích tin tuyển dụng

Ngày 24/11/2026. Không sửa schema, nội dung tin, published pointer, UI hoặc dữ liệu AI thật; không chạy lại queue thật.

## Chẩn đoán

JobVersion lỗi gần nhất là **19**, thuộc **jobs.id=13**. Metadata: failed, analysis_failed, attempt_count=3, token khớp payload failed job, lease NULL, result NULL. Failed job lúc 13:54:18 UTC (20:54:18 Việt Nam); log có các lần lỗi chung lúc 13:51:22, 13:52:19 và 13:54:18 UTC.

Log cũ chỉ có `Job AI analysis failed; retry is limited.` và mã analysis_failed. Vì code catch chung đã bỏ lỗi gốc, không thể chứng minh chính xác lỗi lịch sử là JSON/evidence hay database. Không suy luận rằng thời lượng 26–27 giây là timeout: HTTP timeout đang là 120 giây.

Đã tái hiện trên tin giả lập tiếng Việt, không có dữ liệu thật: Ollama trả response nhưng validator ném **unsupported_ai_claim** sau 7,4 giây. Đây là lỗi evidence, sau khi gọi AI và trước khi ghi database. Cấu hình đang dùng qwen3:1.7b, context 8192, output 2500, input 10000 ký tự. Không đổi model/cấu hình.

So sánh CV/Job: dùng chung HTTP, schema dạng mảng text/evidence và giới hạn. CV đã có prompt trích đoạn ngắn và loại mục không khớp bằng chứng; Job vẫn hủy toàn bộ kết quả vì một mục không khớp. Prompt Job chưa yêu cầu rõ giữ nguyên ngôn ngữ, dấu câu và không ghép các đoạn xa nhau. Không có bằng chứng schema quá phức tạp hoặc GPU bị lỗi trong lần thử giả lập.

CHECK yêu cầu completed có result, analyzed_at và model_version; Service ghi cả ba cùng transaction. PK JobVersion xác định nơi lưu, token/lease và số lần thử được kiểm tra. Test MariaDB xác nhận CHECK không cản dữ liệu hợp lệ. Không đọc/in nội dung tin thật hoặc raw response thật.

## Sửa đổi

- `AIOllamaService.php`: chỉ cải thiện nhánh prompt Job, yêu cầu tối đa 3 trích đoạn ngắn mỗi nhóm, giữ nguyên ngôn ngữ/chữ/dấu câu. JSON vẫn validate đủ key, kiểu, độ dài, số lượng. Job loại bỏ từng mục không qua đối chiếu evidence/text và ghi rejected_claim_count; không sửa bằng chứng hoặc dùng fuzzy match. Nếu toàn bộ mục bị loại thì vẫn lỗi. Nhánh CV giữ nguyên hành vi.
- `AIJobAnalysisService.php`: mã lỗi rõ theo source/Ollama/storage; log chỉ version_id, stage và mã an toàn. Lỗi kết nối/response/JSON/evidence retry tối đa 3; endpoint cấu hình sai kết thúc ngay. Giữ mã lỗi qua callback queue. Không sửa nội dung/timestamp nghiệp vụ hoặc cơ chế V1/V2.
- `AIAnalyzeJobJob.php`: terminal failure gọi fail() rõ ràng; không để job cùng token đã failed nhận lại rồi báo DONE. Retry tạm thời tiếp tục dùng backoff hiện có.
- `tests/Feature/AIJobAnalysisTest.php`: thêm trường hợp lỗi tạm thời → phục hồi, chỉ lưu claim có bằng chứng vào đúng version.

Kết quả có thể chỉ chứa một phần thông tin trích xuất. Dữ liệu Recruiter nhập vẫn giữ đầy đủ ở structured_requirements; không coi thông tin bị AI bỏ sót là điều kiện không tồn tại.

## Kiểm thử

Lệnh: `php tests/Database/models.php --auth --job-analysis`.

Kết quả: **36/36 test đạt, 391 assertions**, Pint đạt. Các test kiểm tra trực tiếp ai_analysis_status=completed và JSON hợp lệ đúng job_version_id trên MariaDB riêng, giữ V1/V2 độc lập, retry an toàn và regression AI CV. Không chỉ kiểm tra queue DONE. Runner xác nhận schema và lịch sử migration chính không thay đổi trong lúc kiểm thử.

Ollama thật với cùng tin giả lập: trước sửa unsupported_ai_claim; sau sửa JSON qua validator trong khoảng 7,8 giây. Đây là kiểm tra model/validator, không phải chứng minh JobVersion 19 đã phục hồi. JobVersion thật 19 chưa được chạy lại, vẫn failed/result NULL.

## Thử lại đúng một JobVersion

1. Dừng worker cũ (Ctrl+C), bảo đảm Ollama đang bật. Không dùng queue:retry all.
2. Trong `php artisan tinker`, kiểm tra metadata phiên bản 19 (không lấy jobs.id=13):

```php
$v = App\Models\JobVersion::findOrFail(19);
[$v->id, $v->job_id, $v->review_status->value, $v->ai_analysis_status];
```

3. Chỉ nếu đúng phiên bản approved đang failed cần thử lại:

```php
app(App\Services\AIJobAnalysisService::class)->enqueue(19);
```

4. Chạy một worker đã nạp code mới:

```powershell
php artisan queue:work ai --queue=ai --tries=3 --timeout=240 --sleep=2
```

Worker xử lý tất cả job đang chờ trong queue ai, nên kiểm tra hàng đợi trước nếu có tác vụ khác; enqueue(19) chỉ tạo yêu cầu cho một phiên bản. Không retry payload token cũ. Báo cáo này không tự chạy worker hoặc enqueue dữ liệu thật.

5. Kiểm tra lại bằng Tinker, không in toàn bộ JSON:

```php
$v = App\Models\JobVersion::findOrFail(19);
[$v->ai_analysis_status, $v->ai_error_code, $v->ai_attempt_count,
    is_array($v->ai_analysis_result), $v->ai_analysis_result['structured_requirements']['job_version_id'] ?? null];
```

Chỉ xác nhận phục hồi khi completed, result có cấu trúc hợp lệ và version ID là 19. Nếu vẫn lỗi, dùng mã mới để phân biệt Ollama/JSON/evidence/storage; không gửi raw response hoặc nội dung tin nhạy cảm.
