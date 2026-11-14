<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveCompanyRequest;
use App\Services\CompanyProfiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CompanyProfileController extends Controller
{
    public function __construct(private CompanyProfiles $profiles) {}

    public function store(SaveCompanyRequest $request): RedirectResponse
    {
        $this->profiles->save(null, $request->validated());

        return redirect()->route('recruiter.company-edit')->with('company_notice', 'Đã lưu hồ sơ doanh nghiệp trong phiên demo, chưa cập nhật database.');
    }

    public function update(SaveCompanyRequest $request, string $company): RedirectResponse
    {
        $this->profiles->save($company, $request->validated());

        return redirect()->route('recruiter.company-edit')->with('company_notice', 'Đã cập nhật hồ sơ trong phiên demo, chưa cập nhật database.');
    }

    public function media(string $company, string $slot): BinaryFileResponse
    {
        $record = $this->profiles->find($company);
        abort_unless($record && $record['status'] === 'active', 404);
        $path = match ($slot) {
            'logo' => $record['logo'],
            'cover' => $record['cover'],
            default => preg_match('/^office-([0-5])$/', $slot, $match) ? ($record['office_images'][(int) $match[1]] ?? null) : null,
        };
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        $absolute = Storage::disk('local')->path($path);
        $mime = getimagesize($absolute)['mime'] ?? '';
        abort_unless(in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true), 404);

        return response()->file($absolute, ['Content-Type' => $mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
