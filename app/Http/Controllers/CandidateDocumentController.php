<?php

namespace App\Http\Controllers;

use App\Http\Requests\CandidateApplyRequest;
use App\Http\Requests\CandidateResumeActionRequest;
use App\Http\Requests\ResumeUploadRequest;
use App\Models\Application;
use App\Models\Resume;
use App\Services\CandidateApplicationService;
use App\Services\CandidateResumeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class CandidateDocumentController extends Controller
{
    public function upload(ResumeUploadRequest $request, CandidateResumeService $resumes, ?string $id = null)
    {
        $resumes->upload($request->user(), $request->file('pdf'), $id);

        return redirect()->route('candidate.resumes')->with('notice', 'Đã lưu CV PDF riêng tư.');
    }

    public function resumeAction(CandidateResumeActionRequest $request, CandidateResumeService $resumes, string $id, string $action)
    {
        $resumes->change($request->user(), $id, $action, $request->validated());

        return redirect()->route('candidate.resumes')->with('notice', 'Đã cập nhật CV.');
    }

    public function pdf(Request $request, string $id)
    {
        $resume = Resume::forCandidate($request->user()->id)->where('public_id', $id)->firstOrFail();
        Gate::authorize('view', $resume);
        $revision = $resume->currentRevision()->firstOrFail();

        return $this->privatePdf($request, $revision->file_path, $revision->original_filename);
    }

    public function snapshot(Request $request, string $id)
    {
        $application = Application::where('candidate_id', $request->user()->id)->where('public_id', $id)->firstOrFail();
        Gate::authorize('viewSnapshot', $application);

        return $this->privatePdf($request, $application->resume_snapshot_path, $application->resume_snapshot_filename);
    }

    public function apply(CandidateApplyRequest $request, CandidateApplicationService $applications, int $id)
    {
        $application = $applications->apply($request->user(), $id, $request->validated());

        return redirect()->route('candidate.application-detail', $application->public_id)->with('notice', 'Đã nộp hồ sơ ứng tuyển thành công.');
    }

    public function withdraw(Request $request, CandidateApplicationService $applications, string $id)
    {
        $applications->withdraw($request->user(), $id);

        return redirect()->route('candidate.application-detail', $id)->with('notice', 'Đã rút hồ sơ ứng tuyển.');
    }

    public function readNotification(Request $request, string $id)
    {
        $query = $request->user()->notifications();
        if ($id !== 'all') {
            $query->whereKey($id)->firstOrFail()->markAsRead();
        } else {
            $query->whereNull('read_at')->update(['read_at' => now()]);
        }

        return back()->with('notice', 'Đã đánh dấu thông báo đã đọc.');
    }

    private function privatePdf(Request $request, string $path, string $filename)
    {
        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);
        $headers = [
            'Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "sandbox; default-src 'none'",
        ];

        return $request->boolean('download') ? $disk->download($path, $filename, $headers) : $disk->response($path, $filename, $headers);
    }
}
