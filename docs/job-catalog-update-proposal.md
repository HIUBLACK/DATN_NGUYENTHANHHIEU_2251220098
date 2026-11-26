# Cập nhật danh mục và form tin tuyển dụng

Quyết định người dùng ngày 19/11/2026 thay thế đề xuất nhiều lĩnh vực: **chỉ thêm danh mục Vị trí công việc, chọn một lĩnh vực, dùng 34 tỉnh/thành phố**.

## Phạm vi

- Admin quản lý Vị trí: danh sách, tìm kiếm/lọc, phân trang, thêm/sửa, ẩn/hiện, kiểm tra tên trùng và form cũ. Dùng sidebar, Controller và Service danh mục hiện có.
- Recruiter chọn vị trí từ danh mục, một lĩnh vực, tỉnh/thành phố; nhập Địa điểm chi tiết. Bỏ kinh nghiệm và ô chọn nhóm nghề. Backend suy ra nhóm từ chuyên môn và kiểm tra trạng thái nhóm cha.
- Tin cũ giữ nội dung, kể cả vị trí nhập tự do và kinh nghiệm. Vị trí đã ẩn vẫn hiển thị khi sửa tin từng chọn nhưng không được gửi duyệt mới. Tên vị trí là snapshot trong phiên bản, không bị đổi theo tên danh mục.
- Candidate hiển thị tỉnh và địa điểm chi tiết, lọc tỉnh theo mã; tin cũ chưa có mã vẫn lọc theo địa điểm đã lưu. Không làm lộ bản chưa duyệt.

## Schema

- Thêm `job_positions`, tổng **33 bảng**. Cột và khóa theo mục danh mục bổ sung trong database-design.md.
- Thêm `job_versions.job_position_id` FK nullable và `province_code` VARCHAR(2) nullable, index riêng. Giữ `industry_id` một giá trị; không tạo bảng liên kết lĩnh vực.
- Mở rộng `position` từ 100 lên 120 ký tự để đồng bộ giới hạn tên danh mục. Điều chỉnh CHECK `jv_check_11` bỏ yêu cầu kinh nghiệm, giữ các điều kiện nội dung khác.
- Không xóa cột kinh nghiệm, không sửa phiên bản lịch sử, không backfill vị trí/tỉnh bằng dữ liệu suy đoán, không seed danh mục thật.
- Migration: `2026_11_19_120000_add_job_positions_and_province`. Rollback từ chối nếu có danh mục hoặc dữ liệu mới; cũng từ chối nếu dữ liệu không còn thỏa điều kiện cũ.
- Triển khai bằng `database/deploy-job-positions.php --apply`: backup riêng tư, restore kiểm chứng, diễn tập up/down/up, đối chiếu dữ liệu cũ và schema trước khi áp dụng database chính.

## Routes

Tái sử dụng vòng đăng ký routes trong `routes/admin.php`: `/admin/danh-muc/vi-tri` theo cấu trúc routes hiện có, tên `admin.positions.index/create/store/edit/update/status`. Form Recruiter giữ routes cũ.

## Nghiệm thu

1. Admin thêm vị trí, thử tên trùng, sửa/ẩn/hiện và phân quyền.
2. Recruiter tạo tin: vị trí dropdown, một lĩnh vực, đủ 34 tỉnh; không có nhóm nghề và kinh nghiệm.
3. Lưu nháp chỉ tiêu đề được phép; gửi duyệt cần vị trí/tỉnh và các nội dung bắt buộc còn lại.
4. Vị trí ẩn chặn lựa chọn mới/gửi duyệt; kiểm tra bản đã lưu vẫn xem được.
5. Duyệt V2 thay V1; từ chối V2 giữ V1; lọc tỉnh Candidate chỉ lấy phiên bản công khai hợp lệ.

Kết quả kiểm thử và triển khai ghi trong báo cáo **các thay đổi của tôi.md**.
