@if ($analysis)
    <p>
        Phân tích CV:
        <span class="badge badge-{{ $analysis->parsing_status->value === 'completed' ? 'success' : 'secondary' }}">
            {{ ['pending' => 'Chờ phân tích', 'processing' => 'Đang phân tích', 'completed' => 'Hoàn thành', 'failed' => 'Thất bại'][$analysis->parsing_status->value] }}
        </span>
    </p>
    @if ($analysis->parsing_status->value === 'failed')
        <p class="small">{{ $analysis->error_code === 'no_extractable_text' ? 'Không đọc được văn bản. Hãy dùng PDF có văn bản chọn được; hệ thống không xử lý ảnh scan.' : 'Chưa phân tích được CV. Bạn vẫn có thể dùng CV để ứng tuyển.' }}</p>
        <form method="post" action="{{ route('candidate.analysis-retry', $resume->public_id) }}" class="mb-3">
            @csrf
            <button class="btn btn-outline-primary" type="submit">Phân tích lại</button>
        </form>
    @elseif ($analysis->parsing_status->value === 'completed' && $analysis->analysis_result)
        <details class="mb-3">
            <summary>Xem kết quả phân tích</summary>
            <p class="small mt-2">Thông tin AI chỉ để tham khảo, không tự thay đổi hồ sơ của bạn. Kết quả dựa trên phần văn bản được đọc trong giới hạn xử lý.</p>
            <p class="small">{{ App\Support\DisplayDate::format($analysis->parsed_at) }} · {{ $analysis->parser_version }}</p>
            @foreach (App\Services\AIOllamaService::SECTIONS as $key => $label)
                <h4 class="h6">{{ $label }}</h4>
                <ul>
                    @forelse ($analysis->analysis_result[$key] ?? [] as $item)
                        <li>{{ $item['text'] }}</li>
                    @empty
                        <li>Chưa có thông tin được trích xuất.</li>
                    @endforelse
                </ul>
            @endforeach
        </details>
    @endif
@endif
