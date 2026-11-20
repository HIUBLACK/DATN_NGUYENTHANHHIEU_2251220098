# Xem trước và xác nhận gửi duyệt tin tuyển dụng

## Thao tác trạng thái tại danh sách — 20/11/2026

`employer/jobs.blade.php` bổ sung nút có icon theo trạng thái và Policy operate: đang mở có Tạm dừng/Đóng tin; tạm dừng có Mở lại/Đóng tin; bản nháp, đã đóng hoặc bị khóa không có các nút này. Dùng POST có CSRF tới route recruiter.job-transition và Service hiện có. Mở lại vẫn được backend kiểm tra điều kiện công khai, hạn tuyển và trạng thái chủ sở hữu. Không đổi nghiệp vụ/schema. Biên dịch Blade đạt.

## Bổ sung lưu khi sửa bản nháp — 20/11/2026

Trang chỉnh sửa phiên bản có trạng thái `draft` hiển thị **Lưu thay đổi**, dùng lại luồng lưu nháp và kiểm tra phiên bản cũ. Không gửi duyệt khi bấm lưu. Trang tạo vẫn có Lưu bản nháp; phiên bản pending/approved/rejected không có nút lưu nháp này. Gửi duyệt giữ luồng kiểm tra đầy đủ và modal xác nhận. Quyết định này thay thế giới hạn trước đó chỉ cho lưu nháp khi tạo mới.

## Hiển thị lỗi — 20/11/2026

Đã bỏ khung danh sách lỗi validation cố định trong layout Nhà tuyển dụng. Lỗi thiếu trường từ backend chỉ hiện thông báo nổi tiếng Việt ở góc trái, tự ẩn sau 5 giây; giữ đánh dấu đỏ các trường tương ứng. Backend vẫn chặn gửi duyệt thiếu dữ liệu, kể cả thao tác gửi bản đã lưu từ trang chi tiết. Thông báo thành công và thông tin trạng thái không bị xóa.

## Quyết định mới nhất — 20/11/2026

Khôi phục nút **Lưu bản nháp chỉ trên trang tạo tin mới**, không hiển thị trên trang chỉnh sửa. Lưu nháp bắt buộc tiêu đề; trường đã nhập vẫn kiểm tra định dạng và khoảng lương. Gửi duyệt vẫn yêu cầu đủ dữ liệu và xác nhận qua modal. Hai thao tác dùng lại routes/backend hiện có, không thay đổi database. Các mô tả bỏ lưu nháp phía dưới là lịch sử trước quyết định này.

Ngày: 19/11/2026.

## Kiểm tra lương và nội dung không thay đổi

- Lương vẫn phải là số không âm, tối đa hai chữ số thập phân và trong giới hạn backend. Frontend bổ sung kiểm tra lương tối đa >= lương tối thiểu; lỗi đánh đỏ ô lương tối đa và hiện thông báo nổi 5 giây. Hai mức bằng nhau được chấp nhận.
- Khi sửa tin, so sánh dữ liệu gửi với phiên bản mới nhất đã lưu (khác với mốc công khai dùng để trình bày so sánh trong modal). Nếu không đổi, thông báo “Bạn chưa thay đổi nội dung tin tuyển dụng.” và không gửi request.
- Backend kiểm tra lại dưới row lock và token phiên bản, không tạo phiên bản mới khi nội dung không đổi. Chuẩn hóa số tiền, xuống dòng, khoảng trắng đầu/cuối và thứ tự kỹ năng trước khi so sánh.
- Bổ sung tests chặn lương đảo ngược và gửi lại nội dung không thay đổi; không đổi database.

## Kiểm tra form và thông báo lỗi dùng chung

Form tạo/sửa tin yêu cầu đầy đủ các trường thực tế trước khi mở modal Gửi duyệt. Các trường thiếu/sai được tô đỏ, thông báo ở góc trên trái tự ẩn sau 5 giây; sửa hợp lệ sẽ bỏ viền đỏ. Backend validation vẫn được giữ nguyên.

Thành phần `shared/form-validation.blade.php`, `public/js/form-validation.js`, `public/css/form-validation.css` được dùng trong ba layout Candidate, Recruiter, Admin. Các form khác áp dụng theo thuộc tính required/ràng buộc HTML sẵn có và lỗi Laravel trả về; không biến trường tùy chọn thành bắt buộc. Lỗi backend cũng được gắn lại vào trường tương ứng và hiện thông báo nổi. Thông báo hiển thị bằng textContent.

Kiểm tra bổ sung: tests/job-review.test.cjs (3 trường hợp) và tests/form-validation.test.cjs (1 trường hợp) kiểm tra chặn form thiếu dữ liệu, đánh dấu đỏ, sửa lỗi và thời gian tự ẩn 5 giây. Biên dịch Blade và kiểm tra cú pháp JavaScript đạt. Chưa nghiệm thu trực quan trên trình duyệt thật.

## Điều chỉnh sau bàn giao: bỏ lưu nháp trên giao diện

Theo yêu cầu làm gọn form, đã bỏ nút Lưu bản nháp, hướng dẫn lưu nháp và liên kết Xem trước bản đã lưu/Gửi duyệt ở cuối form. Form chỉ còn Gửi duyệt và Quay lại. Gửi duyệt là nút chính; luôn đi qua modal xác nhận, kể cả submit bằng phím Enter. Giữ cơ chế draft nội bộ, dữ liệu cũ và backend tương thích; không xóa trạng thái hoặc schema. Kiểm tra lại: 2 tests JavaScript đạt, biên dịch Blade đạt. Phần dưới ghi lại triển khai ban đầu.

## Cách hoạt động

- Form tạo/sửa giữ Lưu bản nháp, thêm Gửi duyệt mở Bootstrap modal lớn, cuộn được. Mở modal hoặc quay lại không gọi API.
- Dữ liệu mới lấy trực tiếp từ input/select/textarea, gồm thay đổi chưa lưu. Tiền định dạng VND/tháng, ngày dd/mm/yyyy, kỹ năng thành danh sách, nội dung giữ xuống dòng.
- Dữ liệu cũ ưu tiên bản công khai; nếu chưa có, lấy phiên bản mới nhất đã lưu. Ghi rõ số phiên bản. Hai cột trên desktop, xếp dọc trên mobile; đánh dấu trường thay đổi, giá trị trống ghi “Chưa nhập”.
- Dùng textContent và Js::encode để render an toàn. Nội dung dài xuống dòng, cuộn riêng. Nếu form thay đổi sau khi mở modal, cập nhật lại và yêu cầu xác nhận lần nữa.
- Xác nhận gửi một request vào route store/update hiện có với intent=submit. Service bọc create/update và submit hiện có trong transaction: validation lỗi rollback cả bước lưu, giữ old input và không tạo phiên bản thừa.
- Giữ creation_key/session lock, version_id/updated_at, row lock và phân quyền chủ tin. Khóa nút lúc gửi. Candidate tiếp tục xem bản đã duyệt trong khi bản mới pending.
- Không đổi schema 33 bảng, không migration/seed, không thêm route hoặc liên kết xem công khai.

## File

- `app/Http/Controllers/RecruiterJobController.php`: baseline và điều phối intent.
- `app/Http/Requests/StoreJobRequest.php`, `UpdateJobRequest.php`: kiểm tra intent.
- `app/Services/RecruiterJobService.php`: saveForReview, reviewBaseline; tái sử dụng nghiệp vụ hiện có.
- `resources/views/employer/job-form.blade.php`, `layout.blade.php`, `component/job-review-modal.blade.php`.
- `public/recruiter/js/job-review.js`, `public/recruiter/css/job-review.css`.
- `tests/Feature/RecruiterJobTest.php`, `tests/job-review.test.cjs`, `tests/Database/models.php` (lọc --job-review).

## Kiểm thử và giới hạn

- `php tests/Database/models.php --auth --job-review`: **21 tests, 298 assertions đạt**, dùng MariaDB riêng; schema và lịch sử migration nguồn không đổi. Kiểm tra validation rollback, tạo/sửa, chống lặp, quyền và hồi quy kiểm duyệt/public visibility.
- `node --test tests/job-review.test.cjs`: **2 tests đạt**, DOM mô phỏng kiểm tra mở/quay lại không submit, dữ liệu chưa lưu, so sánh, cập nhật lại khi form đổi và chống gửi lặp.
- Laravel Pint, biên dịch Blade và cú pháp JavaScript đạt.
- Chưa kiểm tra trực quan trên trình duyệt thật. Test DOM không thay thế kiểm tra Bootstrap focus/scroll và bố cục mobile.

## Nghiệm thu thủ công

1. Nhập tin mới, mở Gửi duyệt, kiểm tra đủ trường rồi quay lại: dữ liệu còn nguyên.
2. Xác nhận khi thiếu nội dung: lỗi hiển thị, giữ input, không tạo tin/phiên bản thừa.
3. Sửa bản đã công khai: đổi tiêu đề, xóa một trường, kiểm tra cột cũ/mới và đánh dấu. Khi điền hợp lệ, xác nhận chỉ tạo một pending; Candidate vẫn thấy bản cũ.
4. Hai tab sửa cùng tin: tab gửi sau bị chặn nếu phiên bản đã đổi.
5. Kiểm tra desktop/mobile, nội dung dài, Escape và nút Quay lại chỉnh sửa.
