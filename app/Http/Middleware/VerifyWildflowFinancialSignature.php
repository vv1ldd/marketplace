<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class VerifyWildflowFinancialSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $legalEntity = $request->attributes->get('meanly_api_legal_entity')
            ?: $request->attributes->get('wildflow_legal_entity');
        $secret = (string) (
            $legalEntity?->meanlyFinancialSecret()
            ?: config('services.wildflow.financial_secret', '')
        );

        if ($secret === '') {
            return $next($request);
        }

        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $signature = (string) $request->header('X-Financial-Signature');
        if ($signature === '') {
            return response()->json(['error' => 'Missing X-Financial-Signature header'], 401);
        }

        $timestamp = $request->header('X-Financial-Timestamp');
        if (! is_numeric($timestamp) || abs(time() - (int) $timestamp) > 300) {
            return response()->json([
                'error' => 'Invalid financial timestamp',
                'hint' => 'Send X-Financial-Timestamp within the 300 second replay window.',
            ], 403);
        }

        $body = $request->getContent();
        $legacySignature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);
        $v2Signature = hash_hmac('sha256', implode('.', [
            $timestamp,
            strtoupper($request->method()),
            $request->getRequestUri(),
            $body,
        ]), $secret);

        if (! hash_equals($v2Signature, $signature) && ! hash_equals($legacySignature, $signature)) {
            return response()->json([
                'error' => 'Invalid financial signature',
                'hint' => 'Sign timestamp.method.path.body with Meanly API HMAC-SHA256. Legacy timestamp.body signatures are still accepted.',
            ], 403);
        }

        if (! $request->isMethodSafe() && ! $this->hasIdempotencyKey($request)) {
            $scope = $legalEntity?->id ?? 'platform';
            $cacheKey = 'meanly_financial_signature_replay:'.sha1($scope.'|'.$timestamp.'|'.$signature);
            if (! Cache::add($cacheKey, true, now()->addMinutes(5))) {
                return response()->json([
                    'error' => 'Replay detected',
                    'hint' => 'Generate a fresh timestamp and signature for each write request.',
                ], 409);
            }
        }

        return $next($request);
    }

    private function hasIdempotencyKey(Request $request): bool
    {
        return $request->headers->has('Idempotency-Key')
            || filled($request->input('reference'))
            || filled($request->input('reference_code'))
            || filled($request->input('referenceCode'));
    }
}
