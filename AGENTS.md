# Quy định coding style và chất lượng — VIEJOB AI

Nguồn: tài liệu người dùng cung cấp `D:\ĐỒ ÁN TỐT NGHIỆP\QUY ĐỊNH BẮT BUỘC VỀ CODING STYLE.pdf` (6 trang), đọc ngày 02/11/2026.

Áp dụng bắt buộc cho mọi mã nguồn do dự án tự phát triển khi viết mới, chỉnh sửa hoặc refactor, không chỉ nhiệm vụ hiện tại. Không tự động refactor toàn bộ dự án chỉ vì đọc quy định này.

## 1. Dễ đọc trước, ngắn gọn sau

- Code rõ ràng, đơn giản, có cấu trúc và dễ bảo trì; sinh viên có kiến thức Laravel trung bình phải đọc, sửa và giải thích được.
- Không gộp nhiều câu lệnh trên một dòng hoặc dùng cú pháp phức tạp khi có cách đơn giản.
- Tên biến, hàm, class mô tả rõ ý nghĩa; tránh viết tắt khó hiểu và tên chung chung thiếu ngữ cảnh như `getData`, `handleAll`, `doIt`.
- Mỗi hàm có một trách nhiệm chính; tránh hàm quá dài, nhiều logic và abstraction/helper/design pattern không cần thiết.

## 2. HTML và Blade

- Thụt lề **4 spaces** theo cấu trúc cha/con; thẻ đóng thẳng hàng với thẻ mở.
- Mỗi thẻ HTML chính trên dòng riêng; không viết cả component, nhiều thẻ lồng nhau hoặc vòng lặp Blade và HTML trên một dòng.
- Xuống dòng thuộc tính khi cần để dễ đọc.
- Các khối `@if`, `@foreach`, `@section`, `@push` có phân cấp và thụt lề rõ ràng.
- Không nhúng PHP phức tạp, query phức tạp hoặc nhiều logic nghiệp vụ vào Blade.

## 3. CSS

- Mỗi selector là một block rõ ràng, mỗi thuộc tính một dòng, thụt lề 4 spaces.
- Nhóm CSS theo khu vực giao diện; không minify file nguồn của dự án.
- Hạn chế `!important`; dùng class thay inline CSS khi có thể.
- Ưu tiên tái sử dụng class template; không đổi class làm hỏng giao diện.

## 4. JavaScript

- Ưu tiên dễ đọc, dễ debug; không nhiều câu lệnh cùng dòng, không nested ternary phức tạp.
- Không lạm dụng arrow function nếu giảm khả năng đọc hiểu.
- Tên biến mô tả chức năng; tách các xử lý khác nhau thành hàm riêng; không lặp logic không cần thiết.
- Dùng file JavaScript riêng và `addEventListener`; không nhúng JavaScript inline dài vào HTML.
- Kiểm tra phần tử tồn tại trước khi thao tác DOM.

## 5. PHP và Laravel

- Tuân thủ PSR-12 và convention Laravel; ưu tiên Dependency Injection.
- Controller nhận request, điều hướng và trả response; không chứa toàn bộ nghiệp vụ.
- Validation đặt trong Form Request khi phù hợp; nghiệp vụ phức tạp trong Service.
- Model quản lý dữ liệu và relationships; Policy kiểm tra quyền; Job xử lý nền; Notification quản lý thông báo.
- Chỉ tách tầng khi phù hợp, không tạo kiến trúc phức tạp quá quy mô đồ án.

## 6. Comment

- Giải thích lý do của logic khó hoặc quy tắc nghiệp vụ quan trọng, không chỉ lặp lại code.
- Ngắn gọn, rõ ràng; có thể dùng tiếng Việt để phục vụ học và bảo vệ đồ án.
- Không comment từng dòng đơn giản; không để comment hoặc TODO không cần thiết.

## 7. File và component

- Tách Header, Footer, Sidebar và thành phần dùng lại; không nhồi phần không liên quan vào một Blade.
- Không tạo quá nhiều component nhỏ không cần thiết; không nhân bản khối code lớn có thể tái sử dụng.
- Tách CSS/JS theo chức năng khi phù hợp, giữ cấu trúc thư mục nhất quán.
- Không tự ý đổi tên hoặc di chuyển file khi không cần thiết.

## 8. Giữ ổn định giao diện

- Format code không được làm thay đổi giao diện.
- Giữ cấu trúc DOM quan trọng, class/id mà CSS/JS sử dụng, assets, thư viện phụ thuộc và thứ tự phần tử ảnh hưởng layout.
- Chỉ format/tổ chức lại khi an toàn; không refactor toàn bộ template chỉ để làm đẹp code.
- **Không format lại thư viện bên thứ ba hoặc file minified** như `bootstrap.min.css`, `jquery.min.js`; tiêu chuẩn chỉ áp dụng cho mã nguồn do dự án tự phát triển.

## 9. Tự kiểm tra trước khi hoàn thành

1. HTML/Blade phân cấp rõ ràng, thụt lề thống nhất, không gộp nhiều thẻ trên một dòng.
2. JavaScript/PHP không bị rút gọn khó hiểu; tên biến/hàm rõ nghĩa.
3. Logic đúng tầng, không trùng lặp không cần thiết.
4. Không làm thay đổi giao diện ngoài phạm vi yêu cầu.
5. Kiểm tra cú pháp và lỗi phát sinh sau sửa; chỉ hoàn thành khi đạt các tiêu chí trên.

## 10. Thứ tự ưu tiên

- Dễ hiểu hơn ngắn; rõ ràng hơn cú pháp thông minh.
- File có trách nhiệm rõ ràng và tổ chức hợp lý hơn một file khổng lồ.
- Ổn định giao diện hơn refactor mạnh.
- Giải pháp đơn giản đáp ứng yêu cầu hơn giải pháp phức tạp.
