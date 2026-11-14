# Đánh giá CV cho Recruiter — giao diện demo

Phạm vi: chỉ giao diện và dữ liệu mẫu trong trình duyệt. Không gọi FastAPI, không phân tích PDF, không tạo queue, snapshot CV thật hoặc ghi database.

- Danh sách hồ sơ: điểm 0–100, trạng thái, lọc đánh giá kết hợp tên/tin/trạng thái ứng tuyển, sắp xếp tăng/giảm điểm. Hồ sơ chưa có điểm luôn nằm cuối khi sắp xếp.
- Nút xem đánh giá dẫn tới phần AI trong trang chi tiết hồ sơ. Hiển thị kỹ năng đáp ứng/còn thiếu, điểm ngữ nghĩa, nhận xét, thời gian mẫu và phiên bản JD/đánh giá mẫu.
- Công thức minh họa: làm tròn (0,6 × điểm ngữ nghĩa + 0,4 × tỷ lệ kỹ năng đáp ứng × 100). Điểm không phải xác suất tuyển dụng. Điểm ngữ nghĩa và kỹ năng được khai báo sẵn, không suy ra từ CV thật.
- Thử lại chỉ chuyển trạng thái mẫu thất bại sang chưa đánh giá trong sessionStorage; không tự tạo kết quả thành công, không đổi trạng thái ứng tuyển.
- Các hồ sơ không có PDF vẫn có thể mang kết quả mẫu để minh họa trạng thái giao diện; không được dùng dữ liệu này để đánh giá ứng viên thật.

Kiểm tra: `node tests/recruiter-ai-browser.cjs` và `node tests/recruiter-browser.cjs`.
