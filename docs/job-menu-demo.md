# Menu khám phá việc làm và danh mục Admin

Menu Candidate có sáu nhóm: lĩnh vực, kỹ năng, chuyên môn, cấp bậc, công ty, thành phố. Desktop mở bằng hover; nút mũi tên hỗ trợ bàn phím và điện thoại. Danh sách bên phải có chiều cao giới hạn và cuộn riêng. Escape hoặc bấm ngoài đóng menu.

Nhấn tên nhóm mở trang việc làm và nhấn mạnh bộ lọc tương ứng. Nhấn một giá trị áp dụng bộ lọc chính xác; chuyên môn kèm nhóm nghề. Bộ lọc công ty dùng tên đầy đủ, không dùng tìm kiếm gần đúng. Bộ lọc kết hợp được với các điều kiện hiện có và chỉ lấy tin đủ điều kiện công khai.

Admin thêm ba mục trong sidebar hiện có: Lĩnh vực, Kỹ năng, Cấp bậc. Tái sử dụng bảng, modal, xác nhận ẩn/hiện, kiểm tra tên trùng và phân trang của Nhóm nghề/Chuyên môn. Không xóa cứng.

Dữ liệu demo từ JobCategoryCatalog. Chỉnh sửa Admin lưu sessionStorage trong cùng tab; menu và các lựa chọn lọc đọc trạng thái này khi tải trang. Danh mục ẩn không xuất hiện trong menu. Dữ liệu tin tuyển dụng vẫn là mẫu server độc lập: mục mới chưa gắn với tin sẽ cho kết quả rỗng. Giá trị lọc gốc được giữ riêng với tên hiển thị để đổi tên danh mục mẫu không làm mất kết quả. Chưa có database hoặc đồng bộ dữ liệu thật giữa vai trò.

Kiểm tra: php artisan test; node --test tests/admin-categories.test.cjs; node tests/job-menu-browser.cjs. Ảnh kiểm tra lưu trong storage/app/private/menu-checks.
