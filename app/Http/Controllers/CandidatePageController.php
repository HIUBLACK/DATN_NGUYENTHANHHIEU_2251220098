<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Services\CandidateDemo;
use App\Services\CompanyService;
use App\Services\PublicJobService;
use App\Support\CandidateCatalog;
use App\Support\CompanyDemoCatalog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CandidatePageController extends Controller
{
    public function __construct(
        private CandidateCatalog $catalog,
        private CandidateDemo $demo,
        private PublicJobService $publicJobs,
    ) {}

    public function home(): View
    {
        return view('Candidate.ca_pages.home', $this->sharedData());
    }

    public function jobs(Request $request): View
    {
        $filters = $request->validate([
            'browse' => ['nullable', 'in:industry,skill,specialtyId,level,company,location'],
            'industry' => ['nullable', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:120'],
            'q' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:100'],
            'level' => ['nullable', 'string', 'max:100'],
            'mode' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:100'],
            'salary' => ['nullable', 'integer', 'min:0'],
            'occupationId' => ['nullable', 'string', 'max:30'],
            'specialtyId' => ['nullable', 'string', 'max:30'],
            'experience' => ['nullable', 'string', 'max:100'],
            'skill' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'in:newest,salary'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $jobs = $this->publicJobs->search($filters)->paginate(6)->withQueryString()
            ->through(fn ($job) => $this->publicJobs->card($job));

        return view('Candidate.ca_pages.jobs', $this->sharedData() + [
            'advancedFiltersOpen' => count(array_filter(array_intersect_key($filters, array_flip(['specialtyId', 'industry', 'skill', 'company', 'experience', 'type'])))) > 0,
            'title' => 'Việc làm', 'page' => 'jobs', 'jobs' => $jobs,
            'realJobs' => true, 'suggestedJobs' => [], 'featuredCompanies' => $this->publicJobs->featuredCompanies(),
        ]);
    }

    public function job(int $id): View
    {
        $job = $this->publicJobs->query()->findOrFail($id);

        return view('Candidate.ca_pages.job', $this->sharedData() + [
            'page' => 'job', 'title' => $job->publishedVersion->title,
            'job' => $this->publicJobs->card($job),
        ]);
    }

    public function demoJob(int $id): View
    {
        $job = $this->catalog->find($id);
        abort_unless($job, 404);

        return view('Candidate.ca_pages.job-demo', array_replace($this->sharedData(), ['catalogJobs' => $this->catalog->publicJobs()]) + [
            'page' => 'job',
            'title' => $job['title'],
            'job' => $job,
            'applicationError' => $this->demo->applicationError($job),
        ]);
    }

    public function demoCompany(int $id, CompanyDemoCatalog $companies): View
    {
        $profile = $companies->find($id);
        abort_unless($profile && $profile['status'] === 'active', 404);

        return view('Candidate.ca_pages.company', $this->sharedData() + [
            'page' => 'company',
            'title' => $profile['name'],
            'companyProfile' => $profile,
            'companyJobs' => $this->catalog->publicJobs()->where('company', $profile['name']),
            'isDemoCompany' => true,
        ]);
    }

    public function company(string $company, CompanyService $profiles): View
    {
        $record = $profiles->publicQuery()->where('public_id', $company)->firstOrFail();
        $profile = $profiles->publicData($record);

        return view('Candidate.ca_pages.company', $this->sharedData() + [
            'page' => 'company',
            'title' => $profile['name'],
            'companyProfile' => $profile,
            'companyJobs' => $this->publicJobs->query()->where('company_id', $record->id)->get()->map(fn ($job) => $this->publicJobs->card($job)),
        ]);
    }

    public function page(Request $request): View
    {
        $page = $request->route()->defaults['page'];
        $title = $request->route()->defaults['title'];
        $data = $this->sharedData() + ['title' => $title, 'page' => $page];

        if ($page === 'companies') {
            $companies = app(CompanyService::class);
            $data['companyProfiles'] = $companies->publicQuery()->latest('id')->paginate(12)
                ->through(fn ($company) => $companies->publicData($company));
        }

        if ($page === 'saved') {
            $data['savedJobs'] = array_map($this->catalog->find(...), $data['demo']['saved']);
        }

        if ($page === 'recommendations') {
            $filters = $request->validate([
                'location' => ['nullable', 'string', 'max:100'],
                'mode' => ['nullable', 'string', 'max:100'],
            ]);
            $data['recommendations'] = $this->catalog->recommendations($data['mainResume'], $filters);
        }

        if ($page === 'applications') {
            foreach ($data['demo']['applications'] as &$application) {
                $job = $this->catalog->find($application['job_id']);
                $application['can_reapply'] = $application['status'] === 'withdrawn'
                    && $this->demo->applicationError($job) === null;
            }
        }

        if ($page === 'profile') {
            $data['profileFields'] = [
                'name' => ['label' => 'Họ và tên', 'type' => 'text', 'required' => true],
                'email' => ['label' => 'Email', 'type' => 'email', 'required' => true],
                'phone' => ['label' => 'Số điện thoại', 'type' => 'tel', 'required' => false],
                'location' => ['label' => 'Địa điểm', 'type' => 'text', 'required' => false],
                'profession' => ['label' => 'Vị trí chuyên môn', 'type' => 'text', 'required' => false],
                'experience' => ['label' => 'Kinh nghiệm', 'type' => 'text', 'required' => false],
            ];
        }

        return view('Candidate.ca_pages.'.$page, $data);
    }

    private function sharedData(): array
    {
        $demo = $this->demo->state();
        $demo['active'] = auth()->check() && auth()->user()->role === UserRole::Candidate;

        return $this->publicJobs->navigation() + [
            'jobMenuGroups' => ['industry' => 'lĩnh vực', 'skill' => 'kỹ năng', 'specialtyId' => 'chuyên môn', 'level' => 'cấp bậc', 'company' => 'công ty', 'location' => 'thành phố'],
            'catalogJobs' => $this->publicJobs->query()->latest('published_at')->limit(7)->get()->map(fn ($job) => $this->publicJobs->card($job)),
            'statusLabels' => $this->catalog->statuses(),
            'demo' => $demo,
            'unreadNotificationCount' => collect($this->demo->state()['notifications'])->where('read', false)->count(),
            'mainResume' => $this->demo->mainResume(),
        ];
    }
}
