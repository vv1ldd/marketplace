<?php

namespace App\Http\Controllers;

use App\Services\Identity\Governance\IdentityGovernanceStreamAuthorizeService;
use App\Services\Identity\Governance\IdentityGovernanceStreamHandoffService;
use App\Services\Identity\Governance\IdentityGovernanceStreamRegisterService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Delegates SL1e authorize endpoints to the governance stream or legacy runtime proxy.
 */
final class Sl1eAuthorizeGatewayController extends Controller
{
    public function options(Request $request): Response
    {
        return $this->delegate($request, 'authorize/options', 'options', IdentityGovernanceStreamAuthorizeController::class);
    }

    public function verify(Request $request): Response
    {
        return $this->delegate($request, 'authorize/verify', 'verify', IdentityGovernanceStreamAuthorizeController::class);
    }

    public function registerOptions(Request $request): Response
    {
        return $this->delegate($request, 'authorize/register/options', 'registerOptions', IdentityGovernanceStreamAuthorizeController::class);
    }

    public function registerVerify(Request $request): Response
    {
        return $this->delegate($request, 'authorize/register/verify', 'registerVerify', IdentityGovernanceStreamAuthorizeController::class);
    }

    public function handoff(Request $request): Response
    {
        return $this->delegate($request, 'authorize/handoff', 'handoff', IdentityGovernanceStreamAuthorizeController::class);
    }

    public function handoffStatus(Request $request, string $handoffId): Response
    {
        if (config('identity_governance.stream_authorize_enabled')) {
            return app(IdentityGovernanceStreamAuthorizeController::class)->handoffStatus(
                $handoffId,
                app(IdentityGovernanceStreamHandoffService::class),
            );
        }

        return app(SimpleL1WebWalletProxyController::class)->sl1eApi($request, 'authorize/handoff/'.$handoffId);
    }

    public function introspect(Request $request): Response
    {
        if (config('identity_governance.stream_authorize_enabled')) {
            return app(IdentityGovernanceSl1eProofController::class)->introspect(
                $request,
                app(\App\Services\Identity\Governance\IdentityGovernanceSl1eProofIssuer::class),
            );
        }

        return app(SimpleL1WebWalletProxyController::class)->sl1eApi($request, 'proofs/introspect');
    }

    private function delegate(
        Request $request,
        string $proxyPath,
        string $streamAction,
        string $streamController,
    ): Response {
        if (config('identity_governance.stream_authorize_enabled')) {
            return app($streamController)->{$streamAction}(
                $request,
                ...$this->streamActionDependencies($streamAction),
            );
        }

        return app(SimpleL1WebWalletProxyController::class)->sl1eApi($request, $proxyPath);
    }

    /**
     * @return list<object>
     */
    private function streamActionDependencies(string $action): array
    {
        return match ($action) {
            'options' => [app(IdentityGovernanceStreamAuthorizeService::class)],
            'verify' => [app(IdentityGovernanceStreamAuthorizeService::class)],
            'registerOptions' => [app(IdentityGovernanceStreamRegisterService::class)],
            'registerVerify' => [app(IdentityGovernanceStreamRegisterService::class)],
            'handoff' => [app(IdentityGovernanceStreamHandoffService::class)],
            default => [],
        };
    }
}
