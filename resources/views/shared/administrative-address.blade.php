<div class="col-12" data-administrative-address data-country="{{ $addressCountry ?? '' }}">
    <div class="row g-3" data-vietnam-address>
        <div class="col-lg-6 form-group-custom">
            <label for="province_code" class="form-label-custom">Tỉnh/thành phố</label>
            <select id="province_code" name="province_code" class="form-select-custom" data-province-select>
                <option value="">Chọn tỉnh/thành phố</option>
                @foreach (App\Support\Provinces::ALL as $code => $name)
                    <option value="{{ $code }}" @selected((string) old('province_code', $addressValues['province_code'] ?? '') === (string) $code)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-6 form-group-custom">
            <label for="ward_code" class="form-label-custom">Xã/phường/đặc khu</label>
            <select id="ward_code" name="ward_code" class="form-select-custom" data-ward-select>
                <option value="">Chọn xã/phường/đặc khu</option>
                @foreach (App\Support\VietnamAddress::data()['wards'] as $ward)
                    @if ($ward['province_code'] === (string) old('province_code', $addressValues['province_code'] ?? ''))
                        <option value="{{ $ward['code'] }}" @selected((string) old('ward_code', $addressValues['ward_code'] ?? '') === $ward['code'])>{{ $ward['name'] }}</option>
                    @endif
                @endforeach
            </select>
        </div>
    </div>
    @if (! empty($addressValues[$addressField]) && empty($addressValues['ward_code']))
        <p class="small text-warning mt-2">Địa chỉ cũ chưa có mã xã/phường. Hãy chọn tỉnh/xã và giữ lại số nhà, tên đường trong ô bên dưới.</p>
    @endif
</div>
@once
    <script type="application/json" id="administrative-address-data">{!! Illuminate\Support\Js::encode(App\Support\VietnamAddress::data()['wards']) !!}</script>
    <script src="{{ asset('js/administrative-address.js') }}" defer></script>
@endonce
