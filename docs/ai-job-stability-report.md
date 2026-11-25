# Ổn định AI Job Analysis

Kiểm tra ngày 24/11/2026. Không sửa schema, UI, tin thật, metadata AI thật hoặc retry queue thật. Các thay đổi CV/Moderation từ những giai đoạn trước đang có trong workspace được giữ nguyên; lần này chỉ sửa nhánh Job, Job Service/worker và kiểm thử.

## 1. Đối chiếu tác vụ thật

| JobVersion | jobs.id | failed_jobs.id | UUID queue | Token SHA-256 (12 ký tự) | DB attempts/status/error |
| --- | --- | --- | --- | --- | --- |
| 17 | 12 | 1 | ebeca6c2-23ab-4f5b-b726-feefbd164cca | 456552425cef | 3 / failed / analysis_failed |
| 18 | 13 | 2 | 536953e7-1f11-4156-a98c-7f94ea0a1669 | d08895797d57 | 3 / failed / analysis_failed |
| 19 | 13 | 5 | c6d3312a-c1ca-4058-994b-2ab2a0b7eaab | e61e8bc26c60 | 3 / failed / analysis_failed |
| 20 | 13 | 6 | c10776be-f91f-4f54-b87c-cae96dbfd9aa | 69d913e0506e | 3 / failed / invalid_ai_json |
| 22 | 14 | 7 | 51cc2dd2-3dec-40f8-96c8-1400857131fc | 0293d03cc9f1 | 3 / failed / invalid_ai_json |

Tất cả token payload trùng token DB, result NULL. Không có Job AI đang chờ trong queue_jobs tại thời điểm khảo sát. failed_jobs.id là ID bản ghi lỗi, **không phải** ID queue_jobs đã bị xóa. UUID payload vẫn xác định được tác vụ. Không in token gốc hoặc nội dung tin.

Không có log tương quan ID cho từng dòng terminal DONE trước đây, nên không thể khẳng định FAIL/DONE có cùng phiên bản/token. Các phiên bản 18/19/20 cùng jobs.id=13 nhưng là các tác vụ khác nhau. Không ghép chúng chỉ dựa vào tên class hoặc thời gian chạy.

Thống kê log thật có mã cụ thể: version 20 có 2 dòng invalid_ai_json, version 22 có 3 dòng invalid_ai_json (giai đoạn JSON sau Ollama). Versions 17–19 chỉ còn analysis_failed nên nguyên nhân đọc dữ liệu/Ollama/evidence/database chưa xác định. Không có bằng chứng lỗi GPU/database từ các dòng này. Log test trước đây dùng chung file nên không gộp các ID fixture 6/7/8 vào thống kê production.

## 2. Thay đổi

- Worker ghi `Job AI queue trace`: queue_job_id, queue_uuid, queue_attempts, job_version_id, token_hash, token_matches, attempt_count DB, status/error_code/has_result, outcome và tên database. Có log bắt đầu, kết thúc, exception và terminal failure. Không ghi nội dung hoặc raw response. Phân biệt database test và database thật khi thống kê.
- Service trả outcome rõ: completed, already_completed, stale_token, busy, superseded, missing/ineligible. Lease đang hiệu lực được release thay vì âm thầm DONE. Failed đúng token vẫn fail rõ ràng. Không tăng tries=3; token cũ không sửa lượt mới.
- Lỗi JSON/schema, evidence, nguồn, Ollama và storage được phân loại riêng. Output bị cắt do hết token có mã ollama_output_truncated. Endpoint sai, dữ liệu gốc không hợp lệ, bản ghi nguồn mất hoặc lỗi ràng buộc/schema database là terminal; kết nối/deadlock/model retry hữu hạn. Callback giữ mã lỗi cụ thể.
- `structured_requirements` luôn là nguồn gốc từ Recruiter. Nếu các trường văn bản bổ sung đều rỗng hoặc ghi rõ “Không có”/“none”/“n/a”, và vị trí/chuyên môn/cấp bậc có cấu trúc đầy đủ, không gọi model; lưu completed với `extraction_outcome=structured_only_no_supplement` và mảng claim rỗng. Đây không phải kết quả AI suy đoán.
- Có văn bản thực chất nhưng model trả toàn mảng rỗng: empty_job_extraction, không completed. Toàn claim bị loại do evidence vẫn là unsupported_ai_claim; không dùng structured data để che lỗi model hoặc JSON. Kiểm tra evidence giữ nguyên đối chiếu nguyên văn, không fuzzy match.
- Riêng schema Job giới hạn 3 mục mỗi nhóm, text 160 ký tự/evidence 240 thay vì 12/300/600; bỏ chỉ dẫn professional_summary không thuộc schema Job, thống nhất prompt 3 mục. Giữ temperature=0, model qwen3:1.7b, context=8192, output=2500, timeout=120. Không sửa cấu hình hoặc hành vi CV.

File: app/Jobs/AIAnalyzeJobJob.php, app/Services/AIJobAnalysisService.php, nhánh Job trong AIOllamaService; tests/Feature/AIJobAnalysisTest.php, tests/Database/models.php và hai script mẫu trong tests/Support.

## 3. Thử nghiệm trước/sau với Ollama thật

Tám tin giả lập trong `tests/Support/job-analysis-samples.php`. Đo bằng `php tests/Support/measure-job-analysis.php`; một lượt mỗi mẫu mỗi phương án, không retry để làm đẹp tỷ lệ. Không đọc tin thật.

| Mẫu | Trước: claim đúng / bị loại | Sau: claim đúng / bị loại | Giây trước → sau |
| --- | --- | --- | --- |
| Việt ngắn | 3 / 2 | 3 / 2 | 6,0 → 5,8 |
| Anh ngắn | 5 / 0 | 5 / 0 | 4,7 → 4,3 |
| Việt nhiều kỹ năng | 16 / 3 | 9 / 3 | 8,2 → 7,6 |
| Anh dài | 9 / 4 | 14 / 0 | 6,1 → 7,6 |
| Việt dài | 14 / 6 | 13 / 0 | 9,6 → 8,5 |
| Không bổ sung | JsonException | 0 / 0, JSON hợp lệ | 25,9 → 2,8 |
| Việt nhiều yêu cầu | 15 / 16 | 8 / 2 | 12,7 → 7,7 |
| Anh có dấu nháy | 10 / 15 | 7 / 9 | 8,8 → 7,0 |

Tầng model/validator: **7/8 → 8/8 hợp lệ**, tổng claim được giữ **72 → 59**, mục bị loại **46 → 16** (không đếm được claim của response JSON lỗi). Tổng thời gian 82,0 → 51,3 giây. Giới hạn ngắn giúp giảm lỗi nhưng cũng có thể giảm độ bao phủ; không coi ít claim là chất lượng tốt hơn tuyệt đối. Một lượt không chứng minh ổn định tuyệt đối trên mọi tin.

Không có phép đo persisted completed trước sửa trên cùng corpus: baseline trên đây chỉ là model/validator, không gọi nhầm là tỷ lệ completed database. Kiểm tra pipeline sau sửa dùng `php tests/Database/models.php --auth --job-stability-live`, tạo tin giả lập trong MariaDB test riêng, gọi Ollama thật rồi kiểm tra completed và result_version_id. **8/8 completed**, JSON đúng version; 1 test/16 assertions đạt, khoảng 50 giây. Số claim lần lượt 5, 1, 11, 13, 10, 0, 15, 10. Mẫu không bổ sung không gọi model. Không sửa database thật. Danh mục fixture và phần structured đưa vào prompt khác phép đo trực tiếp nên số claim có thể khác.

## 4. Test và giới hạn

Bổ sung tối đa 5 nhóm: structured-only không gọi model; rỗng sai trên văn bản có nội dung; output cắt; busy/stale/terminal và log tương quan; corpus thật tùy chọn. Regression còn lại tái sử dụng CV/Jobs/Moderation. Lệnh mock: `php tests/Database/models.php --auth --job-analysis`. Probe live mặc định được skip và chỉ chạy khi có cờ riêng.

Regression mock: **40 test đạt, 405 assertions; 1 probe live được skip có chủ đích** trong tổng 41 test. Probe live chạy riêng đã đạt 1 test/16 assertions như trên. Pint và diff check đạt. Runner xác nhận schema và lịch sử migration chính không đổi. Không tự thay metadata các phiên bản 17/18/19/20/22; vẫn cần người dùng thử lại riêng phiên bản muốn kiểm tra.

Rủi ro: schema ngắn có thể bỏ sót yêu cầu, evidence đúng không bảo đảm phân loại ngữ nghĩa đúng. Chỉ nhận trường rỗng/không có rõ ràng để tránh kết luận không có bổ sung từ văn bản ít chữ. Nếu văn bản có nội dung nhưng không trích xuất được, giữ lỗi minh bạch. Một worker là cấu hình vận hành; tranh chấp lock giữa nhiều worker vẫn tiêu thụ lượt queue. Windows không PCNTL không bảo đảm cưỡng chế worker timeout; HTTP vẫn có timeout.

## 5. Kiểm tra lại một JobVersion

Dừng worker cũ, kiểm tra hàng đợi, rồi trong Tinker xác minh đúng **job_versions.id** (ví dụ 22, không phải jobs.id=14) và chỉ enqueue phiên bản approved/failed đó:

```php
$v = App\Models\JobVersion::findOrFail(22);
[$v->id, $v->job_id, $v->ai_analysis_status, $v->ai_error_code];
app(App\Services\AIJobAnalysisService::class)->enqueue(22);
```

Khởi động một worker mới bằng lệnh hiện tại. Worker có thể xử lý những job khác đang chờ trong queue ai; không dùng queue:retry all. Đối chiếu trace theo UUID/token_hash để biết dòng FAIL/DONE thuộc lượt nào. Chỉ kết luận thành công khi DB completed, result là JSON hợp lệ và structured_requirements.job_version_id đúng 22; nếu structured-only thì kiểm tra extraction_outcome tương ứng. Không in toàn bộ JSON/log có dữ liệu nhạy cảm.
