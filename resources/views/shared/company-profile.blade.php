<article class="company-profile">
    <header class="company-profile-header">
        @if ($profile['cover_url'])
            <img src="{{ $profile['cover_url'] }}" alt="Ảnh bìa {{ $profile['name'] }}" class="company-cover">
        @else
            <div class="company-cover company-cover-placeholder" aria-hidden="true"></div>
        @endif
        <div class="company-profile-heading">
            @if ($profile['logo_url'])
                <img src="{{ $profile['logo_url'] }}" alt="Logo {{ $profile['name'] }}" class="company-profile-logo">
            @else
                <div class="company-profile-logo company-logo-placeholder" aria-hidden="true">DN</div>
            @endif
            <div>
                <h2>{{ $profile['name'] }}</h2>
                @if (! empty($profile['short_name']))
                    <p class="mb-1">{{ $profile['short_name'] }}</p>
                @endif
                <p class="mb-0">{{ $profile['summary'] ?: 'Chưa có mô tả ngắn.' }}</p>
            </div>
        </div>
    </header>
    <div class="company-profile-body">
        <section class="mb-4" aria-labelledby="company-introduction">
            <h3 id="company-introduction">Giới thiệu doanh nghiệp</h3>
            <p class="company-profile-text">{{ $profile['description'] ?: 'Chưa cập nhật giới thiệu.' }}</p>
        </section>
        <section class="mb-4" aria-labelledby="company-information">
            <h3 id="company-information">Thông tin doanh nghiệp</h3>
            <dl class="company-facts">
                @foreach (['industry' => 'Lĩnh vực', 'company_type' => 'Mô hình công ty', 'size' => 'Quy mô nhân sự', 'founded_year' => 'Năm thành lập', 'country' => 'Quốc gia', 'address' => 'Địa điểm'] as $field => $label)
                    <div>
                        <dt>{{ $label }}</dt>
                        <dd>{{ $profile[$field] ?: 'Chưa cập nhật' }}</dd>
                    </div>
                @endforeach
                <div>
                    <dt>Website</dt>
                    <dd>
                        @if (! empty($profile['website']))
                            <a href="{{ $profile['website'] }}" target="_blank" rel="noopener noreferrer">{{ $profile['website'] }}</a>
                        @else
                            Chưa cập nhật
                        @endif
                    </dd>
                </div>
            </dl>
        </section>
        <section class="mb-4" aria-labelledby="company-workplace">
            <h3 id="company-workplace">Môi trường làm việc</h3>
            @foreach (['working_hours' => 'Thời gian làm việc', 'ot_policy' => 'Chính sách làm thêm giờ (OT)', 'benefits' => 'Phúc lợi nổi bật', 'technologies' => 'Công nghệ sử dụng'] as $field => $label)
                <h4>{{ $label }}</h4>
                <p class="company-profile-text">{{ $profile[$field] ?: 'Chưa cập nhật' }}</p>
            @endforeach
        </section>
        <section aria-labelledby="company-office">
            <h3 id="company-office">Không gian văn phòng</h3>
            @if (count($profile['gallery_urls']))
                <div class="company-office-gallery">
                    @foreach ($profile['gallery_urls'] as $image)
                        <a href="{{ $image }}" target="_blank" rel="noopener">
                            <img src="{{ $image }}" alt="Hình ảnh môi trường làm việc {{ $loop->iteration }}" loading="lazy">
                        </a>
                    @endforeach
                </div>
            @else
                <p>Doanh nghiệp chưa bổ sung ảnh văn phòng.</p>
            @endif
        </section>
    </div>
</article>
