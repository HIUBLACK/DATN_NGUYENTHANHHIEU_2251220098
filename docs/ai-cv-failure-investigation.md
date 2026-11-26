# Điều tra lỗi AI phân tích CV

Ngày kiểm tra: 24/11/2026. Không thay đổi schema hoặc dữ liệu CV thật; không chạy worker/queue:retry và không gọi Ollama với CV thật.

## Bằng chứng và nguyên nhân

- `queue:failed` có hai job CV lỗi gần nhất, revision 8 và 7. Tập trung kiểm tra revision 8: revision hiện hành 1, trạng thái `failed`, attempt_count=3, token trùng job lỗi, analysis_result NULL. Tên cột thực tế là `parsing_status` và `error_code`, không phải analysis_status/analysis_error_code.
- Log cho revision gần nhất có lỗi chung lúc 11:47:07, 11:47:51, 11:49:36 UTC (18:47:07, 18:47:51, 18:49:36 giờ Việt Nam). Lần cuối bắt đầu 11:49:22 UTC. Đây là ba lần thử, không phải bằng chứng thất bại vĩnh viễn ngay lần đầu.
- Code cũ chuyển mọi lỗi ngoài thiếu văn bản thành `analysis_failed`, sau đó callback failed() ghi đè thành `queue_failed`. Vì mất mã lỗi gốc, **không thể xác nhận chính xác lỗi lịch sử của revision 8 là evidence, JSON hay lưu database** chỉ từ log cũ.
- Đọc riêng PDF revision 8 bằng hàm trích xuất hiện có: thành công, 1.916 ký tự; hash/path qua kiểm tra. Không in nội dung, tên file hay thông tin cá nhân. Không gửi văn bản này đến model.
- Ollama đang hoạt động, có qwen3:1.7b (1,4 GB). Cấu hình runtime: timeout 120 giây, context 8192, output 2500 token, input 10000 ký tự. CV giả lập ngắn tiếng Anh phân tích thành công khoảng 6,8 giây.
- Với CV tiếng Việt giả lập, tái hiện `unsupported_ai_claim` sau 12–13 giây: model ghép lại bằng chứng (ví dụ rút một tên công cụ ra khỏi danh sách) và đổi chữ thường thành hoa. JSON có cấu trúc nhưng không đạt kiểm tra nguyên văn. Cơ chế cũ hủy toàn bộ kết quả chỉ vì một mục sai. Đây là lỗi đã tái hiện được, phù hợp thời lượng người dùng báo, nhưng không thay thế bằng chứng còn thiếu cho lần lỗi lịch sử.

## Vì sao FAIL rồi DONE rất nhanh?

`process()` cũ trả về ngay khi status failed/completed, token cũ, revision đã thay hoặc lease còn hiệu lực. Worker thấy handle() không ném lỗi nên có thể in DONE trong vài ms dù không gọi Ollama và không lưu kết quả. Điều này không có nghĩa AI đã thành công.

Revision 8 đã có 3 lần thử và token đúng; không có bằng chứng cho giả thuyết nó bị failed ngay lần đầu. Log cũ không lưu lý do skip nên không thể xác định job DONE 28ms thuộc token/revision nào. Lỗi thiết kế xác nhận được là skip không có lý do rõ ràng và callback làm mất mã lỗi.

## Sửa đổi

- `AIResumeAnalysisService.php`: trả outcome rõ ràng (completed/failed/busy/obsolete/missing); log metadata skip, phân loại lỗi theo giai đoạn PDF/Ollama/lưu dữ liệu. Chỉ log revision ID, mã lỗi, giai đoạn, số lần thử; không log response/exception gốc.
- Lỗi mạng, response, JSON/evidence và lưu trữ được thử lại tối đa 3 lần; PDF không có text, PDF sai/hash không khớp, parser lỗi hoặc endpoint sai kết thúc ngay. next_retry_at khớp backoff 30/90 giây. Callback failed giữ mã lỗi cụ thể, không ghi đè thành queue_failed.
- `AIAnalyzeResumeJob.php`: outcome failed gọi fail() rõ ràng, không âm thầm DONE; busy được release để chờ. Job cũ không còn liên quan được bỏ qua có log lý do; không sửa token/revision mới.
- `AIOllamaService.php`: chỉ điều chỉnh prompt CV yêu cầu trích đoạn ngắn, đúng nguyên văn/ngôn ngữ; tối đa 3 mục mỗi nhóm để giảm đầu ra sai. Không sửa prompt hay cơ chế validate của Job.
- Giữ nguyên kiểm tra cấu trúc JSON và phép đối chiếu evidence/text. Với CV, **loại bỏ hoàn toàn** từng mục không đạt bằng chứng, chỉ lưu mục đã xác minh; lưu `rejected_claim_count`. Nếu không còn mục nào hợp lệ sau khi loại thì vẫn lỗi/retry. Không dùng fuzzy match, không sửa bằng chứng, không lưu thông tin model bịa. Đây là kết quả trích xuất có thể thiếu mục, không khẳng định đầy đủ CV.
- `tests/Feature/ResumeAnalysisTest.php`: kiểm tra retry phục hồi, lỗi vĩnh viễn, queue fail rõ ràng, giữ error_code và chỉ lưu thông tin đã xác minh. Giữ nguyên UI, CV gốc, application snapshots, SMTP và schema.

## Kết quả kiểm thử

Lệnh: `php tests/Database/models.php --auth --job-analysis` (CV + Job AI + Jobs/Moderation liên quan).

Kết quả: **35/35 test đạt, 384 assertions**; Pint đạt. Các test thành công kiểm tra trực tiếp analysis_result, parsing_status, attempt_count trên MariaDB test riêng, bao gồm PDF hợp lệ và kết quả đúng revision, không chỉ kiểm tra terminal DONE. Runner xác nhận schema và lịch sử migration chính không đổi trong lúc kiểm thử.

Ollama thật, CV tiếng Việt giả lập: trước sửa evidence thất bại; sau sửa giữ nguyên validator, loại 3 mục sai và trả JSON hợp lệ khoảng 13,3 giây. Đây là kiểm tra model bằng dữ liệu không nhạy cảm; không phải xác nhận CV thật đã phục hồi. Không có bằng chứng lỗi GPU hoặc timeout trong probe này.

## Trạng thái CV thật và cách thử lại đúng một CV

Revision 8 vẫn failed, attempt_count=3, error_code=queue_failed, analysis_result NULL; không tự sửa hoặc enqueue. Mã cũ không thể khôi phục ngược từ log. Chưa tuyên bố CV này phân tích thành công.

1. Dừng worker cũ bằng Ctrl+C để tránh tiếp tục dùng code cũ; bật Ollama.
2. Trong trang quản lý CV, chỉ bấm **Phân tích lại** ở CV tương ứng revision 8. Không thay/xóa PDF và không bấm các CV khác. Service sẽ tạo token mới cho đúng revision đó.
3. Chạy đúng một worker: `php artisan queue:work ai --queue=ai --tries=3 --timeout=240 --sleep=2`. Lưu ý worker này xử lý mọi job đang có trong queue ai, nên kiểm tra queue trước nếu còn tác vụ khác; báo cáo này không tự xử lý chúng.
4. Tải lại trang CV; chỉ kết luận thành công khi trạng thái Hoàn thành và có kết quả. Nếu vẫn lỗi, lấy mã error_code cùng revision_id từ metadata/log mới, không gửi nội dung CV hoặc toàn bộ log.
5. Không dùng `queue:retry all`: job cũ mang token cũ không phải lượt thử lại hợp lệ. Không đổi thủ công status thành completed.

Giới hạn: nguyên nhân chính xác của lần lỗi lịch sử chưa thể khẳng định do log cũ đã mất thông tin. Đã sửa các lỗi tái hiện được và bổ sung chẩn đoán đủ phân biệt lỗi ở lần thử được người dùng thực hiện tiếp theo.
