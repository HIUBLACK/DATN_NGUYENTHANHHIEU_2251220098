@props(['status'=>'open'])
@php($labels=['open'=>'Đang tuyển','closed'=>'Đã đóng','expired'=>'Hết hạn','hidden'=>'Không khả dụng','pending'=>'Chờ phân tích','processing'=>'Đang phân tích','needs_review'=>'Cần xác nhận','completed'=>'Đã xác nhận','failed'=>'Phân tích thất bại','applied'=>'Đã ứng tuyển','reviewing'=>'Đang xem xét','interview'=>'Mời phỏng vấn','hired'=>'Trúng tuyển','rejected'=>'Từ chối','withdrawn'=>'Đã rút'])
<span class="status status-{{ $status }}">{{ $labels[$status] ?? 'Chưa xác định' }}</span>
