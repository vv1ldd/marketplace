<?php

namespace App\Http\Controllers;

use App\Services\LlmServiceFactsService;
use Illuminate\View\View;

class MeanlyServiceController extends Controller
{
    public function index(LlmServiceFactsService $facts): View
    {
        $services = $facts->services();

        return view('services.index', [
            'services' => $services,
            'serviceJsonLd' => $facts->serviceListJsonLd(),
        ]);
    }

    public function show(string $slug, LlmServiceFactsService $facts): View
    {
        $service = $facts->find($slug);
        abort_unless($service !== null, 404);

        return view('services.show', [
            'service' => $service,
            'serviceJsonLd' => $facts->serviceJsonLd($service),
        ]);
    }
}
