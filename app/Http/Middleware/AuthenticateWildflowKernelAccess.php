<?php

namespace App\Http\Middleware;

use App\Models\LegalEntity;
use App\Models\Provider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateWildflowKernelAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = trim((string) ($request->header('X-Auth-Token') ?: $request->bearerToken()));
        $clientId = trim((string) $request->header('X-Client-Id'));

        if ($token === '') {
            return response()->json(['success' => false, 'error' => 'Missing identity headers (X-Auth-Token)'], 401);
        }

        $legalEntity = $clientId !== '' ? $this->resolveLegalEntity($clientId) : null;
        $platformTokenAllowed = hash_equals((string) config('app.wildflow_token'), $token)
            || $this->matchesProviderToken($token);

        if ($legalEntity && ! $this->tokenMatchesLegalEntity($legalEntity, $token)) {
            return response()->json(['success' => false, 'error' => 'Unauthorized identity factors'], 401);
        }

        if (! $legalEntity && ! $platformTokenAllowed) {
            return response()->json(['success' => false, 'error' => 'Unauthorized identity factors'], 401);
        }

        if ($legalEntity && ! $this->ipAllowed($request->ip(), $legalEntity->meanlyIpWhitelist())) {
            return response()->json([
                'success' => false,
                'error' => 'Ip not in whitelist',
                'detected_ip' => $request->ip(),
            ], 401);
        }

        $request->attributes->set('meanly_api_legal_entity', $legalEntity);
        $request->attributes->set('meanly_api_platform_token', $platformTokenAllowed);
        $request->attributes->set('wildflow_legal_entity', $legalEntity);
        $request->attributes->set('wildflow_platform_token', $platformTokenAllowed);

        $request->macro('meanlyApiLegalEntity', fn () => $request->attributes->get('meanly_api_legal_entity'));
        $request->macro('wildflowLegalEntity', fn () => $request->attributes->get('wildflow_legal_entity'));

        return $next($request);
    }

    private function resolveLegalEntity(string $clientId): ?LegalEntity
    {
        $query = LegalEntity::query()->where('is_active', true);

        if (ctype_digit($clientId)) {
            $match = (clone $query)->whereKey((int) $clientId)->first();
            if ($match) {
                return $match;
            }
        }

        $terminal = \App\Models\SellerTerminal::query()
            ->with('legalEntity')
            ->where('terminal_id', $clientId)
            ->first();

        if ($terminal?->legalEntity?->is_active) {
            return $terminal->legalEntity;
        }

        if (Schema::hasColumn('legal_entities', 'agreement_metadata')) {
            return $query->get()->first(function (LegalEntity $entity) use ($clientId): bool {
                return in_array($clientId, array_filter([
                    data_get($entity->agreement_metadata, 'kernel_external_id'),
                    data_get($entity->agreement_metadata, 'l1_address'),
                    data_get($entity->agreement_metadata, 'meanly_api_client_id'),
                    data_get($entity->agreement_metadata, 'wildflow_client_id'),
                ]), true);
            });
        }

        return null;
    }

    private function tokenMatchesLegalEntity(LegalEntity $entity, string $token): bool
    {
        $entityToken = $entity->meanlyApiToken();
        if ($entityToken !== '' && hash_equals($entityToken, $token)) {
            return true;
        }

        return hash_equals((string) config('app.wildflow_token'), $token)
            || $this->matchesProviderToken($token);
    }

    private function matchesProviderToken(string $token): bool
    {
        return Provider::query()
            ->where('is_active', true)
            ->get()
            ->contains(function (Provider $provider) use ($token): bool {
                $candidate = (string) data_get($provider->credentials, 'api_key', '');

                return $candidate !== '' && hash_equals($candidate, $token);
            });
    }

    private function ipAllowed(?string $ip, mixed $whitelist): bool
    {
        $whitelist = is_array($whitelist) ? array_filter($whitelist) : [];
        if ($whitelist === []) {
            return true;
        }

        if (! $ip) {
            return false;
        }

        foreach ($whitelist as $entry) {
            $entry = trim((string) $entry);
            if ($entry !== '' && IpUtils::checkIp($ip, $entry)) {
                return true;
            }
        }

        return false;
    }
}
