# Dashboard và quản lý hệ thống Admin — Giai đoạn 3.5D

Cập nhật ngày 22/11/2026.

## Chức năng hoàn thành

- Dashboard lấy số liệu MariaDB: Candidate/Recruiter, active/locked, doanh nghiệp, tin tuyển dụng, tin công khai/chờ duyệt và phân bố trạng thái ứng tuyển.
- Giữ các cards và biểu đồ cột ApexCharts hiện có. Khoảng thống kê mặc định 30 ngày, cho chọn tối đa 366 ngày; nhóm ngày theo giờ Việt Nam từ timestamp UTC. Dữ liệu trống hiển thị 0.
- Quản lý người dùng: tìm tên/email, lọc Candidate/Recruiter và trạng thái, phân trang 15 mục, chi tiết hồ sơ và lịch sử khóa/mở khóa.
- Quản lý doanh nghiệp: tìm kiếm, lọc, phân trang, chi tiết các trường hiện hành gồm môi trường làm việc, lịch làm việc, OT; ảnh qua controller kiểm tra quyền, kể cả doanh nghiệp bị khóa.
- Giám sát ứng tuyển: tìm kiếm và kết hợp bộ lọc ứng viên, Recruiter, doanh nghiệp, tên tin, trạng thái; xem từng lượt, phiên bản đã nộp và lịch sử xử lý.
- Thao tác khóa/mở khóa dùng modal xác nhận, bắt buộc lý do. Kết quả/lỗi dùng thông báo nổi chung hiện có.

Không thay layout, sidebar, header, CSS global hoặc giao diện Candidate/Recruiter. Không thay schema, seed dữ liệu, SMTP hay nghiệp vụ kiểm duyệt. Các trang đã nối backend không chạy JavaScript demo.

## Cách tính Dashboard

- Thẻ người dùng đếm Candidate và Recruiter, không cộng tài khoản Admin vào active/locked.
- Tổng tin đếm `jobs`, không đếm `job_versions`. Chờ duyệt đếm tin có phiên bản pending; thẻ công khai tái sử dụng đầy đủ `PublicJobService::query()`.
- Tổng hồ sơ đếm từng bản ghi `applications`, gồm đơn đã rút và lượt ứng tuyển lại. Phân bố dùng trạng thái hiện tại của từng lượt.
- Biểu đồ đếm ngày tạo Job và `applied_at` của Application theo ngày Việt Nam. Thẻ tổng không bị giới hạn bởi khoảng ngày biểu đồ.
- Dùng truy vấn tổng hợp, eager-load quan hệ và phân trang; không đưa toàn bộ dữ liệu người dùng vào JSON frontend.

## Khóa/mở khóa và bảo mật

- Middleware yêu cầu Admin active; UserPolicy chỉ cho quản lý Candidate/Recruiter, chặn tự khóa và quản lý trạng thái Admin khác. CompanyPolicy hiện có kiểm tra quyền quản trị doanh nghiệp.
- `AdminStatusService` khóa hàng User theo ID tăng dần rồi Company trong transaction, kiểm tra lại quyền từ dữ liệu mới, trạng thái, `updated_at` và ID audit cuối cùng. Audit ID chặn yêu cầu cũ ngay cả khi khóa/mở khóa trong cùng giây.
- Chỉ gán trường trạng thái. Thao tác lặp, lý do trống và form cũ bị từ chối; không ghi audit trùng. `activity_logs` ghi actor, subject, action, old/new status, lý do và thời điểm trong cùng transaction.
- Khóa tài khoản được middleware active hiện tại áp dụng ở request tiếp theo. Khóa Company làm tin không còn đạt public visibility và chặn các thao tác Recruiter theo Policy hiện có.
- Mở khóa không đổi Job, published pointer hoặc JobVersion. Tin chỉ trở lại công khai nếu vẫn đáp ứng toàn bộ điều kiện public hiện có.
- Giữ nguyên ApplicationPolicy: Admin được xem metadata/lịch sử; **không được xem CV snapshot hoặc ghi chú Recruiter**. Không mở endpoint tải CV mới, không nới quyền hoặc hiển thị đường dẫn private storage. Không cho Admin đổi kết quả ứng tuyển.

## File và routes

Backend mới:

- `app/Http/Controllers/AdminManagementController.php`
- `app/Http/Requests/AdminStatusRequest.php`
- `app/Services/AdminDashboardService.php`
- `app/Services/AdminManagementService.php`
- `app/Services/AdminStatusService.php`
- `app/Policies/UserPolicy.php`

Tích hợp: `routes/admin.php`, `CompanyProfileController::adminMedia`; layout Admin, các Blade dashboard/users/companies/applications; các partial `management-list`, `management-detail`, `status-button`, `status-modal`, `stat-cards`; `public/admin-assets/js/admin-management.js`. Các file thông báo nổi đang thay đổi từ công việc trước được giữ nguyên.

Giữ tên và đường dẫn GET hiện có: `/admin`, `/admin/nguoi-dung`, `/admin/doanh-nghiep`, `/admin/ung-tuyen` và các trang chi tiết/lịch sử. Chi tiết User/Company nhận `?id=` số; Application nhận UUID. Thiếu ID hoặc không tìm thấy bản ghi trả 404.

Thêm:

- PATCH `/admin/quan-ly/{type}/{id}/trang-thai`, `admin.management.status`; type chỉ nhận `users` hoặc `companies`, có CSRF và throttle.
- GET `/admin/doanh-nghiep/{company}/anh/{slot}`, `admin.company-media`; UUID doanh nghiệp, slot logo hoặc office-0..5, kiểm tra Policy và file an toàn bằng xử lý ảnh hiện có.

## Kiểm thử

Lệnh: `php tests/Database/models.php --auth --admin-management`.

**78 tests đạt, 0 thất bại, 946 assertions.** Gồm 6 nhóm test mới trong `AdminManagementTest` và regression Admin pages, Auth/Policies, Jobs, Moderation, Recruiter Dashboard/Applications. `AdminPageTest` được cập nhật vì các trang chi tiết thật cần ID hợp lệ.

Các nhóm mới kiểm tra: thống kê không đếm nhầm phiên bản/lượt; danh sách và phân trang; khóa/mở khóa User, audit và chống replay; khóa Company giữ pointer/lịch sử và chặn công khai; giám sát hồ sơ với giới hạn CV/ghi chú; Guest/Candidate/Recruiter/Admin bị khóa không được quản trị. Dữ liệu test chỉ trên MariaDB riêng, bộ chạy xác nhận schema và lịch sử migration database chính không đổi.

## Kiểm tra thủ công và giới hạn

1. Đăng nhập Admin, mở Dashboard, đổi khoảng ngày và đối chiếu các cards với danh sách.
2. Tìm User/Company, mở modal khóa, nhập lý do, xác nhận; kiểm tra thông báo nổi và lịch sử. Mở lại form cũ để xác nhận bị từ chối.
3. Khóa doanh nghiệp có tin công khai; xác nhận Candidate không thấy tin, sau đó mở khóa và kiểm tra điều kiện công khai còn hiệu lực.
4. Lọc hồ sơ ứng tuyển, mở chi tiết và đối chiếu lượt/phiên bản/lịch sử; không có chức năng tải CV, ghi chú nội bộ hoặc chuyển trạng thái cho Admin.

Chưa kiểm tra trực quan trên trình duyệt ở các kích thước màn hình. Không bổ sung thống kê AI hoặc khu vực hoạt động mới vì Dashboard cũ không có. Module quản lý tin demo ngoài màn hình kiểm duyệt thật giữ nguyên phạm vi trước đó. Không có thay đổi schema cần phê duyệt.
