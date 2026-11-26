<?php

namespace App\Http\Controllers;

use App\Http\Requests\CandidateProfileRequest;
use App\Models\CandidateProfile;
use App\Services\CandidateProfileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class CandidateProfileController extends Controller
{
    public function update(CandidateProfileRequest $request, CandidateProfileService $profiles)
    {
        $profiles->update($request->user(), $request->validated());

        return redirect()->route('candidate.profile')->with('notice', 'Đã cập nhật hồ sơ cá nhân.');
    }

    public function avatar(Request $request, CandidateProfileService $profiles)
    {
        Gate::authorize('viewAny', CandidateProfile::class);
        $path = $request->user()->candidateProfile?->avatar_path;
        $headers = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];
        if ($profiles->ownsAvatar($request->user()->id, $path) && Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->response($path, null, $headers);
        }

        return response()->file(public_path('jobboard/images/person_1.jpg'), $headers);
    }
}
