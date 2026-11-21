<?php

namespace App\Http\Controllers;

use App\Http\Requests\SavedJobRequest;
use App\Services\SavedJobService;

class SavedJobController extends Controller
{
    public function update(SavedJobRequest $request, SavedJobService $jobs, int $id)
    {
        $saved = ! $request->isMethod('delete') && $request->boolean('saved');
        $jobs->setSaved($request->user(), $id, $saved);

        return back()->with('notice', $saved ? 'Đã lưu việc làm.' : 'Đã bỏ lưu việc làm.');
    }
}
