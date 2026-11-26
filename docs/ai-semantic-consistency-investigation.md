# Đối chiếu chênh lệch Semantic Matching

Ngày kiểm tra: 26/11/2026.

## Nguyên nhân xác định được

Recommendation run **7**, item **11** so với application **15**, evaluation **2**:

- Cùng resume **19**, ResumeRevision ID **19**, revision **1**, JobVersion **27**.
- Hash PDF revision, snapshot đã ghi và file snapshot thực tế đều là `0a06f607db512db99127b11c673ea14183759d2fdd3283c6cee8d32135dd40fe`.
- Các claim JSON ở cả 7 nhóm giống nhau sau tái kiểm chứng evidence với PDF. Tập 11 kỹ năng CV giống nhau; cùng 6 kỹ năng của tin.
- Candidate sắp kỹ năng theo `skill_id`: Git, GitHub, PHP, Laravel, MySQL, REST API, Docker, SQL, Postman, JavaScript, MariaDB.
- Recruiter lấy thứ tự tên từ JobCatalog: Docker, Git, GitHub, JavaScript, Laravel, MariaDB, MySQL, PHP, Postman, REST API, SQL.

Đây là khác biệt đầu vào embedding, không phải lỗi công thức. CV text đều dài 826 ký tự nhưng hash khác: Candidate `e6a74120…09615`, Recruiter `d3ba669b…55b5f`. Job text giống nhau, dài 270 ký tự, hash `ccb3bc28…13b28`. Mỗi phía gửi một đoạn CV và một đoạn Job; không khác cách chia đoạn, chuẩn hóa, lọc thông tin cá nhân hoặc tập trường nghề nghiệp.

## Model, cache và Qwen

- Cùng `embeddinggemma:300m`, digest `85462619ee721b466c5927d109d4cb765861907d5417b9109caebc4e614679f1`, trước sửa là `career-1`.
- Cache key vốn chứa identity riêng từng luồng, digest, cấu hình embedding và nội dung đoạn. Hai key khác nhau là đúng về cách ly nguồn, không tự gây khác điểm. Đã tái hiện chênh lệch khi cả hai lần đều **cache miss**.
- Đường xử lý tái hiện dùng JSON revision đã có và kiểm chứng evidence thành công, không gọi `/api/chat` để phân tích snapshot lại. Test tích hợp cũng kiểm tra không có request Qwen. Database cũ không lưu cờ nguồn phân tích hoặc raw JSON fallback của từng evaluation, nên không có audit độc lập để khẳng định mọi chi tiết của lần chạy lịch sử. Tuy nhiên chỉ thay thứ tự kỹ năng đã tái hiện chính xác hai điểm đã lưu, không cần giả thuyết Qwen tạo JSON khác.

## Sửa tối thiểu

- Thêm `AIMatchingService::embeddingTexts()`: nhận kỹ năng đã xác minh, bỏ trùng và sắp theo mã trước khi gọi hàm chuẩn bị văn bản hiện có.
- Candidate Matching và Recruiter Evaluation cùng gọi hàm này. Giữ thứ tự/nội dung claim, nguồn lịch sử, evidence validation, lọc thông tin cá nhân, thuật toán embedding/cosine và công thức 60/40.
- Đổi `config/ai.php` sang `preprocessing_version = career-2`, vừa giới hạn cột model_version hiện có. Cache/kết quả cũ được nhận diện theo phiên bản; không xóa cache hàng loạt, không cập nhật điểm trực tiếp.
- Không sửa giao diện, schema hoặc dữ liệu thật.

## Kết quả thử Ollama thật

| Đường chuẩn bị đầu vào | Semantic trước sửa | Semantic sau sửa | Skill | Tổng sau sửa |
| --- | ---: | ---: | ---: | ---: |
| Candidate | 85,81276477 | 85,81276477 | 100 | 91,49 |
| Recruiter | 84,82355582 | 85,81276477 | 100 | 91,49 |

Sau sửa, cả CV text và Job text giống nhau từng byte. Vector 768 chiều, hai đoạn mỗi lần gọi; hai cache key riêng vẫn cho cùng điểm. Lượt kiểm tra sau sửa mất khoảng **3,52 giây** và **0,06 giây** (model đã được nạp ở lượt sau), cả hai cache miss. Chỉ đọc nguồn thật và gọi Ollama cục bộ; cache chẩn đoán trong bộ nhớ, không ghi điểm hoặc tạo run/evaluation trong database chính.

## Kiểm thử và vận hành

Thêm đúng 2 test:

1. Cùng tập kỹ năng nhưng khác thứ tự/trùng lặp tạo cùng text; nguồn nghề nghiệp thực sự khác vẫn tạo text khác.
2. Chạy cả Matching Candidate và Evaluation Recruiter trên cùng fixture: request embedding giống nhau, điểm bằng nhau, không gọi Qwen lại.

Chạy `php tests/Database/models.php --auth --evaluation` trên MariaDB riêng: **38 test đạt, 392 assertions, 0 thất bại** (2 test mới và regression liên quan). Kiểm tra cú pháp PHP đạt; schema và lịch sử migration database chính giữ nguyên.

Khởi động lại worker AI để nạp code/config mới. Mở Recommendation để tạo kết quả phiên bản mới; tại đúng hồ sơ Recruiter, yêu cầu đánh giá lại. Điểm đã lưu trước sửa vẫn giữ nguyên cho lịch sử, không tự đổi thành 91,49. Khi CV/JobVersion/claim thực sự khác, hai luồng vẫn có thể cho điểm khác nhau đúng theo nguồn.
