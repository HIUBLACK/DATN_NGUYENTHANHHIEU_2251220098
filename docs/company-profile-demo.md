# Hồ sơ doanh nghiệp mở rộng — phiên demo

Theo xác nhận của người dùng: chỉ xây giao diện và dữ liệu demo, chưa đụng database. Không có Model, migration, thay đổi bảng hoặc luồng đăng nhập thật trong thay đổi này.

## Giao diện

Form Recruiter tại `/nha-tuyen-dung/quan-ly/doanh-nghiep/chinh-sua` và `/tao` gồm: thông tin cơ bản (tên, viết tắt, logo, bìa, mô tả ngắn, giới thiệu, năm, quy mô), hoạt động (lĩnh vực, mô hình, quốc gia, website), liên hệ, môi trường (giờ làm, OT, phúc lợi, công nghệ) và ảnh văn phòng. Phiên demo có một hồ sơ doanh nghiệp mẫu để chỉnh sửa/thay thông tin; không tạo doanh nghiệp thật. Form có validation, xem trước, xóa/thay ảnh đã lưu, thông báo lỗi và giữ dữ liệu nhập sau lỗi server.

Trang Recruiter có nút mở trang doanh nghiệp phía Candidate. `/cong-ty` dẫn đến chi tiết doanh nghiệp mẫu và hồ sơ vừa chỉnh sửa trong phiên. Tên công ty ở trang chi tiết việc làm cũng dẫn đến hồ sơ mẫu tương ứng.

## Dữ liệu, quyền và công khai

`CompanyProfiles` lưu bản ghi vào phiên Laravel, tách khỏi sessionStorage của tin tuyển dụng. ID hồ sơ và token sở hữu do server tạo; request chỉ được sửa hồ sơ thuộc phiên sở hữu và trạng thái active. Không nhận ID chủ sở hữu/trạng thái từ form. Đây là kiểm tra sở hữu phiên demo, **không phải xác thực hoặc phân quyền Recruiter thật**.

Đường dẫn `/cong-ty/{uuid}` là xem trước trong cùng phiên trình duyệt. Phiên khác không xem được hồ sơ/ảnh. Hồ sơ blocked không hiển thị ở danh sách công khai, chi tiết trả 404 và không phục vụ ảnh. `/cong-ty/mau/{id}` là hồ sơ mẫu có sẵn; danh sách việc làm chỉ lấy tin đủ điều kiện công khai từ CandidateCatalog.

Trang Candidate dùng danh sách trường công khai cho phép rõ ràng: không đưa email liên hệ quản lý, số điện thoại, token sở hữu hay đường dẫn lưu trữ lên trang. Văn bản được Blade escape. Hồ sơ mới chỉnh sửa chưa có tin liên kết công khai thì hiển thị trạng thái rỗng, không ghép tin của doanh nghiệp khác. Chưa có luồng xuất bản tin thật từ Recruiter/Admin.

## Ảnh

- Chỉ JPEG/PNG/WebP; kiểm tra đuôi, MIME/nội dung ảnh, kích thước và dung lượng ở client và Form Request.
- Logo tối đa 2 MB, ảnh bìa 5 MB, tối đa 6 ảnh văn phòng mỗi ảnh 3 MB; tối đa 5000 × 5000 pixel.
- Từ chối SVG, tệp thực thi, ảnh giả và nội dung có thẻ PHP.
- Tên tệp UUID do server tạo; lưu ở disk local ngoài public, trong `storage/app/private/company-images`.
- Phục vụ qua endpoint theo ID hồ sơ/vị trí ảnh, MIME cố định lấy từ ảnh, header nosniff và no-store. Không nhận đường dẫn tệp từ URL.
- Khi lỗi lưu/giới hạn tổng ảnh: xóa các tệp vừa ghi, giữ hồ sơ cũ. Khi thay/xóa thành công: xóa ảnh cũ khỏi disk.
- Đây là ảnh thử nghiệm; ảnh còn lại khi phiên hết hạn cần được dọn định kỳ ở giai đoạn quản trị lưu trữ. Hiện chưa có tác vụ dọn nền tự động.

## File chính

- `app/Http/Requests/SaveCompanyRequest.php`: validation/kiểm tra quyền phiên.
- `app/Services/CompanyProfiles.php`: dữ liệu phiên, lưu/xóa ảnh, tách dữ liệu công khai.
- `app/Http/Controllers/CompanyProfileController.php`: lưu và phục vụ ảnh.
- `app/Support/CompanyDemoCatalog.php`: hồ sơ công ty minh họa Candidate.
- `RecruiterPageController`, `CandidatePageController`, `RecruiterCatalog`, `CandidateCatalog`: nối dữ liệu và điều hướng.
- Form/trang doanh nghiệp Recruiter; `shared/company-profile.blade.php`; trang `Candidate/ca_pages/company.blade.php` và danh sách công ty.
- `public/recruiter/js/company-profile-form.js`, `public/css/company-profile.css`.
- `routes/recruiter.php`, `routes/web.php`: route demo lưu/xem/ảnh có CSRF và throttle, khóa phiên khi lưu.

## Kiểm tra

`php artisan test --compact`: 20 tests, 381 assertions đạt. Bao phủ validation, tệp giả/SVG/PHP/quá dung lượng, chèn trường không được phép, sở hữu phiên, hồ sơ blocked, dữ liệu công khai, thay/xóa ảnh và giới hạn tổng thư viện ảnh.

`node tests/company-profile-browser.cjs`: form và trang công khai trên 1440/768/390px, xem trước/lưu/tải lại/xóa ảnh, không lộ liên hệ và từ chối phiên khác. `node tests/recruiter-browser.cjs`: kiểm tra lại các trang và thao tác Recruiter; chỉ hồ sơ doanh nghiệp có request lưu vào phiên server.

Chạy xem thử không cần database:

```powershell
$env:SESSION_DRIVER = 'file'
$env:CACHE_STORE = 'file'
php artisan serve --host=127.0.0.1 --port=8012
```

Khi triển khai backend thật sau này cần xác thực tài khoản, Policy kiểm tra Recruiter/owner, quan hệ doanh nghiệp/tin, lưu dữ liệu thật, quản lý trạng thái và dọn tệp. Không dùng kiểm tra token phiên demo thay cho quyền người dùng thật.
