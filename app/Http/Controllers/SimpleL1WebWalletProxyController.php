<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;

class SimpleL1WebWalletProxyController extends Controller
{
    public function authorize(Request $request): Response
    {
        return $this->proxy($request, 'authorize');
    }

    public function identity(Request $request): Response
    {
        return $this->proxy($request, 'identity');
    }

    public function wallet(Request $request): Response
    {
        return $this->proxy($request, 'wallet');
    }

    public function manifest(Request $request): Response
    {
        return $this->proxy($request, 'manifest.webmanifest');
    }

    public function identityIcon(Request $request): Response
    {
        return $this->proxy($request, 'identity-icon.svg');
    }

    public function deviceHandoff(Request $request, string $handoffId): Response
    {
        return $this->proxy($request, 'device-handoff/'.$handoffId);
    }

    public function devicePairing(Request $request, string $pairingId): Response
    {
        return $this->proxy($request, 'device-pairing/'.$pairingId);
    }

    public function sl1eApi(Request $request, string $path = ''): Response
    {
        return $this->proxy($request, 'api/sl1e/'.ltrim($path, '/'));
    }

    private function proxy(Request $request, string $path): Response
    {
        $runtimeUrl = rtrim((string) config('simple_l1.runtime_url', 'http://localhost:3000'), '/');
        $target = $runtimeUrl.'/'.ltrim($path, '/');
        $query = $request->getQueryString();
        if ($query !== null && $query !== '') {
            $target .= '?'.$query;
        }

        $headers = collect([
            'Accept' => $request->header('Accept'),
            'Content-Type' => $request->header('Content-Type'),
            'Host' => $request->getHost(),
            'X-Forwarded-Host' => $request->getHost(),
            'X-Forwarded-Proto' => $request->getScheme(),
            'User-Agent' => $request->header('User-Agent'),
        ])->filter()->all();

        $upstream = Http::withHeaders($headers)
            ->withOptions(['http_errors' => false])
            ->send($request->method(), $target, [
                'body' => $request->getContent(),
            ]);

        return response($upstream->body(), $upstream->status())
            ->header('Content-Type', $upstream->header('Content-Type') ?: 'text/plain; charset=utf-8');
    }
}
