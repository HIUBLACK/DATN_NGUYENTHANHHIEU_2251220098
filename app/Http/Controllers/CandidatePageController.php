<?php

namespace App\Http\Controllers;

use App\Services\CandidateDemo;
use App\Support\CandidateCatalog;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class CandidatePageController extends Controller
{
    public function __construct(
        private CandidateCatalog $catalog,
        private CandidateDemo $demo,
    ) {}

    public function home(): View
    {
        return view('Candidate.ca_pages.home', $this->sharedData());
    }

    public function jobs(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:100'],
            'level' => ['nullable', 'string', 'max:100'],
            'mode' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:100'],
            'salary' => ['nullable', 'integer', 'min:0'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $jobs = $this->catalog->search($filters);
        $page = (int) ($filters['page'] ?? 1);
        $paginator = new LengthAwarePaginator(
            $jobs->slice(($page - 1) * 6, 6),
            $jobs->count(),
            6,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('Candidate.ca_pages.jobs', $this->sharedData() + [
            'title' => 'Tìm việc làm',
            'jobs' => $paginator,
        ]);
    }

    public function job(int $id): View
    {
        $job = $this->catalog->find($id);
        abort_unless($job, 404);

        return view('Candidate.ca_pages.job', $this->sharedData() + [
            'title' => $job['title'],
            'job' => $job,
            'applicationError' => $this->demo->applicationError($job),
        ]);
    }

    public function page(Request $request): View
    {
        $page = $request->route()->defaults['page'];
        $title = $request->route()->defaults['title'];
        $data = $this->sharedData() + ['title' => $title, 'page' => $page];

        if ($page === 'skills') {
            $data['resume'] = $this->demo->resume((string) $request->query('cv', ''));
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
        return [
            'catalogJobs' => $this->catalog->jobs(),
            'filterOptions' => $this->catalog->filters(),
            'statusLabels' => $this->catalog->statuses(),
            'demo' => $this->demo->state(),
            'mainResume' => $this->demo->mainResume(),
        ];
    }
}
