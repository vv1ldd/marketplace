<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\ExternalSearchQuerySignal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StorefrontNeedRequestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'need_key' => ['nullable', 'string', 'max:80'],
            'need_title' => ['nullable', 'string', 'max:120'],
            'description' => ['required', 'string', 'min:3', 'max:2000'],
            'contact' => ['nullable', 'string', 'max:160'],
            'screenshot' => ['nullable', 'file', 'image', 'max:8192'],
        ]);

        $screenshotPath = null;
        if ($request->hasFile('screenshot')) {
            $screenshotPath = $request->file('screenshot')?->store('need-requests', 'public');
        }

        $description = trim((string) $data['description']);
        $needTitle = trim((string) ($data['need_title'] ?? ''));
        $query = trim($needTitle.' '.$description);
        $normalized = Str::of($query)->lower()->squish()->limit(512, '')->toString();
        $hashBasis = implode('|', [
            'catalog_need_request',
            $normalized,
            (string) ($data['need_key'] ?? ''),
            (string) $screenshotPath,
            now()->format('Y-m-d-H'),
        ]);

        $signal = ExternalSearchQuerySignal::create([
            'signal_hash' => hash('sha256', $hashBasis),
            'query' => $query,
            'normalized_query' => $normalized,
            'source' => 'catalog_need_request',
            'country' => $request->headers->get('CF-IPCountry') ?: null,
            'locale' => $request->getPreferredLanguage() ?: null,
            'impressions' => 1,
            'clicks' => 1,
            'volume' => 1,
            'landing_url' => $request->headers->get('referer'),
            'observed_at' => now(),
            'metadata' => [
                'need_key' => $data['need_key'] ?? null,
                'need_title' => $needTitle !== '' ? $needTitle : null,
                'description' => $description,
                'contact' => $data['contact'] ?? null,
                'screenshot_path' => $screenshotPath,
                'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
            ],
        ]);

        return response()->json([
            'contract' => [
                'name' => 'catalog-need-request',
                'version' => 'v1',
                'authority' => 'marketplace-demand',
            ],
            'success' => true,
            'signal_id' => $signal->id,
            'message' => 'Need request saved.',
        ], 201);
    }
}
