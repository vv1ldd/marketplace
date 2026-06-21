<?php

namespace App\Http\Controllers;

use App\Services\LlmServiceFactsService;
use App\Support\StorefrontFrontendRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MeanlyServiceController extends Controller
{
    public function index(Request $request, LlmServiceFactsService $facts): RedirectResponse
    {
        return StorefrontFrontendRedirect::fromRequest($request);
    }

    public function show(string $slug, Request $request, LlmServiceFactsService $facts): RedirectResponse
    {
        abort_unless($facts->find($slug) !== null, 404);

        return StorefrontFrontendRedirect::fromRequest($request);
    }
}
