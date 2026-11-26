<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\GooglePasswordSetupRequest;
use App\Services\GooglePasswordSetupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GooglePasswordSetupController extends Controller
{
    public function __construct(private GooglePasswordSetupService $setup) {}

    public function start(Request $request): RedirectResponse
    {
        return $this->setup->start($request);
    }

    public function complete(GooglePasswordSetupRequest $request): RedirectResponse
    {
        try {
            return $this->setup->complete($request, $request->validated('password'));
        } catch (ValidationException $exception) {
            return redirect()->route(GooglePasswordSetupService::accountRoute($request->user()))->withErrors($exception->errors());
        }
    }
}
