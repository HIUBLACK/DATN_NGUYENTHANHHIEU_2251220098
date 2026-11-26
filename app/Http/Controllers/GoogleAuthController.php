<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\CompleteGoogleRegistrationRequest;
use App\Http\Requests\Auth\LinkGoogleRequest;
use App\Services\GoogleAuthService;
use App\Services\GooglePasswordSetupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class GoogleAuthController extends Controller
{
    public function __construct(private GoogleAuthService $google) {}

    public function redirect(Request $request): RedirectResponse
    {
        $input = $request->validate(['context' => ['required', 'in:login,register']]);

        return $this->google->start($request, $input['context']);
    }

    public function callback(Request $request, GooglePasswordSetupService $setup): RedirectResponse
    {
        $response = $request->session()->has('google_setup_flow')
            ? $setup->callback($request)
            : $this->google->callback($request);

        return $response
            ->header('Cache-Control', 'no-store, private')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function registration(Request $request, CandidatePageController $pages): View|RedirectResponse
    {
        try {
            $pending = $this->google->pending($request);
        } catch (ValidationException $exception) {
            return redirect()->route('register')->withErrors($exception->errors());
        }

        return $pages->page($request)->with('googleEmail', $pending['email']);
    }

    public function complete(CompleteGoogleRegistrationRequest $request): RedirectResponse
    {
        return $this->google->complete($request, $request->validated('role'));
    }

    public function link(LinkGoogleRequest $request): RedirectResponse
    {
        return $this->google->start($request, 'link');
    }
}
