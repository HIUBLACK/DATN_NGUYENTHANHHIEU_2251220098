# Sửa Skill Matching bằng 0 — 26/11/2026

## Nguyên nhân

ResumeRevision ID 15 (resume_id=15, revision=1) đã completed. JSON technical_skills chứa các câu có PHP, Laravel, MySQL, Git, REST API, Docker; pivot resume_skills của đúng revision lại rỗng. PDF → JSON không làm mất sáu kỹ năng. Code so khớp chính xác cả câu với tên danh mục, ví dụ `Ngôn ngữ: PHP, SQL, JavaScript.` không bằng `PHP`, nên mất ở bước JSON → JobCatalog → pivot. Matching so mã đúng nhưng nhận tập CV rỗng.

JobVersion 27 (jobs.id=18) có sáu mã: Git=2, PHP=100, Laravel=101, MySQL=102, REST API=103, Docker=105; **cả sáu is_required=true**. Dù nội dung mẫu gọi Docker là ưu tiên, Matching hiện ưu tiên cờ đã lưu theo thiết kế. Không tự sửa cờ hoặc nội dung phiên bản đã duyệt.

## Cách sửa

- `JobCatalog::skillsInVerifiedClaims()` nhận diện tên kỹ năng theo biên từ Unicode trong claim đã kiểm chứng, hỗ trợ khác hoa/thường và khoảng trắng. Không fuzzy-match, không thêm alias suy đoán; GitHub không tự khớp Git, JavaScript không tự khớp Java.
- `AIResumeAnalysisService` dùng hàm này khi lưu pivot, vẫn đi qua evidence validation hiện có. Không đổi thuật toán Matching 60/40, model, schema hoặc giao diện.
- Chỉ khôi phục pivot revision 15 sau khi kiểm tra lại từng evidence nằm trong văn bản PDF trích xuất và claim nằm trong evidence. Transaction khóa user/resume/revision; chỉ thực hiện khi revision còn hiện hành, completed và pivot rỗng. Khôi phục 11 mã có bằng chứng: Docker, Git, GitHub, JavaScript, Laravel, MariaDB, MySQL, PHP, Postman, REST API, SQL. Tăng recommendation_generation của Candidate để kết quả cũ không được dùng. Không chạy lại hàng loạt CV hoặc sửa Job.

## Kết quả

- Test mới trong `ResumeAnalysisTest` tái hiện kỹ năng nằm trong câu, kiểm tra sáu mã được lưu/so khớp, chặn khớp chuỗi con và bằng chứng bịa.
- `php tests/Database/models.php --auth --matching`: **29 đạt, 0 lỗi, 1 bỏ qua; 236 assertions**, MariaDB test riêng. Test gọi Ollama Job live có sẵn được bỏ qua.
- Cặp thật revision 15 / JobVersion 27 sau sửa: **completed**, Skill **100**, Semantic **85,81276477**, tổng **91,49/100**, khớp đủ sáu kỹ năng, không còn kỹ năng thiếu. Semantic thay đổi vì đầu vào embedding có thêm tên kỹ năng pivot đã được khôi phục, không phải đổi công thức hoặc model.
- Chỉ kiểm tra Matching và khôi phục pivot nêu trên; chưa chạy worker/lưu Recommendation Run mới. Khởi động lại worker để nạp code, mở trang gợi ý và bấm **Kiểm tra lại đề xuất**, chờ tính xong rồi tải lại. Các revision cũ khác có pivot thiếu chưa được tự động sửa.

Giới hạn: đây là sửa ánh xạ tên có bằng chứng, không mở rộng sang suy luận kinh nghiệm hoặc năng lực. Nếu kỹ năng không có trong JSON đã kiểm chứng, không tự cộng điểm từ suy đoán. Mâu thuẫn Docker ưu tiên/bắt buộc cần chỉnh qua luồng phiên bản tin nếu muốn thay yêu cầu nghiệp vụ.
