<?php

namespace App\Http\Controllers;

use App\Services\AIResumeAnalysisService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AIResumeController extends Controller
{
    /**
     * Yêu cầu phân tích lại; Service kiểm tra chủ sở hữu và trạng thái CV.
     */
    public function retryAnalysis(Request $request, AIResumeAnalysisService $analysis, string $id): RedirectResponse
    {
        $analysis->retry($request->user(), $id);

        return redirect()->route('candidate.resumes')
            ->with('notice', 'Đã đưa CV vào hàng đợi phân tích lại.');
    }
}
