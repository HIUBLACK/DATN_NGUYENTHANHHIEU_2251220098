<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Models\Company;
use App\Models\Industry;
use App\Services\CompanyImageStorage;
use App\Services\CompanyService;
use App\Support\CompanyOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CompanyProfileController extends Controller
{
    public function __construct(private CompanyService $companies, private CompanyImageStorage $images) {}

    public function show(): View
    {
        $company = Company::with('industry')->where('recruiter_id', auth()->id())->first();
        if ($company) {
            Gate::authorize('viewManagement', $company);
        }

        return $this->page($company, false);
    }

    public function create(): View|RedirectResponse
    {
        Gate::authorize('create', Company::class);
        if (Company::where('recruiter_id', auth()->id())->exists()) {
            return redirect()->route('recruiter.company');
        }

        return $this->page(null, true);
    }

    public function edit(): View|RedirectResponse
    {
        $company = Company::with('industry')->where('recruiter_id', auth()->id())->first();
        if (! $company) {
            return redirect()->route('recruiter.company-create');
        }
        Gate::authorize('update', $company);

        return $this->page($company, true);
    }

    public function store(StoreCompanyRequest $request): RedirectResponse
    {
        $this->companies->save($request->user(), null, $request->validated());

        return redirect()->route('recruiter.company')->with('company_notice', 'Đã tạo hồ sơ doanh nghiệp.');
    }

    public function update(UpdateCompanyRequest $request, string $company): RedirectResponse
    {
        $record = Company::where('public_id', $company)->firstOrFail();
        $this->companies->save($request->user(), $record, $request->validated());

        return redirect()->route('recruiter.company')->with('company_notice', 'Đã cập nhật hồ sơ doanh nghiệp.');
    }

    public function media(string $company, string $slot): BinaryFileResponse
    {
        $record = Company::where('public_id', $company)->firstOrFail();
        abort_unless(Gate::allows('viewPublic', $record), 404);

        return $this->imageResponse($record, $slot);
    }

    public function privateMedia(string $company, string $slot): BinaryFileResponse
    {
        $record = Company::where('public_id', $company)->firstOrFail();
        Gate::authorize('viewManagement', $record);

        return $this->imageResponse($record, $slot);
    }

    public function adminMedia(string $company, string $slot): BinaryFileResponse
    {
        $record = Company::where('public_id', $company)->firstOrFail();
        Gate::authorize('moderate', $record);

        return $this->imageResponse($record, $slot);
    }

    private function page(?Company $company, bool $form): View
    {
        $types = CompanyOptions::TYPES;
        $countries = array_values(CompanyOptions::countries());
        if ($company?->company_type && ! in_array($company->company_type, $types, true)) {
            $types[] = $company->company_type;
        }
        if ($company?->country && ! in_array($company->country, $countries, true)) {
            $countries[] = $company->country;
        }

        return view($form ? 'employer.company-form' : 'employer.company', [
            'companyTypes' => $types,
            'companyCountries' => $countries,
            'page' => $form ? 'company-form' : 'company',
            'title' => $form ? ($company ? 'Chỉnh sửa doanh nghiệp' : 'Tạo hồ sơ doanh nghiệp') : 'Hồ sơ doanh nghiệp',
            'section' => 'company',
            'seed' => [],
            'companyRecord' => $company ? $this->companies->formData($company) : null,
            'companyForm' => $company ? $this->companies->formData($company) : [],
            'companyProfile' => $company ? $this->companies->publicData($company, true) : [],
            'canSaveCompany' => $company ? Gate::allows('update', $company) : Gate::allows('create', Company::class),
            'industries' => Industry::where('is_active', true)->when($company?->industry_id, function ($query, $id) {
                $query->orWhere('id', $id);
            })->orderBy('name')->get(),
        ]);
    }

    private function imageResponse(Company $company, string $slot): BinaryFileResponse
    {
        $path = match ($slot) {
            'logo' => $company->logo_path,
            default => preg_match('/^office-([0-5])$/', $slot, $match) ? ($company->office_images[(int) $match[1]]['path'] ?? null) : null,
        };
        abort_unless($this->images->owns($company->public_id, $path) && Storage::disk('local')->exists($path), 404);
        $absolute = Storage::disk('local')->path($path);
        $mime = @getimagesize($absolute)['mime'] ?? '';
        abort_unless(in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true), 404);

        return response()->file($absolute, ['Content-Type' => $mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
