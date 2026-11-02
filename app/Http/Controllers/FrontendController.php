<?php

namespace App\Http\Controllers;

use App\Support\DemoCatalog;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class FrontendController extends Controller
{
    private function data(): array
    {
        return ['allJobs' => DemoCatalog::publicJobs(), 'companies' => DemoCatalog::companies(), 'categories' => DemoCatalog::categories()];
    }

    public function home()
    {
        return view('welcome', $this->data());
    }

    public function jobs(Request $request)
    {
        $jobs = collect(DemoCatalog::publicJobs())->filter(function ($job) use ($request) {
            $term = mb_strtolower(trim((string) $request->query('q', '')));
            if ($term && ! str_contains(mb_strtolower($job['title'].' '.$job['company']['name'].' '.implode(' ', $job['skills'])), $term)) {
                return false;
            }
            foreach (['location', 'level', 'mode', 'type', 'category'] as $key) {
                if ($request->filled($key) && $request->query($key) !== $job[$key]) {
                    return false;
                }
            }

            return ! $request->filled('salary') || $job['max'] >= (int) $request->query('salary');
        })->values();
        $page = max(1, (int) $request->query('page', 1));
        $paginator = new LengthAwarePaginator($jobs->slice(($page - 1) * 6, 6), $jobs->count(), 6, $page, ['path' => $request->url(), 'query' => $request->query()]);

        return view('public.jobs', $this->data() + ['jobs' => $paginator]);
    }

    public function job(int $id)
    {
        $job = collect(DemoCatalog::jobs())->firstWhere('id', $id);
        abort_unless($job && $job['status'] !== 'hidden', 404);

        return view('public.job', $this->data() + ['job' => $job]);
    }

    public function page(Request $request)
    {
        $page = $request->route()->defaults['page'];

        return view('public.'.$page, $this->data());
    }

    public function auth(Request $request)
    {
        return view('public.auth', ['mode' => $request->route()->defaults['mode']]);
    }

    public function candidate(Request $request)
    {
        $page = $request->route()->defaults['page'];

        return view('Candidate.ca_pages.'.($page === 'ca.home' ? 'home' : $page), $this->data() + ['page' => $page]);
    }
}
