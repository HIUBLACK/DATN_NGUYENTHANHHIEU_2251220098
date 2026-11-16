# Tích hợp nhóm nghề/chuyên môn và tìm kiếm việc làm

## Phạm vi

Tiếp tục giao diện demo hiện có, không thêm database hoặc công bố tin thật. `OccupationCatalog` là nguồn danh mục mẫu dùng chung cho Admin, Recruiter và Candidate; mã nhóm NN-xxx và chuyên môn CM-xxx thống nhất. Bổ sung chuyên môn DevOps, UI/UX và Mobile để phân loại các tin mẫu sẵn có.

## Recruiter

Form tạo/sửa tin có nhóm nghề và chuyên môn phụ thuộc. Tạo mới chỉ chọn nhóm/chuyên môn đang hiện. Thay nhóm xóa lựa chọn chuyên môn cũ; kiểm tra quan hệ khi gửi duyệt. Bản nháp vẫn chỉ bắt buộc tiêu đề; nếu đã chọn cặp danh mục thì phải hợp lệ.

Tin cũ giữ cặp danh mục đã lưu, kể cả đã ẩn; danh mục này có nhãn rõ trong dropdown. Xem trước và chi tiết hiển thị tên nhóm/chuyên môn. Lưu hoặc gửi bản sửa chỉ đổi `content`, không ghi đè `published`. Phiên demo cũ được bổ sung phân loại cho các tin mẫu chưa có trường danh mục.

## Candidate

Trang `/viec-lam` có điều hướng nhóm/chuyên môn, dropdown phụ thuộc, bộ lọc kết hợp: từ khóa, nhóm nghề, chuyên môn, địa điểm, cấp bậc, kinh nghiệm, hình thức, loại công việc, kỹ năng và ngưỡng lương. Sắp xếp ngày mới nhất hoặc mức lương cao nhất. Ngưỡng lương giữ cách lọc hiện có: khoảng lương của tin phải có mức tối đa đạt ngưỡng được chọn. Phân trang và liên kết điều hướng giữ các bộ lọc khác, đặt lại trang khi đổi nhóm/chuyên môn.

`CandidateCatalog::isPublic()` kiểm tra đủ: approved, open, chưa hết hạn theo ngày Việt Nam, tin không khóa, doanh nghiệp active và Recruiter active. Search, dữ liệu trang chủ, gợi ý và công ty nổi bật dùng nguồn công khai. Tra cứu tin cũ bằng ID vẫn được giữ cho hồ sơ đã nộp/lưu, không đưa tin không đủ điều kiện vào kết quả tìm kiếm.

Có tin Backend mẫu tại Đà Nẵng, kinh nghiệm 1–3 năm để kiểm tra ví dụ của người dùng. Dữ liệu vẫn là minh họa.

## Phiên demo và tích hợp backend sau

Dropdown và điều hướng đọc danh mục Admin đã chỉnh trong cùng tab qua sessionStorage; nếu không có thì dùng nguồn mẫu chung. Danh mục mới chưa có tin mẫu tương ứng sẽ trả kết quả rỗng. Candidate tìm kiếm trên các tin mẫu do Laravel cung cấp; chưa có luồng xuất bản tin thật từ phiên Recruiter/Admin sang Candidate. Khi tích hợp backend, thay seed bằng dữ liệu danh mục/tin đã duyệt, validation quan hệ và quyền trên server, truy vấn điều kiện công khai từ trạng thái thật. Không coi validation JavaScript là phân quyền.

## File chính

- `app/Support/OccupationCatalog.php`, `AdminCatalog.php`, `RecruiterCatalog.php`, `CandidateCatalog.php`.
- `app/Http/Controllers/CandidatePageController.php`.
- `resources/views/employer/job-form.blade.php`, `layout.blade.php`.
- `public/recruiter/js/recruiter-categories.js`, `recruiter.js`, `recruiter-views.js`.
- `resources/views/Candidate/ca_pages/jobs.blade.php`, `public/jobboard/js/job-category-filters.js`.
- `tests/Feature/JobDiscoveryTest.php`, `tests/job-categories-browser.cjs`.

## Kiểm tra

`php artisan test --compact`: 15 tests, 318 assertions đạt. Kiểm tra kết hợp tất cả bộ lọc, sai quan hệ nhóm/chuyên môn, từng điều kiện loại tin công khai, thứ tự và mã danh mục chung.

`node tests/job-categories-browser.cjs`: đạt trên 1440/768/390px. Kiểm tra chọn phụ thuộc, đổi nhóm, danh mục ẩn, tin cũ, xem trước và gửi bản sửa vẫn giữ nguyên chuyên môn của phiên bản đã duyệt. Ảnh tại `storage/app/private/job-categories-checks`.
