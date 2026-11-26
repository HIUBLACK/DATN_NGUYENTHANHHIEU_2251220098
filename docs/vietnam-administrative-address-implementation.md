# Cập nhật chọn địa chỉ — 26/11/2026

- Form doanh nghiệp và tin tuyển dụng thống nhất: **Tỉnh/thành phố → Xã/phường/đặc khu → Số nhà, tên đường**.
- Tìm tỉnh/xã hỗ trợ không dấu. Đổi tỉnh xóa lựa chọn xã, giữ số nhà/tên đường. Doanh nghiệp ngoài Việt Nam nhập địa chỉ tự do.
- Đã xóa `config/address-suggestions.php`, `public/js/address-autocomplete.js`, `resources/views/shared/address-suggestions.blade.php`. Không còn gợi ý đường cũ.
- Đã chạy migration được duyệt thêm `companies.province_code`, `companies.ward_code`, `job_versions.ward_code`, đều nullable; không thêm bảng.
- `companies.address` và `job_versions.location` lưu số nhà/tên đường; mã tỉnh/xã lưu riêng. Chỉ ghép địa chỉ khi hiển thị, không ghi chuỗi ghép ngược vào form. Địa chỉ cũ thiếu mã được giữ nguyên, không tự suy đoán xã.

## File chính

`VietnamAddress`, `JobInput`, `SaveCompanyRequest`, `CompanyService`, `RecruiterJobService`, Models Company/JobVersion, các Services/view hiển thị địa chỉ, component `shared/administrative-address.blade.php`, form doanh nghiệp/tin, `public/js/administrative-address.js`, `public/recruiter/js/job-review.js`.

## Nguồn dữ liệu và giới hạn

JSON nội bộ `resources/data/vietnam-administrative-address.json`: 34 tỉnh/thành và 3.321 xã/phường/đặc khu. Website không gọi API bên ngoài.

Nguồn: [Địa Chỉ Việt](https://github.com/HuyChuong/vietnam-administrative-units), CC BY 4.0, bản nguồn ghi cập nhật đến 20/09/2026; tải ngày 26/11/2026. Metadata lưu nguồn, ngày và checksum.

Đã đối chiếu 34 mã/tên tỉnh với [Cục Thống kê](https://danhmuchanhchinh.nso.gov.vn/). **Chưa xuất được toàn bộ cấp xã để đối chiếu từng dòng với bản chính thức hiện hành. Không coi đúng số lượng là bằng chứng tất cả tên/mã đã được xác minh.**

Khi cập nhật: tải `data/tinh.json`, `data/xa.json` từ nguồn, đối chiếu bản xuất chính thức cùng ngày; chuyển mã thành chuỗi 2/5 ký tự; kiểm tra mã duy nhất và tỉnh cha; cập nhật JSON và metadata. Không tự đổi mã đã được dữ liệu nghiệp vụ tham chiếu.

## Kiểm tra

45 test liên quan địa chỉ, Company/Jobs, kiểm duyệt và quyền đã đạt trên MariaDB riêng trước lần tinh giản nhãn; không reset/seed db_job. Bao gồm mã và tỉnh cha, lưu/mở lại không ghép lặp, địa chỉ cũ, quốc gia khác Việt Nam. Kết quả lần kiểm tra sau tinh giản ghi bên dưới.

Sau tinh giản: 45/45 test đạt (7.237 assertions) trên MariaDB riêng; Blade biên dịch và JavaScript kiểm tra cú pháp đạt. Chưa chạy được kiểm tra trình duyệt tự động vì môi trường thiếu Playwright; cần kiểm tra thao tác đổi tỉnh/tìm xã trực tiếp trên giao diện.
